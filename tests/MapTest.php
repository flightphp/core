<?php

declare(strict_types=1);

namespace tests;

use Exception;
use flight\Engine;
use tests\classes\Hello;
use PHPUnit\Framework\TestCase;

class MapTest extends TestCase
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
        $this->app->map('map4', [Hello::class, 'sayBye']);

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
