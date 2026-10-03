<?php

declare(strict_types=1);

namespace flight\template;

/**
 * The View class represents output to be displayed. It provides
 * methods for managing view data and inserts the data into
 * view templates upon rendering.
 *
 * @copyright 2011 Mike Cao https://mikecao.com
 * @license https://docs.flightphp.com/license MIT
 */
class View
{
    /** Location of view templates. */
    public string $path;

    /** File extension. */
    public string $extension = '.php';

    public bool $preserveVars = true;

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
        if ($key === null) {
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
     * @param ?array<string, mixed> $templateData Template data
     *
     * @throws \Exception If template not found
     */
    public function render(string $file, ?array $templateData = null): void
    {
        $this->template = $this->getTemplate($file);

        if (!\file_exists($this->template)) {
            $normalized_path = self::normalizePath($this->template);
            throw new \Exception("Template file not found: {$normalized_path}.");
        }

        \extract($this->vars);

        if (\is_array($templateData) === true) {
            \extract($templateData);

            if ($this->preserveVars === true) {
                $this->vars = \array_merge($this->vars, $templateData);
            }
        }

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
     * Absolute paths are rejected. The resolved file must stay inside the
     * configured views directory. If that cannot be shown, this fails closed.
     *
     * @param string $file Template file
     *
     * @return string Template file location
     *
     * @throws \Exception When the path is absolute or resolves outside the views directory.
     */
    public function getTemplate(string $file): string
    {
        $ext = $this->extension;

        if (!empty($ext) && (\substr($file, -1 * \strlen($ext)) != $ext)) {
            $file .= $ext;
        }

        if ($this->isAbsolutePath($file)) {
            throw new \Exception('Template path is not allowed.');
        }

        $viewsPath = \realpath($this->path);
        if ($viewsPath === false || !$this->relativeStaysInside($file)) {
            throw new \Exception('Template path is not allowed.');
        }

        $candidate = $this->path . \DIRECTORY_SEPARATOR . $file;
        $resolved = \realpath($candidate);
        if ($resolved === false) {
            return $candidate;
        }

        $root = \rtrim($viewsPath, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;
        if (\strpos($resolved, $root) !== 0) {
            throw new \Exception('Template path is not allowed.');
        }

        return $resolved;
    }

    /**
     * True when $file is an absolute filesystem path.
     */
    private function isAbsolutePath(string $file): bool
    {
        if ($file === '') {
            return false;
        }

        if ($file[0] === '/' || $file[0] === '\\') {
            return true;
        }

        return \strlen($file) > 1 && \ctype_alpha($file[0]) && $file[1] === ':';
    }

    /**
     * True when relative segments in $file do not climb out of the views directory.
     */
    private function relativeStaysInside(string $file): bool
    {
        $segments = \preg_split('#[\\/]+#', $file, -1, \PREG_SPLIT_NO_EMPTY);
        if ($segments === false) {
            return false;
        }

        $depth = 0;
        foreach ($segments as $segment) {
            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($depth === 0) {
                    return false;
                }

                $depth--;
                continue;
            }

            $depth++;
        }

        return true;
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
        $value = \htmlentities($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo $value;
        return $value;
    }

    protected static function normalizePath(string $path, string $separator = DIRECTORY_SEPARATOR): string
    {
        return \str_replace(['\\', '/'], $separator, $path);
    }
}
