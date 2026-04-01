<?php

declare(strict_types=1);

namespace flight\core;

class EventDispatcher
{
    /** Singleton instance of the EventDispatcher */
    private static ?self $instance = null;

    /** @var array<string, array<int, callable(): mixed>> */
    protected array $listeners = [];

    /** Singleton instance of the EventDispatcher */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a callback for an event.
     * @param string $event Event name
     * @param callable(): mixed $callback Callback function
     */
    public function on(string $event, callable $callback): void
    {
        $this->listeners[$event] ??= [];
        $this->listeners[$event][] = $callback;
    }

    /**
     * Trigger an event with optional arguments.
     * @param string $event Event name
     * @param mixed ...$args Arguments to pass to the callbacks
     * @return mixed
     */
    public function trigger(string $event, ...$args)
    {
        $result = null;

        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $callback) {
                $result = call_user_func_array($callback, $args);

                // If you return false, it will break the loop and stop the other event listeners.
                if ($result === false) {
                    break; // Stop executing further listeners
                }
            }
        }

        return $result;
    }

    /**
     * Check if an event has any registered listeners.
     * @param string $event Event name
     * @return bool True if the event has listeners, false otherwise
     */
    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) && count($this->listeners[$event]) > 0;
    }

    /**
     * Get all listeners registered for a specific event.
     * @param string $event Event name
     * @return array<int, callable(): mixed> Array of callbacks registered for the event
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    /**
     * Get a list of all events that have registered listeners.
     * @return array<int, string> Array of event names
     */
    public function getAllRegisteredEvents(): array
    {
        return array_keys($this->listeners);
    }

    /**
     * Remove a specific listener for an event.
     * @param string   $event    the event name
     * @param callable(): mixed $callback the exact callback to remove
     */
    public function removeListener(string $event, callable $callback): void
    {
        if ($this->hasListeners($event)) {
            $this->listeners[$event] = array_filter(
                $this->listeners[$event],
                static fn(callable $listener): bool => $listener !== $callback,
            );

            $this->listeners[$event] = array_values($this->listeners[$event]); // Re-index the array
        }
    }

    /**
     * Remove all listeners for a specific event.
     * @param string $event the event name
     */
    public function removeAllListeners(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /** Remove the current singleton instance of the EventDispatcher. */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
