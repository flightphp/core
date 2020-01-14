<?php

namespace flight\layers;

use flight\net\Route;
use flight\net\Request;
use flight\net\Response;

class FlightLayers {
    
    /**
    * Pass route to the callback if the 'pass_route' flag is true in the route configuration
    */
    public static function passRoute(Route $route, array $params, Request $request, Response $response, LayersIterator $iterator) {
        if ( isset( $route->config['pass_route'] ) && $route->config['pass_route'] ) {
            $params[] = $route;
        }
        $iterator->next($route, $params, $request, $response);
    }
    
}