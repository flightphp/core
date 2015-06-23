<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'vendor/autoload.php';
require_once __DIR__.'/classes/User.php';
require_once __DIR__.'/classes/Factory.php';

class LoaderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \flight\core\Loader
     */
    private $loader;

    function setUp(){
        $this->loader = new \flight\core\Loader();
        $this->loader->autoload(true, __DIR__.'/classes');
    }

    // Autoload a class
    function testAutoload(){
        $this->loader->register('tests', 'User');

        $test = $this->loader->load('tests');

        $this->assertTrue(is_object($test));
        $this->assertEquals('User', get_class($test));
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

    // Register a class with initialization
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

    // Gets an object from a factory method
    function testRegisterUsingCallable(){
        $this->loader->register('e', array('Factory','create'));

        $obj = $this->loader->load('e');

        $this->assertTrue(is_object($obj));
        $this->assertEquals('Factory', get_class($obj));

        $obj2 = $this->loader->load('e');

        $this->assertTrue(is_object($obj2));
        $this->assertEquals('Factory', get_class($obj2));
        $this->assertTrue($obj === $obj2);

        $obj3 = $this->loader->load('e', false);
        $this->assertTrue(is_object($obj3));
        $this->assertEquals('Factory', get_class($obj3));
        $this->assertTrue($obj !== $obj3);
    }

    // Gets an object from a callback function
    function testRegisterUsingCallback(){
        $this->loader->register('f', function(){
            return Factory::create();
        });

        $obj = $this->loader->load('f');

        $this->assertTrue(is_object($obj));
        $this->assertEquals('Factory', get_class($obj));
    }
}
