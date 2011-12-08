<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */

namespace flight\template;

/**
 * The View class represents output to be displayed. It provides
 * methods for managing view data and inserts the data into
 * view templates upon rendering.
 */
class View {
    /**
     * Locaton of view templates.
     *
     * @var string
     */
    public $path;

    /**
     * View variables.
     *
     * @var array
     */
    protected $data = array();

    /**
     * Constructor.
     *
     * @param string $path Path to templates directory
     */
    public function __construct($path = '.') {
        $this->path = $path;
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
     * Checks if a template variable is set.
     *
     * @param string $key Key
     */
    public function has($key) {
        return isset($this->data[$key]);
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
        $template = $this->getTemplate($file);

        if (is_array($data)) {
            $this->data = array_merge($this->data, $data);
        }

        extract($this->data);

        if (!file_exists($template)) {
            throw new \Exception("Template file not found: $template.");
        }

        include $template;
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
     * Checks if a template file exists.
     *
     * @param string $file Template file
     * @return bool Template file exists
     */
    public function exists($file) {
        return file_exists($this->getTemplate($file));
    }

    /**
     * Gets the full path to a template file.
     *
     * @param string $file Template file
     * @return string Template file location
     */
    public function getTemplate($file) {
        return $this->path.'/'.((substr($file, -4) == '.php') ? $file : $file.'.php');
    }

    /**
     * Displays escaped output.
     *
     * @param string $str String to escape
     * @return string Escaped string
     */
    public function e($str) {
        echo htmlentities($str);
    }
}
?>
