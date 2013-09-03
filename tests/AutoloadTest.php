<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/autoload.php';

class AutoloadTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \flight\Engine
     */
    private $app;
    
    function setUp() {
        $this->app = new \flight\Engine();
        $this->app->path(__DIR__.'/classes');
    }

    // Autoload a class
    function testAutoload(){
        $this->app->register('test', 'TestClass');

        $loaders = spl_autoload_functions();

        $test = $this->app->test();

        $this->assertTrue(sizeof($loaders) > 0);
        $this->assertTrue(is_object($test));
        $this->assertEquals('TestClass', get_class($test));
    }

    // Check autoload failure
    function testMissingClass(){
        $test = null;
        $this->app->register('test', 'NonExistentClass');

        if (class_exists('NonExistentClass')) {
            $test = $this->app->test();
        }

        $this->assertEquals(null, $test);
    }
}
