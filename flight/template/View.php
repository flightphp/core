<?php

declare(strict_types=1);

namespace flight\template;

/**
 * The View class represents output to be displayed. It provides
 * methods for managing view data and inserts the data into
 * view templates upon rendering.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class View
{
    /** Location of view templates. */
    public string $path;

    /** File extension. */
    public string $extension = '.php';

    /**
     * View variables.
     *
     * @var array<string, mixed> $vars
     */
    protected array $vars = [];

    /** Template file. */
    private string $template;

    /**
     * Constructor.
     *
     * @param string $path Path to templates directory
     */
    public function __construct(string $path = '.')
    {
        $this->path = $path;
    }

    /**
     * Gets a template variable.
     *
     * @return mixed Variable value or `null` if doesn't exists
     */
    public function get(string $key)
    {
        return $this->vars[$key] ?? null;
    }

    /**
     * Sets a template variable.
     *
     * @param string|iterable<string, mixed> $key
     * @param mixed $value Value
     *
     * @return self
     */
    public function set($key, $value = null): self
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
     * @return bool If key exists
     */
    public function has(string $key): bool
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a template variable. If no key is passed in, clear all variables.
     *
     * @return $this
     */
    public function clear(?string $key = null): self
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
     * @throws \Exception If template not found
     */
    public function render(string $file, ?array $data = null): void
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
    public function fetch(string $file, ?array $data = null): string
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
    public function exists(string $file): bool
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
    public function getTemplate(string $file): string
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
    public function e(string $str): string
    {
        $value = \htmlentities($str);
        echo $value;
        return $value;
    }

    protected static function normalizePath(string $path, string $separator = DIRECTORY_SEPARATOR): string
    {
        return \str_replace(['\\', '/'], $separator, $path);
    }
}
