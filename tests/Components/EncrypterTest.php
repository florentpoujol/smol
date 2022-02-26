<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Tests\Components;

use Exception;
use FlorentPoujol\Smol\Components\Encrypter\Cipher;
use FlorentPoujol\Smol\Components\Encrypter\Encrypter;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EncrypterTest extends TestCase
{
    private function getEncrypter(Cipher $cipher = Cipher::AES_256_GCM): Encrypter
    {
        return new Encrypter($cipher->generateKey(), $cipher);
    }

    public function test_encrypt_string(): void
    {
        $encrypter = $this->getEncrypter();

        $expected = 'abcdef';

        $encrypted = $encrypter->encrypt($expected);
        self::assertNotSame($expected, $encrypted);

        self::assertSame($expected, $encrypter->decrypt($encrypted));
    }

    public function test_encrypt_int(): void
    {
        $encrypter = $this->getEncrypter();

        $expected = 123456789;
        self::assertSame($expected, $encrypter->decrypt($encrypter->encrypt($expected)));
    }

    public function test_encrypt_int_no_deserialize(): void
    {
        $encrypter = $this->getEncrypter();

        $expected = 123456789;
        self::assertSame("i:$expected;", $encrypter->decrypt($encrypter->encrypt($expected), false));
    }

    public function test_encrypt_array(): void
    {
        $encrypter = $this->getEncrypter();

        $object = new stdClass();
        $object->prop = 'the property';

        $expected = [
            'string' => 'abcdef',
            'int' => 123456789,
            'object' => $object,
        ];

        $actual = $encrypter->decrypt($encrypter->encrypt($expected));

        self::assertEquals($expected, $actual);
        self::assertSame($object->prop, $actual['object']->prop);
    }

    public function test_decrypt_with_another_key(): void
    {
        $encrypter = $this->getEncrypter(Cipher::AES_256_GCM);

        $expected = 'some value';
        $encrypted = $encrypter->encrypt($expected);

        $encrypter2 = new Encrypter(Cipher::AES_256_GCM->generateKey(), Cipher::AES_256_GCM);

        $this->expectException(Exception::class);
        $encrypter2->decrypt($encrypted);
    }

    // --------------------------------------------------
    // test all algorithms

    public function test_aes_128_cbc(): void
    {
        $encrypter = $this->getEncrypter(Cipher::AES_128_CBC);

        $expected = bin2hex(random_bytes(32));
        self::assertSame($expected, $encrypter->decrypt($encrypter->encrypt($expected)));
    }

    public function test_aes_256_cbc(): void
    {
        $encrypter = $this->getEncrypter(Cipher::AES_256_CBC);

        $expected = bin2hex(random_bytes(32));
        self::assertSame($expected, $encrypter->decrypt($encrypter->encrypt($expected)));
    }

    public function test_aes_128_gcm(): void
    {
        $encrypter = $this->getEncrypter(Cipher::AES_128_GCM);

        $expected = bin2hex(random_bytes(32));
        self::assertSame($expected, $encrypter->decrypt($encrypter->encrypt($expected)));
    }

    public function test_aes_256_gcm(): void
    {
        $encrypter = $this->getEncrypter(Cipher::AES_256_GCM);

        $expected = bin2hex(random_bytes(32));
        self::assertSame($expected, $encrypter->decrypt($encrypter->encrypt($expected)));
    }
}
