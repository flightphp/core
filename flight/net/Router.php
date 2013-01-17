<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
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
    protected $routes = array();

    /**
     * Matched route.
     *
     * @var string
     */
    public $matched = null;

    /**
     * Matched URL parameters.
     *
     * @var array
     */
    public $params = array();

    /**
     * Gets mapped routes.
     *
     * @return array Array of routes
     */
    public function getRoutes() {
        return $this->routes;
    }

    /**
     * Resets the router.
     */
    public function clear() {
        $this->routes = array();
    }

    /**
     * Maps a URL pattern to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callback $callback Callback function
     */
    public function map($pattern, $callback) {
        if (strpos($pattern, ' ') !== false) {
            list($method, $url) = explode(' ', trim($pattern), 2);

            foreach (explode('|', $method) as $value) {
                $this->routes[$value][$url] = $callback;
            }
        }
        else {
            $this->routes['*'][$pattern] = $callback;
        }
    }

    /**
     * Tries to match a request to a route. Also parses named parameters in the url.
     *
     * @param string $pattern URL pattern
     * @param string $url Requested URL
     * @return boolean Match status
     */
    public function match($pattern, $url) {
        $ids = array();

        // Build the regex for matching
        $regex = preg_replace_callback(
            '#@([\w]+)(:([^/\(\)]*))?#',
            function($matches) use (&$ids) {
                $ids[$matches[1]] = null;
                if (isset($matches[3])) {
                    return '(?P<'.$matches[1].'>'.$matches[3].')';
                }
                return '(?P<'.$matches[1].'>[^/\?]+)';
            },
            $pattern
        );

        // Allow trailing slash
        if (substr($pattern, -1) === '/') {
            $regex .= '?';
        }
        else {
            $regex .= '/?';
        }

        // Replace wildcard
        if (substr($pattern, -1) === '*') {
            $regex = str_replace('*', '.+?', $pattern);
        }

        // Convert optional parameters
        $regex = str_replace(')', ')?', $regex);

        // Attempt to match route and named parameters
        if (preg_match('#^'.$regex.'(?:\?.*)?$#i', $url, $matches)) {
            foreach ($ids as $k => $v) {
                $this->params[$k] = (array_key_exists($k, $matches)) ? urldecode($matches[$k]) : null;
            }

            $this->matched = $pattern;

            return true;
        }

        return false;
    }

    /**
     * Routes the current request.
     *
     * @param Request $request Request object
     * @return callable|boolean Matched callback function or false if not found
     */
    public function route(Request $request) {
        $this->matched = null;
        $this->params = array();

        $routes = isset($this->routes[$request->method]) ? $this->routes[$request->method] : array();
        if (isset($this->routes['*'])) $routes += $this->routes['*'];

        foreach ($routes as $pattern => $callback) {
            if ($pattern === '*' || $request->url === $pattern || self::match($pattern, $request->url)) {
                return $callback;
            }
        }

        return false;
    }
}
?>
