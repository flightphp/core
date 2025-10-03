<?php

declare(strict_types=1);

use flight\Engine;
use flight\net\Request;
use flight\net\Response;
use flight\net\Router;
use flight\template\View;
use flight\net\Route;
use flight\core\EventDispatcher;
use Psr\Container\ContainerInterface;

require_once __DIR__ . '/autoload.php';

/**
 * The Flight class is a static representation of the framework.
 *
 * @license MIT, https://docs.flightphp.com/license
 * @copyright Copyright (c) 2011-2025, Mike Cao <mike@mikecao.com>, n0nag0n <n0nag0n@sky-9.com>
 *
 * @method static void start()
 * @method static void path(string $dir)
 * @method static void stop(int $code = null)
 * @method static void halt(int $code = 200, string $message = '', bool $actuallyExit = true)
 * @method static void register(string $name, string $class, array $params = [], callable $callback = null)
 * @method static void unregister(string $methodName)
 * @method static void registerContainerHandler($containerHandler)
 * @method static EventDispatcher eventDispatcher()
 * @method static Route route(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * @method static void group(string $pattern, callable $callback, array $group_middlewares = [])
 * @method static Route post(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * @method static Route put(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * @method static Route patch(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * @method static Route delete(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * @method static void resource(string $pattern, string $controllerClass, array $methods = [])
 * @method static Router router()
 * @method static string getUrl(string $alias, array $params = [])
 * @method static void map(string $name, callable $callback)
 * @method static void before(string $name, Closure $callback)
 * @method static void after(string $name, Closure $callback)
 * @method static void set($key, $value)
 * @method static mixed get($key = null)
 * @method static bool has(string $key)
 * @method static void clear($key = null)
 * @method static void render(string $file, array $data = null, string $key = null)
 * @method static View view()
 * @method void onEvent(string $event, callable $callback)
 * @method void triggerEvent(string $event, ...$args)
 * @method static Request request()
 * @method static Response response()
 * @method static void redirect(string $url, int $code = 303)
 * @method static void json($data, int $code = 200, bool $encode = true, string $charset = "utf8", int $encodeOption = 0, int $encodeDepth = 512)
 * @method static void jsonHalt($data, int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0)
 * @method static void jsonp($data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = "utf8", int $encodeOption = 0, int $encodeDepth = 512)
 * @method static void error(Throwable $exception)
 * @method static void notFound()
 * @method static void methodNotFound(Route $route)
 * @method static void etag(string $id, string $type = 'strong')
 * @method static void lastModified(int $time)
 * @method static void download(string $filePath)
 *
 * @phpstan-template FlightTemplate of object
 * @phpstan-method static void register(string $name, class-string<FlightTemplate> $class, array<int|string, mixed> $params = [], (callable(class-string<FlightTemplate> $class, array<int|string, mixed> $params): void)|null $callback = null)
 * @phpstan-method static void registerContainerHandler(ContainerInterface|callable(class-string<FlightTemplate> $id, array<int|string, mixed> $params): ?FlightTemplate $containerHandler)
 * @phpstan-method static Route route(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @phpstan-method static void group(string $pattern, callable $callback, (class-string|callable|array{0: class-string, 1: string})[] $group_middlewares = [])
 * @phpstan-method static Route post(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @phpstan-method static Route put(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @phpstan-method static Route patch(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @phpstan-method static Route delete(string $pattern, callable|string|array{0: class-string, 1: string} $callback, bool $pass_route = false, string $alias = '')
 * @phpstan-method static void resource(string $pattern, class-string $controllerClass, array<string, string|array<string>> $methods = [])
 * @phpstan-method static string getUrl(string $alias, array<string, mixed> $params = [])
 * @phpstan-method static void before(string $name, Closure(array<int, mixed> &$params, string &$output): (void|false) $callback)
 * @phpstan-method static void after(string $name, Closure(array<int, mixed> &$params, string &$output): (void|false) $callback)
 * @phpstan-method static void set(string|iterable<string, mixed> $key, mixed $value)
 * @phpstan-method static mixed get(?string $key)
 * @phpstan-method static void render(string $file, ?array<string, mixed> $data = null, ?string $key = null)
 * @phpstan-method static void json(mixed $data, int $code = 200, bool $encode = true, string $charset = "utf8", int $encodeOption = 0, int $encodeDepth = 512)
 * @phpstan-method static void jsonHalt(mixed $data, int $code = 200, bool $encode = true, string $charset = 'utf-8', int $option = 0)
 * @phpstan-method static void jsonp(mixed $data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = "utf8", int $encodeOption = 0, int $encodeDepth = 512)
 *
 * Note: IDEs will use standard @method tags for autocompletion, while PHPStan will use @phpstan-* tags for advanced type checking.
 */
class Flight
{
    /**
     * @var Engine<FlightTemplate>
     */
    private static Engine $engine;

    /**
     * Don't allow object instantiation
     *
     * @codeCoverageIgnore
     * @return void
     */
    private function __construct()
    {
        //
    }

    /**
     * Forbid cloning the class
     *
     * @codeCoverageIgnore
     * @return void
     */
    private function __clone()
    {
        //
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

    /** @return Engine<FlightTemplate> Application instance */
    public static function app(): Engine
    {
        return self::$engine ?? self::$engine = new Engine();
    }

    /**
     * Set the engine instance
     *
     * @param Engine<FlightTemplate> $engine Vroom vroom!
     */
    public static function setEngine(Engine $engine): void
    {
        self::$engine = $engine;
    }
}
