
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
