<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\Engine;

require_once 'vendor/autoload.php';
require_once __DIR__ . '/../flight/autoload.php';
require_once __DIR__ . '/classes/Hello.php';

class MapTest extends PHPUnit\Framework\TestCase
{
    private Engine $app;

    protected function setUp(): void
    {
        $this->app = new Engine();
    }

    // Map a closure
    public function testClosureMapping()
    {
        $this->app->map('map1', function () {
            return 'hello';
        });

        $result = $this->app->map1();

        self::assertEquals('hello', $result);
    }

    // Map a function
    public function testFunctionMapping()
    {
        $this->app->map('map2', function () {
            return 'hello';
        });

        $result = $this->app->map2();

        self::assertEquals('hello', $result);
    }

    // Map a class method
    public function testClassMethodMapping()
    {
        $h = new Hello();

        $this->app->map('map3', [$h, 'sayHi']);

        $result = $this->app->map3();

        self::assertEquals('hello', $result);
    }

    // Map a static class method
    public function testStaticClassMethodMapping()
    {
        $this->app->map('map4', ['Hello', 'sayBye']);

        $result = $this->app->map4();

        self::assertEquals('goodbye', $result);
    }

    // Unmapped method
    public function testUnmapped()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('doesNotExist must be a mapped method.');

        $this->app->doesNotExist();
    }
}
