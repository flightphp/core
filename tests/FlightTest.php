<?php

use flight\net\Request;
use flight\net\Response;
use flight\net\Router;
use flight\template\View;

/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */
require_once 'vendor/autoload.php';
require_once __DIR__ . '/../flight/Flight.php';

class FlightTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        Flight::init();
    }

    // Checks that default components are loaded
    public function testDefaultComponents()
    {
        $request = Flight::request();
        $response = Flight::response();
        $router = Flight::router();
        $view = Flight::view();

        $this->assertEquals(Request::class, get_class($request));
        $this->assertEquals(Response::class, get_class($response));
        $this->assertEquals(Router::class, get_class($router));
        $this->assertEquals(View::class, get_class($view));
    }

    // Test get/set of variables
    public function testGetAndSet()
    {
        Flight::set('a', 1);
        $var = Flight::get('a');

        $this->assertEquals(1, $var);

        Flight::clear();
        $vars = Flight::get();

        $this->assertCount(0, $vars);

        Flight::set('a', 1);
        Flight::set('b', 2);
        $vars = Flight::get();

        $this->assertCount(2, $vars);
        $this->assertEquals(1, $vars['a']);
        $this->assertEquals(2, $vars['b']);
    }

    // Register a class
    public function testRegister()
    {
        Flight::path(__DIR__ . '/classes');

        Flight::register('user', 'User');
        $user = Flight::user();

        $loaders = spl_autoload_functions();

        self::assertTrue(count($loaders) > 0);
        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
    }

    // Map a function
    public function testMap()
    {
        Flight::map('map1', function () {
            return 'hello';
        });

        $result = Flight::map1();

        self::assertEquals('hello', $result);
    }

    // Unmapped method
    public function testUnmapped()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('doesNotExist must be a mapped method.');

        Flight::doesNotExist();
    }
}
