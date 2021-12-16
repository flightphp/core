<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight\net;

/**
 * The Router class is responsible for routing an HTTP request to
 * an assigned callback function. The Router tries to match the
 * requested URL against a series of URL patterns.
 */
class Router
{
    /**
     * Case sensitive matching.
     */
    public bool $case_sensitive = false;
    /**
     * Mapped routes.
     */
    protected array $routes = [];

    /**
     * Pointer to current route.
     */
    protected int $index = 0;

    /**
     * Gets mapped routes.
     *
     * @return array Array of routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Clears all routes in the router.
     */
    public function clear(): void
    {
        $this->routes = [];
    }

    /**
     * Maps a URL pattern to a callback function.
     *
     * @param string   $pattern    URL pattern to match
     * @param callback $callback   Callback function
     * @param bool     $pass_route Pass the matching route object to the callback
     */
    public function map(string $pattern, callable $callback, bool $pass_route = false): void
    {
        $url = trim($pattern);
        $methods = ['*'];

        if (false !== strpos($url, ' ')) {
            [$method, $url] = explode(' ', $url, 2);
            $url = trim($url);
            $methods = explode('|', $method);
        }

        $this->routes[] = new Route($url, $callback, $methods, $pass_route);
    }

    /**
     * Routes the current request.
     *
     * @param Request $request Request object
     *
     * @return bool|Route Matching route or false if no match
     */
    public function route(Request $request)
    {
        $url_decoded = urldecode($request->url);
        while ($route = $this->current()) {
            if (false !== $route && $route->matchMethod($request->method) && $route->matchUrl($url_decoded, $this->case_sensitive)) {
                return $route;
            }
            $this->next();
        }

        return false;
    }

    /**
     * Gets the current route.
     *
     * @return bool|Route
     */
    public function current()
    {
        return $this->routes[$this->index] ?? false;
    }

    /**
     * Gets the next route.
     */
    public function next(): void
    {
        $this->index++;
    }

    /**
     * Reset to the first route.
     */
    public function reset(): void
    {
        $this->index = 0;
    }
}
