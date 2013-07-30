<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/Flight.php';

class VariableTest extends PHPUnit_Framework_TestCase
{
    function setUp() {
        Flight::init();
    }

    // Set and get a variable
    function testSetAndGet() {
        Flight::set('a', 1);
        $var = Flight::get('a');
        $this->assertEquals(1, $var);
    }

    // Clear a specific variable
    function testClear() {
        Flight::set('b', 1);
        Flight::clear('b');
        $var = Flight::get('b');
        $this->assertEquals(null, $var);
    }

    // Clear all variables
    function testClearAll() {
        Flight::set('c', 1);
        Flight::clear();
        $var = Flight::get('c');
        $this->assertEquals(null, $var);
    }

    // Check if a variable exists
    function testHas() {
        Flight::set('d', 1);
        $this->assertTrue(Flight::has('d'));
    }
}
