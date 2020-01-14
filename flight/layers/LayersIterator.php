<?php

namespace flight\layers;

use flight\net\Route;
use flight\net\Request;
use flight\net\Response;

/**
* LayersIterator iterate over the middlewares stack and call the next layer until it abort or reach the final layer (the route layer)
*/
class LayersIterator {

    private $layers;
    private $cursor;

    /**
    * @param array $layers stack of middleware layers
    */
    public function __construct(array $layers) {
        $this->layers = $layers;
        $this->cursor = count( $layers ) - 1;
    }

    /**
    * Dispatch the next layer of the stack
    * @param Route $route route found by the router
    * @param array $params list of params for the route
    * @param Request $request Flight request object
    * @param Response $response Flight response object
    * @return void
    */
    public function next(Route $route, array $params, Request $request, Response $response) {
        if ( $this->cursor > -1 ) {
            $callable = $this->layers[ $this->cursor ];
            $this->cursor--;
            call_user_func_array( $callable, [ $route, $params, $request, $response, $this ] );
        }
    }

}