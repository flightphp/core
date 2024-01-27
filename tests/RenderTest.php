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
    public function testRenderView()
    {
        $this->app->render('hello', ['name' => 'Bob']);

        $this->expectOutputString('Hello, Bob!');
    }

    // Renders a view into a layout
    public function testRenderLayout()
    {
        $this->app->render('hello', ['name' => 'Bob'], 'content');
        $this->app->render('layouts/layout');

        $this->expectOutputString('<html>Hello, Bob!</html>');
    }
}
