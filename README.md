
# Smol framework

A small but almost fully featured modern framework for PHP8+.

## Goals

- have the smallest footprint possible (the fewest amount of code possible)
- but also have the fewest amount of production third party composer dependencies and non-default PHP extensions
- be non-pessimized by default, strive to not do something if it is not usefull, treat the amont of code, number of objects instanciated, method calls as a core metric and keep as low as possible 
- provide a simple version of all the basic standard feature set enough for the 80% use cases
- implement PSRs and/or Symfony contracts where pertinent ? Or no interfaces at all, but the framework still should to be fairly easily extensible by swaping built-in features/objects by more robust/full-featured implementation
- mimic popular packages API's where pertinent (instead of implementing API)
- be fully strictly typed and fully statically analysable (PHPStan at max level)
- have a full documentation website and companion/example projects (starter project, CRUD admin panel, API starter pack)

## Phylosophy

Smol is a **modern**, **full-featured**, but **lean**, framework for PHP 8.1+.

**modern**

As PHP progresses,  event-loop frameworks like [Swoole](https://www.swoole.co.uk/) are gaining in popularity, and with a strong selling point: make your app so much faster.
Running your app as a long-running application server instead of the traditional short-lived PHP-FPM process has many avantages that goes beyond performance.

Smol as built-in support for been run as a long-running application server, under the Swoole extension.

Thanks to having been written recently, the framework only support the most recent PHP version and makes good use of all its nices features extensively, most notably its type system.
The whole framework is fully strictly typed and run on PHPStan at level 8.

**Full-featured**

Smol provide out-of-the-box most features you would expect from a full-stack framework, including some advanced ones, see the feature list below.

There is no limitation as to what kind of application you can build with it.

Per the feature list, Smol is not a micro-framework that basically only provide the router, like for instance Slim does.

**Lean**

We believe that when building a resilient modern application, we should strive to reduce to a minimum
- the dependencies to third-party Composer packages
- the total amount of code that the application has (including dependencies)
- the amount of code that actually run, that the number of methods calls and object instanciation is a metric that matter

Smol achieve the first goals by having no Composer dependencies at all.

The second goal is achieved despite the "full-featured" aspect by providing our own, but bare-bone implementation of all the features.  
Unlike Symfony components that impressively covers every single use cases and edge cases, or Laravel that offer many conveniences methods, we designed our components to cover only "the 80% use cases", which makes them still usefull for most while keeping their code more straight-forward, and smaller.

Everything has one way to be done and the minimum configuration necessary with sensible defaults.

Out of that, you get **simplicity** and **speed**.

Even if it re-implement the wheel, we do not reinvent it.  
Using the framework will feel familiar and simple if you are already used to a modern framework, and we take advantage of the standards the PHP landscape has to offer today like dependency injection container or PSR compliant implementations. 

Also, there is two exceptions: security and development.    
Security is no joke, and as such security features are provided by the Symfony Security component.    
As for development, the framework integrates with the PHP Debugbar, Ignition and Whoops, and you can of course test your projet with PHPUnit. 

## Features

- general
	- .env file and array-based configuration
	- public front-controller index.php file

- web stuffs
	- router + route definitions in files
	- callable or class-based controllers
	- laravel-like middleware
	- request / response objects
	- body validation
	- twig-like views
	- cookies
	- http sessions
	- url generation
	- HTTP client

- Other
	- cache : PSR16 built-in dans fichiers ou redis
	- collection ?
	- i18n/translations
	- built-in file storage
	- no global helpers appart  `__()` et `env()`
	- event system (PSR 14 Event dispatcher)
	- general exception handling, with built-in whoops
	- PSR3/Monolog compatible log system
	- built-in support for http proxies
	- email
	- basic queues
	- simple query builder or even simple DataMapper ORM
	- built-in way to run as a long-running server with Swoole

- tests
    - ability to test the database and the web stuffs like laravel

- console
	- laravel like scheduler
	- artisan like cli ?
	- tinker-like REPL ?

## Projects to go along the framework

- a starter/squeleton project
- the documentation website
- a basic CRUD admin panel that rely on the built-in ORM/entities
- an package and/or starter project for building API
- an package and/or starter project for building a Wordpress-like blog with static pages
