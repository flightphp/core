<?php

declare(strict_types=1);

namespace tests;

use flight\Engine;
use PHPUnit\Framework\TestCase;

class VariableTest extends TestCase
{
    private Engine $app;

    protected function setUp(): void
    {
        $this->app = new Engine();
    }

    // Set and get a variable
    public function testSetAndGet()
    {
        $this->app->set('a', 1);
        $var = $this->app->get('a');
        $this->assertEquals(1, $var);
    }

    // Clear a specific variable
    public function testClear()
    {
        $this->app->set('b', 1);
        $this->app->clear('b');
        $var = $this->app->get('b');
        $this->assertNull($var);
    }

    // Clear all variables
    public function testClearAll()
    {
        $this->app->set('c', 1);
        $this->app->clear();
        $var = $this->app->get('c');
        $this->assertNull($var);
    }

    // Check if a variable exists
    public function testHas()
    {
        $this->app->set('d', 1);
        $this->assertTrue($this->app->has('d'));
    }
}
