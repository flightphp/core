<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 * @version     0.1
 */
class View {
    public $path;
    public $template;
    public $data = array();

    /**
     * Constructor.
     *
     * @param string $path Path to templates directory
     */
    public function __construct($path = null) {
        $this->path = $path ?: (Flight::get('flight.views.path') ?: './views');
    }

    /**
     * Gets a template variable.
     *
     * @param string $key Key
     * @return mixed
     */
    public function get($key) {
        return $this->data[$key];
    }

    /**
     * Sets a template variable.
     *
     * @param mixed $key Key
     * @param string $value Value
     */
    public function set($key, $value = null) {
        // If key is an array, save each key value pair
        if (is_array($key) || is_object($key)) {
            foreach ($key as $k => $v) {
                $this->data[$k] = $v;
            }
        }
        else if (is_string($key)) {
            $this->data[$key] = $value;
        }
    }

    /**
     * Unsets a template variable. If no key is passed in, clear all variables.
     *
     * @param string $key Key
     */
    public function clear($key = null) {
        if (is_null($key)) {
            $this->data = array();
        }
        else {
            unset($this->data[$key]);
        }
    }

    /**
     * Renders a template.
     *
     * @param string $file Template file
     * @param array $data Template data
     */
    public function render($file, $data = null) {
        $this->template = (substr($file, -4) == '.php') ? $file : $file.'.php';

        if (is_array($data)) {
            $this->data = array_merge($this->data, $data);
        }

        extract($this->data);

        include $this->path.'/'.$this->template;
    }

    /**
     * Gets the output of a template.
     *
     * @param string $file Template file
     * @param array $data Template data
     */
    public function fetch($file, $data = null) {
        ob_start();

        $this->render($file, $data);
        $output = ob_get_contents();

        ob_end_clean();

        return $output;
    }

    /**
     * Loads and executes view helper functions.
     *
     * @param string $name Function name
     * @param array $params Function parameters
     */
    public function __call($name, $params) {
        return Flight::invokeMethod(array('Flight', $name), $params);
    }

    /**
     * Displays escaped output.
     *
     * @param string $str String to escape
     */
    public function e($str) {
        echo htmlentities($str);
    }
}
?>
