<?php

declare(strict_types=1);

namespace flight;

use Closure;
use ErrorException;
use Exception;
use flight\core\Dispatcher;
use flight\core\EventDispatcher;
use flight\core\Loader;
use flight\net\Request;
use flight\net\Response;
use flight\net\Router;
use flight\template\View;
use flight\util\Json;
use Throwable;
use flight\net\Route;
use Psr\Container\ContainerInterface;

/**
 * The Engine class contains the core functionality of the framework.
 * It is responsible for loading an HTTP request, running the assigned services,
 * and generating an HTTP response.
 *
 * @license MIT, https://docs.flightphp.com/license
 * @copyright Copyright (c) 2011-2026,
 * Mike Cao <mike@mikecao.com>, n0nag0n <n0nag0n@sky-9.com>, fadrian06 <https://github.com/fadrian06>
 *
 * @method void start()
 * @method void stop(?int $code = null)
 * @method void halt(int $code = 200, string $message = '', bool $actuallyExit = true)
 * @method EventDispatcher eventDispatcher()
 * @method Route route(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @method void group(string $pattern, callable $callback, array<int, class-string|callable|array{0: class-string, 1: string}> $group_middlewares = [])
 * @method Route post(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @method Route put(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @method Route patch(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @method Route delete(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @method void resource(string $pattern, class-string $controllerClass, array<string, string|array<int, string>> $methods = [])
 * @method Router router()
 * @method string getUrl(string $alias, array<string, mixed> $params)
 * @method void render(string $file, ?array<string, mixed> $data, string $key = null)
 * @method View view()
 * @method void onEvent(string $event, callable $callback)
 * @method void triggerEvent(string $event, ...$args)
 * @method Request request()
 * @method Response response()
 * @method void error(Throwable $e)
 * @method void notFound()
 * @method void methodNotFound(Route $route)
 * @method void redirect(string $url, int $code = 303)
 * @method void json($data, int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0)
 * @method void jsonHalt($data, int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0)
 * @method void jsonp($data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0)
 * @method void etag(string $id, string $type = 'strong')
 * @method void lastModified(int $time)
 * @method void download(string $filePath)
 */
class Engine
{
    /** @var array<int, string> List of methods that can be extended in the Engine class */
    private const MAPPABLE_METHODS = [
        'start',
        'stop',
        'route',
        'halt',
        'error',
        'notFound',
        'methodNotFound',
        'render',
        'redirect',
        'etag',
        'lastModified',
        'json',
        'jsonHalt',
        'jsonp',
        'post',
        'put',
        'patch',
        'delete',
        'group',
        'getUrl',
        'download',
        'resource',
        'onEvent',
        'triggerEvent'
    ];

    /** @var array<string, mixed> */
    private array $vars = [
        'flight.base_url' => null,
        'flight.case_sensitive' => false,
        'flight.handle_errors' => true,
        'flight.log_errors' => false,
        'flight.views.path' => './views',
        'flight.views.extension' => '.php',
        'flight.content_length' => true,
    ];

    protected Loader $loader;
    protected Dispatcher $dispatcher;
    protected EventDispatcher $eventDispatcher;

    /** If the request has been handled or not */
    private bool $requestHandled = false;

    public function __construct()
    {
        $this->loader = new Loader();
        $this->dispatcher = new Dispatcher();

        // Register default components
        $this->loader->register('eventDispatcher', EventDispatcher::class);
        $this->loader->register('request', Request::class);

        $this->loader->register('response', Response::class, [], function (Response $response): void {
            $response->content_length = $this->get('flight.content_length');
        });

        $this->loader->register('router', Router::class, [], function (Router $router): void {
            $router->caseSensitive = $this->vars['flight.case_sensitive'];
        });

        $this->loader->register('view', View::class, [], function (View $view): void {
            $view->path = $this->vars['flight.views.path'];
            $view->extension = $this->vars['flight.views.extension'];
        });

        foreach (self::MAPPABLE_METHODS as $name) {
            $this->dispatcher->set($name, [$this, "_$name"]);
        }

        // Enable error handling
        if ($this->get('flight.handle_errors')) {
            set_error_handler([$this, 'handleError']);
            set_exception_handler([$this, 'handleException']);
        }
    }

