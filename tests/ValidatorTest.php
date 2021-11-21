<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SmolFramework;

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
                'string' => ['nullable', 'string', 'size:10'],
                'int' => ['required', 'int', 'min:122'],
                'stdClass' => ['present', 'instanceof:stdClass'],
                'date' => ['date'],
                'array' => ['array', 'size:3'],
            ]);

        self::assertTrue($validator->isValid());
        self::assertEmpty($validator->getMessages());
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
                'publicProperty' => ['same:publicProperty'],
                'protectedProperty' => ['same:protectedProperty'],
                'privateProperty' => ['same:privateProperty'],

                'publicStaticProperty' => ['same:publicStaticProperty'],
                'protectedStaticProperty' => ['same:protectedStaticProperty'],
                'privateStaticProperty' => ['same:privateStaticProperty'],

                'publicDynamicProperty' => ['same:publicDynamicProperty'],
            ]);

        self::assertTrue($validator->isValid());
        self::assertEmpty($validator->getMessages());
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
                'null' => ['null', 'nullable'],
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

                'min_int' => 12,
                'min_string' => '12',
                'min_countable' => [1, 2],
                'gte' => 12,

                'max_int' => 12,
                'max_string' => '12',
                'max_countable' => [1, 2],
                'lte' => 12,

                'gt' => 12,
                'lt' => 12,

                'size_int' => 12,
                'size_float' => 12.3,
                'size_string' => '12',
                'size_countable' => [1, 2],

                'equal' => 12,
                'same' => '12',
            ])
            ->setRules([
                'instanceof' => ['instanceof:' . Validator::class],
                'regex' => ['regex:/^[0-9]{3}\.[a-z]{1}/'],

                'min_int' => ['min:12'],
                'min_string' => ['min:2'],
                'min_countable' => ['min:2'],
                'gte' => ['gte:12'],

                'max' => ['max:12'],
                'max_string' => ['max:2'],
                'max_countable' => ['max:2'],
                'lte' => ['lte:12'],

                'gt' => ['gt:11'],
                'lt' => ['lt:13'],

                'size_int' => ['size:12'],
                'size_float' => ['size:12.3'],
                'size_string' => ['size:2'],
                'size_countable' => ['size:2'],

                'equal' => ['equal:12'],
                'same' => ['same:12'],
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
                'string' => ['required'],
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

class TestEntityToValidate
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
