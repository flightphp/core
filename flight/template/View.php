<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace Flight\Template;

/**
 * The View class represents output to be displayed. It provides
 * methods for managing view data and inserts the data into
 * view templates upon rendering.
 */
class View {
    /**
     * Location of view templates.
     *
     * @var string
     */
    public $path;

    /**
     * File extension.
     *
     * @var string
     */
    public $extension = '.php';

    /**
     * View variables.
     *
     * @var array
     */
    protected $vars = array();

    /**
     * Template file.
     *
     * @var string
     */
    private $template;

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
     * @return mixed Value
     */
    public function get($key) {
        return isset($this->vars[$key]) ? $this->vars[$key] : null;
    }

    /**
     * Sets a template variable.
     *
     * @param mixed $key Key
     * @param string $value Value
     */
    public function set($key, $value = null) {
        if (is_array($key) || is_object($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }
        }
        else {
            $this->vars[$key] = $value;
        }
    }

    /**
     * Checks if a template variable is set.
     *
     * @param string $key Key
     * @return boolean If key exists
     */
    public function has($key) {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a template variable. If no key is passed in, clear all variables.
     *
     * @param string $key Key
     */
    public function clear($key = null) {
        if (is_null($key)) {
            $this->vars = array();
        }
        else {
            unset($this->vars[$key]);
        }
    }

    /**
     * Renders a template.
     *
     * @param string $file Template file
     * @param array $data Template data
     * @throws \Exception If template not found
     */
    public function render($file, $data = null) {
        $this->template = $this->getTemplate($file);

        if (!file_exists($this->template)) {
            throw new \Exception("Template file not found: {$this->template}.");
        }

        if (is_array($data)) {
            $this->vars = array_merge($this->vars, $data);
        }

        extract($this->vars);

        include $this->template;
    }

    /**
     * Gets the output of a template.
     *
     * @param string $file Template file
     * @param array $data Template data
     * @return string Output of template
     */
    public function fetch($file, $data = null) {
        ob_start();

        $this->render($file, $data);
        $output = ob_get_clean();

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
        $ext = $this->extension;

        if (!empty($ext) && (substr($file, -1 * strlen($ext)) != $ext)) {
            $file .= $ext;
        }

        if ((substr($file, 0, 1) == '/')) {
            return $file;
        }

        return $this->path.'/'.$file;
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
