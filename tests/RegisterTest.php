<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/Flight.php';

class RegisterTest extends PHPUnit_Framework_TestCase
{
    function setUp() {
        Flight::init();
    }

    // Register a class
    function testRegister(){
        Flight::register('reg1', 'User');

        $user = Flight::reg1();

        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
        $this->assertEquals('', $user->name);
    }

    // Register a class with constructor parameters
    function testRegisterWithConstructor(){
        Flight::register('reg2', 'User', array('Bob'));

        $user = Flight::reg2();

        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
        $this->assertEquals('Bob', $user->name);
    }

    // Register a class with initialzation
    function testRegisterWithInitialization(){
        Flight::register('reg3', 'User', array('Bob'), function($user){
            $user->name = 'Fred';
        });

        $user = Flight::reg3();

        $this->assertTrue(is_object($user));
        $this->assertEquals('User', get_class($user));
        $this->assertEquals('Fred', $user->name);
    }

    // Get a non-shared instance of a class
    function testSharedInstance() {
        Flight::register('reg4', 'User');

        $user1 = Flight::reg4();
        $user2 = Flight::reg4();
        $user3 = Flight::reg4(false);

        $this->assertTrue($user1 === $user2);
        $this->assertTrue($user1 !== $user3);
    }

    // Map method takes precedence over register
    function testMapOverridesRegister(){
        Flight::register('reg5', 'User');

        $user = Flight::reg5();

        $this->assertTrue(is_object($user));

        Flight::map('reg5', function(){
            return 123;
        });

        $user = Flight::reg5();

        $this->assertEquals(123, $user);
    }
}

class User {
    public $name;

    public function User($name = ''){
        $this->name = $name;
    }
}