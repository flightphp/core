<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/autoload.php';

class ViewTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \flight\template\View
     */
    private $view;

    function setUp(){
        $this->view = new \flight\template\View();
        $this->view->path = __DIR__.'/views';
    }

    // Set template variables
    function testVariables(){
        $this->view->set('test', 123);

        $this->assertEquals(123, $this->view->get('test'));

        $this->assertTrue($this->view->has('test'));
        $this->assertTrue(!$this->view->has('unknown'));

        $this->view->clear('test');

        $this->assertEquals(null, $this->view->get('test'));
    }

    // Check if template files exist
    function testTemplateExists(){
        $this->assertTrue($this->view->exists('hello.php'));
        $this->assertTrue(!$this->view->exists('unknown.php'));
    }

    // Render a template
    function testRender(){
        $this->view->render('hello', array('name' => 'Bob'));

        $this->expectOutputString('Hello, Bob!');
    }

    // Fetch template output
    function testFetch(){
        $output = $this->view->fetch('hello', array('name' => 'Bob'));

        $this->assertEquals('Hello, Bob!', $output);
    }
}
