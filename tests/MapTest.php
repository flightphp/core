<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/Flight.php';
require_once __DIR__.'/classes/Hello.php';

class MapTest extends PHPUnit_Framework_TestCase
{
    function setUp(){
        Flight::init();
    }

    // Map a closure
    function testClosureMapping(){
        Flight::map('map1', function(){
            return 'hello';
        });

        $result = Flight::map1();

        $this->assertEquals('hello', $result);
    }

    // Map a function
    function testFunctionMapping(){
        Flight::map('map2', function(){
            return 'hello';
        });

        $result = Flight::map2();

        $this->assertEquals('hello', $result);
    }

    // Map a class method
    function testClassMethodMapping(){
        $h = new Hello();

        Flight::map('map3', array($h, 'sayHi'));

        $result = Flight::map3();

        $this->assertEquals('hello', $result);
    }

    // Map a static class method
    function testStaticClassMethodMapping(){
        Flight::map('map4', array('Hello', 'sayBye'));

        $result = Flight::map4();

        $this->assertEquals('goodbye', $result);
    }
}
