<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */

namespace flight\net;

/**
 * The Request class represents an HTTP request. Data from
 * all the super globals $_GET, $_POST, $_COOKIE, and $_FILES
 * are stored and accessible via the Request object.
 *
 * The default request properties are:
 *   url - The URL being requested
 *   base - The parent subdirectory of the URL
 *   method - The request method (GET, POST, PUT, DELETE)
 *   referrer - The referrer URL
 *   ip - IP address of the client
 *   ajax - Whether the request is an AJAX request
 *   scheme - The server protocol (http, https)
 *   user_agent - Browser information
 *   body - Raw data from the request body
 *   type - The content type
 *   length - The content length
 *   query - Query string parameters
 *   data - Post parameters 
 *   cookies - Cookie parameters
 *   files - Uploaded files
 *   params - Matched URL parameters from the router
 *   matched - Matched URL patter from the router
 */
class Request {
    /**
     * Constructor.
     *
     * @param array $config Request configuration
     */
    public function __construct($config = array()) {
        // Default properties
        if (empty($config)) {
            $config = array(
                'url' => $_SERVER['REQUEST_URI'],
                'base' => str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])),
                'method' => $_SERVER['REQUEST_METHOD'],
                'referrer' => $_SERVER['HTTP_REFERER'],
                'ip' => $_SERVER['REMOTE_ADDR'],
                'ajax' => ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'),
                'scheme' => $_SERVER['SERVER_PROTOCOL'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'body' => file_get_contents('php://input'),
                'type' => $_SERVER['CONTENT_TYPE'],
                'length' => $_SERVER['CONTENT_LENGTH'],
                'query' => (object)$_GET,
                'data' => (object)$_POST,
                'cookies' => (object)$_COOKIE,
                'files' => (object)$_FILES,
                'params' => array(),
                'matched' => null
            );
        }

        $this->init($config);
    }

    /**
     * Initialize request properties.
     *
     * @param array $properties Array of request properties
     */
    public function init($properties) {
        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }

        if ($this->base != '/' && strpos($this->url, $this->base) === 0) {
            $this->url = substr($this->url, strlen($this->base));
        }

        if (empty($this->url)) {
            $this->url = '/';
        }
        else {
            $query = self::parseQuery($this->url);
            $this->query = (object)$query;
            $_GET = $query;
        }
    }

    /**
     * Parse query parameters from a URL.
     *
     * @param string $url URL string
     * @return array Query parameters
     */
    public static function parseQuery($url) {
        $params = array();

        $args = parse_url($url);
        if (isset($args['query'])) {
            parse_str($args['query'], $params);
        }

        return $params;
    }
}
?>
