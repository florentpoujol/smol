# Middleware

A middleware is a piece of code that runs before and after the request reach the controller method.  
It typically does thing with the request like authorization and/or with the response like inserting headers.

If a middleware returns a response before the controller method is called, it shunts the request life cycle.  
Further middleware and the controller method aren't called, but the response is passed through the middleware that have run.

Smol support both [PSR-15](https://www.php-fig.org/psr/psr-15/) middleware and simpler callable-based ones, but you can not mix them on one route.

## Assigning middleware to routes

They have to be registered individually for each route, with the `setMiddleware(array $middleware)` of the Route object.

Example :
```php
return [
    (new Route('get', '/admin/{page}', 'Controller@get'))->setMiddleware([
        MyPsr15Middleware::class,
    ]),
];
```

One of the main motivation to have several routes files in the `routes` folder of your application is that it allows so easily separate routes that have a vastly different middleware stack.     
In such files, you can easily define the middleware once, and then set them on all the routes for instance like so :

```php
$middleware = [
    MyMiddleware::class,
    OtherMiddleware::class,
];

return array_map(
    fn (Route $route): Route => $route->setMiddleware($middleware), 
    [
        new Route('get', '/admin/{page}', 'Controller@get'),
    ],
);
```

## PSR-15 middleware

Not much to say here beside they are autowired when instantiated.

As handler, they are passed an instance of `\FlorentPoujol\Smol\Psr15RequestHandler`, from which you can easily fetch the resolved route instance, or even the framework instance.

## Callable middleware

Like for the controller action, the framework support using any callable, as well as "at string" (`Controller@method`) as middleware.

As first argument, they receive either the server request, or the response instance and the route instance as second argument.

Middlewares are run in the order they are passed to the route and unlike the PSR-15 ones **they run sequentially, not "in onion"**.

When running before the controller action, they receive the server request and are expected to return a response only if they want to terminate the request lifecycle early.

When running after the controller action (or after a middleware has returned a response), they receive the response and may modify it or create a new instance and return it.

Middleware that received the request will always receive the response in the opposite order, even if the controller action hasn't been reached.
