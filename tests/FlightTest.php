<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'vendor/autoload.php';
// require_once __DIR__.'/../flight/Flight.php';

use Flight\Flight;

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

        $this->assertEquals('Flight\Net\Request', get_class($request));
        $this->assertEquals('Flight\Net\Response', get_class($response));
        $this->assertEquals('Flight\Net\Router', get_class($router));
        $this->assertEquals('Flight\Template\View', get_class($view));
    }

    // Test get/set of variables
    function testGetAndSet(){
        Flight::set('a', 1);
        $var = Flight::get('a');

        $this->assertEquals(1, $var);

        Flight::clear();
        $vars = Flight::get();

        $this->assertEquals(0, count($vars));

        Flight::set('a', 1);
        Flight::set('b', 2);
        $vars = Flight::get();

        $this->assertEquals(2, count($vars));
        $this->assertEquals(1, $vars['a']);
        $this->assertEquals(2, $vars['b']);
    }

    // Register a class
    function testRegister(){
        Flight::path(__DIR__.'/classes');

        Flight::register('user', 'User');
        $user = Flight::user();

        $loaders = spl_autoload_functions();

        $this->assertTrue(sizeof($loaders) > 0);
        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
    }

    // Map a function
    function testMap(){
        Flight::map('map1', function(){
            return 'hello';
        });

        $result = Flight::map1();

        $this->assertEquals('hello', $result);
    }

    // Unmapped method
    function testUnmapped() {
        $this->setExpectedException('Exception', 'doesNotExist must be a mapped method.');

        Flight::doesNotExist();
    }
}
