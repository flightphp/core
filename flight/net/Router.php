<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 * 
 * @author Shushant Kumar <shushantkumar786@gmail.com>
 */
namespace flight\net;

/**
 * The Router class is responsible for routing an HTTP request to
 * an assigned callback function.
 * The Router tries to match the
 * requested URL against a series of URL patterns.
 */
class Router
{

    /**
     * Mapped routes.
     *
     * @var array
     */
    protected $routes = array();

    /**
     * Pointer to current route
     *
     * @var int
     */
    protected $index = 0;

    /**
     * Default route to be used for prototype
     *
     * @var Route
     */
    protected $defRoute;

    function __construct()
    {
        /**
         * You can actually avoid `Tight coupling` the code because it makes your code hard to reuse
         * But it ok to use here because entire Framework uses `Singleton` design pattern
         * also you can use `Dependency Injection`.
         */
        $this->defRoute = new Route(0, 0, 0);
    }

    /**
     * Gets mapped routes.
     *
     * @return array Array of routes
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Clears all routes the router.
     */
    public function clear()
    {
        $this->routes = array();
    }

    /**
     * Maps a URL pattern to a callback function.
     *
     * @param string $pattern
     *            URL pattern to match
     * @param callback $callback
     *            Callback function
     */
    public function map($pattern, $callback)
    {
        /**
         * Why Cloning the object Here????
         *
         * reason behind cloning the object is to reduce the cost of instantiating objects by using cloning.
         * instead instantiating new objects from a class.
         */
        $route = clone $this->defRoute;
        
        if (strpos($pattern, ' ') !== false) {
            list ($method, $pattern) = explode(' ', trim($pattern), 2);
            $method = explode('|', $method);
        } else
            $method = '*';
        $route->pattern = $pattern;
        $route->callback = $callback;
        $route->methods = (array) $method;
        array_push($this->routes, $route);
    }

    /**
     * Routes the current request.
     *
     * @param Request $request
     *            Request object
     * @return callable boolean callback function or false if not found
     */
    public function route(Request $request)
    {
        while ($route = $this->current()) {
            if ($route !== false && $route->matchMethod($request->method) && $route->matchUrl($request->url)) {
                return $route;
            }
            $this->next();
        }
        
        return false;
    }

    /**
     * Gets the current route.
     *
     * @return Route
     */
    public function current()
    {
        return isset($this->routes[$this->index]) ? $this->routes[$this->index] : false;
    }

    /**
     * Gets the next route.
     *
     * @return Route
     */
    public function next()
    {
        $this->index ++;
    }

    /**
     * Reset to the first route.
     */
    public function reset()
    {
        $this->index = 0;
    }
}
