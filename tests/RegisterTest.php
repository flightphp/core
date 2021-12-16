<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\Engine;

require_once 'vendor/autoload.php';
require_once __DIR__ . '/../flight/autoload.php';
require_once __DIR__ . '/classes/User.php';

class RegisterTest extends PHPUnit\Framework\TestCase
{
    private Engine $app;

    protected function setUp(): void
    {
        $this->app = new Engine();
    }

    // Register a class
    public function testRegister()
    {
        $this->app->register('reg1', 'User');

        $user = $this->app->reg1();

        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('', $user->name);
    }

    // Register a class with constructor parameters
    public function testRegisterWithConstructor()
    {
        $this->app->register('reg2', 'User', ['Bob']);

        $user = $this->app->reg2();

        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('Bob', $user->name);
    }

    // Register a class with initialization
    public function testRegisterWithInitialization()
    {
        $this->app->register('reg3', 'User', ['Bob'], function ($user) {
            $user->name = 'Fred';
        });

        $user = $this->app->reg3();

        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('Fred', $user->name);
    }

    // Get a non-shared instance of a class
    public function testSharedInstance()
    {
        $this->app->register('reg4', 'User');

        $user1 = $this->app->reg4();
        $user2 = $this->app->reg4();
        $user3 = $this->app->reg4(false);

        self::assertSame($user1, $user2);
        self::assertNotSame($user1, $user3);
    }

    // Map method takes precedence over register
    public function testMapOverridesRegister()
    {
        $this->app->register('reg5', 'User');

        $user = $this->app->reg5();

        self::assertIsObject($user);

        $this->app->map('reg5', function () {
            return 123;
        });

        $user = $this->app->reg5();

        self::assertEquals(123, $user);
    }
}
