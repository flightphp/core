<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/autoload.php';

class VariableTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \flight\Engine
     */
    private $app;

    function setUp() {
        $this->app = new \flight\Engine();
    }
    // Set and get a variable
    function testSetAndGet() {
        $this->app->set('a', 1);
        $var = $this->app->get('a');
        $this->assertEquals(1, $var);
    }

    // Clear a specific variable
    function testClear() {
        $this->app->set('b', 1);
        $this->app->clear('b');
        $var = $this->app->get('b');
        $this->assertEquals(null, $var);
    }

    // Clear all variables
    function testClearAll() {
        $this->app->set('c', 1);
        $this->app->clear();
        $var = $this->app->get('c');
        $this->assertEquals(null, $var);
    }

    // Check if a variable exists
    function testHas() {
        $this->app->set('d', 1);
        $this->assertTrue($this->app->has('d'));
    }
}
