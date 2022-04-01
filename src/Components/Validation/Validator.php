<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Validation;

use Error;
use LogicException;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use UnexpectedValueException;

final class Validator
{
    /** @var null|array<string, mixed> */
    private ?array $arrayData = null;

    private ?object $objectData = null;

    /** @var array<string, array<string|callable|RuleInterface>> */
    private array $rules;

    /** @var array<string, array<string>> The keys match the one found in the values */
    private array $messages = [];
    private bool $isValidated = false;

    /**
     * @param array<string, mixed>|object $data an assoc array, or an object
     */
    public function setData(array|object $data): self
    {
        if (is_array($data)) {
            $this->arrayData = $data;
        } else {
            $this->objectData = $data;
        }

        return $this;
    }

    /**
     * @return array<string, mixed>|object
     */
    public function getData(): array|object
    {
        return $this->arrayData ?? $this->objectData; // @phpstan-ignore-line
    }

    /**
     * @param array<string, array<string|callable|RuleInterface>> $rules
     */
    public function setRules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function isValid(): bool
    {
        if (! $this->isValidated) {
            $this->validate();
        }

        return $this->messages === [];
    }

    /**
     * @throws ValidationException if some data isn't valid
     */
    public function throwIfNotValid(): self
    {
        if (! $this->isValidated) {
            $this->validate();
        }

        if ($this->messages === []) {
            return $this;
        }

        throw new ValidationException($this->getData(), $this->messages);
    }

    /**
     * @return array<string, array<string>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param array<string> $exclude The validated keys to exclude from the returned data
     *
     * @return array<string, mixed>|stdClass
     *
     * @throws ValidationException if some data isn't valid
     */
    public function getValidatedData(array $exclude = []): array|stdClass
    {
        $this->throwIfNotValid();

        if ($this->arrayData !== null) {
            return array_diff_key(
                array_intersect_key($this->arrayData, $this->rules),
                array_fill_keys($exclude, null)
            );
        }

        if ($this->objectData instanceof stdClass) {
            $validated = new stdClass();

            $validatedProperties = array_keys($this->rules);
            foreach ((array) $this->objectData as $property => $value) {
                if (
                    in_array($property, $validatedProperties, true)
                    && ! in_array($property, $exclude, true)
                ) {
                    $validated->{$property} = $value;
                }
            }

            return $validated;
        }

        throw new UnexpectedValueException('Can not ');
    }

    private function validate(): void
    {
        if ($this->objectData !== null && $this->rules === []) {
            $this->introspectPropertyRules();
        }

        foreach ($this->rules as $key => $rules) {
            foreach ($rules as $rule) {
                if ($rule === Rule::optional->value) {
                    if ($this->getValue($key) === null) {
                        break; // do not evaluate further rules for that key/property
                    }

                    continue;
                }

                if (is_callable($rule)) {
                    if (is_string($rule)) { // prevent global functions like 'date' to be considered as a callable
                        continue;
                    }

                    $message = $rule($key, $this->getValue($key));

                    if ($message === false) {
                        $this->addMessage($key);
                    } elseif (is_string($message)) {
                        $this->addMessage($key, $message);
                    }

                    continue;
                }

                if ($rule instanceof RuleInterface) {
                    if (! $rule->passes($key, $this->getValue($key))) {
                        $this->addMessage($key, $rule->getMessage($key), basename($rule::class));
                    }

                    continue;
                }

                // now, the rule is a built-in string

                if ($rule === Rule::exists->value) {
                    if (
                        ($this->arrayData !== null && ! array_key_exists($key, $this->arrayData))
                        || ($this->objectData !== null && ! property_exists($this->objectData, $key))
                    ) {
                        $this->addMessage($key, null, $rule);
                    }

                    break; // do not check more rules for that key, but move on with the remaining keys
                }

                if (! $this->passeBuiltInRule($this->getValue($key), $rule)) {
                    $this->addMessage($key, null, $rule);
                }
            }
        }

        $this->isValidated = true;
    }

