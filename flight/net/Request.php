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
 *   json - JSON decoded body for application/json requests
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
    protected $_body;

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
     * @var \flight\util\Collection JSON decoded body
     */
    public $json;

    /**
     * @var \flight\util\Collection Cookie parameters
     */
    public $cookies;

    /**
     * @var \flight\util\Collection Uploaded files
     */
    public $files;

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
     * @var bool true if body already load
     */
    protected $_body_loaded = false;

    /**
     * Constructor.
     *
     * @param array $config Request configuration
     */
    public function __construct($config = array()) {
        // Default properties
        if (empty($config)) {
            $config = array(
                'url' => self::getVar('REQUEST_URI', '/'),
                'base' => str_replace(array('\\',' '), array('/','%20'), dirname(self::getVar('SCRIPT_NAME'))),
                'method' => self::getMethod(),
                'referrer' => self::getVar('HTTP_REFERER'),
                'ip' => self::getVar('REMOTE_ADDR'),
                'ajax' => self::getVar('HTTP_X_REQUESTED_WITH') == 'XMLHttpRequest',
                'scheme' => self::getVar('SERVER_PROTOCOL', 'HTTP/1.1'),
                'user_agent' => self::getVar('HTTP_USER_AGENT'),
                'type' => self::getVar('CONTENT_TYPE'),
                'length' => self::getVar('CONTENT_LENGTH', 0),
                'query' => new Collection($_GET),
                'data' => new Collection($_POST),
                'cookies' => new Collection($_COOKIE),
                'files' => new Collection($_FILES),
                'secure' => self::getVar('HTTPS', 'off') != 'off',
                'accept' => self::getVar('HTTP_ACCEPT'),
                'proxy_ip' => self::getProxyIpAddress()
            );
        }

        $this->init($config);
    }

    /**
     * Overloading method to redirect request for ->body to ->_body
     *
     * @param string $name  name of variable to set
     * @param mixed  $value value to set
     *
     * @return void
     */
    public function __set($name, $value) {
        if ($name == 'body') {
            $this->_body = $value;
            $this->_body_loaded = true;
            return;
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __set(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
    }

    /**
     * Overloading method to load body only when requested
     *
     * @param string $name name of variable to get
     *
     * @return mixed value of variable
     */
    public function __get($name) {
        if ($name == 'body') {
            if (!$this->_body_loaded) {
                $this->_body = file_get_contents('php://input');
                $this->_body_loaded = true;
            }

            return $this->_body;
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    /**
     * Overloading method to redirect request for ->body to ->_body
     *
     * @param string $name name of variable to get
     *
     * @return bool true if variable is set
     */
    public function __isset($name) {
        if ($name == 'body') {
            $this->__get('body');
            return isset($this->_body);
        }

        return false;
    }

    /**
     * Overloading method to redirect request for ->body to ->_body
     *
     * @param string $name name of variable to get
     *
     * @return void
     */
    public function __unset($name) {
        if ($name == 'body') {
            unset($this->_body);
        }
    }

    /**
     * Initialize request properties.
     *
     * @param array $properties Array of request properties
     */
    public function init($properties = array()) {
        // Set all the defined properties
        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }

        // Get the requested URL without the base directory
        if ($this->base != '/' && strlen($this->base) > 0 && strpos($this->url, $this->base) === 0) {
            $this->url = substr($this->url, strlen($this->base));
        }

        if (empty($this->url)) {
            $this->url = '/';
        }
        // Merge URL query parameters with $_GET
        else {
            $_GET += self::parseQuery($this->url);

            $this->query->setData($_GET);
        }

        // Check for JSON input
        $json = array();
        if ($this->body != '' && strpos($this->type, 'application/json') === 0) {
            $json = json_decode($this->body, true);
        }
        $this->json = new Collection($json);
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
    public static function getMethod() {
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        }
        elseif (isset($_REQUEST['_method'])) {
            return $_REQUEST['_method'];
        }

        return self::getVar('REQUEST_METHOD', 'GET');
    }

    /**
     * Gets the real remote IP address.
     *
     * @return string IP address
     */
    public static function getProxyIpAddress() {
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
    public static function getVar($var, $default = '') {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : $default;
    }
}
