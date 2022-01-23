<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components;

use Error;
use FlorentPoujol\SmolFramework\Components\Validation\ValidationException;
use FlorentPoujol\SmolFramework\Components\Validation\Validator;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use UnexpectedValueException;

abstract class Entity
{
    public static function fromArray(array $array, bool $throwOnMissingProperty = true, bool $validate = true): static
    {
        $instance = new static();

        foreach ($array as $key => $value) {
            if (! property_exists($instance, $key)) {
                if ($throwOnMissingProperty) {
                    $class = $instance::class;
                    throw new UnexpectedValueException("Missing property '$key' on instance $class.");
                }

                continue;
            }

            $instance->_properties[$key] = false;

            if (! is_array($value)) {
                $instance->{$key} = $value;

                continue;
            }

            // the value is an array, so check if the typehint isn't also a struct
            $reflProperty = new ReflectionProperty($instance, $key);
            $reflType = $reflProperty->getType();
            if ($reflType === null) { // no typehint, so yolo
                $instance->{$key} = $value;

                continue;
            }

            if ($reflType instanceof ReflectionNamedType) {
                $typeName = $reflType->getName();
                if ($typeName === 'array') {
                    $instance->{$key} = $value;

                    continue;
                }

                if (in_array(self::class, class_parents($typeName), true)) {
                    /** @var class-string<self> $typeName */
                    $instance->{$key} = $typeName::fromArray($value, $throwOnMissingProperty);
                    $instance->_properties[$key] = true;

                    continue;
                }
            }

            $fqcn = $instance::class;
            throw new UnexpectedValueException("Unsupported type on property '$key' of instance $fqcn.");
        }

        if ($validate) {
            $instance->validate();
        }

        return $instance;
    }

    // --------------------------------------------------
    // validation

    /** @var array<string, bool> Key is the property name, value is true if its typehint is an Entity instance */
    protected array $_properties = [];

    /** @var array<string, array<string>> */
    protected array $validationRules = [];

    /**
     * @return array<string, array<string>>
     */
    protected function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /** @var class-string<Validator> */
    protected string $validatorFqcn = Validator::class;

    public function validate(bool $recursive = true): void
    {
        $rules = $this->getValidationRules();
        if ($rules !== []) {
            (new $this->validatorFqcn())
                ->setRules($rules)
                ->setData($this)
                ->throwIfNotValid();
        }

        if ($this->_properties !== []) {
            foreach ($this->_properties as $propertyName => $isStruct) {
                try {
                    $this->{$propertyName}; // this will throw an Error if this is an uninitialized type property, this is what we want
                } catch (Error $error) {
                    throw new ValidationException($this, [$propertyName => [$error->getMessage()]]);
                }

                if ($recursive && $isStruct) {
                    $this->{$propertyName}->validate();
                }
            }

            return;
        }

        // if the Struct wasn't hydrated through fromArray(), resort to reflection here to loop on all properties and recursively validate

        $reflProperties = (new ReflectionClass($this))->getProperties();
        foreach ($reflProperties as $reflProperty) {
            if (! $reflProperty->isPublic()) {
                continue;
            }

            $propertyName = $reflProperty->getName();

            try {
                $this->{$propertyName};
            } catch (Error) {
                throw new ValidationException($this, [$propertyName => [$error->getMessage()]]);
            }

            if (! $recursive) {
                continue;
            }

            $reflType = $reflProperty->getType();
            if (
                $reflType instanceof ReflectionNamedType
                && in_array(self::class, class_parents($reflType->getName()), true)
            ) {
                $this->{$propertyName}->validate();
            }
        }
    }
}