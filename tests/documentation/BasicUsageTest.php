<?php

use PHPUnit\Framework\TestCase;

class BasicUsageTest extends TestCase
{
    protected function setUp(): void
    {
        Flight::init();
    }

    protected function tearDown(): void
    {
        Flight::clear();
    }

    public function testReadmeBasicUsage(): void
    {
        $this->expectOutputString('Hello, World!');

        Flight::route('/', function (): void {
            echo 'Hello, World!';
        });

        Flight::start();
    }

    public function testQuickStart(): void
    {
        $this->expectOutputString('{"Hello":"World!"}');

        Flight::route('/api', function (): void {
            Flight::json(['Hello' => 'World!']);
        });

        Flight::request()->url = '/api';

        Flight::start();
    }
}
