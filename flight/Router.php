<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 * @version     0.1
 */
class Router {
    protected $routes = array();

    /**
     * Maps a URL pattern to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callback $callback Callback function
     */
    public function map($pattern, $callback) {
        list($method, $url) = explode(' ', trim($pattern), 2);

        if (!is_null($url)) {
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
     * @param object $request Request object
     */
    public function match($pattern, $url, array &$params = array()) {
        $ids = array();

        // Build the regex for matching
        $regex = '/^'.implode('\/', array_map(
            function($str) use (&$ids){
                if ($str == '*') {
                    $str = '(.*)';
                }
                else if (@$str{0} == '@') {
                    if (preg_match('/@(\w+)(\:([^\/]*))?/', $str, $matches)) {
                        $ids[$matches[1]] = true;
                        return '(?P<'.$matches[1].'>'.(isset($matches[3]) ? $matches[3] : '[^(\/|\?)]*').')';
                    }
                }
                return $str; 
            },
            explode('/', $pattern)
        )).'\/?(?:\?.*)?$/i';

        // Attempt to match route and named parameters
        if (preg_match($regex, $url, $matches)) {
            if (!empty($ids)) {
                $params = array_intersect_key($matches, $ids);
            }
            return true;
        }

        return false;
    }

    /**
     * Routes the current request.
     *
     * @param object $request Request object
     */
    public function route(&$request) {
        $params = array();
        $routes = ($this->routes[$request->method] ?: array()) + ($this->routes['*'] ?: array());

        foreach ($routes as $pattern => $callback) {
            if ($pattern === '*' || $request->url === $pattern || self::match($pattern, $request->url, $params)) {
                $request->matched = $pattern;
                return array($callback, array($params));
            }
        }

        return false;
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
     * Resets the router.
     */
    public function clear() {
        $this->routes = array();
    }
}
?>
