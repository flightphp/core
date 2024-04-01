<?php

declare(strict_types=1);

namespace tests;

use flight\core\Loader;
use tests\classes\Factory;
use tests\classes\User;
use PHPUnit\Framework\TestCase;
use tests\classes\TesterClass;

class LoaderTest extends TestCase
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
        $this->loader->register('tests', User::class);

        $test = $this->loader->load('tests');

        self::assertIsObject($test);
        self::assertInstanceOf(User::class, $test);
    }

    // Register a class
    public function testRegister()
    {
        $this->loader->register('a', User::class);

        $user = $this->loader->load('a');

        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('', $user->name);
    }

    // Register a class with constructor parameters
    public function testRegisterWithConstructor()
    {
        $this->loader->register('b', User::class, ['Bob']);

        $user = $this->loader->load('b');

        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
        self::assertEquals('Bob', $user->name);
    }

    // Register a class with initialization
    public function testRegisterWithInitialization()
    {
        $this->loader->register('c', User::class, ['Bob'], function ($user) {
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
        $this->loader->register('d', User::class);

        $user1 = $this->loader->load('d');
        $user2 = $this->loader->load('d');
        $user3 = $this->loader->load('d', false);

        self::assertSame($user1, $user2);
        self::assertNotSame($user1, $user3);
    }

    // Gets an object from a factory method
    public function testRegisterUsingCallable()
    {
        $this->loader->register('e', ['\tests\classes\Factory', 'create']);

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

    public function testUnregisterClass()
    {
        $this->loader->register('g', User::class);
        $current_class = $this->loader->get('g');
        $this->assertEquals([ User::class, [], null ], $current_class);
        $this->loader->unregister('g');
        $unregistered_class_result = $this->loader->get('g');
        $this->assertNull($unregistered_class_result);
    }

    public function testNewInstance6Params()
    {
        $TesterClass = $this->loader->newInstance(TesterClass::class, ['Bob','Fred', 'Joe', 'Jane', 'Sally', 'Suzie']);
        $this->assertEquals('Bob', $TesterClass->param1);
        $this->assertEquals('Fred', $TesterClass->param2);
        $this->assertEquals('Joe', $TesterClass->param3);
        $this->assertEquals('Jane', $TesterClass->param4);
        $this->assertEquals('Sally', $TesterClass->param5);
        $this->assertEquals('Suzie', $TesterClass->param6);
    }

    public function testAddDirectoryAsArray()
    {
        $loader = new class extends Loader {
            public function getDirectories()
            {
                return self::$dirs;
            }
        };
        $loader->addDirectory([__DIR__ . '/classes']);
        self::assertEquals([
            dirname(__DIR__),
            __DIR__ . '/classes'
        ], $loader->getDirectories());
    }

    public function testV2ClassLoading()
    {
        $loader = new class extends Loader {
            public static function getV2ClassLoading()
            {
                return self::$v2ClassLoading;
            }
        };
        $this->assertTrue($loader::getV2ClassLoading());
        $loader::setV2ClassLoading(false);
        $this->assertFalse($loader::getV2ClassLoading());
    }
}
