<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\core\Loader;

require_once 'vendor/autoload.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Factory.php';

class LoaderTest extends PHPUnit\Framework\TestCase
{
    private Loader $loader;

    protected function setUp(): void
    {
        $this->loader = new Loader();
        $this->loader->autoload(true, __DIR__ . '/classes');
    }

    // Autoload a class
    public function testAutoload()
    {
        $this->loader->register('tests', 'User');

        $test = $this->loader->load('tests');

        self::assertIsObject($test);
        self::assertInstanceOf(User::class, $test);
    }

    // Register a class
    public function testRegister()
    {
        $this->loader->register('a', 'User');

        $user = $this->loader->load('a');

        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('', $user->name);
    }

    // Register a class with constructor parameters
    public function testRegisterWithConstructor()
    {
        $this->loader->register('b', 'User', ['Bob']);

        $user = $this->loader->load('b');

        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('Bob', $user->name);
    }

    // Register a class with initialization
    public function testRegisterWithInitialization()
    {
        $this->loader->register('c', 'User', ['Bob'], function ($user) {
            $user->name = 'Fred';
        });

        $user = $this->loader->load('c');

        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('Fred', $user->name);
    }

    // Get a non-shared instance of a class
    public function testSharedInstance()
    {
        $this->loader->register('d', 'User');

        $user1 = $this->loader->load('d');
        $user2 = $this->loader->load('d');
        $user3 = $this->loader->load('d', false);

        self::assertSame($user1, $user2);
        self::assertNotSame($user1, $user3);
    }

    // Gets an object from a factory method
    public function testRegisterUsingCallable()
    {
        $this->loader->register('e', ['Factory', 'create']);

        $obj = $this->loader->load('e');

        self::assertIsObject($obj);
        self::assertInstanceOf(Factory::class, $obj);

        $obj2 = $this->loader->load('e');

        self::assertIsObject($obj2);
        self::assertInstanceOf(Factory::class, $obj2);
        self::assertSame($obj, $obj2);

        $obj3 = $this->loader->load('e', false);
        self::assertIsObject($obj3);
        self::assertInstanceOf(Factory::class, $obj3);
        self::assertNotSame($obj, $obj3);
    }

    // Gets an object from a callback function
    public function testRegisterUsingCallback()
    {
        $this->loader->register('f', function () {
            return Factory::create();
        });

        $obj = $this->loader->load('f');

        self::assertIsObject($obj);
        self::assertInstanceOf(Factory::class, $obj);
    }
}
