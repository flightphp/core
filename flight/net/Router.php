<?php
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
class Router {
    /**
     * Mapped routes.
     *
     * @var array
     */
    protected $routes;

    /**
     * Pointer to current route.
     *
     * @var int
     */
    protected $index;

    /**
     * Case sensitive matching.
     *
     * @var boolean
     */
    public $case_sensitive;

    public function __construct() {
        $this->routes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'PATCH' => [],
            'DELETE' => [],
            'OPTIONS' => [],
            'HEAD' => []
        ];
        $this->index = 0;
        $this->case_sensitive = false;
    }

    /**
     * Gets mapped routes.
     *
     * @return array Array of routes
     */
    public function getRoutes() {
        return $this->routes;
    }

    /**
     * Clears all routes in the router.
     */
    public function clear() {
        $this->routes = array();
    }

    /**
     * Maps a URL pattern to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callback $callback Callback function
     * @param boolean $pass_route Pass the matching route object to the callback
     */
    public function map($pattern, $callback, array $config = []) {
        $url = $pattern;
        $methods = [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD' ];

        if (strpos($pattern, ' ') !== false) {
            list($method, $url) = explode(' ', trim($pattern), 2);
            $url = trim($url);
            $methods = explode('|', $method);
        }

        $route = new Route($url, $callback, $methods, $config);
        foreach( $methods as $method ) {
            $this->routes[ $method ][] = $route;
        }
    }

    /**
     * Routes the current request.
     *
     * @param Request $request Request object
     * @return Route|bool Matching route or false if no match
     */
    public function route(Request $request) {
        $url_decoded = urldecode( $request->url );

        $bucket = $this->routes[ $request->method ];

        while( $this->index < count( $bucket ) ) {
            $route = $bucket[ $this->index ];
            $this->index++;
            if ( $route->matchUrl($url_decoded, $this->case_sensitive) ) {
                return $route;
            }
        }

        return false;
    }

    public function reset() {
        $this->index = 0;
    }

}
