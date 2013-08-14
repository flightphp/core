<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/Flight.php';

class AutoloadTest extends PHPUnit_Framework_TestCase
{
    function setUp() {
    }

    // Autoload a class
    function testAutoload(){
        Flight::path(__DIR__.'/classes');

        Flight::register('test', 'TestClass');

        $loaders = spl_autoload_functions();

        $test = Flight::test();

        $this->assertTrue(sizeof($loaders) > 0);
        $this->assertTrue(is_object($test));
        $this->assertEquals('TestClass', get_class($test));
    }
}
