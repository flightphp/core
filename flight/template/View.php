<?php

declare(strict_types=1);

namespace flight\template;

use Closure;
use Exception;
use ReflectionFunction;

/**
 * The View class represents output to be displayed. It provides
 * methods for managing view data and inserts the data into
 * view templates upon rendering.
 *
 * @license [MIT](https://docs.flightphp.com/license)
 * @copyright 2011 [Mike Cao](https://mikecao.com)
 */
class View
{
    /** Location of view templates */
    public string $path;

    /** File extension */
    public string $extension = '.php';

    public bool $preserveVars = true;

    /** @var array<string, mixed> View variables */
    protected array $vars = [];

    private string $componentPrefix;
    private string $componentsPath;
    private int $fetchDepth = 0;
    /** @var array<string, bool> */
    private array $styles = [];
    /** @var array<string, bool> */
    private array $scripts = [];

    /**
     * @param string $path Path to templates directory
     * @param string $componentPrefix Prefix for component tags.
     * For example, if the prefix is `f`, then a component tag would look like `<f-component-name />`
     * @param string $componentsPath Path to components directory.
     * If is a relative path, it will be relative to the `$path` property.
     * **We recomment that you always use absolute paths for explicitness**.
     */
    public function __construct(
        string $path = ".",
        string $componentPrefix = 'f',
        string $componentsPath = 'components'
    ) {
        $this->path = $path;
        $this->componentPrefix = $componentPrefix;
        $this->componentsPath = $componentsPath;
    }

    /**
     * Gets a template variable
     * @return mixed Variable value or `null` if doesn't exists
     */
    public function get(string $key)
    {
        return $this->vars[$key] ?? null;
    }

    /**
     * Sets a template variable
     * @param string|array<string, mixed> $key
     * @param mixed $value Value
     * @return $this
     */
    public function set($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }
        } else {
            $this->vars[$key] = $value;
        }

        return $this;
    }

    /**
     * Checks if a template variable is set
     * @return bool If key exists and is not `null`
     */
    public function has(string $key): bool
    {
        return isset($this->vars[$key]);
    }

    /**
     * Unsets a template variable. If no key is passed in, clear all variables
     * @return $this
     */
    public function clear(?string $key = null)
    {
        if ($key === null) {
            $this->vars = [];
        } else {
            unset($this->vars[$key]);
        }

        return $this;
    }

    /**
     * Renders a template
     * @param string $file Template file
     * @param ?array<string, mixed> $templateData Template data
     * @throws Exception If template not found
     */
    public function render(string $file, ?array $templateData = null): void
    {
        echo $this->fetch($file, $templateData);
    }

    /**
     * Gets the output of a template
     * @param string $file Template file
     * @param ?array<string, mixed> $data Template data
     * @return string Output of template
     */
    public function fetch(string $file, ?array $data = null): string
    {
        if ($this->fetchDepth === 0) {
            $this->styles = [];
            $this->scripts = [];
        }

        $this->fetchDepth++;

        try {
            $template = $this->getTemplate($file);

            if (!$this->exists($file)) {
                throw new Exception("Template file not found: $template.");
            }

            extract($this->vars);

            if (is_array($data)) {
                extract($data);

                if ($this->preserveVars) {
                    $this->vars += $data;
                }
            }

            ob_start();
            $component = include $template;

            switch (true) {
                case is_callable($component):
                    $arguments = $this->getCallableArguments($component, $data);
                    $view = $component(...$arguments);
                    ob_end_clean();

                    break;
                case $component instanceof Component:
                    $view = $component->html();
                    $css = $component->css();
                    $js = $component->js();

                    if ($css && !array_key_exists($template, $this->styles)) {
                        $view .= <<<html
                        <style>
                            $css
                        </style>
                        html;

                        $this->styles[$template] = true;
                    }

                    if ($js && !array_key_exists($template, $this->scripts)) {
                        $view .= <<<html
                        <script>
                            $js
                        </script>
                        html;

                        $this->scripts[$template] = true;
                    }

                    ob_end_clean();

                    break;
                default:
                    $view = ob_get_clean();
            }

            preg_match_all(
                "/<$this->componentPrefix-(?<component>[a-z-]+)\s*(?<props>([a-z]+=\"[^\"]+\"\s*)*)?\s*\/>/",
                $view,
                $tagsMatches,
            );

            $tagsMatches = array_filter($tagsMatches);

            foreach ($tagsMatches[0] ?? [] as $tagIndex => $match) {
                $tag = $match;
                $component = $tagsMatches['component'][$tagIndex];
                $props = $tagsMatches['props'][$tagIndex] ?? '';

                preg_match_all(
                    '/(?<name>[a-z]+)="(?<value>[^"]+)"/',
                    $props,
                    $propsMatches,
                );

                $propsMatches = array_filter($propsMatches);

                if ($propsMatches) {
                    $props = [];

                    foreach (array_keys($propsMatches[0]) as $propIndex) {
                        $name = $propsMatches['name'][$propIndex];
                        $value = $propsMatches['value'][$propIndex];
                        $props[$name] = $value;
                    }
                } else {
                    $props = [];
                }

                $component = $this->fetch("$this->componentsPath/$component", $props);
                $tagPosition = strpos($view, $tag);

                if ($tagPosition === false) {
                    continue;
                }

                $view = substr_replace($view, $component, $tagPosition, strlen($tag));
            }

            return $view;
        } finally {
            $this->fetchDepth--;
        }
    }

    /**
     * Checks if a template file exists
     * @param string $file Template file
     * @return bool Template file exists
     */
    public function exists(string $file): bool
    {
        return file_exists($this->getTemplate($file));
    }

    /**
     * Gets the full path to a template file
     * @param string $file Template file
     * @return string Template file location
     */
    public function getTemplate(string $file): string
    {
        $fileDoesNotHaveExtension = substr($file, -strlen($this->extension)) !== $this->extension;

        if ($fileDoesNotHaveExtension) {
            $file .= $this->extension;
        }

        $isLinuxAbsolutePath = $file[0] === '/';
        $isWindowsAbsolutePath = PHP_OS === 'WINNT' && $file[1] === ':';

        if ($isLinuxAbsolutePath || $isWindowsAbsolutePath) {
            return $this::normalizePath($file);
        }

        return $this::normalizePath("$this->path/$file");
    }

    /**
     * Displays escaped output
     * @param string $str String to escape
     * @return string Escaped string
     */
    public function e(string $str): string
    {
        $value = htmlentities($str);
        echo $value;

        return $value;
    }

    protected static function normalizePath(
        string $path,
        string $separator = DIRECTORY_SEPARATOR
    ): string {
        return str_replace(['\\', '/'], $separator, $path);
    }

    /**
     * @param callable $component
     * @param ?array<string, mixed> $data
     * @return array<int, mixed>
     */
    private function getCallableArguments(callable $component, ?array $data): array
    {
        if (!is_array($data) || !$data) {
            return [];
        }

        $arguments = [];
        $remainingData = $data;
        $reflection = new ReflectionFunction(Closure::fromCallable($component));

        foreach ($reflection->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                foreach ($remainingData as $value) {
                    $arguments[] = $value;
                }

                break;
            }

            $name = $parameter->getName();

            if (array_key_exists($name, $remainingData)) {
                $arguments[] = $remainingData[$name];
                unset($remainingData[$name]);
            }
        }

        return $arguments;
    }
}
