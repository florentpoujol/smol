<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Config;

use FlorentPoujol\Smol\Components\Validation\Validator;
use ReflectionClass;
use ReflectionProperty;
use function FlorentPoujol\Smol\Infrastructure\env;

abstract class Config
{
    private function __construct()
    {
    }

    /** @var array<class-string<static>, static> */
    private static array $_instances = [];

    public static function make(): static
    {
        $fqcn = static::class;
        if (isset(self::$_instances[$fqcn])) {
            return self::$_instances[$fqcn];
        }

        $instance = new static();

        // set properties from environment
        $properties = (new ReflectionClass($instance))->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            foreach ($property->getAttributes() as $attribute) {
                if ($attribute->getName() !== Env::class) {
                    continue;
                }

                $arguments = $attribute->getArguments();
                $envVarName = $arguments[0];
                $envVarValue = env($envVarName);

                $defaultValue = $arguments[1] ?? null; // note that the default value can also be set as the regular property default value

                if ($envVarValue !== null || $defaultValue !== null) {
                    $property->setValue($instance, $envVarValue ?? $defaultValue);
                }
            }
        }

        // validate
        (new Validator())->setData($instance)->throwIfNotValid();

        self::$_instances[$fqcn] = $instance;

        return $instance;
    }

    /**
     * @param null|class-string<static> $fqcn
     *
     * @return int Number of instances cleared
     */
    public static function clearInstances(string $fqcn = null): int
    {
        if ($fqcn === null) {
            $count = count(self::$_instances);
            self::$_instances = [];
        } else {
            $count = (int) isset(self::$_instances[$fqcn]);
            unset(self::$_instances[$fqcn]);
        }

        return $count;
    }
}
