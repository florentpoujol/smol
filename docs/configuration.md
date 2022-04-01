# Configuration and environment

## Framework configuration

The framework as its own store for primitive configuration it may use itself.

Typically, it is modified through the constructor's `$frameworkConfig` argument in your project's `index.php` file.

But you can also get or set them via the `get(string $key, string $defaul = null): ?string` and `set(string $key, ?string $value): self` methods.  
Unlike the application configuration this one is flat, values can only be null or string.

Values set in the framework config before the framework is booted **can be autowired**.

For instance `baseAppPath` is one of the framework config, you can get that value in any autowired service just by having an argument with that name.

Exemple:
```php
final class MyService
{
    public function __construct(
        private string $baseAppPath
    ) {
    }
}
```

## Application configuration

General configuration for your application can be stored and fetched via the `ConfigRepository` service that offers the classic `get(string $key, mixed $default = null): mixed` and `set(string $key, mixed $value): void` methods.

Configuration sources are `.php` files that return an associative array, expected to be found in the `config` folder of your application. 

Nested config files aren't allowed, but nested values are.  
You can use the classic *dot notation* to fetch theses values.

Exemple:
```php
return [
    'key' => 'value',
    'array' => [
        'nested-key' => 'nested-value',
    ],
];
```

```php
$configRepository->get('unknown-key'); // null
$configRepository->get('unknown-key', 'default value'); // "default value"

$configRepository->set('unknown-key', null); // existing keys that are null don't return the default value
$configRepository->get('unknown-key', 'default value'); // null

$configRepository->get('key'); // 'value'
$configRepository->get('array.nested-key'); // 'nested-value'
```

## Environment

Configuration values may differ from environments when they are set via the standard `env(string $envVar, mixed $default = null): mixed` global function.  
Values that are `true`, `false` or `null` are converted to their corresponding PHP types.

Also, additional environment values may be defined in an `.env` file at the root of your project.

Some restrictions apply on the format for the keys/values :
- keys and their values must be on the same line
- keys and values are trimmed for whitespace
- if you need whitespace at the beginning or end of a value, surround it with single or double quotes
- comments after the value aren't allowed

Exemple :

The `.env` file :
```dotenv
SOME_VAR=some value
 WHITESPACE_ARE_TRIMMED  =  some value
WITH_QUOTES=" some value with whitespace  "
A_BOOLEAN=false

# all lines that don't look like VAR=value are ignored(even without a # in front)
```

A `config/app.php` file :

```php
return [
    'key' => 'value',
    'from-env' => [
        'unknown-var' => env('UNKNOWN_VAR', 'the default value'),
        'unknown-var-without-default' => env('UNKNOWN_VAR'),
        'trimmed-whitespaces' => env('WHITESPACE_ARE_TRIMMED'),
        'with-quotes' => env('WHITESPACE_ARE_TRIMMED'),
        'a boolean' => env('A_BOOLEAN'),
    ],   
];
```

This file returns the following config :
```php
[
    'key' => 'value',
    'from-env' => [
        'unknown-var' => 'the default value',
        'unknown-var-without-default' => null,
        'trimmed-whitespaces' => 'some value',
        'with-quotes' => ' some value with whitespace  ',
        'a boolean' => false, // instead of 'false'
    ],   
];
```


## Strong object-based configuration

Beside traditional loose array-based configuration like above, you can create strictly typed, validated, object-based configuration.

To do so, have a class extends the abstract `Config` class and define properties.    
Those which value come from environment variables, can have the `Env` attribute set to the name of the env variable. 
You can also validate values with the `Validates` attributes.

```php
final class MyConfig extends AbstractConfig
{
    #[Env('SOME_ENV_VAR')]
    public readonly string $key;
    
    #[Env('SOME_OTHER_ENV_VAR')]
    #[Validates(['minLength:5'])]
    public string $otherKey = 'default value';
}
```

Default values can also be set as the second argument of the Env attribute, which allows you to not set it on the property itself, and thus allows you to mark it readonly:
```php
#[Env('SOME_OTHER_ENV_VAR', 'default value')]
#[Validates(['minLength:5'])]
public readonly string $otherKey;
```

To get an instance of that class with the properties properly filled with their default value or the one from the environment, call the static `make()` method that returns a singleton.

To be able to inject it via dependency injection, you can just register this `make` method as the object's factory.

Ie:
```php
$config = MyConfig::make();
if ($config->key === '...') {
    // ...
}

// or
$container->bind(MyConfig::class, 'MyConfig::make');

// then in a controller or service class
public function __construct(MyConfig $config)
{
}
```
