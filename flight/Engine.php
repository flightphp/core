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
 *
 * Core methods
 * @method void start() Starts engine
 * @method void stop() Stops framework and outputs current response
 * @method void halt(int $code = 200, string $message = '') Stops processing and returns a given response.
 *
 *
 * Routing
 * @method void route(string $pattern, callable $callback, bool $pass_route = false) Routes a URL to a callback function.
 * @method \flight\net\Router router() Gets router
 *
 * Views
 * @method void render(string $file, array $data = null, string $key = null) Renders template
 * @method \flight\template\View view() Gets current view
 *
 * Request-response
 * @method \flight\net\Request request() Gets current request
 * @method \flight\net\Response response() Gets current response
 * @method void error(\Exception $e) Sends an HTTP 500 response for any errors.
 * @method void notFound() Sends an HTTP 404 response when a URL is not found.
 * @method void redirect(string $url, int $code = 303)  Redirects the current request to another URL.
 * @method void json(mixed $data, int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0) Sends a JSON response.
 * @method void jsonp(mixed $data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0) Sends a JSONP response.
 *
 * HTTP caching
 * @method void etag($id, string $type = 'strong') Handles ETag HTTP caching.
 * @method void lastModified(int $time) Handles last modified HTTP caching.
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
     * @var Loader
     */
    protected $loader;

    /**
     * Event dispatcher.
     *
     * @var Dispatcher
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
     * @return mixed Callback results
     * @throws \Exception
     */
    public function __call($name, $params) {
        $callback = $this->dispatcher->get($name);

        if (is_callable($callback)) {
            return $this->dispatcher->run($name, $params);
        }

        if (!$this->loader->get($name)) {
            throw new \Exception("{$name} must be a mapped method.");
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
            $view->extension = $self->get('flight.views.extension');
        });

        // Register framework methods
        $methods = array(
            'start','stop','route','halt','error','notFound',
            'render','redirect','etag','lastModified','json','jsonp'
        );
        foreach ($methods as $name) {
            $this->dispatcher->set($name, array($this, '_'.$name));
        }

        // Default configuration settings
        $this->set('flight.base_url', null);
        $this->set('flight.case_sensitive', false);
        $this->set('flight.handle_errors', true);
        $this->set('flight.log_errors', false);
        $this->set('flight.views.path', './views');
        $this->set('flight.views.extension', '.php');

        // Startup configuration
        $this->before('start', function() use ($self) {
            // Enable error handling
            if ($self->get('flight.handle_errors')) {
                set_error_handler(array($self, 'handleError'));
                set_exception_handler(array($self, 'handleException'));
            }

            // Set case-sensitivity
            $self->router()->case_sensitive = $self->get('flight.case_sensitive');
        });

        $initialized = true;
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
    public function handleException($e) {
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
    public function get($key = null) {
        if ($key === null) return $this->vars;

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
     * @throws \Exception
     */
    public function _start() {
        $dispatched = false;
        $self = $this;

        $request = $this->request();
        $response = $this->response();
        $router = $this->router();

        // Allow filters to run
        $this->after('start', function() use ($self) {
            $self->stop();
        });

        // Flush any existing output
        if (ob_get_length() > 0) {
            $response->write(ob_get_clean());
        }

        // Enable output buffering
        ob_start();

        // Route the request
        while ($route = $router->route($request)) {

            if ( $route->cors_allowed ) {
                if ( isset ( $_SERVER['HTTP_ORIGIN'] ) ) {
                    // Allow source
                    $response->header('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN'])
                    ->header('Access-Control-Allow-Credentials', true)
                    ->header('Access-Control-Max-Age', 86400);
                }

                // Access-Control headers are received during OPTIONS requests
                if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
                    if ( isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) ) {
                        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                    }

                    if ( isset( $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ) ) {
                        $response->header('Access-Control-Allow-Methods', $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
                    }
                    $response->status(200)->send();

                    exit(0);
                }
            }

            $params = array_values($route->params);

            // Add route info to the parameter list
            if ($route->pass) {
                $params[] = $route;
            }

            // Call route handler
            $continue = $this->dispatcher->execute(
                $route->callback,
                $params
            );

            $dispatched = true;

            if (!$continue) break;

            $router->next();

            $dispatched = false;
        }

        if (!$dispatched) {
            $this->notFound();
        }
    }

    /**
     * Stops the framework and outputs the current response.
     *
     * @param int $code HTTP status code
     * @throws \Exception
     */
    public function _stop($code = null) {
        $response = $this->response();

        if (!$response->sent()) {
            if ($code !== null) {
                $response->status($code);
            }

            $response->write(ob_get_clean());

            $response->send();
        }
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callback $callback Callback function
     * @param boolean $pass_route Pass the matching route object to the callback
     */
    public function _route($pattern, $callback, $pass_route = false, $cors_allowed = false) {
        $this->router()->map($pattern, $callback, $pass_route, $cors_allowed);
    }

    /**
     * Stops processing and returns a given response.
     *
     * @param int $code HTTP status code
     * @param string $message Response message
     */
    public function _halt($code = 200, $message = '') {
        $this->response()
            ->clear()
            ->status($code)
            ->write($message)
            ->send();
        exit();
    }

    /**
     * Sends an HTTP 500 response for any errors.
     *
     * @param \Exception|\Throwable $e Thrown exception
     */
    public function _error($e) {
        $msg = sprintf('<h1>500 Internal Server Error</h1>'.
            '<h3>%s (%s)</h3>'.
            '<pre>%s</pre>',
            $e->getMessage(),
            $e->getCode(),
            $e->getTraceAsString()
        );

        try {
            $this->response()
                ->clear()
                ->status(500)
                ->write($msg)
                ->send();
        }
        catch (\Throwable $t) { // PHP 7.0+
            exit($msg);
        } catch(\Exception $e) { // PHP < 7
            exit($msg);
        }
    }

    /**
     * Sends an HTTP 404 response when a URL is not found.
     */
    public function _notFound() {
        $this->response()
            ->clear()
            ->status(404)
            ->write(
                '<h1>404 Not Found</h1>'.
                '<h3>The page you have requested could not be found.</h3>'.
                str_repeat(' ', 512)
            )
            ->send();
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

        // Append base url to redirect url
        if ($base != '/' && strpos($url, '://') === false) {
            $url = $base . preg_replace('#/+#', '/', '/' . $url);
        }

        $this->response()
            ->clear()
            ->status($code)
            ->header('Location', $url)
            ->send();
    }

    /**
     * Renders a template.
     *
     * @param string $file Template file
     * @param array $data Template data
     * @param string $key View variable name
     * @throws \Exception
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
     * @param mixed $data JSON data
     * @param int $code HTTP status code
     * @param bool $encode Whether to perform JSON encoding
     * @param string $charset Charset
     * @param int $option Bitmask Json constant such as JSON_HEX_QUOT
     * @throws \Exception
     */
    public function _json(
        $data,
        $code = 200,
        $encode = true,
        $charset = 'utf-8',
        $option = 0
    ) {
        $json = ($encode) ? json_encode($data, $option) : $data;

        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/json; charset='.$charset)
            ->write($json)
            ->send();
    }

    /**
     * Sends a JSONP response.
     *
     * @param mixed $data JSON data
     * @param string $param Query parameter that specifies the callback name.
     * @param int $code HTTP status code
     * @param bool $encode Whether to perform JSON encoding
     * @param string $charset Charset
     * @param int $option Bitmask Json constant such as JSON_HEX_QUOT
     * @throws \Exception
     */
    public function _jsonp(
        $data,
        $param = 'jsonp',
        $code = 200,
        $encode = true,
        $charset = 'utf-8',
        $option = 0
    ) {
        $json = ($encode) ? json_encode($data, $option) : $data;

        $callback = $this->request()->query[$param];

        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/javascript; charset='.$charset)
            ->write($callback.'('.$json.');')
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

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
            $_SERVER['HTTP_IF_NONE_MATCH'] === $id) {
            $this->halt(304);
        }
    }

    /**
     * Handles last modified HTTP caching.
     *
     * @param int $time Unix timestamp
     */
    public function _lastModified($time) {
        $this->response()->header('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T', $time));

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
            strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $time) {
            $this->halt(304);
        }
    }
}