    /**
     * When the validated data is an object and no validation rules are passed to the validator,
     * find them via the Validates attributes on the object's properties.
     */
    private function introspectPropertyRules(): void
    {
        assert($this->objectData !== null);

        $properties = (new ReflectionClass($this->objectData))->getProperties();
        foreach ($properties as $property) {
            foreach ($property->getAttributes() as $attribute) {
                if ($attribute->getName() === Validates::class) {
                    continue;
                }

                $rules = $attribute->getArguments();
                $type = $property->getType();
                // not typed: can be optional or not-null
                // typed not nullable: can only be not-null
                // typed nullable: can only be optional

                if ($type === null && ! in_array('optional', $rules, true) && ! in_array('not-null', $rules, true)) {
                    array_unshift($rules, 'optional');
                } elseif ($type !== null) {
                    if ($type->allowsNull()) {
                        if (in_array('not-null', $rules, true)) {
                            $name = $property->getName();
                            $fqcn = $this->objectData::class;

                            throw new LogicException("Property '$name' on instance of '$fqcn', can't be both typed-nullable and has the 'not-null' validation rule.");
                        }

                        if (! in_array('optional', $rules, true)) {
                            array_unshift($rules, 'optional');
                        }
                    } elseif (in_array('optional', $rules, true)) {
                        $name = $property->getName();
                        $fqcn = $this->objectData::class;

                        throw new LogicException("Property '$name' on instance of '$fqcn', can't be both non-nullable and has the 'optional' validation rule.");
                    }
                }

                $this->rules[$property->getName()] = $rules;
            }
        }
    }

    private function getValue(string $key): mixed
    {
        if ($this->arrayData !== null) {
            return $this->arrayData[$key] ?? null;
        }

        if ($this->objectData !== null && property_exists($this->objectData, $key)) {
            try {
                return (new ReflectionProperty($this->objectData, $key))->getValue($this->objectData); // using reflection to get values from protected/private properties
            } catch (Error) {
                // probably uninitialized typed properties
            }
        }

        return null;
    }

    private function addMessage(string $key, string $message = null, string $ruleName = null): void
    {
        if ($message === null) {
            $message = "The value for '$key' isn't valid";
            if ($ruleName !== null) {
                $message = "The value for '$key' doesn't pass the '$ruleName' validation rule.";
            }
        }

        $this->messages[$key] ??= [];
        $this->messages[$key][] = $message;
    }

    private function passeBuiltInRule(mixed $value, string $rule): bool
    {
        $functionName = "\is_$rule"; // is_int() for instance
        if (function_exists($functionName)) {
            return $functionName($value); // @phpstan-ignore-line
        }

        if (str_contains($rule, ':')) {
            return $this->passesParameterizedRule($value, $rule);
        }

        switch ($rule) {
            case Rule::notNull->value: return $value !== null;
            case Rule::uuid->value: return preg_match('/^[0-9a-f]{8}(\b-)?[0-9a-f]{4}(\b-)?[145][0-9a-f]{3}(\b-)?[0-9a-f]{4}(\b-)?[0-9a-f]{12}$/i', $value) === 1;
            case Rule::email->value:
                return preg_match(
                    // from https://www.emailregex.com/
                    '/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD',
                    $value
                ) === 1;
            case Rule::date->value: return strtotime($value) !== false;
        }

        throw new UnexpectedValueException("Unknown rule '$rule'. Should be one from the Rule enum.");
    }

    private function passesParameterizedRule(mixed $value, string $rule): bool
    {
        [$rule, $arg] = explode(':', $rule, 2);

        $args = [$arg];
        if (str_contains(',', $arg)) {
            $args = explode(',', $arg);
            assert(is_array($args)); // @phpstan-ignore-line
        }

        switch ($rule) {
            case ParametrizedRule::instanceof->value: return $value instanceof $arg;
            case ParametrizedRule::regex->value: return preg_match($arg, $value) === 1;
            case ParametrizedRule::superiorOrEqual->value: return $value >= $arg;
            case ParametrizedRule::superior->value: return $value > $arg;
            case ParametrizedRule::inferiorOrEqual->value: return $value <= $arg;
            case ParametrizedRule::inferior->value:  return $value < $arg;
            case ParametrizedRule::minLength->value:
                if (is_string($value)) {
                    return strlen($value) >= (int) $arg;
                }

                if (is_countable($value)) {
                    return count($value) >= (int) $arg;
                }
                break;
            case ParametrizedRule::maxLength->value:
                if (is_string($value)) {
                    return strlen($value) <= (int) $arg;
                }

                if (is_countable($value)) {
                    return count($value) <= (int) $arg;
                }
                break;
            case ParametrizedRule::length->value:
                if (is_string($value)) {
                    return strlen($value) === (int) $arg;
                }

                if (is_countable($value)) {
                    return count($value) === (int) $arg;
                }
                break;
            case ParametrizedRule::equal->value: return $value == $arg;
            case ParametrizedRule::strictlyEqual->value: return $value === $arg;
            case ParametrizedRule::in->value: return in_array((string) $value, $args, true);
            case ParametrizedRule::sameAs->value: return $value === $this->getValue($arg);
        }

        throw new UnexpectedValueException("Unknown rule '$rule'. Should be one from the ParametrizedRule enum.");
    }
}
