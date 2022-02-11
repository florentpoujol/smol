# Dependency Injection Container

`\FlorentPoujol\Smol\Container\Container` is a PSR-11 container.

The two main uses of a DI Container is to return a working object instance when asked for an interface, as well as autowiring (automatically filling) constructor arguments when instantiating a new instance.

## Aliasing concrete classes to interfaces

Pass the interface and the concrete class fuly qualified class anmes to the `setFactory()` method.

Example: 
```php
$container->setFactory(\Psr\Log\LoggerInterface::class, \FlorentPoujol\Smol\Log\DailyFileLogger::class);
```

In your controller, you can typehint the constructor for the `LoggerInterface`, you will receive a `DailyFileLogger` instance.

If, in the container you swap the `DailyFileLogger` class by any other , you will receive that new class in your controller without changing it.

## Providing actual factory functions

When a class can not (or you don't want to) be created automatically by the container, you can provide an actual factory, in the form of any callable, that will just return the instance .

```php
$container->setFactory(LoggerInterface::class, function (Container $container, array $extraArguments = []): DailyFileLogger {
    $baseAppPath = $container->getParameter('baseAppPath');
    return new DailyFileLogger($baseAppPath);
});

$container->setFactory(ServerRequestInterface::class, [ServiceFactories::class, 'makeServerRequest']);
```

The callable receive two arguments : the instance of the container itself and whatever is passed as second orgument of the container's `get()` or `make()` methods.


## Resolving objects out of the container

Its main usage will be through autowiring, but you can also use the container directly to resolve objects.

You can autowire the Container or get it via the Framework's `getContainer()` methods :

```php
// In a service
__construct(
    private \Psr\Container\ContainerInterface::class $container,
)

// or anywhere else
$container = Framework::getInstance()->getContainer();
```

Then you can use its two methods :
- `get(string $serviceName, array $extraArguments = []): object` 
- `make(string $serviceName, array $extraArguments = []): ?object`

`get()` will throw a `NotFoundException` if the service can't be resolved, where `make()` can return `null`.  
Also `get()` will always return the same instance, whereas `make()` will always create a new instance.

The second argument is used to directly pass values for the constructor arguments when they can't be autowired.  
It is expected to be an associative array where the keys match some of the constructor's arguments names. 

## Parameters

When a constructor argument isn't an interface or a class, and has a primitive type or no type at all the container can not know on its own how to build the value for that, unless they are provided via the `$extraArguments` argument of the `get()` or `make()` methods, or they are set as parameters in the container. 

Example:
```php
final class MyClass
{
    public function __construct(
        private SomeClass $someClass,
        private int $somePrimitiveValue
    ) {}
}

$container->get(MyClass::class); 
// would throw a ContainerException because the container doesn't known which value to pass to `$somePrimitiveValue`

$container->get(MyClass::class, [
    'somePrimitiveValue' => 10,
]);
// this would work

// if the value may appear in several controller and is known in advance it can be set directly in the container
$container->setParameter('somePrimitiveValue', 10);
$container->get(MyClass::class); 
// this would also work
```

### Parameter alias

You can set a parameter to point to another parameter that has a different name, if you prefix it with `%`;

Example:
```php
final class MyClass
{
    public function __construct(
        private SomeClass $someClass,
        private int $somePrimitiveValue
    ) {}
}

// if the value may appear in several controller and is known in advance it can be set directly in the container
$container->setParameter('someOtherParam', 10);
$container->get(MyClass::class, [
    'somePrimitiveValue' => '%someOtherParam',
]); 
```




