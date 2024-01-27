<?php

declare(strict_types=1);

namespace tests;

use flight\Engine;
use tests\classes\User;
use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase
{
    private Engine $app;

    protected function setUp(): void
    {
        $this->app = new Engine();
        $this->app->path(__DIR__ . '/classes');
    }

    // Autoload a class
    public function testAutoload()
    {
        $this->app->register('user', User::class);

        $loaders = spl_autoload_functions();

        $user = $this->app->user();

        self::assertTrue(count($loaders) > 0);
        self::assertIsObject($user);
        self::assertInstanceOf(User::class, $user);
    }

    // Check autoload failure
    public function testMissingClass()
    {
        $test = null;
        $this->app->register('test', 'NonExistentClass');

        if (class_exists('NonExistentClass')) {
            $test = $this->app->test();
        }

        self::assertNull($test);
    }
}
