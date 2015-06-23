<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'vendor/autoload.php';
require_once __DIR__.'/../flight/autoload.php';
require_once __DIR__.'/classes/User.php';

class RegisterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \flight\Engine
     */
    private $app;

    function setUp() {
        $this->app = new \flight\Engine();
    }

    // Register a class
    function testRegister(){
        $this->app->register('reg1', 'User');

        $user = $this->app->reg1();

        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
        $this->assertEquals('', $user->name);
    }

    // Register a class with constructor parameters
    function testRegisterWithConstructor(){
        $this->app->register('reg2', 'User', array('Bob'));

        $user = $this->app->reg2();

        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
        $this->assertEquals('Bob', $user->name);
    }

    // Register a class with initialization
    function testRegisterWithInitialization(){
        $this->app->register('reg3', 'User', array('Bob'), function($user){
            $user->name = 'Fred';
        });

        $user = $this->app->reg3();

        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
        $this->assertEquals('Fred', $user->name);
    }

    // Get a non-shared instance of a class
    function testSharedInstance() {
        $this->app->register('reg4', 'User');

        $user1 = $this->app->reg4();
        $user2 = $this->app->reg4();
        $user3 = $this->app->reg4(false);

        $this->assertTrue($user1 === $user2);
        $this->assertTrue($user1 !== $user3);
    }

    // Map method takes precedence over register
    function testMapOverridesRegister(){
        $this->app->register('reg5', 'User');

        $user = $this->app->reg5();

        $this->assertTrue(is_object($user));

        $this->app->map('reg5', function(){
            return 123;
        });

        $user = $this->app->reg5();

        $this->assertEquals(123, $user);
    }
}
