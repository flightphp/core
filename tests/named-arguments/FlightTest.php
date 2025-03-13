<?php

declare(strict_types=1);

namespace Tests\PHP8;

use DateTimeImmutable;
use ExampleClass;
use Flight;
use flight\Container;
use flight\Engine;
use flight\net\Route;
use PHPUnit\Framework\TestCase;
use stdClass;

final class FlightTest extends TestCase
{
    private Engine $engine;

    protected function setUp(): void
    {
        $this->engine = new Engine;
        Flight::init();
        Flight::setEngine($this->engine);
    }

    //////////////////
    // CORE METHODS //
    //////////////////
    public function test_path(): void
    {
        Flight::path(dir: __DIR__);

        $exampleObject = new ExampleClass();
        self::assertInstanceOf(ExampleClass::class, $exampleObject);
    }

    public function test_stop_with_code(): void
    {
        Flight::stop(code: 500);

        self::expectOutputString('');
        self::assertSame(500, Flight::response()->status());
    }

    public function test_halt(): void
    {
        Flight::halt(actuallyExit: false, code: 500, message: 'Test');

        self::expectOutputString('Test');
        self::assertSame(500, Flight::response()->status());
    }

    public function test_register(): void
    {
        Flight::register(
            class: stdClass::class,
            name: 'customClass',
            callback: static function (stdClass $object): void {
                $object->property = 'value';
            },
            params: []
        );

        $object = Flight::customClass();

        self::assertInstanceOf(stdClass::class, $object);
        self::assertObjectHasProperty('property', $object);
        self::assertSame('value', $object->property);

        Flight::unregister(methodName: 'customClass');
    }

    public function test_register_container(): void
    {
        $dateTime = new DateTimeImmutable();

        $controller = new class($dateTime) {
            public function __construct(private DateTimeImmutable $dateTime)
            {
                //
            }

            public function test(): void
            {
                echo $this->dateTime->format('Y-m-d');
            }
        };

        Flight::registerContainerHandler(
            containerHandler: new Container()
        );

        Flight::request()->url = '/test';

        Flight::route(
            pass_route: true,
            alias: 'testRoute',
            callback: [$controller::class, 'test'],
            pattern: '/test'
        );

        self::expectOutputString($dateTime->format('Y-m-d'));

        Flight::start();
    }

    /////////////////////
    // ROUTING METHODS //
    /////////////////////
    public function test_static_route(): void
    {
        Flight::request()->url = '/test';

        $route = Flight::route(
            pass_route: true,
            alias: 'testRoute',
            callback: function () {
                echo 'test';
            },
            pattern: '/test'
        );

        self::assertInstanceOf(Route::class, $route);
        self::expectOutputString('test');
        Flight::start();
    }
}
