<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/autoload.php';

class FilterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \flight\Engine
     */
    private $app;

    function setUp() {
        $this->app = new \flight\Engine();
    }

    // Run before and after filters
    function testBeforeAndAfter() {
        $this->app->map('hello', function($name){
            return "Hello, $name!";
        });

        $this->app->before('hello', function(&$params, &$output){
            // Manipulate the parameter
            $params[0] = 'Fred';
        });

        $this->app->after('hello', function(&$params, &$output){
            // Manipulate the output
            $output .= " Have a nice day!";
        });

        $result = $this->app->hello('Bob');

        $this->assertEquals('Hello, Fred! Have a nice day!', $result);
    }

    // Break out of a filter chain by returning false
    function testFilterChaining() {
        $this->app->map('bye', function($name){
            return "Bye, $name!";
        });

        $this->app->before('bye', function(&$params, &$output){
            $params[0] = 'Bob';
        });
        $this->app->before('bye', function(&$params, &$output){
            $params[0] = 'Fred';
            return false;
        });
        $this->app->before('bye', function(&$params, &$output){
            $params[0] = 'Ted';
        });

        $result = $this->app->bye('Joe');

        $this->assertEquals('Bye, Fred!', $result);
    }
}
