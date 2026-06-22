<?php

declare(strict_types=1);

namespace tests;

use flight\Engine;
use PHPUnit\Framework\TestCase;

class RenderTest extends TestCase
{
    private Engine $app;

    protected function setUp(): void
    {
        $this->app = new Engine();
        $this->app->set('flight.views.path', __DIR__ . '/views');
    }

    // Render a view
    public function testRenderView(): void
    {
        $this->app->render('hello', ['name' => 'Bob']);

        $this->expectOutputString('Hello, Bob!');
    }

    // Renders a view into a layout
    public function testRenderLayout(): void
    {
        $this->app->render('hello', ['name' => 'Bob'], 'content');
        ob_start();
        $this->app->render('layouts/layout');
        $html = ob_get_clean();
        $html = str_replace(["\r\n", "\n"], '', $html);
        echo $html;

        $this->expectOutputString("<body>Hello, Bob!</body>");
    }
}
