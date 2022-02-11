# Routing

Routes are defined in `.php` files found in the `routes` folder of your project that must return an array of `Route` instances.  
You can define several routes files in the folder.

Example :
```php
return [
    new Route('get', '/', 'redirect:/docs/getting-started'),
    new Route('get', '/{docsOrGuides}/{page}', Controller::class . '@get', [
        'docsOrGuides' => '(docs|guides)',
        'page' => '[a-z0-9-]+',
    ]),
];
```

The route object accept these arguments :
- one or several (as an array) http methods
- the uri, which can have placeholder segment
- the action (controller) : a callable that will be called as the target of the route
- when the route has placeholders, an optional assoc array with regexes each segments must match
- the route name

## URI placeholders

Uri segments surrounded by braces are placeholder which value :
- will be matched against the regexes defined in the fourth `regexPlaceholders` argument and which
- will be injected to the action's argument if one match the placeholder's name. In the example above, the controller method would have that signature `get(string $docsOrGuides, string $page)`

## Actions

The action can be any callable, or the two following form of string :
- an "at" string like `Controller@method`, where "Controller" is the fully qualified class name of a controller that will be **instantiated and autowired**
- a redirection, see below

### Redirections

An action defined as a string that begins by `redirect:` or `redirect-permanent:` and is followed by a relative or absolute URI will automatically redirect the user to the provided URI, with a 302 or 301 status code, respectively.
