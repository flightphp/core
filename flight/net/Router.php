<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2024, Mike Cao <mike@mikecao.com>
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
     * @var bool Case sensitive matching.
     */
    public $case_sensitive = false;
    /**
     * Mapped routes.
     * @var array<int,Route>
     */
    protected $routes = [];

    /**
     * @var int Pointer to current route.
     */
    protected $index = 0;

	/**
	 * When groups are used, this is mapped against all the routes
	 *
	 * @var string
	 */
	protected $group_prefix = '';

	/**
	 * Group Middleware
	 *
	 * @var array<int,mixed>
	 */
	protected $group_middlewares = [];

    /**
     * Gets mapped routes.
     *
     * @return array<int,Route> Array of routes
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Clears all routes in the router.
     *
     * @return void
     */
    public function clear()
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
	 * @return Route
     */
    public function map($pattern, $callback, $pass_route = false, $route_alias = '')
    {
        $url = trim($pattern);
        $methods = ['*'];

        if (false !== strpos($url, ' ')) {
            [$method, $url] = explode(' ', $url, 2);
            $url = trim($url);
            $methods = explode('|', $method);
        }

		$route = new Route($this->group_prefix.$url, $callback, $methods, $pass_route, $route_alias);

		// to handle group middleware
		foreach($this->group_middlewares as $gm) {
			$route->addMiddleware($gm);
		}

        $this->routes[] = $route;

		return $route;
    }

	/**
	 * Creates a GET based route
	 *
	 * @param string   $pattern    URL pattern to match
     * @param callable $callback   Callback function
     * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return Route
	 */
	public function get($pattern, $callback, $pass_route = false, $alias = '') {
		return $this->map('GET ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Creates a POST based route
	 *
	 * @param string   $pattern    URL pattern to match
	 * @param callable $callback   Callback function
	 * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return Route
	 */
	public function post($pattern, $callback, $pass_route = false, $alias = '') {
		return $this->map('POST ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Creates a PUT based route
	 *
	 * @param string   $pattern    URL pattern to match
	 * @param callable $callback   Callback function
	 * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return Route
	 */
	public function put($pattern, $callback, $pass_route = false, $alias = '') {
		return $this->map('PUT ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Creates a PATCH based route
	 *
	 * @param string   $pattern    URL pattern to match
	 * @param callable $callback   Callback function
	 * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return Route
	 */
	public function patch($pattern, $callback, $pass_route = false, $alias = '') {
		return $this->map('PATCH ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Creates a DELETE based route
	 *
	 * @param string   $pattern    URL pattern to match
	 * @param callable $callback   Callback function
	 * @param bool     $pass_route Pass the matching route object to the callback
	 * @param string   $alias 	   Alias for the route
	 * @return Route
	 */
	public function delete($pattern, $callback, $pass_route = false, $alias = '') {
		return $this->map('DELETE ' . $pattern, $callback, $pass_route, $alias);
	}

	/**
	 * Group together a set of routes
	 *
	 * @param string   $group_prefix group URL prefix (such as /api/v1)
	 * @param callable $callback     The necessary calling that holds the Router class
	 * @param array<int,callable|object>  $group_middlewares The middlewares to be applied to the group Ex: [ $middleware1, $middleware2 ]
	 * @return void
	 */
	public function group($group_prefix, $callback, $group_middlewares = []) {
		$old_group_prefix = $this->group_prefix;
		$old_group_middlewares = $this->group_middlewares;
		$this->group_prefix .= $group_prefix;
		$this->group_middlewares = array_merge($this->group_middlewares, $group_middlewares);
		$callback($this);
		$this->group_prefix = $old_group_prefix;
		$this->group_middlewares = $old_group_middlewares;
	}

    /**
     * Routes the current request.
     *
     * @param Request $request Request object
     *
     * @return bool|Route Matching route or false if no match
     */
    public function route($request)
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
	public function getUrlByAlias($alias, $params = []) {
		$potential_aliases = [];
		foreach($this->routes as $route) {
			$potential_aliases[] = $route->alias;
			if ($route->matchAlias($alias)) {
				return $route->hydrateUrl($params);
			}

		}

		// use a levenshtein to find the closest match and make a recommendation
		$closest_match = '';
		$closest_match_distance = 0;
		foreach($potential_aliases as $potential_alias) {
			$levenshtein_distance = levenshtein($alias, $potential_alias);
			if($levenshtein_distance > $closest_match_distance) {
				$closest_match = $potential_alias;
				$closest_match_distance = $levenshtein_distance;
			}
		}

		$exception_message = 'No route found with alias: \'' . $alias . '\'.';
		if($closest_match !== '') {
			$exception_message .= ' Did you mean \'' . $closest_match . '\'?';
		}
		
		throw new Exception($exception_message);
	}

	/**
	 * Rewinds the current route index.
     *
     * @return void
	 */
	public function rewind()
	{
		$this->index = 0;
	}

	/**
	 * Checks if more routes can be iterated.
	 *
	 * @return bool More routes
	 */
	public function valid()
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
     *
     * @return void
     */
    public function next()
    {
        $this->index++;
    }

    /**
     * Reset to the first route.
     *
     * @return void
     */
    public function reset()
    {
        $this->index = 0;
    }
}
