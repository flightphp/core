<?php

declare(strict_types=1);

namespace flight\core;

class EventDispatcher
{
    private static ?self $instance = null;

    /** @var array<string, callable[]> */
    protected array $listeners = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function on(string $event, callable $callback): void
    {
        $this->listeners[$event] ??= [];
        $this->listeners[$event][] = $callback;
    }

    /**
     * @param mixed ...$args Arguments to pass to the listeners.
     * @return mixed
     */
    public function trigger(string $event, ...$args)
    {
        $listenerReturnValue = null;

        foreach ($this->getListeners($event) as $listener) {
            $listenerReturnValue = $listener(...$args);

            if ($listenerReturnValue === false) {
                break;
            }
        }

        return $listenerReturnValue;
    }

    public function hasListeners(string $event): bool
    {
        return (
            isset($this->listeners[$event])
            && is_array($this->listeners[$event])
            && count($this->listeners[$event])
        );
    }

    /** @return callable[] */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    /** @return string[] */
    public function getAllRegisteredEvents(): array
    {
        return array_keys($this->listeners);
    }

    public function removeListener(string $event, callable $callback): void
    {
        if (!$this->hasListeners($event)) {
            return;
        }

        $this->listeners[$event] = array_values(array_filter(
            $this->getListeners($event),
            static fn(callable $listener): bool => $listener !== $callback,
        ));
    }

    /**
     * Remove all listeners for a specific event.
     *
     * @param string $event the event name
     *
     * @return void
     */
    public function removeAllListeners(string $event): void
    {
        if (isset($this->listeners[$event]) === true) {
            unset($this->listeners[$event]);
        }
    }

    /**
     * Remove the current singleton instance of the EventDispatcher.
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
