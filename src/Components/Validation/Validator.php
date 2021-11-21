<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Validation;

use ArrayAccess;
use ReflectionProperty;

final class Validator
{
    /** @var array<string, mixed>|object an assoc array, or an object */
    private array|object $data;
    private bool $dataIsArrayLike;

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
        $this->data = $data;
        $this->dataIsArrayLike = is_array($data) || $data instanceof ArrayAccess;

        return $this;
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
     * @throws ValidationException
     *
     * @return void|never-returns
     */
    public function throwIfNotValid(): void
    {
        if (! $this->isValidated) {
            $this->validate();
        }

        if ($this->messages === []) {
            return;
        }

        throw new ValidationException($this->data, $this->messages);
    }

    /**
     * @return array<string, array<string>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    private function validate(): void
    {
        foreach ($this->rules as $key => $rules) {
            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                if (is_callable($rule) && ! is_string($rule)) { // prevent 'date' to be considered as a callable
                    $message = $rule($this->getValue($key), $this->data);

                    if ($message === false) {
                        $this->addMessage($key);
                    } elseif (is_string($message)) {
                        $this->addMessage($key, $message);
                    }

                    continue;
                }

                if ($rule instanceof RuleInterface) {
                    if (! $rule->passes($this->getValue($key), $this->data)) {
                        $this->addMessage($key, $rule->getMessage(), basename(get_class($rule)));
                    }

                    continue;
                }

                // the rule is a built-in string
                if ($rule === 'present') {
                    if (
                        ($this->dataIsArrayLike && ! array_key_exists($key, $this->data)) // @phpstan-ignore-line
                        || (! $this->dataIsArrayLike && ! property_exists($this->data, $key)) // @phpstan-ignore-line
                    ) {
                        $this->addMessage($key, null, $rule);
                    }

                    continue;
                }

                if (! $this->passeBuiltInRule($this->getValue($key), $rule)) {
                    $this->addMessage($key, null, $rule);
                }
            }
        }

        $this->isValidated = true;
    }

    private function getValue(string $key): mixed
    {
        if ($this->dataIsArrayLike) {
            return $this->data[$key] ?? null; // @phpstan-ignore-line
        }

        if (property_exists($this->data, $key)) { // @phpstan-ignore-line
            $reflectionProperty = new ReflectionProperty($this->data, $key);
            $reflectionProperty->setAccessible(true);

            return $reflectionProperty->getValue($this->data); // @phpstan-ignore-line
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
        $functionName = "is_$rule"; // is_int() for instance
        if (function_exists($functionName)) {
            return $functionName($value);
        }

        if (str_contains($rule, ':')) {
            return $this->passesParameterizedRule($value, $rule);
        }

        switch ($rule) {
            case 'required': return $value !== null;
            case 'uuid': return preg_match('/^[0-9a-fA-F]{8}(\b-)?[0-9a-fA-F]{4}(\b-)?[0-9a-fA-F]{4}(\b-)?[0-9a-fA-F]{4}(\b-)?[0-9a-fA-F]{12}$/i', $value) === 1;
            case 'email':
                return preg_match(
                    // from https://www.emailregex.com/
                    '/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD',
                    $value
                ) === 1;
            case 'date': return strtotime($value) !== false;
        }

        throw new \Exception("Unknown rule '$rule'.");
    }

    private function passesParameterizedRule(mixed $value, string $rule): bool
    {
        [$rule, $arg] = explode(':', $rule, 2);

        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

        switch ($rule) {
            case 'instanceof': return $value instanceof $arg;
            case 'regex': return preg_match($arg, $value) === 1;
            case 'min':
                if (is_string($value)) {
                    return $strlen($value) >= (int) $arg;
                }

                if (is_countable($value)) {
                    return count($value) >= (int) $arg;
                }
            // no break
            case 'gte':
                return $value >= $arg;

            case 'max':
                if (is_string($value)) {
                    return $strlen($value) <= (int) $arg;
                }

                if (is_countable($value)) {
                    return count($value) <= (int) $arg;
                }
            // no break
            case 'lte':
                return $value <= $arg;

            case 'gt': return $value > $arg;
            case 'lt': return $value < $arg;
            case 'size':
                if (is_string($value)) {
                    return $strlen($value) === (int) $arg;
                }

                if (is_int($value)) {
                    return $value === (int) $arg;
                }

                if (is_float($value)) {
                    return $value === (float) $arg;
                }

                if (is_countable($value)) {
                    return count($value) === (int) $arg;
                }

                return $value === $arg;

            case 'equal': return $value == $arg;
            case 'same': return $value === $arg;
        }

        throw new \Exception("Unknown rule '$rule'.");
    }
}
