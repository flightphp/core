<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */
require_once 'PHPUnit/Autoload.php';
// Mike Cao version
//require_once __DIR__.'/../flight/Flight.php';

// Jallander version
require_once '../vendor/autoload.php';
use flight\Flight;

class RenderTest extends PHPUnit_Framework_TestCase
{
    function setUp(){
        Flight::init();
        Flight::set('flight.views.path', __DIR__.'/views');
    }

    // Render a view
    function testRenderView(){
        Flight::render('hello', array('name' => 'Bob'));

        $this->expectOutputString('Hello, Bob!');
    }

    // Renders a view into a layout
    function testRenderLayout(){
        Flight::render('hello', array('name' => 'Bob'), 'content');
        Flight::render('layouts/layout');

        $this->expectOutputString('<html>Hello, Bob!</html>');
    }
}