    /**
     * @param string $name Method name
     * @param array<int, mixed> $arguments Method parameters
     * @throws Throwable
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $callback = $this->dispatcher->get($name);

        if (is_callable($callback)) {
            return $this->dispatcher->run($name, $arguments);
        }

        if (!$this->loader->get($name)) {
            throw new Exception("$name must be a mapped method.");
        }

        $shared = empty($arguments) || $arguments[0];

        return $this->loader->load($name, $shared);
    }

    //////////////////
    // Core Methods //
    //////////////////

    /**
     * Converts errors into exceptions
     * @param int $errno Level of the error raised.
     * @param string $errstr Error message.
     * @param string $errfile Filename that the error was raised in.
     * @param int $errline Line number where the error was raised.
     * @throws ErrorException
     * @return false
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if ($errno & error_reporting()) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        }

        return false;
    }

    /** Logs exceptions */
    public function handleException(Throwable $ex): void
    {
        if ($this->get('flight.log_errors')) {
            error_log($ex->getMessage());
        }

        $this->error($ex);
    }

    /**
     * Registers the container handler
     * @template T of object
     * @param ContainerInterface|callable(class-string<T>): T $containerHandler
     * Callback function or PSR-11 Container object that sets the container and how it will inject classes
     */
    public function registerContainerHandler($containerHandler): void
    {
        $this->dispatcher->setContainerHandler($containerHandler);
    }

    /**
     * Maps a callback to a framework method
     * @throws Exception If trying to map over a framework method
     */
    public function map(string $name, callable $callback): void
    {
        $this->ensureMethodNotExists($name)->dispatcher->set($name, $callback);
    }

    /** @throws Exception */
    private function ensureMethodNotExists(string $method): self
    {
        if (method_exists($this, $method)) {
            throw new Exception('Cannot override an existing framework method.');
        }

        return $this;
    }

    /**
     * Registers a class to a framework method.
     * # Usage example:
     * ```
     * $app = new Engine;
     * $app->register('user', User::class);
     *
     * $app->user(); # <- Return a User instance
     * ```
     * @template T of object
     * @param string $name Method name
     * @param class-string<T> $class Class name
     * @param array<int, mixed> $params Class initialization parameters
     * @param ?callable(T): void $callback Function to call after object instantiation
     * @throws Exception If trying to map over a framework method
     */
    public function register(string $name, string $class, array $params = [], ?callable $callback = null): void
    {
        $this->ensureMethodNotExists($name)->loader->register($name, $class, $params, $callback);
    }

    /** Unregisters a class to a framework method */
    public function unregister(string $methodName): void
    {
        $this->loader->unregister($methodName);
    }

    /**
     * Adds a pre-filter to a method
     * @param string $name Method name
     * @param callable(array<int, mixed> &$params, string &$output): (void|false) $callback
     */
    public function before(string $name, callable $callback): void
    {
        $this->dispatcher->hook($name, Dispatcher::FILTER_BEFORE, $callback);
    }

    /**
     * Adds a post-filter to a method
     * @param string $name Method name
     * @param callable(array<int, mixed> &$params, string &$output): (void|false) $callback
     */
    public function after(string $name, callable $callback): void
    {
        $this->dispatcher->hook($name, Dispatcher::FILTER_AFTER, $callback);
    }

    /**
     * Gets a variable
     * @param ?string $key Variable name
     * @return mixed Variable value or `null` if `$key` doesn't exists.
     */
    public function get(?string $key = null)
    {
        if ($key === null) {
            return $this->vars;
        }

        return $this->vars[$key] ?? null;
    }

    /**
     * Sets a variable
     * @param string|iterable<string, mixed> $key
     * Variable name as `string` or an iterable of `'varName' => $varValue`
     * @param mixed $value Ignored if `$key` is an `iterable`
     */
    public function set($key, $value = null): void
    {
        if (is_iterable($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }

            return;
        }

        $this->vars[$key] = $value;
    }

    /**
     * Checks if a variable has been set
     * @param string $key Variable name
     * @return bool Variable status
     */
    public function has(string $key): bool
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a variable. If no key is passed in, clear all variables
     * @param ?string $key Variable name, if `$key` isn't provided, it clear all variables.
     */
    public function clear(?string $key = null): void
    {
        if ($key === null) {
            $this->vars = [];
            return;
        }

        unset($this->vars[$key]);
    }

