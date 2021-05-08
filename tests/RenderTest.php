<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\Engine;

require_once 'vendor/autoload.php';
require_once __DIR__ . '/../flight/Flight.php';

class RenderTest extends PHPUnit\Framework\TestCase
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
