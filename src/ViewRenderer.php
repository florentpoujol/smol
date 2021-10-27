<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

use const EXTR_OVERWRITE;

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
        if (! str_ends_with($viewPath, '.php')) {
            $viewPath .= '.php';
        }

        extract($variables, EXTR_OVERWRITE);

        ob_start();
        require $this->baseAppPath . "/views/$viewPath";
        $viewContent = ob_get_clean();
        assert(is_string($viewContent));

        return $viewContent;
    }
}
