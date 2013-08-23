<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/autoload.php';

class RouterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \flight\net\Router
     */
    private $router;

    /**
     * @var \flight\net\Request
     */
    private $request;
    
    function setUp(){
        $this->router = new \flight\net\Router();
        $this->request = new \flight\net\Request();
    }

    // Simple output
    function ok(){
        echo 'OK';
    }

    // Checks if a route was matched
    function check($str = 'OK'){
        $route = $this->router->route($this->request);
        $params = array_values($route->params);

        $this->assertTrue(is_callable($route->callback));

        call_user_func_array($route->callback, $route->params);

        $this->expectOutputString($str);
    }

    // Default route
    function testDefaultRoute(){
        $this->router->map('/', array($this, 'ok'));
        $this->request->url = '/';

        $this->check();
    }

    // Simple path
    function testPathRoute(){
        $this->router->map('/path', array($this, 'ok'));
        $this->request->url = '/path';

        $this->check();
    }

    // POST route
    function testPostRoute(){
        $this->router->map('POST /', array($this, 'ok'));
        $this->request->url = '/';
        $this->request->method = 'POST';

        $this->check();
    }

    // Either GET or POST route
    function testGetPostRoute(){
        $this->router->map('GET|POST /', array($this, 'ok'));
        $this->request->url = '/';
        $this->request->method = 'GET';

        $this->check();
    }

    // Test regular expression matching
    function testRegEx(){
        $this->router->map('/num/[0-9]+', array($this, 'ok'));
        $this->request->url = '/num/1234';

        $this->check();
    }

    // Passing URL parameters
    function testUrlParameters(){
        $this->router->map('/user/@id', function($id){
            echo $id;
        });
        $this->request->url = '/user/123';

        $this->check('123');
    }

    // Passing URL parameters matched with regular expression
    function testRegExParameters(){
        $this->router->map('/test/@name:[a-z]+', function($name){
            echo $name;
        });

        $this->request->url = '/test/abc';

        $this->check('abc');
    }

    // Optional parameters
    function testOptionalParameters(){
        $this->router->map('/blog(/@year(/@month(/@day)))', function($year, $month, $day){
            echo "$year,$month,$day";
        });

        $this->request->url = '/blog/2000';

        $this->check('2000,,');
    }

    // Regex in optional parameters
    function testRegexOptionalParameters(){
        $this->router->map('/@controller/@method(/@id:[0-9]+)', function($controller, $method, $id){
            echo "$controller,$method,$id";
        });

        $this->request->url = '/user/delete/123';

        $this->check('user,delete,123');
    }

    // Regex in optional parameters
    function testRegexEmptyOptionalParameters(){
        $this->router->map('/@controller/@method(/@id:[0-9]+)', function($controller, $method, $id){
            echo "$controller,$method,$id";
        });

        $this->request->url = '/user/delete/';

        $this->check('user,delete,');
    }

    // Wildcard matching
    function testWildcard(){
        $this->router->map('/account/*', array($this, 'ok'));
        $this->request->url = '/account/123/abc/xyz';

        $this->check();
    }
}
