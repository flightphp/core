<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight\template;

/**
 * The View class represents output to be displayed. It provides
 * methods for managing view data and inserts the data into
 * view templates upon rendering.
 */
class View
{
    /** @var string Location of view templates. */
    public $path;

    /** @var string File extension. */
    public $extension = '.php';

    /** @var array<string, mixed> View variables. */
    protected $vars = [];

    /** @var string Template file. */
    private $template;

    /**
     * Constructor.
     *
     * @param string $path Path to templates directory
     */
    public function __construct($path = '.')
    {
        $this->path = $path;
    }

    /**
     * Gets a template variable.
     *
     * @param string $key
     *
     * @return mixed Variable value or `null` if doesn't exists
     */
    public function get($key)
    {
        return $this->vars[$key] ?? null;
    }

    /**
     * Sets a template variable.
     *
     * @param string|iterable<string, mixed> $key
     * @param mixed $value Value
     * @return $this
     */
    public function set($key, $value = null)
    {
        if (\is_iterable($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }
        } else {
            $this->vars[$key] = $value;
        }

        return $this;
    }

    /**
     * Checks if a template variable is set.
     *
     * @param string $key
     *
     * @return bool If key exists
     */
    public function has($key)
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a template variable. If no key is passed in, clear all variables.
     *
     * @param ?string $key
     *
     * @return $this
     */
    public function clear($key = null)
    {
        if (null === $key) {
            $this->vars = [];
        } else {
            unset($this->vars[$key]);
        }

        return $this;
    }

    /**
     * Renders a template.
     *
     * @param string $file Template file
     * @param ?array<string, mixed> $data Template data
     *
     * @return void
     * @throws \Exception If template not found
     */
    public function render($file, $data = null)
    {
        $this->template = $this->getTemplate($file);

        if (!\file_exists($this->template)) {
            $normalized_path = self::normalizePath($this->template);
            throw new \Exception("Template file not found: {$normalized_path}.");
        }

        if (\is_array($data)) {
            $this->vars = \array_merge($this->vars, $data);
        }

        \extract($this->vars);

        include $this->template;
    }

    /**
     * Gets the output of a template.
     *
     * @param string $file Template file
     * @param ?array<string, mixed> $data Template data
     *
     * @return string Output of template
     */
    public function fetch($file, $data = null)
    {
        \ob_start();

        $this->render($file, $data);

        return \ob_get_clean();
    }

    /**
     * Checks if a template file exists.
     *
     * @param string $file Template file
     *
     * @return bool Template file exists
     */
    public function exists($file)
    {
        return \file_exists($this->getTemplate($file));
    }

    /**
     * Gets the full path to a template file.
     *
     * @param string $file Template file
     *
     * @return string Template file location
     */
    public function getTemplate($file)
    {
        $ext = $this->extension;

        if (!empty($ext) && (\substr($file, -1 * \strlen($ext)) != $ext)) {
            $file .= $ext;
        }

		$is_windows = \strtoupper(\substr(PHP_OS, 0, 3)) === 'WIN';

        if (('/' == \substr($file, 0, 1)) || ($is_windows === true && ':' == \substr($file, 1, 1))) {
            return $file;
        }

        return $this->path . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * Displays escaped output.
     *
     * @param string $str String to escape
     *
     * @return string Escaped string
     */
    public function e($str)
    {
		$value = \htmlentities($str);
        echo $value;
        return $value;
    }

    /**
     * @param string $path An unnormalized path.
     * @param string $separator Path separator.
     *
     * @return string Normalized path.
     */
    protected static function normalizePath($path, $separator = DIRECTORY_SEPARATOR)
    {
        return \str_replace(['\\', '/'], $separator, $path);
    }
}
