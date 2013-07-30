<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/core/Loader.php';

class LoaderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \flight\core\Loader
     */
    private $loader;

    function setUp(){
        $this->loader = new \flight\core\Loader();
        $this->loader->start();
        $this->loader->addDirectory(__DIR__.'/classes');
    }

    // Autoload a class
    function testAutoload(){
        $this->loader->register('tests', 'TestClass');

        $test = $this->loader->load('tests');

        $this->assertTrue(is_object($test));
        $this->assertEquals('TestClass', get_class($test));
    }

    // Register a class
    function testRegister(){
        $this->loader->register('a', 'User');

        $user = $this->loader->load('a');

        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
        $this->assertEquals('', $user->name);
    }

    // Register a class with constructor parameters
    function testRegisterWithConstructor(){
        $this->loader->register('b', 'User', array('Bob'));

        $user = $this->loader->load('b');

        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
        $this->assertEquals('Bob', $user->name);
    }

    // Register a class with initialzation
    function testRegisterWithInitialization(){
        $this->loader->register('c', 'User', array('Bob'), function($user){
            $user->name = 'Fred';
        });

        $user = $this->loader->load('c');

        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
        $this->assertEquals('Fred', $user->name);
    }

    // Get a non-shared instance of a class
    function testSharedInstance() {
        $this->loader->register('d', 'User');

        $user1 = $this->loader->load('d');
        $user2 = $this->loader->load('d');
        $user3 = $this->loader->load('d', false);

        $this->assertTrue($user1 === $user2);
        $this->assertTrue($user1 !== $user3);
    }
}
