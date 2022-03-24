<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight\core;

/**
 * The Dispatcher class is responsible for dispatching events. Events
 * are simply aliases for class methods or functions. The Dispatcher
 * allows you to hook other functions to an event that can modify the
 * input parameters and/or the output.
 */
class Dispatcher {
    /**
     * Mapped events.
     *
     * @var array
     */
    protected $events = array();

    /**
     * Method filters.
     *
     * @var array
     */
    protected $filters = array();

    /**
     * Dispatches an event.
     *
     * @param string $name Event name
     * @param array $params Callback parameters
     * @return string Output of callback
     * @throws \Exception
     */
    public function run($name, array $params = array()) {
        $output = '';

        // Run pre-filters
        if (!empty($this->filters[$name]['before'])) {
            $this->filter($this->filters[$name]['before'], $params, $output);
        }

        // Run requested method
        $output = $this->get($name)(...$params);

        // Run post-filters
        if (!empty($this->filters[$name]['after'])) {
            $this->filter($this->filters[$name]['after'], $params, $output);
        }

        return $output;
    }

    /**
     * Assigns a callback to an event.
     *
     * @param string $name Event name
     * @param callback $callback Callback function
     */
    public function set($name, $callback) {
        $this->events[$name] = $callback;
    }

    /**
     * Gets an assigned callback.
     *
     * @param string $name Event name
     * @return callback $callback Callback function
     */
    public function get($name) {
        return isset($this->events[$name]) ? $this->events[$name] : null;
    }

    /**
     * Checks if an event has been set.
     *
     * @param string $name Event name
     * @return bool Event status
     */
    public function has($name) {
        return isset($this->events[$name]);
    }

    /**
     * Clears an event. If no name is given,
     * all events are removed.
     *
     * @param string $name Event name
     */
    public function clear($name = null) {
        if ($name !== null) {
            unset($this->events[$name]);
            unset($this->filters[$name]);
        }
        else {
            $this->events = array();
            $this->filters = array();
        }
    }

    /**
     * Hooks a callback to an event.
     *
     * @param string $name Event name
     * @param string $type Filter type
     * @param callback $callback Callback function
     */
    public function hook($name, $type, $callback) {
        $this->filters[$name][$type][] = $callback;
    }

    /**
     * Executes a chain of method filters.
     *
     * @param array $filters Chain of filters
     * @param array $params Method parameters
     * @param mixed $output Method output
     * @throws \Exception
     */
    public function filter($filters, &$params, &$output) {
        $args = array(&$params, &$output);
        foreach ($filters as $callback) {
            $continue =  $callback( ...$args );
            if ($continue === false) break;
        }
    }

    /**
     * Resets the object to the initial state.
     */
    public function reset() {
        $this->events = array();
        $this->filters = array();
    }
}
