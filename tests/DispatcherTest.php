<?php

declare(strict_types=1);

namespace tests;

use Closure;
use Exception;
use flight\core\Dispatcher;
use tests\classes\Hello;
use PHPUnit\Framework\TestCase;

class DispatcherTest extends TestCase
{
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new Dispatcher();
    }

    // Map a closure
    public function testClosureMapping()
    {
        $closure = Closure::fromCallable(function (): string {
            return 'hello';
        });

        $this->dispatcher->set('map1', $closure);

        $result = $this->dispatcher->run('map1');
        self::assertEquals('hello', $result);
    }

    // Map a function
    public function testFunctionMapping()
    {
        $this->dispatcher->set('map2', function (): string {
            return 'hello';
        });

        $result = $this->dispatcher->run('map2');
        self::assertEquals('hello', $result);
    }

    public function testHasEvent()
    {
        $this->dispatcher->set('map-event', function (): void {
        });

        $result = $this->dispatcher->has('map-event');
        $this->assertTrue($result);
    }

    public function testClearAllRegisteredEvents()
    {
        $customFunction = $anotherFunction = function (): void {
        };

        $this->dispatcher
            ->set('map-event', $customFunction)
            ->set('map-event-2', $anotherFunction);

        $this->dispatcher->clear();

        $this->assertFalse($this->dispatcher->has('map-event'));
        $this->assertFalse($this->dispatcher->has('map-event-2'));
    }

    public function testClearDeclaredRegisteredEvent()
    {
        $this->dispatcher->set('map-event', function () {
            return 'hello';
        });

        $this->dispatcher->set('map-event-2', function () {
            return 'there';
        });

        $this->dispatcher->clear('map-event');

        $result = $this->dispatcher->has('map-event');
        $this->assertFalse($result);
        $result = $this->dispatcher->has('map-event-2');
        $this->assertTrue($result);
    }

    // Map a static function
    public function testStaticFunctionMapping()
    {
        $this->dispatcher->set('map2', 'tests\classes\Hello::sayBye');

        $result = $this->dispatcher->run('map2');

        self::assertEquals('goodbye', $result);
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
        $this->dispatcher->set('map4', ['\tests\classes\Hello', 'sayBye']);

        $result = $this->dispatcher->run('map4');

        self::assertEquals('goodbye', $result);
    }

    // Run before and after filters
    public function testBeforeAndAfter()
    {
        $this->dispatcher->set('hello', function ($name) {
            return "Hello, $name!";
        });

        $this->dispatcher->hook('hello', 'before', function (&$params) {
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

    public function testCallFunction4Params()
    {
        $closure = function ($param1, $params2, $params3, $param4) {
            return 'hello' . $param1 . $params2 . $params3 . $param4;
        };
        $params = ['param1', 'param2', 'param3', 'param4'];
        $result = $this->dispatcher->callFunction($closure, $params);
        $this->assertEquals('helloparam1param2param3param4', $result);
    }

    public function testCallFunction5Params()
    {
        $closure = function ($param1, $params2, $params3, $param4, $param5) {
            return 'hello' . $param1 . $params2 . $params3 . $param4 . $param5;
        };
        $params = ['param1', 'param2', 'param3', 'param4', 'param5'];
        $result = $this->dispatcher->callFunction($closure, $params);
        $this->assertEquals('helloparam1param2param3param4param5', $result);
    }

    public function testCallFunction6Params()
    {
        $closure = function ($param1, $params2, $params3, $param4, $param5, $param6) {
            return 'hello' . $param1 . $params2 . $params3 . $param4 . $param5 . $param6;
        };
        $params = ['param1', 'param2', 'param3', 'param4', 'param5', 'param6'];
        $result = $this->dispatcher->callFunction($closure, $params);
        $this->assertEquals('helloparam1param2param3param4param5param6', $result);
    }
}