    /**
     * Processes each routes middleware
     * @param Route $route The route to process the middleware for.
     * @param string $eventName If this is the before or after method.
     */
    private function processMiddleware(Route $route, string $eventName): bool
    {
        $atLeastOneMiddlewareFailed = false;

        // Process things normally for before, and then in reverse order for after.
        $middlewares = $eventName === Dispatcher::FILTER_BEFORE
            ? $route->middleware
            : array_reverse($route->middleware);

        $params = $route->params;

        foreach ($middlewares as $middleware) {
            // Assume that nothing is going to be executed for the middleware.
            $middlewareObject = false;

            // Closure functions can only run on the before event
            if ($eventName === Dispatcher::FILTER_BEFORE && is_object($middleware) && $middleware instanceof Closure) {
                $middlewareObject = $middleware;

                // If the object has already been created, we can just use it if the event name exists.
            } elseif (is_object($middleware)) {
                $middlewareObject = method_exists($middleware, $eventName) ? [$middleware, $eventName] : false;

                // If the middleware is a string, we need to create the object and then call the event.
            } elseif (is_string($middleware) && method_exists($middleware, $eventName)) {
                $resolvedClass = null;

                // if there's a container assigned, we should use it to create the object
                if ($this->dispatcher->mustUseContainer($middleware)) {
                    $resolvedClass = $this->dispatcher->resolveContainerClass($middleware, $params);
                    // otherwise just assume it's a plain jane class, so inject the engine
                    // just like in Dispatcher::invokeCallable()
                } elseif (class_exists($middleware)) {
                    $resolvedClass = new $middleware($this);
                }

                // If something was resolved, create an array callable that will be passed in later.
                if ($resolvedClass !== null) {
                    $middlewareObject = [$resolvedClass, $eventName];
                }
            }

            // If nothing was resolved, go to the next thing
            if (!$middlewareObject) {
                continue;
            }

            // This is the way that v3 handles output buffering (which captures output correctly)
            $useV3OutputBuffering = !$route->is_streamed;

            if ($useV3OutputBuffering) {
                ob_start();
            }

            // Here is the array callable $middlewareObject that we created earlier.
            // It looks bizarre but it's really calling [ $class, $method ]($params)
            // Which loosely translates to $class->$method($params)
            $start = microtime(true);
            $middlewareResult = $middlewareObject($params);

            $this->triggerEvent(
                'flight.middleware.executed',
                $route,
                $middleware,
                $eventName,
                microtime(true) - $start
            );

            if ($useV3OutputBuffering) {
                $this->response()->write(ob_get_clean());
            }

            // If you return false in your middleware, it will halt the request
            // and throw a 403 forbidden error by default.
            if ($middlewareResult === false) {
                $atLeastOneMiddlewareFailed = true;
                break;
            }
        }

        return $atLeastOneMiddlewareFailed;
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

        // This behavior is specifically for test suites, and for async platforms like swoole, workerman, etc.
        if ($this->requestHandled === false) {
            // not doing much here, just setting the requestHandled flag to true
            $this->requestHandled = true;

            // Allow filters to run
            // This prevents multiple after events from being registered
            $this->after('start', function () use ($self) {
                $self->stop();
            });
        } else {
            // deregister the request and response objects and re-register them with new instances
            $this->unregister('request');
            $this->unregister('response');
            $this->register('request', Request::class);
            $this->register('response', Response::class);
            $this->router()->reset();
        }
        $request = $this->request();
        $this->triggerEvent('flight.request.received', $request);

        $response = $this->response();
        $router = $this->router();

        // Route the request
        $failedMiddlewareCheck = false;
        while ($route = $router->route($request)) {
            $this->triggerEvent('flight.route.matched', $route);
            $params = array_values($route->params);

            // Add route info to the parameter list
            if ($route->pass) {
                $params[] = $route;
            }

            // OPTIONS request handling
            if ($request->method === 'OPTIONS') {
                $allowedMethods = $route->methods;
                $response->status(204)
                    ->header('Allow', implode(', ', $allowedMethods))
                    ->send();
                return;
            }

            // If this route is to be streamed, we need to output the headers now
            if ($route->is_streamed === true) {
                if (count($route->streamed_headers) > 0) {
                    $response->status($route->streamed_headers['status'] ?? 200);
                    unset($route->streamed_headers['status']);
                    foreach ($route->streamed_headers as $header => $value) {
                        $response->header($header, $value);
                    }
                }

                $response->header('X-Accel-Buffering', 'no');
                $response->header('Connection', 'close');

                // We obviously don't know the content length right now. This must be false.
                $response->content_length = false;
                $response->sendHeaders();
                $response->markAsSent();
            }

            // Run any before middlewares
            if (count($route->middleware) > 0) {
                $atLeastOneMiddlewareFailed = $this->processMiddleware($route, 'before');
                if ($atLeastOneMiddlewareFailed === true) {
                    $failedMiddlewareCheck = true;
                    break;
                }
                $this->triggerEvent('flight.middleware.before', $route);
            }

            $useV3OutputBuffering = !$route->is_streamed;

            if ($useV3OutputBuffering) {
                ob_start();
            }

            // Call route handler
            $routeStart = microtime(true);
            $continue = $this->dispatcher->execute($route->callback, $params);
            $this->triggerEvent('flight.route.executed', $route, microtime(true) - $routeStart);

            if ($useV3OutputBuffering) {
                $response->write(ob_get_clean());
            }

            // Run any after middlewares
            if (count($route->middleware) > 0) {
                // process the middleware in reverse order now
                $atLeastOneMiddlewareFailed = $this->processMiddleware($route, 'after');

                if ($atLeastOneMiddlewareFailed === true) {
                    $failedMiddlewareCheck = true;
                    break;
                }

                $this->triggerEvent('flight.middleware.after', $route);
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

        if ($failedMiddlewareCheck === true) {
            $this->halt(403, 'Forbidden', empty(getenv('PHPUNIT_TEST')));
        } elseif ($dispatched === false) {
            // Get the previous route and check if the method failed, but the URL was good.
            $lastRouteExecuted = $router->executedRoute;
            if ($lastRouteExecuted !== null && $lastRouteExecuted->matchUrl($request->url) && !$lastRouteExecuted->matchMethod($request->method)) {
                $this->methodNotFound($lastRouteExecuted);
            } else {
                $this->notFound();
            }
        }
    }

    /**
     * Sends an HTTP 500 response for any errors.
     *
     * @param Throwable $e Thrown exception
     */
    public function _error(Throwable $e): void
    {
        $this->triggerEvent('flight.error', $e);
        $msg = sprintf(
            <<<'HTML'
            <h1>500 Internal Server Error</h1>
                <h3>%s (%s)</h3>
                <pre>%s</pre>
            HTML, // phpcs:ignore
            $e->getMessage(),
            $e->getCode(),
            $e->getTraceAsString()
        );

        try {
            $this->response()
                ->cache(0)
                ->clearBody()
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

        if ($response->sent() === false) {
            if ($code !== null) {
                $response->status($code);
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
     *
     * @return Route
     */
    public function _post(string $pattern, $callback, bool $pass_route = false, string $route_alias = ''): Route
    {
        return $this->router()->map('POST ' . $pattern, $callback, $pass_route, $route_alias);
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool $pass_route Pass the matching route object to the callback
     *
     * @return Route
     */
    public function _put(string $pattern, $callback, bool $pass_route = false, string $route_alias = ''): Route
    {
        return $this->router()->map('PUT ' . $pattern, $callback, $pass_route, $route_alias);
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool $pass_route Pass the matching route object to the callback
     *
     * @return Route
     */
    public function _patch(string $pattern, $callback, bool $pass_route = false, string $route_alias = ''): Route
    {
        return $this->router()->map('PATCH ' . $pattern, $callback, $pass_route, $route_alias);
    }

    /**
     * Routes a URL to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool $pass_route Pass the matching route object to the callback
     *
     * @return Route
     */
    public function _delete(string $pattern, $callback, bool $pass_route = false, string $route_alias = ''): Route
    {
        return $this->router()->map('DELETE ' . $pattern, $callback, $pass_route, $route_alias);
    }

    /**
     * Create a resource controller customizing the methods names mapping.
     *
     * @param class-string $controllerClass
     * @param array<string, string|array<string>> $options
     */
    public function _resource(
        string $pattern,
        string $controllerClass,
        array $options = []
    ): void {
        $this->router()->mapResource($pattern, $controllerClass, $options);
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
        if ($this->response()->getHeader('Cache-Control') === null) {
            $this->response()->cache(0);
        }

        $this->response()
            ->clearBody()
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
            ->clearBody()
            ->status(404)
            ->write($output)
            ->send();
    }

    /**
     * Function to run if the route has been found but not the method.
     *
     * @param Route $route - The executed route
     *
     * @return void
     */
    public function _methodNotFound(Route $route): void
    {
        $this->response()->setHeader('Allow', implode(', ', $route->methods));

        $this->halt(
            405,
            'Method Not Allowed. Allowed Methods are: ' . implode(', ', $route->methods),
            empty(getenv('PHPUNIT_TEST'))
        );
    }

    /**
     * Redirects the current request to another URL.
     *
     * @param int $code HTTP status code
     */
    public function _redirect(string $url, int $code = 303): void
    {
        $base = $this->get('flight.base_url');

        if ($base === null) {
            $base = $this->request()->base;
        }

        // Append base url to redirect url
        if ($base !== '/'   && strpos($url, '://') === false) {
            $url = $base . preg_replace('#/+#', '/', '/' . $url);
        }

        $this->triggerEvent('flight.redirect', $url, $code);

        $this->response()
            ->clearBody()
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
        if ($key !== null) {
            $this->view()->set($key, $this->view()->fetch($file, $data));
            return;
        }

        $start = microtime(true);
        $this->view()->render($file, $data);
        $this->triggerEvent('flight.view.rendered', $file, microtime(true) - $start);
    }

    /**
     * Sends a JSON response.
     *
     * @param mixed $data JSON data
     * @param int $code HTTP status code
     * @param bool $encode Whether to perform JSON encoding
     * @param ?string $charset Charset
     * @param int $option Bitmask Json constant such as JSON_HEX_QUOT
     *
     * @throws Exception
     */
    public function _json(
        $data,
        int $code = 200,
        bool $encode = true,
        ?string $charset = 'utf-8',
        int $option = 0
    ): void {
        $json = $encode ? Json::encode($data, $option) : $data;

        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/json')
            ->write($json);
    }

    /**
     * Sends a JSON response and halts execution immediately.
     *
     * @param mixed $data JSON data
     * @param int $code HTTP status code
     * @param bool $encode Whether to perform JSON encoding
     * @param string $charset Charset
     * @param int $option Bitmask Json constant such as JSON_HEX_QUOT
     *
     * @throws Exception
     */
    public function _jsonHalt(
        $data,
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf-8',
        int $option = 0
    ): void {
        $this->json($data, $code, $encode, $charset, $option);
        $jsonBody = $this->response()->getBody();
        $this->response()->clearBody();
        $this->response()->send();
        $this->halt($code, $jsonBody, empty(getenv('PHPUNIT_TEST')));
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
        $json = $encode ? Json::encode($data, $option) : $data;
        $callback = $this->request()->query[$param];

        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/javascript; charset=' . $charset)
            ->write($callback . '(' . $json . ');');
    }

    /**
     * Downloads a file
     *
     * @param string $filePath The path to the file to download
     * @param string $fileName The name the file should be downloaded as
     *
     * @throws Exception If the file cannot be found
     *
     * @return void
     */
    public function _download(string $filePath, string $fileName = ''): void
    {
        $this->response()->downloadFile($filePath, $fileName);
    }

    /**
     * Handles ETag HTTP caching.
     *
     * @param string $id ETag identifier
     * @param 'strong'|'weak' $type ETag type
     */
    public function _etag(string $id, string $type = 'strong'): void
    {
        $id = (($type === 'weak') ? 'W/' : '') . $id;

        $this->response()->header('ETag', '"' . str_replace('"', '\"', $id) . '"');

        $hit = isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $id;
        $this->triggerEvent('flight.cache.checked', 'etag', $hit, 0.0);

        if ($hit === true) {
            $this->response()->clear();
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
        $request = $this->request();
        $ifModifiedSince = $request->header('If-Modified-Since');

        $hit = isset($ifModifiedSince) && strtotime($ifModifiedSince) === $time;
        $this->triggerEvent('flight.cache.checked', 'lastModified', $hit, 0.0);

        if ($hit === true) {
            $this->response()->clear();
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

    /**
     * Adds an event listener.
     *
     * @param string $eventName The name of the event to listen to
     * @param callable $callback The callback to execute when the event is triggered
     */
    public function _onEvent(string $eventName, callable $callback): void
    {
        $this->eventDispatcher()->on($eventName, $callback);
    }

    /**
     * Triggers an event.
     *
     * @param string $eventName The name of the event to trigger
     * @param mixed ...$args The arguments to pass to the event listeners
     */
    public function _triggerEvent(string $eventName, ...$args): void
    {
        $this->eventDispatcher()->trigger($eventName, ...$args);
    }
}
