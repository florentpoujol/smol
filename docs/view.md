# Views

Your view files are the place to put the HTML returned by your application.

They are expected to be `.php` or `.smol.php` files found in the `views` folder.

## PHP views

Did you know that PHP was actually itself a template engine ?

Your can write PHP within your HTML by surrounding the php code with the opening and closing tags like so :
```php
<html>
<body>
    <?php echo $content; ?>
    
    <ul>
    <?php foreach ($users as $user) { ?>
        <li><?php echo $user->name; ?></li>    
    <?php } ?>
    <ul>

</body>
</html>
```

Alternatively, when they are enabled, you can use short tags that will automatically echo the value :
```php
<?= $user->name ?>
```

There is also alternative syntax instead of using braces : 
```php
<ul>
<?php foreach ($users as $user): ?>
    <li><?= $user->name ?></li>    
<?php endforeach; ?>
<ul>
```

## Smol, Twig-like views

In addition, view which file name ends with `.smol.php` can use a small subset of [the Twig syntax](https://twig.symfony.com).

The following are supported :
```
{{ content }}
{{ content|e }}
{{ content|escape }}

{{ array.key }}
{{ object.property }}

{% for value in array %}
    {{ value.key }}
{% endfor %}

{# a comment #}
```

## Rendering a view from a controller

Resolve somehow an instance of the ViewRendered (typically by autowiring it in the controller's constructor) and call the `render(string $viewName, array $variables)` method on it.

The first argument is the path of the view file (possibly nested in subdirectories), with or without the extension.

The second argument is an associative array of variables that will exist in the view.

Example :

```php
<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolDocs\Http;

use FlorentPoujol\Smol\ViewRenderer;
use Nyholm\Psr7\Response;

final class Controller
{
    public function __construct(
        private ViewRenderer $viewRenderer
    ) {
    }

    public function get(): Response
    {
        $html = $this->viewRenderer->render('some/views', [
            'content' => 'some content',
        ]);

        return new Response(body: $html);
    }
}
```

This will look for a `views/some/view.smoll.php` and then for a `views/some/view.php` files if the first isn't found.
