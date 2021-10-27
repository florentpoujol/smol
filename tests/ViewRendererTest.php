<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SimplePhpFramework;

use FlorentPoujol\SimplePhpFramework\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class ViewRendererTest extends TestCase
{
    public function test_basic_view_without_variables(): void
    {
        $rendered = new ViewRenderer(__DIR__ . '/Fixtures');

        $this->expectError();
        $this->expectErrorMessage('Undefined variable $viewData');
        ob_end_clean();

        $rendered->render('view');
    }

    public function test_basic_view_with_variables(): void
    {
        $rendered = new ViewRenderer(__DIR__ . '/Fixtures');

        $viewContent = $rendered->render('view', ['viewData' => 'test view data']);
        self::assertStringContainsString('test view data', $viewContent);
    }

    public function test_nested_view_with_variables(): void
    {
        $rendered = new ViewRenderer(__DIR__ . '/Fixtures');

        $viewContent = $rendered->render('admin/view', ['viewData' => 'test view data']);
        self::assertStringContainsString('Admin test view data', $viewContent);
    }
}
