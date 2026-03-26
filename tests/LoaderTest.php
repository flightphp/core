<?php

declare(strict_types=1);

namespace tests;

use flight\core\Loader;
use PHPUnit\Framework\TestCase;
use tests\classes\User;
use tests\classes\Factory;
use tests\classes\TesterClass;

class LoaderTest extends TestCase
{
    private Loader $loader;

    protected function setUp(): void
    {
        $this->loader = new Loader();
    }

    public function testRegister(): void
    {
        $this->loader->register('a', User::class);
        $user = $this->loader->load('a');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('', $user->name);
    }

    public function testRegisterWithConstructor(): void
    {
        $this->loader->register('b', User::class, ['Bob']);
        $user = $this->loader->load('b');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('Bob', $user->name);
    }

    public function testRegisterWithInitialization(): void
    {
        $this->loader->register('c', User::class, ['Bob'], static function (User $user): void {
            $user->name = 'Fred';
        });

        $user = $this->loader->load('c');

        self::assertInstanceOf(User::class, $user);
        self::assertEquals('Fred', $user->name);
    }

    public function testSharedInstance(): void
    {
        $this->loader->register('d', User::class);
        $user1 = $this->loader->load('d');
        $user2 = $this->loader->load('d');
        $user3 = $this->loader->load('d', false);

        self::assertSame($user1, $user2);
        self::assertNotSame($user1, $user3);
    }

    public function testRegisterUsingCallable(): void
    {
        $this->loader->register('e', [Factory::class, 'create']);
        $obj = $this->loader->load('e');
        $obj2 = $this->loader->load('e');
        $obj3 = $this->loader->load('e', false);

        self::assertInstanceOf(Factory::class, $obj);
        self::assertSame($obj, $obj2);
        self::assertInstanceOf(Factory::class, $obj3);
        self::assertNotSame($obj, $obj3);
    }

    public function testRegisterUsingCallback(): void
    {
        $this->loader->register('f', static fn(): Factory => Factory::create());
        $obj = $this->loader->load('f');

        self::assertInstanceOf(Factory::class, $obj);
    }

    public function testUnregisterClass(): void
    {
        $this->loader->register('g', User::class);
        $current_class = $this->loader->get('g');

        $this->assertSame([User::class, [], null], $current_class);

        $this->loader->unregister('g');
        $unregistered_class_result = $this->loader->get('g');

        $this->assertNull($unregistered_class_result);
    }

    public function testNewInstance6Params(): void
    {
        $TesterClass = $this->loader->newInstance(
            TesterClass::class,
            ['Bob', 'Fred', 'Joe', 'Jane', 'Sally', 'Suzie']
        );

        $this->assertEquals('Bob', $TesterClass->param1);
        $this->assertEquals('Fred', $TesterClass->param2);
        $this->assertEquals('Joe', $TesterClass->param3);
        $this->assertEquals('Jane', $TesterClass->param4);
        $this->assertEquals('Sally', $TesterClass->param5);
        $this->assertEquals('Suzie', $TesterClass->param6);
    }
}
