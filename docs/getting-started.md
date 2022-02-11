# Getting Started

## What is Smol

Smol is a **lean** but **full-featured** framework for PHP 8.0+.

By "lean" believe that when building a resilient modern application, the amount of code that you have, particularly including your dependencies matter and that less is better.

Smol strive to have the smallest footprint and the least amount of dependencies possible.

But in order to reduce the amount of dependencies you might need, the framework needs to provide all the features instead.

Smol balance theses requirements by providing its own but bare-bone, **straight-forward** implementation of each feature, **just enough for most of the use-cases**.  

As such, the framework does not aim to cover every single use cases and edge cases imaginables, like [Symfony](https://symfony.com) does.    
Nor does it aim to provide a lot of (or any, really) conveniences methods for "quick DX", like [Laravel](https://laravel.com) does.

Everything has one way to be done and the minimum configuration necessary with sensible defaults.

Out of that, you get **simplicity** and somewhat **speed**.

Even if it re-implement the wheel, we do not reinvent it.  
Using the framework will feel familiar and simple if you are already used to a modern framework, and we take advantage of the standards the PHP landscape has to offer today like dependency injection container or PSR compliant implementations. 

Also, there is two exceptions: security and development.    
Security is no joke, and as such security features are provided by the Symfony Security component.    
As for development, the framework integrates with the PHP Debugbar, Ignition and Whoops, and you can of course test your projet with PHPUnit. 

In addition to that, we took advantage to have started writing it recently to make our code fully strictly typed and statically analysable at the strictest level.

## General feature list

- array-based configuration, with `.env` file support
- DI container with autowiring
- Router, with regex segments, injection of captured route segments to controller arguments, fallback routes, redirects 
- callable-based controllers
- HTTP PSR-15 or callable-based middleware
- PSR-7/17 objects (provided by Nyholm\Psr7\)
- request body validation
- Views, with optional Twig-like template
- PSR-18 HTTP client


## Hello world

In the route.php file :
```php
<?php 

declare(strict_types=1);

return [
    new Route('get', '/hello-world', Controller::class . '@hello'),
];
```

In the Controller : 
```php
<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;

final class Controller
{
    public function hello(): Response
    {
        return new Response(body: 'hello work');
    }
}
```

TODO insert statistics on how many methods are called, how many objects are instantiated.
and throw a benchmark for good measure
