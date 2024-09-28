<?php

declare(strict_types=1);

use flight\Engine;
use flight\net\Request;
use flight\net\Response;
use flight\net\Router;
use flight\template\View;
use flight\net\Route;

require_once __DIR__ . '/autoload.php';

/**
 * The Flight class is a static representation of the framework.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 *
 * # Core methods
 * @method static void start() Starts the framework.
 * @method static void path(string $path) Adds a path for autoloading classes.
 * @method static void stop(?int $code = null) Stops the framework and sends a response.
 * @method static void halt(int $code = 200, string $message = '', bool $actuallyExit = true)
 * Stop the framework with an optional status code and message.
 * @method static void register(string $name, string $class, array $params = [], ?callable $callback = null)
 * Registers a class to a framework method.
 * @method static void unregister(string $methodName)
 * Unregisters a class to a framework method.
 * @method static void registerContainerHandler(callable|object $containerHandler) Registers a container handler.
 *
 * # Routing
 * @method static Route route(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Maps a URL pattern to a callback with all applicable methods.
 * @method static void group(string $pattern, callable $callback, callable[] $group_middlewares = [])
 * Groups a set of routes together under a common prefix.
 * @method static Route post(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Routes a POST URL to a callback function.
 * @method static Route put(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Routes a PUT URL to a callback function.
 * @method static Route patch(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Routes a PATCH URL to a callback function.
 * @method static Route delete(string $pattern, callable|string $callback, bool $pass_route = false, string $alias = '')
 * Routes a DELETE URL to a callback function.
 * @method static void resource(string $pattern, string $controllerClass, array $methods = [])
 * Adds standardized RESTful routes for a controller.
 * @method static Router router() Returns Router instance.
 * @method static string getUrl(string $alias, array<string, mixed> $params = []) Gets a url from an alias
 *
 * @method static void map(string $name, callable $callback) Creates a custom framework method.
 *
 * @method static void before(string $name, Closure(array<int, mixed> &$params, string &$output): (void|false) $callback)
 * Adds a filter before a framework method.
 * @method static void after(string $name, Closure(array<int, mixed> &$params, string &$output): (void|false) $callback)
 * Adds a filter after a framework method.
 *
 * @method static void set(string|iterable<string, mixed> $key, mixed $value) Sets a variable.
 * @method static mixed get(?string $key) Gets a variable.
 * @method static bool has(string $key) Checks if a variable is set.
 * @method static void clear(?string $key = null) Clears a variable.
 *
 * # Views
 * @method static void render(string $file, ?array<string, mixed> $data = null, ?string $key = null)
 * Renders a template file.
 * @method static View view() Returns View instance.
 *
 * # Request-Response
 * @method static Request request() Returns Request instance.
 * @method static Response response() Returns Response instance.
 * @method static void redirect(string $url, int $code = 303) Redirects to another URL.
 * @method static void json(mixed $data, int $code = 200, bool $encode = true, string $charset = "utf8", int $encodeOption = 0, int $encodeDepth = 512)
 * Sends a JSON response.
 * @method static void jsonHalt(mixed $data, int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0)
 * Sends a JSON response and immediately halts the request.
 * @method static void jsonp(mixed $data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = "utf8", int $encodeOption = 0, int $encodeDepth = 512)
 * Sends a JSONP response.
 * @method static void error(Throwable $exception) Sends an HTTP 500 response.
 * @method static void notFound() Sends an HTTP 404 response.
 *
 * # HTTP methods
 * @method static void etag(string $id, ('strong'|'weak') $type = 'strong') Performs ETag HTTP caching.
 * @method static void lastModified(int $time) Performs last modified HTTP caching.
 * @method static void download(string $filePath) Downloads a file
 */
class Flight
{
    /** Framework engine. */
    private static Engine $engine;

    /** Whether or not the app has been initialized. */
    private static bool $initialized = false;

    /**
     * Don't allow object instantiation
     *
     * @codeCoverageIgnore
     * @return void
     */
    private function __construct()
    {
    }

    /**
     * Forbid cloning the class
     *
     * @codeCoverageIgnore
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Handles calls to static methods.
     *
     * @param string $name Method name
     * @param array<int, mixed> $params Method parameters
     *
     * @return mixed Callback results
     * @throws Exception
     */
    public static function __callStatic(string $name, array $params)
    {
        return self::app()->{$name}(...$params);
    }

    /** @return Engine Application instance */
    public static function app(): Engine
    {
        if (!self::$initialized) {
            require_once __DIR__ . '/autoload.php';

            self::setEngine(new Engine());
            self::$initialized = true;
        }

        return self::$engine;
    }

    /**
     * Set the engine instance
     *
     * @param Engine $engine Vroom vroom!
     */
    public static function setEngine(Engine $engine): void
    {
        self::$engine = $engine;
    }
}
