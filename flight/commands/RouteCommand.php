<?php

declare(strict_types=1);

namespace flight\commands;

use Flight;
use flight\net\Route;

/**
 * @property-read ?bool $get
 * @property-read ?bool $post
 * @property-read ?bool $delete
 * @property-read ?bool $put
 * @property-read ?bool $patch
 */
class RouteCommand extends AbstractBaseCommand
{
    /**
     * Construct
     *
     * @param array<string,mixed> $config JSON config from .runway-config.json
     */
    public function __construct(array $config)
    {
        parent::__construct('routes', 'Gets all routes for an application', $config);

        $this->option('--get', 'Only return GET requests');
        $this->option('--post', 'Only return POST requests');
        $this->option('--delete', 'Only return DELETE requests');
        $this->option('--put', 'Only return PUT requests');
        $this->option('--patch', 'Only return PATCH requests');
    }

    /**
     * Executes the function
     *
     * @return void
     */
    public function execute(): void
    {
        $io = $this->app()->io();

        if (empty($this->config['runway'])) {
            $io->warn('Using a .runway-config.json file is deprecated. Move your config values to app/config/config.php with `php runway config:migrate`.', true); // @codeCoverageIgnore
            $runwayConfig = json_decode(file_get_contents($this->projectRoot . '/.runway-config.json'), true); // @codeCoverageIgnore
        } else {
            $runwayConfig = $this->config['runway'];
        }

        if (isset($runwayConfig['index_root']) === false) {
            $io->error('index_root not set in app/config/config.php', true);
            return;
        }

        $io->bold('Routes', true);

        $index_root = $this->projectRoot . '/' . $runwayConfig['index_root'];

        // This makes it so the framework doesn't actually execute
        Flight::map('start', function () {
            return;
        });
        include($index_root);
        $routes = Flight::router()->getRoutes();
        $arrayOfRoutes = [];
        foreach ($routes as $route) {
            if ($this->shouldAddRoute($route) === true) {
                $middlewares = [];
                if (!empty($route->middleware)) {
                    try {
                        $middlewares = array_map(function ($middleware) {
                            if (is_string($middleware)) {
                                $middleware_class_name = explode("\\", $middleware);
                            } else {
                                $middleware_class_name = explode("\\", get_class($middleware));
                            }
                            return preg_match("/^class@anonymous/", end($middleware_class_name)) ? 'Anonymous' : end($middleware_class_name);
                        }, $route->middleware);
                    } catch (\TypeError $e) { // @codeCoverageIgnore
                        $middlewares[] = 'Bad Middleware'; // @codeCoverageIgnore
                    } finally {
                        if (is_string($route->middleware) === true) {
                            $middlewares[] = $route->middleware;
                        }
                    }
                }

                $arrayOfRoutes[] = [
                    'Pattern' => $route->pattern,
                    'Methods' => implode(', ', $route->methods),
                    'Alias' => $route->alias ?? '',
                    'Streamed' => $route->is_streamed ? 'Yes' : 'No',
                    'Middleware' => !empty($middlewares) ? implode(",", $middlewares) : '-'
                ];
            }
        }
        $io->table($arrayOfRoutes, [
            'head' => 'boldGreen'
        ]);
    }

    /**
     * Whether or not to add the route based on the request
     *
     * @param Route $route Flight Route object
     *
     * @return boolean
     */
    public function shouldAddRoute(Route $route)
    {
        $boolval = false;

        $showAll = !$this->get && !$this->post && !$this->put && !$this->delete && !$this->patch;
        if ($showAll === true) {
            $boolval = true;
        } else {
            $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
            foreach ($methods as $method) {
                $lowercaseMethod = strtolower($method);
                if (
                    $this->{$lowercaseMethod} === true &&
                    (
                        $route->methods[0] === '*' ||
                        in_array($method, $route->methods, true) === true
                    )
                ) {
                    $boolval = true;
                    break;
                }
            }
        }
        return $boolval;
    }
}
