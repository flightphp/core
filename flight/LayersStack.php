<?php

namespace flight;

/*
* LayersStack is a push down collection of middleware layers, at resolution they are resolved at inverted order
*/
class LayersStack {
    
    //Stack of layers
    private $layers;
    
    /**
    * Public constructor of the LayersStack
    * @param bool $push_self push the realDispatch method as the final layer of the middlewares
    */
    public function __construct(bool $push_self = false) {
        $this->stack = [];
        if ( $push_self ) {
            $this->stack[] = [ $this, 'realDispatch' ];
        }
    }
    
    /**
    * Dispatch is a repeatable middlewares dispatch function that start the iteration on the current stack of layers before reaching the final route dispatch
    * You shouldn't invoke it directly, instead map this method to Flight as "dispatchRoute" with Flight::map( 'dispatchRoute', [ $thisObject, 'dispatch' ] 
    * overriding the original mapped method Flight::dispatchRoute and let Flight call it for you when it find a compatible route for the request.
    * As it may found multiple routes compatibile with yours the repeatability of the dispatch function is a must
    */
    public function dispatch(net\Route $route, array $params) {
        $iterator = new LayersIterator( $this->layers );
        $iterator->next($route, $params, \Flight::request(), \Flight::response);
    }
    
    /**
    * RealDispatch is a super thin wrapper over the original Flight::dispatchRoute
    */
    public function realDispatch(net\Route $route, array $params, net\Request $request, net\Response $response, LayersIterator $iterator) {
        \Flight::_dispatchRoute($route, $params);
    }
    
}