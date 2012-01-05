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
     * Tries to match a requst to a route. Also parses named parameters in the url.
     *
     * @param string $pattern URL pattern
     * @param string $url Requested URL
     */
    public function match($pattern, $url) {
        $ids = array();

        // Build the regex for matching
        $regex = '/^'.implode('\/', array_map(
            function($str) use (&$ids){
                if ($str == '*') {
                    $str = '(.*)';
                }
                else if ($str != null && $str{0} == '@') {
                    if (preg_match('/@(\w+)(\:([^\/]*))?/', $str, $matches)) {
                        $ids[$matches[1]] = true;
                        return '(?P<'.$matches[1].'>'.(isset($matches[3]) ? $matches[3] : '[^(\/|\?)]+').')';
                    }
                }
                return $str; 
            },
            explode('/', $pattern)
        )).'\/?(?:\?.*)?$/i';

        // Attempt to match route and named parameters
        if (preg_match($regex, $url, $matches)) {
            if (!empty($ids)) {
                $this->params = array_intersect_key($matches, $ids);
            }

            $this->matched = $pattern;

            return true;
        }

        return false;
    }

    /**
     * Routes the current request.
     *
     * @param object $request Request object
     * @return callable Matched callback function
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
