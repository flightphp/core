<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\core\Dispatcher;

require_once 'vendor/autoload.php';
require_once __DIR__ . '/classes/Hello.php';

class DispatcherTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var Dispatcher|null
     */
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new Dispatcher();
    }

    // Map a closure
    public function testClosureMapping()
    {
        $this->dispatcher->set('map1', function () {
            return 'hello';
        });

        $result = $this->dispatcher->run('map1');

        self::assertEquals('hello', $result);
    }

    // Map a function
    public function testFunctionMapping()
    {
        $this->dispatcher->set('map2', function () {
            return 'hello';
        });

        $result = $this->dispatcher->run('map2');

        self::assertEquals('hello', $result);
    }

    // Map a class method
    public function testClassMethodMapping()
    {
        $h = new Hello();

        $this->dispatcher->set('map3', [$h, 'sayHi']);

        $result = $this->dispatcher->run('map3');

        self::assertEquals('hello', $result);
    }

    // Map a static class method
    public function testStaticClassMethodMapping()
    {
        $this->dispatcher->set('map4', ['Hello', 'sayBye']);

        $result = $this->dispatcher->run('map4');

        self::assertEquals('goodbye', $result);
    }

    // Run before and after filters
    public function testBeforeAndAfter()
    {
        $this->dispatcher->set('hello', function ($name) {
            return "Hello, $name!";
        });

        $this->dispatcher->hook('hello', 'before', function (&$params, &$output) {
            // Manipulate the parameter
            $params[0] = 'Fred';
        });

        $this->dispatcher->hook('hello', 'after', function (&$params, &$output) {
            // Manipulate the output
            $output .= ' Have a nice day!';
        });

        $result = $this->dispatcher->run('hello', ['Bob']);

        self::assertEquals('Hello, Fred! Have a nice day!', $result);
    }

    // Test an invalid callback
    public function testInvalidCallback()
    {
        $this->expectException(Exception::class);

        $this->dispatcher->execute(['NonExistentClass', 'nonExistentMethod']);
    }
}
