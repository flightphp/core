<?php

declare(strict_types=1);

namespace tests;

use Closure;
use Exception;
use flight\core\Dispatcher;
use InvalidArgumentException;
use PharIo\Manifest\InvalidEmailException;
use tests\classes\Hello;
use PHPUnit\Framework\TestCase;
use tests\classes\TesterClass;
use TypeError;

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
            ->hook('hello', Dispatcher::FILTER_BEFORE, function (array &$params): void {
                // Manipulate the parameter
                $params[0] = 'Fred';
            })
            ->hook('hello', Dispatcher::FILTER_AFTER, function (array &$params, string &$output): void {
                // Manipulate the output
                $output .= ' Have a nice day!';
            });

        $result = $this->dispatcher->run('hello', ['Bob']);

        $this->assertSame('Hello, Fred! Have a nice day!', $result);
    }

    public function testInvalidCallback(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Class 'NonExistentClass' not found. Is it being correctly autoloaded with Flight::path()?");

        $this->dispatcher->execute(['NonExistentClass', 'nonExistentMethod']);
    }

    public function testInvalidCallableString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid callback specified.');

        $this->dispatcher->execute('nonexistentGlobalFunction');
    }

    public function testInvalidCallbackBecauseConstructorParameters(): void
    {
        $class = TesterClass::class;
        $method = 'instanceMethod';
        $exceptionMessage = "Method '$class::$method' cannot be called statically. ";
        $exceptionMessage .= "$class::__construct require 6 parameters";

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);

        static $params = [];
        $this->dispatcher->invokeMethod([$class, $method], $params);
    }

    // It will be useful for executing instance Controller methods statically
    public function testCanExecuteAnNonStaticMethodStatically(): void
    {
        $this->assertSame('hello', $this->dispatcher->execute([Hello::class, 'sayHi']));
    }

    public function testItThrowsAnExceptionWhenRunAnUnregistedEventName(): void
    {
        $this->expectException(Exception::class);

        $this->dispatcher->run('nonExistentEvent');
    }

    public function testWhenAnEventThrowsAnExceptionItPersistUntilNextCatchBlock(): void
    {
        $this->dispatcher->set('myMethod', function (): void {
            throw new Exception('myMethod Exception');
        });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('myMethod Exception');

        $this->dispatcher->run('myMethod');
    }

    public function testWhenAnEventThrowsCustomExceptionItPersistUntilNextCatchBlock(): void
    {
        $this->dispatcher->set('checkEmail', function (string $email): void {
            throw new InvalidEmailException("Invalid email $email");
        });

        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage('Invalid email mail@mail,com');

        $this->dispatcher->run('checkEmail', ['mail@mail,com']);
    }

    public function testItThrowsNoticeForOverrideRegisteredEvents(): void
    {
        set_error_handler(function (int $errno, string $errstr): void {
            $this->assertSame(E_USER_NOTICE, $errno);
            $this->assertSame("Event 'myMethod' has been overridden!", $errstr);
        });

        $this->dispatcher->set('myMethod', function (): string {
            return 'Original';
        });

        $this->dispatcher->set('myMethod', function (): string {
            return 'Overridden';
        });

        $this->assertSame('Overridden', $this->dispatcher->run('myMethod'));
        restore_error_handler();
    }

    public function testItThrowsNoticeForInvalidFilterTypes(): void
    {
        set_error_handler(function (int $errno, string $errstr): void {
            $this->assertSame(E_USER_NOTICE, $errno);
            $this->assertStringStartsWith("Invalid filter type 'invalid', use ", $errstr);
        });

        $this->dispatcher
            ->set('myMethod', function (): string {
                return 'Original';
            })
            ->hook('myMethod', 'invalid', function (array &$params, &$output): void {
                $output = 'Overridden';
            });

        $this->assertSame('Original', $this->dispatcher->run('myMethod'));
        restore_error_handler();
    }

    public function testItThrowsAnExceptionForInvalidFilters(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid callable $filters[1]');

        $params = [];
        $output = '';
        $invalidCallable = 'invalidGlobalFunction';

        $validCallable = function (): void {
        };

        $this->dispatcher->filter([$validCallable, $invalidCallable], $params, $output);
    }

    public function testCallFunction6Params(): void
    {
        $func = function ($param1, $param2, $param3, $param4, $param5, $param6) {
            return "hello{$param1}{$param2}{$param3}{$param4}{$param5}{$param6}";
        };

        $params = ['param1', 'param2', 'param3', 'param4', 'param5', 'param6'];
        $result = $this->dispatcher->callFunction($func, $params);

        $this->assertSame('helloparam1param2param3param4param5param6', $result);
    }
}
