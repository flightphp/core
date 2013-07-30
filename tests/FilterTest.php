<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/Flight.php';

class FilterTest extends PHPUnit_Framework_TestCase
{
    function setUp() {
        Flight::init();
    }

    // Run before and after filters
    function testBeforeAndAfter() {
        Flight::map('hello', function($name){
            return "Hello, $name!";
        });

        Flight::before('hello', function(&$params, &$output){
            // Manipulate the parameter
            $params[0] = 'Fred';
        });

        Flight::after('hello', function(&$params, &$output){
            // Manipulate the output
            $output .= " Have a nice day!";
        });

        $result = Flight::hello('Bob');

        $this->assertEquals('Hello, Fred! Have a nice day!', $result);
    }

    // Break out of a filter chain by returning false
    function testFilterChaining() {
        Flight::map('bye', function($name){
            return "Bye, $name!";
        });

        Flight::before('bye', function(&$params, &$output){
            $params[0] = 'Bob';
        });
        Flight::before('bye', function(&$params, &$output){
            $params[0] = 'Fred';
            return false;
        });
        Flight::before('bye', function(&$params, &$output){
            $params[0] = 'Ted';
        });

        $result = Flight::bye('Joe');

        $this->assertEquals('Bye, Fred!', $result);
    }
}
