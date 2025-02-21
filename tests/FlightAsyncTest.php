<?php

declare(strict_types=1);

namespace tests;

use Flight;
use flight\Engine;
use PHPUnit\Framework\TestCase;

class FlightAsyncTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Flight::setEngine(new Engine());
    }

    protected function setUp(): void
    {
        $_SERVER = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        unset($_REQUEST);
        unset($_SERVER);
    }

    // Checks that default components are loaded
    public function testSingleRoute()
    {
        Flight::route('GET /', function () {
            echo 'hello world';
        });

        $this->expectOutputString('hello world');
        Flight::start();
    }

    public function testMultipleRoutes()
    {
        Flight::route('GET /', function () {
            echo 'hello world';
        });

        Flight::route('GET /test', function () {
            echo 'test';
        });

        $this->expectOutputString('test');
        $_SERVER['REQUEST_URI'] = '/test';
        Flight::start();
    }

    public function testMultipleStartsSingleRoute()
    {
        Flight::route('GET /', function () {
            echo 'hello world';
        });

        $this->expectOutputString('hello worldhello world');
        Flight::start();
        Flight::start();
    }

    public function testMultipleStartsMultipleRoutes()
    {
        Flight::route('GET /', function () {
            echo 'hello world';
        });

        Flight::route('GET /test', function () {
            echo 'test';
        });

        $this->expectOutputString('testhello world');
        $_SERVER['REQUEST_URI'] = '/test';
        Flight::start();
        $_SERVER['REQUEST_URI'] = '/';
        Flight::start();
    }
}
