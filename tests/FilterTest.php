<?php

declare(strict_types=1);

namespace tests;

use flight\Engine;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    private Engine $app;

    protected function setUp(): void
    {
        $this->app = new Engine();
    }

    // Run before and after filters
    public function testBeforeAndAfter()
    {
        $this->app->map('hello', function ($name) {
            return "Hello, $name!";
        });

        $this->app->before('hello', function (&$params) {
            // Manipulate the parameter
            $params[0] = 'Fred';
        });

        $this->app->after('hello', function (&$params, &$output) {
            // Manipulate the output
            $output .= ' Have a nice day!';
        });

        $result = $this->app->hello('Bob');

        $this->assertEquals('Hello, Fred! Have a nice day!', $result);
    }

    // Break out of a filter chain by returning false
    public function testFilterChaining()
    {
        $this->app->map('bye', function ($name) {
            return "Bye, $name!";
        });

        $this->app->before('bye', function (&$params) {
            $params[0] = 'Bob';
        });
        $this->app->before('bye', function (&$params) {
            $params[0] = 'Fred';

            return false;
        });
        $this->app->before('bye', function (&$params) {
            $params[0] = 'Ted';
        });

        $result = $this->app->bye('Joe');

        $this->assertEquals('Bye, Fred!', $result);
    }
}
