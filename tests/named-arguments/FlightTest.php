<?php

namespace Tests\PHP8;

use ExampleClass;
use Flight;
use flight\Engine;
use flight\net\Route;
use PHPUnit\Framework\TestCase;

class FlightTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [];
        $_REQUEST = [];
        Flight::init();
        Flight::setEngine(new Engine());
    }

    protected function tearDown(): void
    {
        unset($_REQUEST);
        unset($_SERVER);
        Flight::clear();
    }

    //////////////////
    // CORE METHODS //
    //////////////////
    public function test_path(): void
    {
        Flight::path(path: __DIR__);

        $exampleObject = new ExampleClass();
        self::assertInstanceOf(ExampleClass::class, $exampleObject);
    }

    public function test_stop_with_code(): void
    {
        Flight::stop(code: 500);

        self::expectOutputString('');
        self::assertSame(500, Flight::response()->status());
    }

    public function test_halt(): void
    {
        Flight::halt(500, actuallyExit: false, message: 'Test');

        self::expectOutputString('Test');
        self::assertSame(500, Flight::response()->status());
    }

    /////////////////////
    // ROUTING METHODS //
    /////////////////////
    public function test_static_route(): void
    {
        Flight::request()->url = '/test';

        $route = Flight::route(
            pass_route: true,
            alias: 'testRoute',
            callback: function () {
                echo 'test';
            },
            pattern: '/test'
        );

        self::assertInstanceOf(Route::class, $route);
        self::expectOutputString('test');
        Flight::start();
    }
}
