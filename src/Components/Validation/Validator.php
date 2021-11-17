<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Validation;

final class Validator
{
    /** @var array<string, array<string>> The keys match the one found in the values */
    private array $messages = [];

    private bool $isValidated = false;

    public function __construct(
        /** @var array<string, mixed> Mixed, can be an assoc or numerical array */
        private array $values = [],

        /** @var array<string, array<string|callable|RuleInterface>> */
        private array $rules = [],
    ) {
    }

    public function isValid(): bool
    {
        if (! $this->isValidated) {
            $this->validate();
        }

        return $this->messages === [];
    }

    public function throwIfNotValid(): void
    {
        if (! $this->isValidated) {
            $this->validate();
        }

        if ($this->messages === []) {
            return;
        }

        throw new \Exception();
    }

    private function validate(): void
    {
        foreach ($this->rules as $key => $rules) {
            foreach ($rules as $rule) {
                if (is_callable($rule)) {
                    $message = $rule($this->values[$key] ?? null);

                    if ($message === false) {
                        $this->addMessage($key);
                    } elseif (is_string($message)) {
                        $this->addMessage($key, $message);
                    }

                    continue;
                }

                if ($rule instanceof RuleInterface) {
                    if (! $rule->passes($this->values[$key] ?? null)) {
                        $this->addMessage($key, $rule->getMessage(), basename(get_class($rule)));
                    }

                    continue;
                }

                // the rule is a built-in string
                if ($rule === 'present' && ! array_key_exists($key, $this->values)) {
                    $this->addMessage($key, null, $rule);

                    continue;
                }

                if (! $this->passeBuiltInRule($this->values[$key] ?? null, $rule)) {
                    $this->addMessage($key, null, $rule);
                }
            }
        }

        $this->isValidated = true;
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
            return $functionName($value);
        }

        if (str_contains($rule, ':')) {
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
            }
        }

        switch ($rule) {
            case 'required': return $value !== null;
            case 'uuid': return preg_match('/^[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}$/i', $value) === 1;
            case 'email':
                return preg_match(
                    // from https://www.emailregex.com/
                    '/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD',
                    $value
                ) === 1;
            case 'date': return strtotime($value) !== false;
        }

        return true;
    }
}
