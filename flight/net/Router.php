<?php

declare(strict_types=1);

namespace flight\net;

use Exception;
use flight\net\Route;

/**
 * The Router class is responsible for routing an HTTP request to
 * an assigned callback function. The Router tries to match the
 * requested URL against a series of URL patterns.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Router
{
    /**
     * Case sensitive matching.
     */
    public bool $caseSensitive = false;

    /**
     * Mapped routes.
     *
     * @var array<int,Route> $routes
     */
    protected array $routes = [];

    /**
     * The current route that is has been found and executed.
     */
    public ?Route $executedRoute = null;

    /**
     * Pointer to current route.
     */
    protected int $index = 0;

    /**
     * When groups are used, this is mapped against all the routes
     */
    protected string $groupPrefix = '';

    /**
     * Group Middleware
     *
     * @var array<int,mixed>
     */
    protected array $groupMiddlewares = [];

    /**
     * Allowed HTTP methods
     *
     * @var array<int, string>
     */
    protected array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

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
     * @param string $pattern URL pattern to match.
     * @param callable|string $callback Callback function or string class->method
     * @param bool $pass_route Pass the matching route object to the callback.
     * @param string $route_alias Alias for the route.
     */
    public function map(string $pattern, $callback, bool $pass_route = false, string $route_alias = ''): Route
    {

        // This means that the route is defined in a group, but the defined route is the base
        // url path. Note the '' in route()
        // Ex: Flight::group('/api', function() {
        //    Flight::route('', function() {});
        // }
        // Keep the space so that it can execute the below code normally
        if ($this->groupPrefix !== '') {
            $url = ltrim($pattern);
        } else {
            $url = trim($pattern);
        }

        $methods = ['*'];

        if (strpos($url, ' ') !== false) {
            [$method, $url] = explode(' ', $url, 2);
            $url = trim($url);
            $methods = explode('|', $method);

            // Add head requests to get methods, should they come in as a get request
            if (in_array('GET', $methods, true) === true && in_array('HEAD', $methods, true) === false) {
                $methods[] = 'HEAD';
            }
        }

        // And this finishes it off.
        if ($this->groupPrefix !== '') {
            $url = rtrim($this->groupPrefix . $url);
        }

        $route = new Route($url, $callback, $methods, $pass_route, $route_alias);

        // to handle group middleware
        foreach ($this->groupMiddlewares as $gm) {
            $route->addMiddleware($gm);
        }

        $this->routes[] = $route;

        return $route;
    }

    /**
     * Creates a GET based route
     *
     * @param string   $pattern    URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool     $pass_route Pass the matching route object to the callback
     * @param string   $alias      Alias for the route
     */
    public function get(string $pattern, $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('GET ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * Creates a POST based route
     *
     * @param string   $pattern    URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool     $pass_route Pass the matching route object to the callback
     * @param string   $alias      Alias for the route
     */
    public function post(string $pattern, $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('POST ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * Creates a PUT based route
     *
     * @param string   $pattern    URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool     $pass_route Pass the matching route object to the callback
     * @param string   $alias      Alias for the route
     */
    public function put(string $pattern, $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('PUT ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * Creates a PATCH based route
     *
     * @param string   $pattern    URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool     $pass_route Pass the matching route object to the callback
     * @param string   $alias      Alias for the route
     */
    public function patch(string $pattern, $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('PATCH ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * Creates a DELETE based route
     *
     * @param string   $pattern    URL pattern to match
     * @param callable|string $callback Callback function or string class->method
     * @param bool     $pass_route Pass the matching route object to the callback
     * @param string   $alias      Alias for the route
     */
    public function delete(string $pattern, $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('DELETE ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * Group together a set of routes
     *
     * @param string $groupPrefix group URL prefix (such as /api/v1)
     * @param callable $callback The necessary calling that holds the Router class
     * @param array<int, callable|object> $groupMiddlewares
     * The middlewares to be applied to the group. Example: `[$middleware1, $middleware2]`
     */
    public function group(string $groupPrefix, callable $callback, array $groupMiddlewares = []): void
    {
        $oldGroupPrefix = $this->groupPrefix;
        $oldGroupMiddlewares = $this->groupMiddlewares;
        $this->groupPrefix .= $groupPrefix;
        $this->groupMiddlewares = array_merge($this->groupMiddlewares, $groupMiddlewares);
        $callback($this);
        $this->groupPrefix = $oldGroupPrefix;
        $this->groupMiddlewares = $oldGroupMiddlewares;
    }

    /**
     * Routes the current request.
     *
     * @return false|Route Matching route or false if no match
     */
    public function route(Request $request)
    {
        while ($route = $this->current()) {
            $urlMatches = $route->matchUrl($request->url, $this->caseSensitive);
            $methodMatches = $route->matchMethod($request->method);
            if ($urlMatches === true && $methodMatches === true) {
                $this->executedRoute = $route;
                return $route;
            // capture the route but don't execute it. We'll use this in Engine->start() to throw a 405
            } elseif ($urlMatches === true && $methodMatches === false) {
                $this->executedRoute = $route;
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
     */
    public function getUrlByAlias(string $alias, array $params = []): string
    {
        $potential_aliases = [];
        foreach ($this->routes as $route) {
            $potential_aliases[] = $route->alias;
            if ($route->matchAlias($alias)) {
                // This will make it so the params that already
                // exist in the url will be passed in.
                if (!empty($this->executedRoute->params)) {
                    $params = $params + $this->executedRoute->params;
                }
                return $route->hydrateUrl($params);
            }
        }

        // use a levenshtein to find the closest match and make a recommendation
        $closest_match = '';
        $closest_match_distance = 0;
        foreach ($potential_aliases as $potential_alias) {
            $levenshtein_distance = levenshtein($alias, $potential_alias);
            if ($levenshtein_distance > $closest_match_distance) {
                $closest_match = $potential_alias;
                $closest_match_distance = $levenshtein_distance;
            }
        }

        $exception_message = 'No route found with alias: \'' . $alias . '\'.';
        if ($closest_match !== '') {
            $exception_message .= ' Did you mean \'' . $closest_match . '\'?';
        }

        throw new Exception($exception_message);
    }

    /**
     * Create a resource controller customizing the methods names mapping.
     *
     * @param class-string $controllerClass
     * @param array<string, string|array<string>> $options
     */
    public function mapResource(
        string $pattern,
        string $controllerClass,
        array $options = []
    ): void {

        $defaultMapping = [
            'index' => 'GET ',
            'create' => 'GET /create',
            'store' => 'POST ',
            'show' => 'GET /@id',
            'edit' => 'GET /@id/edit',
            'update' => 'PUT /@id',
            'destroy' => 'DELETE /@id'
        ];

        // Create a custom alias base
        $aliasBase = trim(basename($pattern), '/');
        if (isset($options['alias_base']) === true) {
            $aliasBase = $options['alias_base'];
        }

        // Only use these controller methods
        if (isset($options['only']) === true) {
            $only = $options['only'];
            $defaultMapping = array_filter($defaultMapping, function ($key) use ($only) {
                return in_array($key, $only, true) === true;
            }, ARRAY_FILTER_USE_KEY);

        // Exclude these controller methods
        } elseif (isset($options['except']) === true) {
            $except = $options['except'];
            $defaultMapping = array_filter($defaultMapping, function ($key) use ($except) {
                return in_array($key, $except, true) === false;
            }, ARRAY_FILTER_USE_KEY);
        }

        // Add group middleware
        $middleware = [];
        if (isset($options['middleware']) === true) {
            $middleware = $options['middleware'];
        }

        $this->group(
            $pattern,
            function (Router $router) use ($controllerClass, $defaultMapping, $aliasBase): void {
                foreach ($defaultMapping as $controllerMethod => $methodPattern) {
                    $router->map(
                        $methodPattern,
                        [ $controllerClass, $controllerMethod ]
                    )->setAlias($aliasBase . '.' . $controllerMethod);
                }
            },
            $middleware
        );
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
     * @return false|Route
     */
    public function current()
    {
        return $this->routes[$this->index] ?? false;
    }

    /**
     * Gets the previous route.
     */
    public function previous(): void
    {
        --$this->index;
    }

    /**
     * Gets the next route.
     */
    public function next(): void
    {
        ++$this->index;
    }

    /**
     * Reset to the first route.
     */
    public function reset(): void
    {
        $this->rewind();
    }
}
