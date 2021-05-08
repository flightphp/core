<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */
require_once 'vendor/autoload.php';
require_once __DIR__ . '/../flight/autoload.php';

class VariableTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var \flight\Engine
     */
    private $app;

    protected function setUp(): void
    {
        $this->app = new \flight\Engine();
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
