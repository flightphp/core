<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight\net;

use Exception;
use flight\net\Route;

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
     * @var array<int, Route>
     */
    protected array $routes = [];

    /**
     * Pointer to current route.
     */
    protected int $index = 0;

	/**
	 * When groups are used, this is mapped against all the routes
	 *
	 * @var string
	 */
	protected string $group_prefix = '';

    /**
     * Gets mapped routes.
     *
     * @return array<int,Route> Array of routes
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
     * @param callable $callback   Callback function
     * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $route_alias Alias for the route
	 * @return void
     */
    public function map(string $pattern, callable $callback, bool $pass_route = false, string $route_alias = ''): void
    {
        $url = trim($pattern);
        $methods = ['*'];

        if (false !== strpos($url, ' ')) {
            [$method, $url] = explode(' ', $url, 2);
            $url = trim($url);
            $methods = explode('|', $method);
        }

        $this->routes[] = new Route($this->group_prefix.$url, $callback, $methods, $pass_route, $route_alias);
    }

	/**
	 * Creates a GET based route
	 *
	 * @param string   $pattern    URL pattern to match
     * @param callable $callback   Callback function
     * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return void
	 */
	public function get(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): void {
		$this->map('GET ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Creates a POST based route
	 *
	 * @param string   $pattern    URL pattern to match
	 * @param callable $callback   Callback function
	 * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return void
	 */
	public function post(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): void {
		$this->map('POST ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Creates a PUT based route
	 *
	 * @param string   $pattern    URL pattern to match
	 * @param callable $callback   Callback function
	 * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return void
	 */
	public function put(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): void {
		$this->map('PUT ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Creates a PATCH based route
	 *
	 * @param string   $pattern    URL pattern to match
	 * @param callable $callback   Callback function
	 * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return void
	 */
	public function patch(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): void {
		$this->map('PATCH ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Creates a DELETE based route
	 *
	 * @param string   $pattern    URL pattern to match
	 * @param callable $callback   Callback function
	 * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return void
	 */
	public function delete(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): void {
		$this->map('DELETE ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Group together a set of routes
	 *
	 * @param string   $group_prefix group URL prefix (such as /api/v1)
	 * @param callable $callback     The necessary calling that holds the Router class
	 * @return void
	 */
	public function group(string $group_prefix, callable $callback): void {
		$old_group_prefix = $this->group_prefix;
		$this->group_prefix .= $group_prefix;
		$callback($this);
		$this->group_prefix = $old_group_prefix;
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
            if ($route->matchMethod($request->method) && $route->matchUrl($url_decoded, $this->case_sensitive)) {
                return $route;
            }
            $this->next();
        }

        return false;
    }

	/**
	 * Gets the URL for a given route alias
	 *
	 * @param string $alias  the alias to match
	 * @param array<string,mixed>  $params the parameters to pass to the route
	 * @return string
	 */
	public function getUrlByAlias(string $alias, array $params = []): string {
		while ($route = $this->current()) {
            if ($route->matchAlias($alias)) {
                return $route->hydrateUrl($params);
            }
            $this->next();
        }
		
		throw new Exception('No route found with alias: ' . $alias);
	}

	/**
	 * Rewinds the current route index.
	 */
	public function rewind(): void
	{
		$this->index = 0;
	}

	/**
	 * Checks if more routes can be iterated.
	 *
	 * @return bool More routes
	 */
	public function valid(): bool
	{
		return isset($this->routes[$this->index]);
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
