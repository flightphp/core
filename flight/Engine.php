<?php

declare(strict_types=1);

namespace flight;

use Closure;
use ErrorException;
use Exception;
use flight\core\Dispatcher;
use flight\core\Loader;
use flight\net\Request;
use flight\net\Response;
use flight\net\Router;
use flight\template\View;
use Throwable;
use flight\net\Route;

/**
 * The Engine class contains the core functionality of the framework.
 * It is responsible for loading an HTTP request, running the assigned services,
 * and generating an HTTP response.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 *
 * # Core methods
 * @method void start() Starts engine
 * @method void stop() Stops framework and outputs current response
 * @method void halt(int $code = 200, string $message = '', bool $actuallyExit = true) Stops processing and returns a given response.
 *
 * # Routing
 * @method Route route(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Routes a URL to a callback function with all applicable methods
 * @method void group(string $pattern, callable $callback, array<int, callable|object> $group_middlewares = [])
 * Groups a set of routes together under a common prefix.
 * @method Route post(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Routes a POST URL to a callback function.
 * @method Route put(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Routes a PUT URL to a callback function.
 * @method Route patch(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Routes a PATCH URL to a callback function.
 * @method Route delete(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Routes a DELETE URL to a callback function.
 * @method Router router() Gets router
 * @method string getUrl(string $alias) Gets a url from an alias
 *
 * # Views
 * @method void render(string $file, ?array $data = null, ?string $key = null) Renders template
 * @method View view() Gets current view
 *
 * # Request-Response
 * @method Request request() Gets current request
 * @method Response response() Gets current response
 * @method void error(Throwable $e) Sends an HTTP 500 response for any errors.
 * @method void notFound() Sends an HTTP 404 response when a URL is not found.
 * @method void redirect(string $url, int $code = 303)  Redirects the current request to another URL.
 * @method void json(mixed $data, int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0)
 * Sends a JSON response.
 * @method void jsonp(mixed $data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0)
 * Sends a JSONP response.
 *
 * # HTTP caching
 * @method void etag(string $id, ('strong'|'weak') $type = 'strong') Handles ETag HTTP caching.
 * @method void lastModified(int $time) Handles last modified HTTP caching.
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Engine
{
    /**
     * @var array<string> List of methods that can be extended in the Engine class.
     */
    private const MAPPABLE_METHODS = [
        'start', 'stop', 'route', 'halt', 'error', 'notFound',
        'render', 'redirect', 'etag', 'lastModified', 'json', 'jsonp',
        'post', 'put', 'patch', 'delete', 'group', 'getUrl'
    ];

    /** @var array<string, mixed> Stored variables. */
    protected array $vars = [];

    /** Class loader. */
    protected Loader $loader;

    /** Event dispatcher. */
    protected Dispatcher $dispatcher;

    /** If the framework has been initialized or not. */
    protected bool $initialized = false;

    public function __construct()
    {
        $this->loader = new Loader();
        $this->dispatcher = new Dispatcher();
        $this->init();
    }

    /**
     * Handles calls to class methods.
     *
     * @param string $name Method name
     * @param array<int, mixed> $params Method parameters
     *
     * @throws Exception
     * @return mixed Callback results
     */
    public function __call(string $name, array $params)
    {
        $callback = $this->dispatcher->get($name);

        if (\is_callable($callback)) {
            return $this->dispatcher->run($name, $params);
        }

        if (!$this->loader->get($name)) {
            throw new Exception("$name must be a mapped method.");
        }

        $shared = empty($params) || $params[0];

        return $this->loader->load($name, $shared);
    }

    //////////////////
    // Core Methods //
    //////////////////

    /** Initializes the framework. */
    public function init(): void
    {
        $initialized = $this->initialized;
        $self = $this;

        if ($initialized) {
            $this->vars = [];
            $this->loader->reset();
            $this->dispatcher->reset();
        }

        // Add this class to Dispatcher
        $this->dispatcher->setEngine($this);

        // Register default components
        $this->loader->register('request', Request::class);
        $this->loader->register('response', Response::class);
        $this->loader->register('router', Router::class);

        $this->loader->register('view', View::class, [], function (View $view) use ($self) {
            $view->path = $self->get('flight.views.path');
            $view->extension = $self->get('flight.views.extension');
        });

        foreach (self::MAPPABLE_METHODS as $name) {
            $this->dispatcher->set($name, [$this, "_$name"]);
        }

        // Default configuration settings
        $this->set('flight.base_url');
        $this->set('flight.case_sensitive', false);
        $this->set('flight.handle_errors', true);
        $this->set('flight.log_errors', false);
        $this->set('flight.views.path', './views');
        $this->set('flight.views.extension', '.php');
        $this->set('flight.content_length', true);
        $this->set('flight.v2.output_buffering', false);

        // Startup configuration
        $this->before('start', function () use ($self) {
            // Enable error handling
            if ($self->get('flight.handle_errors')) {
                set_error_handler([$self, 'handleError']);
                set_exception_handler([$self, 'handleException']);
            }

            // Set case-sensitivity
            $self->router()->case_sensitive = $self->get('flight.case_sensitive');
            // Set Content-Length
            $self->response()->content_length = $self->get('flight.content_length');
            // This is to maintain legacy handling of output buffering
            // which causes a lot of problems. This will be removed
            // in v4
            $self->response()->v2_output_buffering = $this->get('flight.v2.output_buffering');
        });

        $this->initialized = true;
    }

    /**
     * Custom error handler. Converts errors into exceptions.
     *
     * @param int $errno Error number
     * @param string $errstr Error string
     * @param string $errfile Error file name
     * @param int $errline Error file line number
     *
     * @return false
     * @throws ErrorException
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if ($errno & error_reporting()) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        }

        return false;
    }

    /**
     * Custom exception handler. Logs exceptions.
     *
     * @param Throwable $e Thrown exception
     */
    public function handleException(Throwable $e): void
    {
        if ($this->get('flight.log_errors')) {
            error_log($e->getMessage()); // @codeCoverageIgnore
        }

        $this->error($e);
    }

    /**
     * Registers the container handler
     *
     * @param callable|object $containerHandler Callback function or PSR-11 Container object that sets the container and how it will inject classes
     *
     * @return void
     */
    public function registerContainerHandler($containerHandler): void
    {
        $this->dispatcher->setContainerHandler($containerHandler);
    }

    /**
     * Maps a callback to a framework method.
     *
     * @param string $name Method name
     * @param callable $callback Callback function
     *
     * @throws Exception If trying to map over a framework method
     */
    public function map(string $name, callable $callback): void
    {
        if (method_exists($this, $name)) {
            throw new Exception('Cannot override an existing framework method.');
        }

        $this->dispatcher->set($name, $callback);
    }

    /**
     * Registers a class to a framework method.
     *
     * # Usage example:
     * ```
     * $app = new Engine;
     * $app->register('user', User::class);
     *
     * $app->user(); # <- Return a User instance
     * ```
     *
     * @param string $name Method name
     * @param class-string<T> $class Class name
     * @param array<int, mixed> $params Class initialization parameters
     * @param ?Closure(T $instance): void $callback Function to call after object instantiation
     *
     * @template T of object
     * @throws Exception If trying to map over a framework method
     */
    public function register(string $name, string $class, array $params = [], ?callable $callback = null): void
    {
        if (method_exists($this, $name)) {
            throw new Exception('Cannot override an existing framework method.');
        }

        $this->loader->register($name, $class, $params, $callback);
    }

    /** Unregisters a class to a framework method. */
    public function unregister(string $methodName): void
    {
        $this->loader->unregister($methodName);
    }

    /**
     * Adds a pre-filter to a method.
     *
     * @param string $name Method name
     * @param Closure(array<int, mixed> &$params, string &$output): (void|false) $callback
     */
    public function before(string $name, callable $callback): void
    {
        $this->dispatcher->hook($name, 'before', $callback);
    }

    /**
     * Adds a post-filter to a method.
     *
     * @param string $name Method name
     * @param Closure(array<int, mixed> &$params, string &$output): (void|false) $callback
     */
    public function after(string $name, callable $callback): void
    {
        $this->dispatcher->hook($name, 'after', $callback);
    }

    /**
     * Gets a variable.
     *
     * @param ?string $key Variable name
     *
     * @return mixed Variable value or `null` if `$key` doesn't exists.
     */
    public function get(?string $key = null)
    {
        if (null === $key) {
            return $this->vars;
        }

        return $this->vars[$key] ?? null;
    }

    /**
     * Sets a variable.
     *
     * @param string|iterable<string, mixed> $key
     * Variable name as `string` or an iterable of `'varName' => $varValue`
     * @param mixed $value Ignored if `$key` is an `iterable`
     */
    public function set($key, $value = null): void
    {
        if (\is_iterable($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }

            return;
        }

        $this->vars[$key] = $value;
    }

    /**
     * Checks if a variable has been set.
     *
     * @param string $key Variable name
     *
     * @return bool Variable status
     */
    public function has(string $key): bool
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a variable. If no key is passed in, clear all variables.
     *
     * @param ?string $key Variable name, if `$key` isn't provided, it clear all variables.
     */
    public function clear(?string $key = null): void
    {
        if (null === $key) {
            $this->vars = [];
            return;
        }

        unset($this->vars[$key]);
    }

    /**
     * Adds a path for class autoloading.
     *
     * @param string $dir Directory path
     */
    public function path(string $dir): void
    {
        $this->loader->addDirectory($dir);
    }

    /**
     * Processes each routes middleware.
     *
     * @param Route $route The route to process the middleware for.
     * @param string $event_name If this is the before or after method.
     */
    protected function processMiddleware(Route $route, string $event_name): bool
    {
        $at_least_one_middleware_failed = false;

        $middlewares = $event_name === Dispatcher::FILTER_BEFORE ? $route->middleware : array_reverse($route->middleware);
        $params = $route->params;

        foreach ($middlewares as $middleware) {
            $middleware_object = false;

            if ($event_name === Dispatcher::FILTER_BEFORE) {
                // can be a callable or a class
                $middleware_object = (is_callable($middleware) === true
                    ? $middleware
                    : (method_exists($middleware, Dispatcher::FILTER_BEFORE) === true
                        ? [$middleware, Dispatcher::FILTER_BEFORE]
                        : false
                    )
                );
            } elseif ($event_name === Dispatcher::FILTER_AFTER) {
                // must be an object. No functions allowed here
                if (
                    is_object($middleware) === true
                    && !($middleware instanceof Closure)
                    && method_exists($middleware, Dispatcher::FILTER_AFTER) === true
                ) {
                    $middleware_object = [$middleware, Dispatcher::FILTER_AFTER];
                }
            }

            if ($middleware_object === false) {
                continue;
            }

            $use_v3_output_buffering =
                $this->response()->v2_output_buffering === false &&
                $route->is_streamed === false;

            if ($use_v3_output_buffering === true) {
                ob_start();
            }

            // It's assumed if you don't declare before, that it will be assumed as the before method
            $middleware_result = $middleware_object($params);

            if ($use_v3_output_buffering === true) {
                $this->response()->write(ob_get_clean());
            }

            if ($middleware_result === false) {
                $at_least_one_middleware_failed = true;
                break;
            }
        }

        return $at_least_one_middleware_failed;
    }

    ////////////////////////
    // Extensible Methods //
    ////////////////////////
    /**
     * Starts the framework.
     *
     * @throws Exception
     */
    public function _start(): void
    {
        $dispatched = false;
        $self = $this;
        $request = $this->request();
        $response = $this->response();
        $router = $this->router();

        // Allow filters to run
        $this->after('start', function () use ($self) {
            $self->stop();
        });

        if ($response->v2_output_buffering === true) {
            // Flush any existing output
            if (ob_get_length() > 0) {
                $response->write(ob_get_clean()); // @codeCoverageIgnore
            }

            // Enable output buffering
            // This is closed in the Engine->_stop() method
            ob_start();
        }

        // Route the request
        $failed_middleware_check = false;

        while ($route = $router->route($request)) {
            $params = array_values($route->params);

            // Add route info to the parameter list
            if ($route->pass) {
                $params[] = $route;
            }

            // If this route is to be streamed, we need to output the headers now
            if ($route->is_streamed === true) {
                $response->status($route->streamed_headers['status']);
                unset($route->streamed_headers['status']);
                $response->header('X-Accel-Buffering', 'no');
                $response->header('Connection', 'close');
                foreach ($route->streamed_headers as $header => $value) {
                    $response->header($header, $value);
                }

                // We obviously don't know the content length right now. This must be false.
                $response->content_length = false;
                $response->sendHeaders();
                $response->markAsSent();
            }

            // Run any before middlewares
            if (count($route->middleware) > 0) {
                $at_least_one_middleware_failed = $this->processMiddleware($route, 'before');
                if ($at_least_one_middleware_failed === true) {
                    $failed_middleware_check = true;
                    break;
                }
            }

            $use_v3_output_buffering =
                $this->response()->v2_output_buffering === false &&
                $route->is_streamed === false;

            if ($use_v3_output_buffering === true) {
                ob_start();
            }

            // Call route handler
            $continue = $this->dispatcher->execute(
                $route->callback,
                $params
            );

            if ($use_v3_output_buffering === true) {
                $response->write(ob_get_clean());
            }

            // Run any before middlewares
            if (count($route->middleware) > 0) {
                // process the middleware in reverse order now
                $at_least_one_middleware_failed = $this->processMiddleware($route, 'after');

                if ($at_least_one_middleware_failed === true) {
                    $failed_middleware_check = true;
                    break;
                }
            }

            $dispatched = true;

            if (!$continue) {
                break;
            }

            $router->next();

            $dispatched = false;
        }

        // HEAD requests should be identical to GET requests but have no body
        if ($request->method === 'HEAD') {
            $response->clearBody();
        }

        if ($failed_middleware_check === true) {
            $this->halt(403, 'Forbidden', empty(getenv('PHPUNIT_TEST')));
        } elseif ($dispatched === false) {
            $this->notFound();
        }
    }

    /**
     * Sends an HTTP 500 response for any errors.
     *
     * @param Throwable $e Thrown exception
     */
    public function _error(Throwable $e): void
    {
        $msg = sprintf(
            '<h1>500 Internal Server Error</h1>' .
                '<h3>%s (%s)</h3>' .
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
            // @codeCoverageIgnoreStart
        } catch (Throwable $t) {
            exit($msg);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Stops the framework and outputs the current response.
     *
     * @param ?int $code HTTP status code
     *
     * @throws Exception
     * @deprecated 3.5.3 This method will be removed in v4
     */
    public function _stop(?int $code = null): void
    {
        $response = $this->response();

        if (!$response->sent()) {
            if (null !== $code) {
                $response->status($code);
            }

            if ($response->v2_output_buffering === true && ob_get_length() > 0) {
                $response->write(ob_get_clean());
            }

            $response->send();
        }
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callable|string $callback Callback function
     * @param bool $pass_route Pass the matching route object to the callback
     * @param string $alias The alias for the route
     */
    public function _route(string $pattern, $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->router()->map($pattern, $callback, $pass_route, $alias);
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callable $callback Callback function that includes the Router class as first parameter
     * @param array<int, callable|object> $group_middlewares The middleware to be applied to the route
     */
    public function _group(string $pattern, callable $callback, array $group_middlewares = []): void
    {
        $this->router()->group($pattern, $callback, $group_middlewares);
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool $pass_route Pass the matching route object to the callback
     */
    public function _post(string $pattern, $callback, bool $pass_route = false, string $route_alias = ''): void
    {
        $this->router()->map('POST ' . $pattern, $callback, $pass_route, $route_alias);
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool $pass_route Pass the matching route object to the callback
     */
    public function _put(string $pattern, $callback, bool $pass_route = false, string $route_alias = ''): void
    {
        $this->router()->map('PUT ' . $pattern, $callback, $pass_route, $route_alias);
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool $pass_route Pass the matching route object to the callback
     */
    public function _patch(string $pattern, $callback, bool $pass_route = false, string $route_alias = ''): void
    {
        $this->router()->map('PATCH ' . $pattern, $callback, $pass_route, $route_alias);
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool $pass_route Pass the matching route object to the callback
     */
    public function _delete(string $pattern, $callback, bool $pass_route = false, string $route_alias = ''): void
    {
        $this->router()->map('DELETE ' . $pattern, $callback, $pass_route, $route_alias);
    }

    /**
     * Stops processing and returns a given response.
     *
     * @param int $code HTTP status code
     * @param string $message Response message
     * @param bool $actuallyExit Whether to actually exit the script or just send response
     */
    public function _halt(int $code = 200, string $message = '', bool $actuallyExit = true): void
    {
        $this->response()
            ->clear()
            ->status($code)
            ->write($message)
            ->send();
        if ($actuallyExit === true) {
            exit(); // @codeCoverageIgnore
        }
    }

    /** Sends an HTTP 404 response when a URL is not found. */
    public function _notFound(): void
    {
        $output = '<h1>404 Not Found</h1><h3>The page you have requested could not be found.</h3>';

        $this->response()
            ->clear()
            ->status(404)
            ->write($output)
            ->send();
    }

    /**
     * Redirects the current request to another URL.
     *
     * @param int $code HTTP status code
     */
    public function _redirect(string $url, int $code = 303): void
    {
        $base = $this->get('flight.base_url');

        if (null === $base) {
            $base = $this->request()->base;
        }

        // Append base url to redirect url
        if ('/' !== $base && false === strpos($url, '://')) {
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
     * @param ?array<string, mixed> $data Template data
     * @param ?string $key View variable name
     *
     * @throws Exception If template file wasn't found
     */
    public function _render(string $file, ?array $data = null, ?string $key = null): void
    {
        if (null !== $key) {
            $this->view()->set($key, $this->view()->fetch($file, $data));
            return;
        }

        $this->view()->render($file, $data);
    }

    /**
     * Sends a JSON response.
     *
     * @param mixed $data JSON data
     * @param int $code HTTP status code
     * @param bool $encode Whether to perform JSON encoding
     * @param string $charset Charset
     * @param int $option Bitmask Json constant such as JSON_HEX_QUOT
     *
     * @throws Exception
     */
    public function _json(
        $data,
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf-8',
        int $option = 0
    ): void {
        $json = $encode ? json_encode($data, $option) : $data;

        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/json; charset=' . $charset)
            ->write($json);
        if ($this->response()->v2_output_buffering === true) {
            $this->response()->send();
        }
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
     *
     * @throws Exception
     */
    public function _jsonp(
        $data,
        string $param = 'jsonp',
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf-8',
        int $option = 0
    ): void {
        $json = $encode ? json_encode($data, $option) : $data;
        $callback = $this->request()->query[$param];

        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/javascript; charset=' . $charset)
            ->write($callback . '(' . $json . ');');
        if ($this->response()->v2_output_buffering === true) {
            $this->response()->send();
        }
    }

    /**
     * Handles ETag HTTP caching.
     *
     * @param string $id ETag identifier
     * @param 'strong'|'weak' $type ETag type
     */
    public function _etag(string $id, string $type = 'strong'): void
    {
        $id = (('weak' === $type) ? 'W/' : '') . $id;

        $this->response()->header('ETag', '"' . str_replace('"', '\"', $id) . '"');

        if (
            isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
            $_SERVER['HTTP_IF_NONE_MATCH'] === $id
        ) {
            $this->halt(304, '', empty(getenv('PHPUNIT_TEST')));
        }
    }

    /**
     * Handles last modified HTTP caching.
     *
     * @param int $time Unix timestamp
     */
    public function _lastModified(int $time): void
    {
        $this->response()->header('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T', $time));

        if (
            isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
            strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $time
        ) {
            $this->halt(304, '', empty(getenv('PHPUNIT_TEST')));
        }
    }

    /**
     * Gets a url from an alias that's supplied.
     *
     * @param string $alias the route alias.
     * @param array<string, mixed> $params The params for the route if applicable.
     */
    public function _getUrl(string $alias, array $params = []): string
    {
        return $this->router()->getUrlByAlias($alias, $params);
    }
}
