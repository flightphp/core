<?php

/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace tests;

use flight\Engine;
use tests\classes\User;

class AutoloadTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Engine
     */
    private $app;

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
