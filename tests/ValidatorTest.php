<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SmolFramework;

use FlorentPoujol\SmolFramework\Components\Validation\RuleInterface;
use FlorentPoujol\SmolFramework\Components\Validation\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

final class ValidatorTest extends TestCase
{
    public function test_validate_array(): void
    {
        $validator = (new Validator())
            ->setData([
                'string' => 'the string',
                'int' => 123,
                'stdClass' => new stdClass(),
                'date' => '2021-11-21',
                'array' => [0, 1, 2],
            ])
            ->setRules([
                'string' => ['optional', 'string', 'length:10'],
                'int' => ['notnull', 'int', '>:122'],
                'stdClass' => ['exists', 'instanceof:stdClass'],
                'date' => ['date'],
                'array' => ['array', 'length:3'],
            ]);

        $isValid = $validator->isValid();

        self::assertSame([], $validator->getMessages());
        self::assertTrue($isValid);
    }

    public function test_validate_object(): void
    {
        $object = new TestEntityToValidate();
        $object->setProperties([
            'publicProperty' => 'publicProperty',
            'protectedProperty' => 'protectedProperty',
            'privateProperty' => 'privateProperty',

            'publicStaticProperty' => 'publicStaticProperty',
            'protectedStaticProperty' => 'protectedStaticProperty',
            'privateStaticProperty' => 'privateStaticProperty',

            'publicDynamicProperty' => 'publicDynamicProperty',
        ]);

        self::assertSame('publicProperty', $object->publicProperty);
        self::assertSame('publicStaticProperty', $object::$publicStaticProperty);
        self::assertSame('publicDynamicProperty', $object->publicDynamicProperty); // @phpstan-ignore-line

        $validator = (new Validator())
            ->setData($object)
            ->setRules([
                'publicProperty' => ['===:publicProperty'],
                'protectedProperty' => ['===:protectedProperty'],
                'privateProperty' => ['===:privateProperty'],

                'publicStaticProperty' => ['===:publicStaticProperty'],
                'protectedStaticProperty' => ['===:protectedStaticProperty'],
                'privateStaticProperty' => ['===:privateStaticProperty'],

                'publicDynamicProperty' => ['===:publicDynamicProperty'],
            ]);

        $isValid = $validator->isValid();

        self::assertSame([], $validator->getMessages());
        self::assertTrue($isValid);
    }

    // --------------------------------------------------
    // test all the rules

    public function test_is_method_rules(): void
    {
        $validator = (new Validator())
            ->setData([
                'string' => '123',
                'int' => 123,
                'float' => 12.3,
                'bool' => true,
                'object' => new stdClass(),
                'closure' => function (): void {},
                'array' => [0, 1, 2],
                'null' => null,
            ])
            ->setRules([
                'string' => ['string', 'numeric', 'scalar'],
                'int' => ['int', 'scalar'],
                'float' => ['float', 'scalar'],
                'bool' => ['bool', 'scalar'],
                'object' => ['object'],
                'closure' => ['callable'],
                'array' => ['array', 'iterable', 'countable'],
                'null' => ['null', 'optional'],
            ]);

        $isValid = $validator->isValid();

        self::assertSame([], $validator->getMessages());
        self::assertTrue($isValid);
    }

    public function test_parametrized_rules(): void
    {
        $validator = (new Validator())
            ->setData([
                'instanceof' => new Validator(),
                'regex' => '123.abcd',

                '>=' => 12,
                '>' => 12,
                '<=' => 12,
                '<' => 12,

                'minlength_string' => '12',
                'minlength_countable' => [1, 2],
                'maxlength_string' => '12',
                'maxlength_countable' => [1, 2],
                'length_string' => '12',
                'length_countable' => [1, 2],

                '==' => 12,
                '===' => '12',
            ])
            ->setRules([
                'instanceof' => ['instanceof:' . Validator::class],
                'regex' => ['regex:/^[0-9]{3}\.[a-z]{1}/'],

                '>=' => ['>=:12'],
                '>' => ['>:11'],
                '<=' => ['<=:12'],
                '<' => ['<:13'],

                'minlength_string' => ['minlength:2'],
                'minlength_countable' => ['minlength:2'],
                'maxlength_string' => ['maxlength:2'],
                'maxlength_countable' => ['maxlength:2'],
                'length_string' => ['length:2'],
                'length_countable' => ['length:2'],

                '==' => ['==:12'],
                '===' => ['===:12'],
            ]);

        $isValid = $validator->isValid();

        self::assertSame([], $validator->getMessages());
        self::assertTrue($isValid);
    }

    public function test_other_builtin_rules(): void
    {
        $validator = (new Validator())
            ->setData([
                'string' => '123',
                'uuid' => '0e9cb36e-a905-4f42-bbbe-9936353734d2',
                'uuid2' => '0e9cb36ea9054f42bbbe9936353734d2',
                'email' => 'some.e+mail@site.ab.cd',
                'date' => '1970-01-01',
                'datetime' => '1970-01-01 00:00:00',
            ])
            ->setRules([
                'string' => ['notnull'],
                'uuid' => ['uuid'],
                'uuid2' => ['uuid'],
                'email' => ['email'],
                'date' => ['date'],
                'datetime' => ['date'],
            ]);

        $isValid = $validator->isValid();

        self::assertSame([], $validator->getMessages());
        self::assertTrue($isValid);
    }
}

final class TestEntityToValidate
{
    public string $publicProperty = '';
    protected string $protectedProperty = '';
    private string $privateProperty = '';

    public static string $publicStaticProperty = '';
    protected static string $protectedStaticProperty = '';
    private static string $privateStaticProperty = '';

    /**
     * @param array<string, string> $data
     */
    public function setProperties(array $data): void
    {
        foreach ($data as $key => $value) {
            if (str_contains($key, 'Dynamic')) {
                $this->{$key} = $value; // @phpstan-ignore-line

                continue;
            }

            $reflectionProperty = new ReflectionProperty(self::class, $key);
            if ($reflectionProperty->isStatic()) {
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($value);
            } else {
                $this->{$key} = $value; // @phpstan-ignore-line
            }
        }
    }
}

final class TestRule implements RuleInterface
{
    public function passes(string $key, mixed $value): bool
    {
        return true;
    }

    public function getMessage(string $key): ?string
    {
        return 'Some message';
    }
}
