<?php

declare(strict_types=1);

namespace flight\core;

class EventDispatcher
{
    /** @var array<string, array<int, callable>> */
    protected array $listeners = [];

    /**
     * Register a callback for an event.
     *
     * @param string $event Event name
     * @param callable $callback Callback function
     */
    public function on(string $event, callable $callback): void
    {
        if (isset($this->listeners[$event]) === false) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $callback;
    }

    /**
     * Trigger an event with optional arguments.
     *
     * @param string $event Event name
     * @param mixed ...$args Arguments to pass to the callbacks
     */
    public function trigger(string $event, ...$args): void
    {
        if (isset($this->listeners[$event]) === true) {
            foreach ($this->listeners[$event] as $callback) {
                $result = call_user_func_array($callback, $args);

                // If you return false, it will break the loop and stop the other event listeners.
                if ($result === false) {
                    break; // Stop executing further listeners
                }
            }
        }
    }
}
