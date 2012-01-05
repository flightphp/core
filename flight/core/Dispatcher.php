<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
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
     */
    public function run($name, $params) {
        $output = '';

        // Run pre-filters
        if (!empty($this->filters[$name]['before'])) {
            $this->filter($this->filters[$name]['before'], $params, $output);
        }

        // Run requested method
        $output = $this->execute($this->get($name), $params);

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
     * @param callback $callback Callback function
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
     * @param string $event Event name
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
     * @param reference $params Method parameters
     * @param reference $output Method output
     */
    public function filter($filters, &$params, &$output) {
        $args = array(&$params, &$output);
        foreach ($filters as $callback) {
            $continue = $this->execute($callback, $args);
            if ($continue === false) break;
        }
    }

    /**
     * Executes a callback function.
     *
     * @param callback $callback Callback function
     * @param array $params Function parameters
     * @return mixed Function results
     */
    public static function execute($callback, array &$params = array()) {
        if (is_callable($callback)) {
            return is_array($callback) ?
                self::invokeMethod($callback, $params) :
                self::callFunction($callback, $params);
        }
    }

    /**
     * Calls a function.
     *
     * @param string $func Name of function to call
     * @param array $params Function parameters 
     */
    public static function callFunction($func, array &$params = array()) {
        switch (count($params)) {
            case 0:
                return $func();
            case 1:
                return $func($params[0]);
            case 2:
                return $func($params[0], $params[1]);
            case 3:
                return $func($params[0], $params[1], $params[2]);
            case 4:
                return $func($params[0], $params[1], $params[2], $params[3]);
            case 5:
                return $func($params[0], $params[1], $params[2], $params[3], $params[4]);
            default:
                return call_user_func_array($func, $params);
        }
    }

    /**
     * Invokes a method.
     *
     * @param mixed $func Class method
     * @param array $params Class method parameters
     */
    public static function invokeMethod($func, array &$params = array()) {
        list($class, $method) = $func;

        switch (count($params)) {
            case 0:
                return $class::$method();
            case 1:
                return $class::$method($params[0]);
            case 2:
                return $class::$method($params[0], $params[1]);
            case 3:
                return $class::$method($params[0], $params[1], $params[2]);
            case 4:
                return $class::$method($params[0], $params[1], $params[2], $params[3]);
            case 5:
                return $class::$method($params[0], $params[1], $params[2], $params[3], $params[4]);
            default:
                return call_user_func_array($func, $params);
        }
    }
}
?>
