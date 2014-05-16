<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight\net;

use flight\util\Collection;

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
 */
class Request {
    /**
     * @var string URL being requested
     */
    public $url;

    /**
     * @var string Parent subdirectory of the URL
     */
    public $base;

    /**
     * @var string Request method (GET, POST, PUT, DELETE)
     */
    public $method;

    /**
     * @var string Referrer URL
     */
    public $referrer;

    /**
     * @var string IP address of the client
     */
    public $ip;

    /**
     * @var bool Whether the request is an AJAX request
     */
    public $ajax;

    /**
     * @var string Server protocol (http, https)
     */
    public $scheme;

    /**
     * @var string Browser information
     */
    public $user_agent;

    /**
     * @var mixed Raw data from the request body
     */
    public $body;

    /**
     * @var string Content type
     */
    public $type;

    /**
     * @var int Content length
     */
    public $length;

    /**
     * @var \flight\util\Collection Query string parameters
     */
    public $query;

    /**
     * @var \flight\util\Collection Post parameters
     */
    public $data;

    /**
     * @var \flight\util\Collection Cookie parameters
     */
    public $cookies;

    /**
     * @var \flight\util\Collection Uploaded files
     */
    public $files;

    /**
     * @var \flight\util\Collection Merged GET, POST, PUT and DELETE parameters
     */
    public $params;

    /**
     * @var bool Whether the connection is secure
     */
    public $secure;

    /**
     * @var string HTTP accept parameters
     */
    public $accept;

    /**
     * @var string Proxy IP address of the client
     */
    public $proxy_ip;

    /**
     * Constructor.
     *
     * @param array $config Request configuration
     */
    public function __construct($config = array()) {
        // Default properties
        if (empty($config)) {
            $config = array(
                'url' => $this->getVar('REQUEST_URI', '/'),
                'base' => str_replace(array('\\',' '), array('/','%20'), dirname($this->getVar('SCRIPT_NAME'))),
                'method' => $this->getMethod(),
                'referrer' => $this->getVar('HTTP_REFERER'),
                'ip' => $this->getVar('REMOTE_ADDR'),
                'ajax' => $this->getVar('HTTP_X_REQUESTED_WITH') == 'XMLHttpRequest',
                'scheme' => $this->getVar('SERVER_PROTOCOL', 'HTTP/1.1'),
                'user_agent' => $this->getVar('HTTP_USER_AGENT'),
                'body' => file_get_contents('php://input'),
                'type' => $this->getVar('CONTENT_TYPE'),
                'length' => $this->getVar('CONTENT_LENGTH', 0),
                'query' => new Collection($_GET),
                'data' => new Collection($_POST),
                'cookies' => new Collection($_COOKIE),
                'files' => new Collection($_FILES),
                'secure' => $this->getVar('HTTPS', 'off') != 'off',
                'accept' => $this->getVar('HTTP_ACCEPT'),
                'proxy_ip' => $this->getProxyIpAddress()
            );
        }

        $this->init($config);
    }

    /**
     * Initialize request properties.
     *
     * @param array $properties Array of request properties
     */
    public function init($properties = array()) {
        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }

        if ($this->base != '/' && strlen($this->base) > 0 && strpos($this->url, $this->base) === 0) {
            $this->url = substr($this->url, strlen($this->base));
        }

        if (empty($this->url)) {
            $this->url = '/';
        }
        else {
            $_GET += self::parseQuery($this->url);

            $this->query->setData($_GET);
        }

        $raw_params = array();
        parse_str($this->body, $raw_params);

        $this->params = new Collection(array_merge($this->query->getData(), $this->data->getData(), $raw_params));
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

    /**
     * Gets the request method.
     *
     * @return string
     */
    private function getMethod() {
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        }
        elseif (isset($_REQUEST['_method'])) {
            return $_REQUEST['_method'];
        }

        return $this->getVar('REQUEST_METHOD', 'GET');
    }

    /**
     * Gets the real remote IP address.
     *
     * @return string IP address
     */
    private function getProxyIpAddress() {
        static $forwarded = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        );

        $flags = \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE;

        foreach ($forwarded as $key) {
            if (array_key_exists($key, $_SERVER)) {
                sscanf($_SERVER[$key], '%[^,]', $ip);
                if (filter_var($ip, \FILTER_VALIDATE_IP, $flags) !== false) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Gets a variable from $_SERVER using $default if not provided.
     *
     * @param string $var Variable name
     * @param string $default Default value to substitute
     * @return string Server variable value
     */
    private function getVar($var, $default = '') {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : $default;
    }
}
