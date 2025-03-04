<?php

declare(strict_types=1);

namespace flight\tests;

use Flight;
use PHPUnit\Framework\TestCase;
use flight\Engine;
use TypeError;

class EventSystemTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset the Flight engine before each test to ensure a clean state
        Flight::setEngine(new Engine());
        Flight::app()->init();
    }

    /**
     * Test registering and triggering a single listener.
     */
    public function testRegisterAndTriggerSingleListener()
    {
        $called = false;
        Flight::onEvent('test.event', function () use (&$called) {
            $called = true;
        });
        Flight::triggerEvent('test.event');
        $this->assertTrue($called, 'Single listener should be called when event is triggered.');
    }

    /**
     * Test registering multiple listeners for the same event.
     */
    public function testRegisterMultipleListeners()
    {
        $counter = 0;
        Flight::onEvent('test.event', function () use (&$counter) {
            $counter++;
        });
        Flight::onEvent('test.event', function () use (&$counter) {
            $counter++;
        });
        Flight::triggerEvent('test.event');
        $this->assertEquals(2, $counter, 'All registered listeners should be called.');
    }

    /**
     * Test triggering an event with no listeners registered.
     */
    public function testTriggerWithNoListeners()
    {
        // Should not throw any errors
        Flight::triggerEvent('non.existent.event');
        $this->assertTrue(true, 'Triggering an event with no listeners should not throw an error.');
    }

    /**
     * Test that a listener receives a single argument correctly.
     */
    public function testListenerReceivesSingleArgument()
    {
        $received = null;
        Flight::onEvent('test.event', function ($arg) use (&$received) {
            $received = $arg;
        });
        Flight::triggerEvent('test.event', 'hello');
        $this->assertEquals('hello', $received, 'Listener should receive the passed argument.');
    }

    /**
     * Test that a listener receives multiple arguments correctly.
     */
    public function testListenerReceivesMultipleArguments()
    {
        $received = [];
        Flight::onEvent('test.event', function ($arg1, $arg2) use (&$received) {
            $received = [$arg1, $arg2];
        });
        Flight::triggerEvent('test.event', 'first', 'second');
        $this->assertEquals(['first', 'second'], $received, 'Listener should receive all passed arguments.');
    }

    /**
     * Test that listeners are called in the order they were registered.
     */
    public function testListenersCalledInOrder()
    {
        $order = [];
        Flight::onEvent('test.event', function () use (&$order) {
            $order[] = 1;
        });
        Flight::onEvent('test.event', function () use (&$order) {
            $order[] = 2;
        });
        Flight::triggerEvent('test.event');
        $this->assertEquals([1, 2], $order, 'Listeners should be called in registration order.');
    }

    /**
     * Test that listeners are not called for unrelated events.
     */
    public function testListenerNotCalledForOtherEvents()
    {
        $called = false;
        Flight::onEvent('test.event1', function () use (&$called) {
            $called = true;
        });
        Flight::triggerEvent('test.event2');
        $this->assertFalse($called, 'Listeners should not be called for different events.');
    }

    /**
     * Test overriding the onEvent method.
     */
    public function testOverrideOnEvent()
    {
        $called = false;
        Flight::map('onEvent', function ($event, $callback) use (&$called) {
            $called = true;
        });
        Flight::onEvent('test.event', function () {
        });
        $this->assertTrue($called, 'Overridden onEvent method should be called.');
    }

    /**
     * Test overriding the triggerEvent method.
     */
    public function testOverrideTriggerEvent()
    {
        $called = false;
        Flight::map('triggerEvent', function ($event, ...$args) use (&$called) {
            $called = true;
        });
        Flight::triggerEvent('test.event');
        $this->assertTrue($called, 'Overridden triggerEvent method should be called.');
    }

    /**
     * Test that an overridden onEvent can still register listeners by calling the original method.
     */
    public function testOverrideOnEventStillRegistersListener()
    {
        $overrideCalled = false;
        Flight::map('onEvent', function ($event, $callback) use (&$overrideCalled) {
            $overrideCalled = true;
            // Call the original method
            Flight::app()->_onEvent($event, $callback);
        });

        $listenerCalled = false;
        Flight::onEvent('test.event', function () use (&$listenerCalled) {
            $listenerCalled = true;
        });

        Flight::triggerEvent('test.event');

        $this->assertTrue($overrideCalled, 'Overridden onEvent should be called.');
        $this->assertTrue($listenerCalled, 'Listener should still be triggered after override.');
    }

    /**
     * Test that an overridden triggerEvent can still trigger listeners by calling the original method.
     */
    public function testOverrideTriggerEventStillTriggersListeners()
    {
        $overrideCalled = false;
        Flight::map('triggerEvent', function ($event, ...$args) use (&$overrideCalled) {
            $overrideCalled = true;
            // Call the original method
            Flight::app()->_triggerEvent($event, ...$args);
        });

        $listenerCalled = false;
        Flight::onEvent('test.event', function () use (&$listenerCalled) {
            $listenerCalled = true;
        });

        Flight::triggerEvent('test.event');

        $this->assertTrue($overrideCalled, 'Overridden triggerEvent should be called.');
        $this->assertTrue($listenerCalled, 'Listeners should still be triggered after override.');
    }

    /**
     * Test that an invalid callable throws an exception (if applicable).
     */
    public function testInvalidCallableThrowsException()
    {
        $this->expectException(TypeError::class);
        // Assuming the event system validates callables
        Flight::onEvent('test.event', 'not_a_callable');
    }
}
