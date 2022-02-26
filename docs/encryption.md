# Encription

The `Encrypter` class provide an easy interface to securely encrypt and decrypt any data.

See the `Cipher` enum for supported cyphers.

## Generate a key

Call the `generateKey()` method on the cipher, which returns a binary string, that you can then store as a regular string in base64 or hexadecimal (typically 32 or 64 characters long).

```php
$key = Cipher::AES_256_GCM->generateKey();

$stored = bin2hex($key)
// and then hex2bin($key)

$stored = base64_encode($key)
// and then base64_decode($key, true)
```

Once you have the key and the algorithm, you can instanciate an encrypter instance
```php
$key = Cipher::AES_256_GCM->generateKey();
$stored = bin2hex($key)

$encrypter = new Encrypter(hex2bin($stored), Cipher::AES_256_GCM);
```

## Encryption

Then call the `encrypt(mixed $data, bool $serialize = true)` method on the encrypter instance with your data, which return a base64 string.

```php
$key = Cipher::AES_256_GCM->generateKey();

$data = [
    'some' => 'data',
];

$encrypter = new Encrypter($key, Cipher::AES_256_GCM);

$encryptedValue = $encrypter->encrypt($data);
// $encrypted is a base64 string
```

The data can only be scalars, arrays and stdClass instance.

## Decryption

Call the `decrypt(string $encryptedValue, bool $unserialize = true)` method on the encrypter instance with the encrypted string, which on success, returns whatever value was encrypted.

```php
$key = Cipher::AES_256_GCM->generateKey();

$data = [
    'some' => 'data',
];

$encrypter = new Encrypter($key, Cipher::AES_256_GCM);
$encryptedValue = $encrypter->encrypt($data);

$decryptedData = $encrypter->decrypt($encryptedValue);

$decryptedData === $data; // true
```

In case of any error in the decryption, an Exception is raised.
