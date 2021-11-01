<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework;

final class ViewRenderer
{
    public function __construct(
        private string $baseAppPath
    ) {
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $viewPath, array $variables = []): string
    {
        $originalViewPath = $viewPath;
        $viewPath = "$this->baseAppPath/views/$viewPath";

        if (! str_ends_with($viewPath, '.php')) {
            $viewPath .= '.smol.php';
            if (! file_exists($viewPath)) {
                $viewPath = str_replace('.smol.php', '.php', $viewPath);
            }
        }

        if (! file_exists($viewPath)) {
            throw new SmolFrameworkException("Couldn't find view '$originalViewPath' in path '$this->baseAppPath/views/'.");
        }

        if (str_ends_with($viewPath, '.smol.php')) {
            $viewPath = $this->compileSmolTemplate($viewPath);
        }

        extract($variables, EXTR_OVERWRITE);

        ob_start();
        require $viewPath;
        $viewContent = ob_get_clean();
        if (! is_string($viewContent)) {
            throw new SmolFrameworkException("Couldn't get buffer output from view at path '$viewPath'.");
        }

        return $viewContent;
    }

    public function compileSmolTemplate(string $viewPath): string
    {
        $viewContent = file_get_contents($viewPath);
        if (! is_string($viewContent)) {
            throw new SmolFrameworkException("Can't read view at path '$viewPath'.");
        }

        $hash = md5($viewContent);
        $compiledViewPath = "$this->baseAppPath/storage/git-ignored/compiled-views/$hash.php";
        if (file_exists($compiledViewPath)) {
            return $compiledViewPath;
        }

        $patterns = [ // search => replace
            // {{ something }}
            '/{{\s*([a-zA-z0-9_-]+)\s*}}/' => '<?= \$$1; ?>',
            // {{ something|e }} {{ something|escape }}
            '/{{\s*([a-zA-z0-9_-]+)\|(e|escape)\s*}}/' => '<?= htmlspecialchars(\$$1); ?>',
            // {{ something.something }}
            '/{{\s*([a-zA-z0-9_-]+)\.([a-zA-z0-9_-]+)\s*}}/' => '<?= is_object(\$$1) ? \$$1->$2 : \$$1[\'$2\']; ?>',
            // TODO : return null value. When object notation, check if it match getBar(), isBar(), hasBar() methods

            // {% for something in something %}
            '/{%\s*for\s+([a-zA-z0-9_-]+)\s+in\s+([a-zA-z0-9_-]+)\s*%}/' => '<?php foreach (\$$2 as \$$1) { ?>',
            // {% endfor %}
            '/{%\s*endfor\s*%}/' => '<?php } ?>',

            // {# comment #}
            '/{#[^#]*#}/' => '',

            // TODO add more syntax : ifs, numerical for, some more filters
        ];

        $viewContent = preg_replace(array_keys($patterns), array_values($patterns), $viewContent);

        $success = file_put_contents($compiledViewPath, $viewContent);
        if ($success === false) {
            throw new SmolFrameworkException("Couldn't write compiled view at path '$compiledViewPath'");
        }

        return $compiledViewPath;
    }
}
