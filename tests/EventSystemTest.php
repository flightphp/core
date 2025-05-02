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
        Flight::eventDispatcher()->resetInstance(); // Clear any existing listeners
    }

    /**
     * Test registering and triggering a single listener.
     */
    public function testRegisterAndTriggerSingleListener(): void
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
    public function testRegisterMultipleListeners(): void
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
    public function testTriggerWithNoListeners(): void
    {
        // Should not throw any errors
        Flight::triggerEvent('non.existent.event');
        $this->assertTrue(true, 'Triggering an event with no listeners should not throw an error.');
    }

    /**
     * Test that a listener receives a single argument correctly.
     */
    public function testListenerReceivesSingleArgument(): void
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
    public function testListenerReceivesMultipleArguments(): void
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
    public function testListenersCalledInOrder(): void
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
    public function testListenerNotCalledForOtherEvents(): void
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
    public function testOverrideOnEvent(): void
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
    public function testOverrideTriggerEvent(): void
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
    public function testOverrideOnEventStillRegistersListener(): void
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
    public function testOverrideTriggerEventStillTriggersListeners(): void
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
    public function testInvalidCallableThrowsException(): void
    {
        $this->expectException(TypeError::class);
        // Assuming the event system validates callables
        Flight::onEvent('test.event', 'not_a_callable');
    }

    /**
     * Test that event propagation stops if a listener returns false.
     */
    public function testStopPropagation(): void
    {
        $firstCalled = false;
        $secondCalled = false;
        $thirdCalled = false;

        Flight::onEvent('test.event', function () use (&$firstCalled) {
            $firstCalled = true;
            return true; // Continue propagation
        });

        Flight::onEvent('test.event', function () use (&$secondCalled) {
            $secondCalled = true;
            return false; // Stop propagation
        });

        Flight::onEvent('test.event', function () use (&$thirdCalled) {
            $thirdCalled = true;
        });

        Flight::triggerEvent('test.event');

        $this->assertTrue($firstCalled, 'First listener should be called');
        $this->assertTrue($secondCalled, 'Second listener should be called');
        $this->assertFalse($thirdCalled, 'Third listener should not be called after propagation stopped');
    }

    /**
     * Test that hasListeners() correctly identifies events with listeners.
     */
    public function testHasListeners(): void
    {
        $this->assertFalse(Flight::eventDispatcher()->hasListeners('test.event'), 'Event should not have listeners before registration');

        Flight::onEvent('test.event', function () {
        });

        $this->assertTrue(Flight::eventDispatcher()->hasListeners('test.event'), 'Event should have listeners after registration');
    }

    /**
     * Test that getListeners() returns the correct listeners for an event.
     */
    public function testGetListeners(): void
    {
        $callback1 = function () {
        };
        $callback2 = function () {
        };

        $this->assertEmpty(Flight::eventDispatcher()->getListeners('test.event'), 'Event should have no listeners before registration');

        Flight::onEvent('test.event', $callback1);
        Flight::onEvent('test.event', $callback2);

        $listeners = Flight::eventDispatcher()->getListeners('test.event');
        $this->assertCount(2, $listeners, 'Event should have two registered listeners');
        $this->assertSame($callback1, $listeners[0], 'First listener should match the first callback');
        $this->assertSame($callback2, $listeners[1], 'Second listener should match the second callback');
    }

    /**
     * Test that getListeners() returns an empty array for events with no listeners.
     */
    public function testGetListenersForNonexistentEvent(): void
    {
        $listeners = Flight::eventDispatcher()->getListeners('nonexistent.event');
        $this->assertIsArray($listeners, 'Should return an array for nonexistent events');
        $this->assertEmpty($listeners, 'Should return an empty array for nonexistent events');
    }

    /**
     * Test that getAllRegisteredEvents() returns all event names with registered listeners.
     */
    public function testGetAllRegisteredEvents(): void
    {
        $this->assertEmpty(Flight::eventDispatcher()->getAllRegisteredEvents(), 'No events should be registered initially');

        Flight::onEvent('test.event1', function () {
        });
        Flight::onEvent('test.event2', function () {
        });

        $events = Flight::eventDispatcher()->getAllRegisteredEvents();
        $this->assertCount(2, $events, 'Should return all registered event names');
        $this->assertContains('test.event1', $events, 'Should contain the first event');
        $this->assertContains('test.event2', $events, 'Should contain the second event');
    }

    /**
     * Test that removeListener() correctly removes a specific listener from an event.
     */
    public function testRemoveListener(): void
    {
        $callback1 = function () {
            return 'callback1';
        };
        $callback2 = function () {
            return 'callback2';
        };

        Flight::onEvent('test.event', $callback1);
        Flight::onEvent('test.event', $callback2);

        $this->assertCount(2, Flight::eventDispatcher()->getListeners('test.event'), 'Event should have two listeners initially');

        Flight::eventDispatcher()->removeListener('test.event', $callback1);

        $listeners = Flight::eventDispatcher()->getListeners('test.event');
        $this->assertCount(1, $listeners, 'Event should have one listener after removal');
        $this->assertSame($callback2, $listeners[0], 'Remaining listener should be the second callback');
    }

    /**
     * Test that removeAllListeners() correctly removes all listeners for an event.
     */
    public function testRemoveAllListeners(): void
    {
        Flight::onEvent('test.event', function () {
        });
        Flight::onEvent('test.event', function () {
        });
        Flight::onEvent('another.event', function () {
        });

        $this->assertTrue(Flight::eventDispatcher()->hasListeners('test.event'), 'Event should have listeners before removal');
        $this->assertTrue(Flight::eventDispatcher()->hasListeners('another.event'), 'Another event should have listeners');

        Flight::eventDispatcher()->removeAllListeners('test.event');

        $this->assertFalse(Flight::eventDispatcher()->hasListeners('test.event'), 'Event should have no listeners after removal');
        $this->assertTrue(Flight::eventDispatcher()->hasListeners('another.event'), 'Another event should still have listeners');
    }

    /**
     * Test that trying to remove listeners for nonexistent events doesn't cause errors.
     */
    public function testRemoveListenersForNonexistentEvent(): void
    {
        // Should not throw any errors
        Flight::eventDispatcher()->removeListener('nonexistent.event', function () {
        });
        Flight::eventDispatcher()->removeAllListeners('nonexistent.event');

        $this->assertTrue(true, 'Removing listeners for nonexistent events should not throw errors');
    }
}
