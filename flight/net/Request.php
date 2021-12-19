<?php

declare(strict_types=1);
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
 *   type - The content type
 *   length - The content length
 *   query - Query string parameters
 *   data - Post parameters
 *   cookies - Cookie parameters
 *   files - Uploaded files
 *   secure - Connection is secure
 *   accept - HTTP accept parameters
 *   proxy_ip - Proxy IP address of the client
 */
final class Request
{
    /**
     * @var string URL being requested
     */
    public string $url;

    /**
     * @var string Parent subdirectory of the URL
     */
    public string $base;

    /**
     * @var string Request method (GET, POST, PUT, DELETE)
     */
    public string $method;

    /**
     * @var string Referrer URL
     */
    public string $referrer;

    /**
     * @var string IP address of the client
     */
    public string $ip;

    /**
     * @var bool Whether the request is an AJAX request
     */
    public bool $ajax;

    /**
     * @var string Server protocol (http, https)
     */
    public string $scheme;

    /**
     * @var string Browser information
     */
    public string $user_agent;

    /**
     * @var string Content type
     */
    public string $type;

    /**
     * @var int Content length
     */
    public int $length;

    /**
     * @var Collection Query string parameters
     */
    public Collection $query;

    /**
     * @var Collection Post parameters
     */
    public Collection $data;

    /**
     * @var Collection Cookie parameters
     */
    public Collection $cookies;

    /**
     * @var Collection Uploaded files
     */
    public Collection $files;

    /**
     * @var bool Whether the connection is secure
     */
    public bool $secure;

    /**
     * @var string HTTP accept parameters
     */
    public string $accept;

    /**
     * @var string Proxy IP address of the client
     */
    public string $proxy_ip;

    /**
     * @var string HTTP host name
     */
    public string $host;

    /**
     * Constructor.
     *
     * @param array $config Request configuration
     */
    public function __construct(array $config = [])
    {
        // Default properties
        if (empty($config)) {
            $config = [
                'url' => str_replace('@', '%40', self::getVar('REQUEST_URI', '/')),
                'base' => str_replace(['\\', ' '], ['/', '%20'], \dirname(self::getVar('SCRIPT_NAME'))),
                'method' => self::getMethod(),
                'referrer' => self::getVar('HTTP_REFERER'),
                'ip' => self::getVar('REMOTE_ADDR'),
                'ajax' => 'XMLHttpRequest' === self::getVar('HTTP_X_REQUESTED_WITH'),
                'scheme' => self::getScheme(),
                'user_agent' => self::getVar('HTTP_USER_AGENT'),
                'type' => self::getVar('CONTENT_TYPE'),
                'length' => (int) self::getVar('CONTENT_LENGTH', 0),
                'query' => new Collection($_GET),
                'data' => new Collection($_POST),
                'cookies' => new Collection($_COOKIE),
                'files' => new Collection($_FILES),
                'secure' => 'https' === self::getScheme(),
                'accept' => self::getVar('HTTP_ACCEPT'),
                'proxy_ip' => self::getProxyIpAddress(),
                'host' => self::getVar('HTTP_HOST'),
            ];
        }

        $this->init($config);
    }

    /**
     * Initialize request properties.
     *
     * @param array $properties Array of request properties
     */
    public function init(array $properties = [])
    {
        // Set all the defined properties
        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }

        // Get the requested URL without the base directory
        if ('/' !== $this->base && '' !== $this->base && 0 === strpos($this->url, $this->base)) {
            $this->url = substr($this->url, \strlen($this->base));
        }

        // Default url
        if (empty($this->url)) {
            $this->url = '/';
        } else {
            // Merge URL query parameters with $_GET
            $_GET = array_merge($_GET, self::parseQuery($this->url));

            $this->query->setData($_GET);
        }

        // Check for JSON input
        if (0 === strpos($this->type, 'application/json')) {
            $body = self::getBody();
            if ('' !== $body && null !== $body) {
                $data = json_decode($body, true);
                if (is_array($data)) {
                    $this->data->setData($data);
                }
            }
        }
    }

    /**
     * Gets the body of the request.
     *
     * @return string Raw HTTP request body
     */
    public static function getBody(): ?string
    {
        static $body;

        if (null !== $body) {
            return $body;
        }

        $method = self::getMethod();

        if ('POST' === $method || 'PUT' === $method || 'DELETE' === $method || 'PATCH' === $method) {
            $body = file_get_contents('php://input');
        }

        return $body;
    }

    /**
     * Gets the request method.
     */
    public static function getMethod(): string
    {
        $method = self::getVar('REQUEST_METHOD', 'GET');

        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        } elseif (isset($_REQUEST['_method'])) {
            $method = $_REQUEST['_method'];
        }

        return strtoupper($method);
    }

    /**
     * Gets the real remote IP address.
     *
     * @return string IP address
     */
    public static function getProxyIpAddress(): string
    {
        static $forwarded = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];

        $flags = \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE;

        foreach ($forwarded as $key) {
            if (\array_key_exists($key, $_SERVER)) {
                sscanf($_SERVER[$key], '%[^,]', $ip);
                if (false !== filter_var($ip, \FILTER_VALIDATE_IP, $flags)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Gets a variable from $_SERVER using $default if not provided.
     *
     * @param string $var     Variable name
     * @param mixed  $default Default value to substitute
     *
     * @return mixed Server variable value
     */
    public static function getVar(string $var, $default = '')
    {
        return $_SERVER[$var] ?? $default;
    }

    /**
     * Parse query parameters from a URL.
     *
     * @param string $url URL string
     *
     * @return array Query parameters
     */
    public static function parseQuery(string $url): array
    {
        $params = [];

        $args = parse_url($url);
        if (isset($args['query'])) {
            parse_str($args['query'], $params);
        }

        return $params;
    }

    public static function getScheme(): string
    {
        if (
            (isset($_SERVER['HTTPS']) && 'on' === strtolower($_SERVER['HTTPS']))
            ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'])
            ||
            (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && 'on' === $_SERVER['HTTP_FRONT_END_HTTPS'])
            ||
            (isset($_SERVER['REQUEST_SCHEME']) && 'https' === $_SERVER['REQUEST_SCHEME'])
        ) {
            return 'https';
        }

        return 'http';
    }
}
