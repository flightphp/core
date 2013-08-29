<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight;

use flight\core\Loader;
use flight\core\Dispatcher;

/**
 * The Engine class contains the core functionality of the framework.
 * It is responsible for loading an HTTP request, running the assigned services,
 * and generating an HTTP response.
 */
class Engine {
    /**
     * Stored variables.
     *
     * @var array
     */
    protected $vars;

    /**
     * Class loader.
     *
     * @var object
     */
    protected $loader;

    /**
     * Event dispatcher.
     *
     * @var object
     */
    protected $dispatcher;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->vars = array();

        $this->loader = new Loader();
        $this->dispatcher = new Dispatcher();

        $this->init();
    }

    /**
     * Handles calls to class methods.
     *
     * @param string $name Method name
     * @param array $params Method parameters
     */
    public function __call($name, $params) {
        $callback = $this->dispatcher->get($name);

        if (is_callable($callback)) {
            return $this->dispatcher->run($name, $params);
        }

        $shared = (!empty($params)) ? (bool)$params[0] : true;

        return $this->loader->load($name, $shared);
    }

    /*** Core Methods ***/

    /**
     * Initializes the framework.
     */
    public function init() {
        static $initialized = false;
        $self = $this;

        if ($initialized) {
            $this->vars = array();
            $this->loader->reset();
            $this->dispatcher->reset();
        }

        // Register default components
        $this->loader->register('request', '\flight\net\Request');
        $this->loader->register('response', '\flight\net\Response');
        $this->loader->register('router', '\flight\net\Router');
        $this->loader->register('view', '\flight\template\View', array(), function($view) use ($self) {
            $view->path = $self->get('flight.views.path');
        });

        // Register framework methods
        $methods = array(
            'start','stop','route','halt','error','notFound',
            'render','redirect','etag','lastModified','json'
        );
        foreach ($methods as $name) {
            $this->dispatcher->set($name, array($this, '_'.$name));
        }

        // Default configuration settings
        $this->set('flight.base_url', null);
        $this->set('flight.handle_errors', true);
        $this->set('flight.log_errors', false);
        $this->set('flight.views.path', './views');

        $initialized = true;
    }

    /**
     * Enables/disables custom error handling.
     *
     * @param bool $enabled True or false
     */
    public function handleErrors($enabled)
    {
        if ($enabled) {
            set_error_handler(array($this, 'handleError'));
            set_exception_handler(array($this, 'handleException'));
        }
        else {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * Custom error handler. Converts errors into exceptions.
     *
     * @param int $errno Error number
     * @param int $errstr Error string
     * @param int $errfile Error file name
     * @param int $errline Error file line number
     * @throws \ErrorException
     */
    public function handleError($errno, $errstr, $errfile, $errline) {
        if ($errno & error_reporting()) {
            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        }
    }

    /**
     * Custom exception handler. Logs exceptions.
     *
     * @param \Exception $e Thrown exception
     */
    public function handleException(\Exception $e) {
        if ($this->get('flight.log_errors')) {
            error_log($e->getMessage());
        }

        $this->error($e);
    }

    /**
     * Maps a callback to a framework method.
     *
     * @param string $name Method name
     * @param callback $callback Callback function
     * @throws \Exception If trying to map over a framework method
     */
    public function map($name, $callback) {
        if (method_exists($this, $name)) {
            throw new \Exception('Cannot override an existing framework method.');
        }

        $this->dispatcher->set($name, $callback);
    }

    /**
     * Registers a class to a framework method.
     *
     * @param string $name Method name
     * @param string $class Class name
     * @param array $params Class initialization parameters
     * @param callback $callback Function to call after object instantiation
     * @throws \Exception If trying to map over a framework method
     */
    public function register($name, $class, array $params = array(), $callback = null) {
        if (method_exists($this, $name)) {
            throw new \Exception('Cannot override an existing framework method.');
        }

        $this->loader->register($name, $class, $params, $callback);
    }

    /**
     * Adds a pre-filter to a method.
     *
     * @param string $name Method name
     * @param callback $callback Callback function
     */
    public function before($name, $callback) {
        $this->dispatcher->hook($name, 'before', $callback);
    }

    /**
     * Adds a post-filter to a method.
     *
     * @param string $name Method name
     * @param callback $callback Callback function
     */
    public function after($name, $callback) {
        $this->dispatcher->hook($name, 'after', $callback);
    }

    /**
     * Gets a variable.
     *
     * @param string $key Key
     * @return mixed
     */
    public function get($key) {
        return isset($this->vars[$key]) ? $this->vars[$key] : null;
    }

    /**
     * Sets a variable.
     *
     * @param mixed $key Key
     * @param string $value Value
     */
    public function set($key, $value = null) {
        if (is_array($key) || is_object($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }
        }
        else {
            $this->vars[$key] = $value;
        }
    }

    /**
     * Checks if a variable has been set.
     *
     * @param string $key Key
     * @return bool Variable status
     */
    public function has($key) {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a variable. If no key is passed in, clear all variables.
     *
     * @param string $key Key
     */
    public function clear($key = null) {
        if (is_null($key)) {
            $this->vars = array();
        }
        else {
            unset($this->vars[$key]);
        }
    }

    /**
     * Adds a path for class autoloading.
     *
     * @param string $dir Directory path
     */
    public function path($dir) {
        $this->loader->addDirectory($dir);
    }

    /*** Extensible Methods ***/

    /**
     * Starts the framework.
     */
    public function _start() {
        $dispatched = false;

        // Flush any existing output
        if (ob_get_length() > 0) {
            $this->response()->write(ob_get_contents());
        }

        // Enable output buffering
        ob_start();

        // Enable error handling
        $this->handleErrors($this->get('flight.handle_errors'));

        // Disable caching for AJAX requests
        if ($this->request()->ajax) {
            $this->response()->cache(false);
        }

        // Allow post-filters to run
        $this->after('start', array($this, 'stop'));

        // Route the request
        while ($route = $this->router()->route($this->request())) {
            $params = array_values($route->params);

            $continue = $this->dispatcher->execute(
                $route->callback,
                $params
            );

            $dispatched = true;

            if (!$continue) break;

            $this->router()->next();
        }

        if (!$dispatched) {
            $this->notFound();
        }
    }

    /**
     * Stops the framework and outputs the current response.
     */
    public function _stop() {
        $this->response()
            ->write(ob_get_clean())
            ->send();
    }

    /**
     * Stops processing and returns a given response.
     *
     * @param int $code HTTP status code
     * @param string $message Response message
     */
    public function _halt($code = 200, $message = '') {
        $this->response(false)
            ->status($code)
            ->write($message)
            ->send();
    }

    /**
     * Sends an HTTP 500 response for any errors.
     *
     * @param \Exception Thrown exception
     */
    public function _error(\Exception $e) {
        $msg = sprintf('<h1>500 Internal Server Error</h1>'.
            '<h3>%s (%s)</h3>'.
            '<pre>%s</pre>',
            $e->getMessage(),
            $e->getCode(),
            $e->getTraceAsString()
        );

        try {
            $this->response(false)
                ->status(500)
                ->write($msg)
                ->send();
        }
        catch (\Exception $ex) {
            exit($msg);
        }
    }

    /**
     * Sends an HTTP 404 response when a URL is not found.
     */
    public function _notFound() {
        $this->response(false)
            ->status(404)
            ->write(
                '<h1>404 Not Found</h1>'.
                '<h3>The page you have requested could not be found.</h3>'.
                str_repeat(' ', 512)
            )
            ->send();
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callback $callback Callback function
     */
    public function _route($pattern, $callback) {
        $this->router()->map($pattern, $callback);
    }

    /**
     * Redirects the current request to another URL.
     *
     * @param string $url URL
     * @param int $code HTTP status code
     */
    public function _redirect($url, $code = 303) {
        $base = $this->get('flight.base_url');

        if ($base === null) {
            $base = $this->request()->base;
        }

        // Append base to relative urls
        if ($base != '/' && $url[0] != '/' && strpos($url, '://') === false) {
            $url = $base.'/'.$url;
        }

        $this->response(false)
            ->status($code)
            ->header('Location', $url)
            ->write($url)
            ->send();
    }

    /**
     * Renders a template.
     *
     * @param string $file Template file
     * @param array $data Template data
     * @param string $key View variable name
     */
    public function _render($file, $data = null, $key = null) {
        if ($key !== null) {
            $this->view()->set($key, $this->view()->fetch($file, $data));
        }
        else {
            $this->view()->render($file, $data);
        }
    }

    /**
     * Sends a JSON response.
     *
     * @param mixed $data Data to JSON encode
     */
    public function _json($data) {
        $this->response()
            ->status(200)
            ->header('Content-Type', 'application/json')
            ->write(json_encode($data))
            ->send();
    }

    /**
     * Handles ETag HTTP caching.
     *
     * @param string $id ETag identifier
     * @param string $type ETag type
     */
    public function _etag($id, $type = 'strong') {
        $id = (($type === 'weak') ? 'W/' : '').$id;

        $this->response()->header('ETag', $id);

        if ($id === getenv('HTTP_IF_NONE_MATCH')) {
            $this->halt(304);
        }
    }

    /**
     * Handles last modified HTTP caching.
     *
     * @param int $time Unix timestamp
     */
    public function _lastModified($time) {
        $this->response()->header('Last-Modified', date(DATE_RFC1123, $time));

        if ($time === strtotime(getenv('HTTP_IF_MODIFIED_SINCE'))) {
            $this->halt(304);
        }
    }
}
