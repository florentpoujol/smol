<?php

declare(strict_types=1);

namespace Tests\FlorentPoujol\SimplePhpFramework;

use FlorentPoujol\SimplePhpFramework\ViewRenderer;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ViewRendererTest extends TestCase
{
    public function test_basic_view_without_variables(): void
    {
        $rendered = new ViewRenderer(__DIR__ . '/Fixtures/Views');

        $this->expectError();
        $this->expectErrorMessage('Undefined variable $viewData');
        ob_end_clean();

        $rendered->render('view');
    }

    public function test_basic_view_with_variables(): void
    {
        $rendered = new ViewRenderer(__DIR__ . '/Fixtures/Views');

        $viewContent = $rendered->render('view', ['viewData' => 'test view data']);
        self::assertStringContainsString('test view data', $viewContent);
    }

    public function test_nested_view_with_variables(): void
    {
        $rendered = new ViewRenderer(__DIR__ . '/Fixtures/Views');

        $viewContent = $rendered->render('admin/view', ['viewData' => 'test view data']);
        self::assertStringContainsString('Admin test view data', $viewContent);
    }

    private function cleanUpCompiledViews(): void
    {
        $basePath = __DIR__ . '/Fixtures/Views/storage/compiled-views/';
        $files = scandir($basePath);
        assert(is_array($files));

        foreach ($files as $file) {
            if (str_ends_with($file, '.')) {
                continue;
            }

            unlink($basePath . $file);
        }
    }

    public function test_smol_template(): void
    {
        $this->cleanUpCompiledViews();

        $rendered = new ViewRenderer(__DIR__ . '/Fixtures/Views');

        $object1 = new stdClass();
        $object1->property = 'property1';

        $object2 = new stdClass();
        $object2->property = 2;

        $viewContent = $rendered->render('smol', [
            'simpleVar' => 'simple<br>var',
            'array' => ['value1', 2],
            'arrayOfObject' => [$object1, $object2],
        ]);

        self::assertStringContainsString('no escape: simple<br>var', $viewContent);
        self::assertStringContainsString('short escape: simple&lt;br&gt;var', $viewContent);
        self::assertStringContainsString('long escape: simple&lt;br&gt;var', $viewContent);

        self::assertStringContainsString('inside for array: value1', $viewContent);
        self::assertStringContainsString('inside for array: 2', $viewContent);

        self::assertStringContainsString('inside for object: property1', $viewContent);
        self::assertStringContainsString('inside for object: 2', $viewContent);
    }
}
