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

/**
 * The Flight class is a static representation of the framework.
 *
 * @license MIT, https://docs.flightphp.com/license
 * @copyright Copyright (c) 2011-2026,
 * Mike Cao <mike@mikecao.com>, n0nag0n <n0nag0n@sky-9.com>, fadrian06 <https://github.com/fadrian06>
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class Flight
{
    private static Engine $engine;

    /**
     * @param array<int, mixed> $arguments
     * @return mixed
     * @throws Throwable
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return self::app()->{$name}(...$arguments);
    }

    public static function app(): Engine
    {
        return self::$engine ?? self::$engine = new Engine();
    }

    public static function setEngine(Engine $engine): void
    {
        self::$engine = $engine;
    }

    public static function start(): void
    {
        self::app()->start();
    }

    public static function stop(?int $code = null): void
    {
        self::app()->stop($code);
    }

    public static function halt(int $code = 200, string $message = '', bool $actuallyExit = true): void
    {
        self::app()->halt($code, $message, $actuallyExit);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param mixed[] $params
     * @param ?callable(T): void $callback
     */
    public static function register(string $name, string $class, array $params = [], ?callable $callback = null): void
    {
        self::app()->register($name, $class, $params, $callback);
    }

    public static function unregister(string $methodName): void
    {
        self::app()->unregister($methodName);
    }

    /**
     * @template T of object
     * @param ContainerInterface|callable(class-string<T>, mixed[]): ?T $containerHandler
     */
    public static function registerContainerHandler($containerHandler): void
    {
        self::app()->registerContainerHandler($containerHandler);
    }

    public static function eventDispatcher(): EventDispatcher
    {
        return self::app()->eventDispatcher();
    }

    /** @param callable|string|array{0: class-string, 1: string} $callback */
    public static function route(
        string $pattern,
        $callback,
        bool $pass_route = false,
        string $alias = ''
    ): Route {
        return self::app()->route($pattern, $callback, $pass_route, $alias);
    }

    /** @param array<int, class-string|callable|array{0: class-string, 1: string}> $group_middlewares */
    public static function group(string $pattern, callable $callback, array $group_middlewares = []): void
    {
        self::app()->group($pattern, $callback, $group_middlewares);
    }

    /** @param callable|string|array{0: class-string, 1: string} $callback */
    public static function post(
        string $pattern,
        $callback,
        bool $pass_route = false,
        string $alias = ''
    ): Route {
        return self::app()->post($pattern, $callback, $pass_route, $alias);
    }

    /** @param callable|string|array{0: class-string, 1: string} $callback */
    public static function put(
        string $pattern,
        $callback,
        bool $pass_route = false,
        string $alias = ''
    ): Route {
        return self::app()->put($pattern, $callback, $pass_route, $alias);
    }

    /** @param callable|string|array{0: class-string, 1: string} $callback */
    public static function patch(
        string $pattern,
        $callback,
        bool $pass_route = false,
        string $alias = ''
    ): Route {
        return self::app()->patch($pattern, $callback, $pass_route, $alias);
    }

    /** @param callable|string|array{0: class-string, 1: string} $callback */
    public static function delete(
        string $pattern,
        $callback,
        bool $pass_route = false,
        string $alias = ''
    ): Route {
        return self::app()->delete($pattern, $callback, $pass_route, $alias);
    }

    /**
     * @param class-string $controllerClass
     * @param array<string, string|array<int, string>> $methods
     */
    public static function resource(string $pattern, string $controllerClass, array $methods = []): void
    {
        self::app()->resource($pattern, $controllerClass, $methods);
    }

    public static function router(): Router
    {
        return self::app()->router();
    }

    /** @param array<string, mixed> $params */
    public static function getUrl(string $alias, array $params = []): string
    {
        return self::app()->getUrl($alias, $params);
    }

    public static function map(string $name, callable $callback): void
    {
        self::app()->map($name, $callback);
    }

    /** @param callable(array<int, mixed> &$params, string &$output): (void|false) $callback */
    public static function before(string $name, callable $callback): void
    {
        self::app()->before($name, $callback);
    }

    /** @param callable(array<int, mixed> &$params, string &$output): (void|false) $callback */
    public static function after(string $name, callable $callback): void
    {
        self::app()->after($name, $callback);
    }

    /**
     * @param string|iterable<string, mixed> $key
     * @param mixed $value
     */
    public static function set($key, $value): void
    {
        self::app()->set($key, $value);
    }

    /** @return mixed */
    public static function get(?string $key = null)
    {
        return self::app()->get($key);
    }

    public static function has(string $key): bool
    {
        return self::app()->has($key);
    }

    public static function clear(?string $key = null): void
    {
        self::app()->clear($key);
    }

    /** @param ?array<string, mixed> $data */
    public static function render(string $file, ?array $data = null, ?string $key = null): void
    {
        self::app()->render($file, $data, $key);
    }

    public static function view(): View
    {
        return self::app()->view();
    }

    public static function onEvent(string $event, callable $callback): void
    {
        self::app()->onEvent($event, $callback);
    }

    /** @param mixed ...$args */
    public static function triggerEvent(string $event, ...$args): void
    {
        self::app()->triggerEvent($event, ...$args);
    }

    public static function request(): Request
    {
        return self::app()->request();
    }

    public static function response(): Response
    {
        return self::app()->response();
    }

    public static function redirect(string $url, int $code = 303): void
    {
        self::app()->redirect($url, $code);
    }

    /** @param mixed $data */
    public static function json(
        $data,
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf8',
        int $encodeOption = 0
    ): void {
        self::app()->json($data, $code, $encode, $charset, $encodeOption);
    }

    /** @param mixed $data */
    public static function jsonHalt(
        $data,
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf8',
        int $encodeOption = 0
    ): void {
        self::app()->jsonHalt($data, $code, $encode, $charset, $encodeOption);
    }

    /** @param mixed $data */
    public static function jsonp(
        $data,
        string $param = 'jsonp',
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf8',
        int $encodeOption = 0
    ): void {
        self::app()->jsonp($data, $param, $code, $encode, $charset, $encodeOption);
    }

    public static function error(Throwable $exception): void
    {
        self::app()->error($exception);
    }

    public static function notFound(): void
    {
        self::app()->notFound();
    }

    public function methodNotFound(Route $route): void
    {
        self::app()->methodNotFound($route);
    }

    public static function etag(string $id, string $type = 'strong'): void
    {
        self::app()->etag($id, $type);
    }

    public static function lastModified(int $time): void
    {
        self::app()->lastModified($time);
    }

    public static function download(string $filePath): void
    {
        self::app()->download($filePath);
    }
}
