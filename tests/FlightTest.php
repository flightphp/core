<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/Flight.php';

class FlightTest extends PHPUnit_Framework_TestCase
{
    function setUp() {
        Flight::init();
    }

    // Checks that default components are loaded
    function testDefaultComponents(){
        $request = Flight::request();
        $response = Flight::response();
        $router = Flight::router();
        $view = Flight::view();

        $this->assertEquals('flight\net\Request', get_class($request));
        $this->assertEquals('flight\net\Response', get_class($response));
        $this->assertEquals('flight\net\Router', get_class($router));
        $this->assertEquals('flight\template\View', get_class($view));
    }

    // Test get/set of variables
    function testGetAndSet(){
        Flight::set('a', 1);
        $var = Flight::get('a');

        $this->assertEquals(1, $var);
    }

    // Register a class
    function testRegister(){
        Flight::path(__DIR__.'/classes');

        Flight::register('test', 'TestClass');
        $test = Flight::test();

        $loaders = spl_autoload_functions();

        $this->assertTrue(sizeof($loaders) > 0);
        $this->assertTrue(is_object($test));
        $this->assertEquals('TestClass', get_class($test));
    }

    // Map a function
    function testMap(){
        Flight::map('map1', function(){
            return 'hello';
        });

        $result = Flight::map1();

        $this->assertEquals('hello', $result);
    }
}
