<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 * @version     0.1
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
                'query' => array(),
                'data' => $_POST,
                'cookies' => $_COOKIE,
                'files' => $_FILES
            );
        }

        self::init($config);
    }

    /**
     * Initialize request properties.
     *
     * @param array $properties Array of request properties
     */
    public function init($properties) {
        foreach ($properties as $name => $value) {
            $this->{$name} = $value;
        }

        if ($this->base != '/' && strpos($this->url, $this->base) === 0) {
            $this->url = substr($this->url, strlen($this->base));
        }

        if (empty($this->url)) {
            $this->url = '/';
        }
        else {
            $this->query = self::parseQuery($this->url);
        }
    }

    /**
     * Parse query parameters from a URL.
     */
    public function parseQuery($url) {
        $params = array();

        $args = parse_url($url);
        if (isset($args['query'])) {
            parse_str($args['query'], $params);
        }

        return $params;
    }
}
?>
