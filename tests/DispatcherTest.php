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

    public function testClosureMapping(): void
    {
        $this->dispatcher->set('map1', Closure::fromCallable(function (): string {
            return 'hello';
        }));

        $this->assertSame('hello', $this->dispatcher->run('map1'));
    }

    public function testFunctionMapping(): void
    {
        $this->dispatcher->set('map2', function (): string {
            return 'hello';
        });

        $this->assertSame('hello', $this->dispatcher->run('map2'));
    }

    public function testHasEvent(): void
    {
        $this->dispatcher->set('map-event', function (): void {
        });

        $this->assertTrue($this->dispatcher->has('map-event'));
    }

    public function testClearAllRegisteredEvents(): void
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

    public function testClearDeclaredRegisteredEvent(): void
    {
        $customFunction = $anotherFunction = function (): void {
        };

        $this->dispatcher
            ->set('map-event', $customFunction)
            ->set('map-event-2', $anotherFunction);

        $this->dispatcher->clear('map-event');

        $this->assertFalse($this->dispatcher->has('map-event'));
        $this->assertTrue($this->dispatcher->has('map-event-2'));
    }

    public function testStaticFunctionMapping(): void
    {
        $this->dispatcher->set('map2', Hello::class . '::sayBye');

        $this->assertSame('goodbye', $this->dispatcher->run('map2'));
    }

    public function testClassMethodMapping(): void
    {
        $this->dispatcher->set('map3', [new Hello(), 'sayHi']);

        $this->assertSame('hello', $this->dispatcher->run('map3'));
    }

    public function testStaticClassMethodMapping(): void
    {
        $this->dispatcher->set('map4', [Hello::class, 'sayBye']);

        $this->assertSame('goodbye', $this->dispatcher->run('map4'));
    }

    public function testBeforeAndAfter(): void
    {
        $this->dispatcher->set('hello', function (string $name): string {
            return "Hello, $name!";
        });

        $this->dispatcher
            ->hook('hello', $this->dispatcher::FILTER_BEFORE, function (array &$params): void {
                // Manipulate the parameter
                $params[0] = 'Fred';
            })
            ->hook('hello', $this->dispatcher::FILTER_AFTER, function (array &$params, string &$output): void {
                // Manipulate the output
                $output .= ' Have a nice day!';
            });

        $result = $this->dispatcher->run('hello', ['Bob']);

        $this->assertSame('Hello, Fred! Have a nice day!', $result);
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
