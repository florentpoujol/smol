<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components;

use FlorentPoujol\Smol\Infrastructure\Exceptions\SmolException;

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
            throw new SmolException("Couldn't find view '$originalViewPath' in path '$this->baseAppPath/views/'.");
        }

        if (str_ends_with($viewPath, '.smol.php')) {
            $viewPath = $this->compileSmolTemplate($viewPath);
        }

        extract($variables, EXTR_OVERWRITE);

        ob_start();
        require $viewPath;
        $viewContent = ob_get_clean();
        if (! is_string($viewContent)) {
            throw new SmolException("Couldn't get buffer output from view at path '$viewPath'.");
        }

        return $viewContent;
    }

    public function compileSmolTemplate(string $viewPath): string
    {
        $viewContent = file_get_contents($viewPath);
        if (! is_string($viewContent)) {
            throw new SmolException("Can't read view at path '$viewPath'.");
        }

        $hash = md5($viewContent);
        $compiledViewPath = "$this->baseAppPath/storage/git-ignored/compiled-views/$hash.php";
        if (file_exists($compiledViewPath)) {
            return $compiledViewPath;
        }

        // --------------------------------------------------
        // deal with extending parents

        $extendsPattern = '/{%\s*extends\s+("|\')?(?<parent>[a-zA-Z-0-9_\.\/-]+)("|\')?\s*%}/';
        $matches = [];
        if (preg_match($extendsPattern, $viewContent, $matches) === 1) {
            $parentPath = "$this->baseAppPath/views/$matches[parent].smol.php";
            $parentContent = file_get_contents($parentPath);
            if (! is_string($parentContent)) {
                throw new SmolException("Can't read parent view at path '$parentPath'.");
            }

            // first collect the content of each block in the child
            $blockPattern = '/{%\s*block\s+(?<blockname>[a-zA-Z0-9_-]+)\s*%}(?<blockcontent>.+){%\s*endblock\s*%}/sU'; // s= . match newline, U = ungreedy quantifiers
            $matches = [];
            preg_match_all($blockPattern, $viewContent, $matches);
            $childBlocks = array_combine(array_values($matches['blockname']), array_values($matches['blockcontent']));

            // then do the same in parents and swap the ones that exists in both
            $parentBlocks = [];
            preg_match_all($blockPattern, $parentContent, $parentBlocks);

            foreach ($childBlocks as $blockName => $childBlockContentWithoutTag) {
                // the blocks in the parent may not be (or matched) in the same order as in the children
                $matchedParentBlockId = array_search($blockName, $parentBlocks['blockname'], true);
                $parentBlockWithTagsAndDefaultValue = $parentBlocks[0][$matchedParentBlockId];

                $parentContent = str_replace($parentBlockWithTagsAndDefaultValue, $childBlockContentWithoutTag, $parentContent);
            }

            $viewContent = $parentContent;
        }

        // --------------------------------------------------
        // now that we have reunited the parent and children, we can replace all the stuffs

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

            // remaining blocks, not replaced by child template
            '/{%\s*block\s+[a-zA-Z0-9_-]+\s*%}/' => '',
            '/{%\s*endblock\s*%}/' => '',

            // TODO add more syntax : ifs, numerical for, some more filters
        ];

        $viewContent = preg_replace(array_keys($patterns), array_values($patterns), $viewContent);

        $success = file_put_contents($compiledViewPath, $viewContent);
        if ($success === false) {
            throw new SmolException("Couldn't write compiled view at path '$compiledViewPath'");
        }

        return $compiledViewPath;
    }
}
