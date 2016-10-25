<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once 'vendor/autoload.php';
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
        $this->dispatcher = new \flight\core\Dispatcher();
    }

    // Simple output
    function ok(){
        echo 'OK';
    }

    // Checks if a route was matched with a given output
    function check($str = '') {
        /*
        $route = $this->router->route($this->request);

        $params = array_values($route->params);

        $this->assertTrue(is_callable($route->callback));

        call_user_func_array($route->callback, $params);
        */

        $this->routeRequest();
        $this->expectOutputString($str);
    }

    function routeRequest() {
        $dispatched = false;

        while ($route = $this->router->route($this->request)) {
            $params = array_values($route->params);

            if ($route->pass) {
                $params[] = $route;
            }

            $continue = $this->dispatcher->execute(
                $route->callback,
                $params
            );

            $dispatched = true;

            if (!$continue) break;

            $this->router->next();

            $dispatched = false;
        }

        if (!$dispatched) {
            echo '404';
        }
    }

    // Default route
    function testDefaultRoute(){
        $this->router->map('/', array($this, 'ok'));
        $this->request->url = '/';

        $this->check('OK');
    }

    // Simple path
    function testPathRoute(){
        $this->router->map('/path', array($this, 'ok'));
        $this->request->url = '/path';

        $this->check('OK');
    }

    // POST route
    function testPostRoute(){
        $this->router->map('POST /', array($this, 'ok'));
        $this->request->url = '/';
        $this->request->method = 'POST';

        $this->check('OK');
    }

    // Either GET or POST route
    function testGetPostRoute(){
        $this->router->map('GET|POST /', array($this, 'ok'));
        $this->request->url = '/';
        $this->request->method = 'GET';

        $this->check('OK');
    }

    // Test regular expression matching
    function testRegEx(){
        $this->router->map('/num/[0-9]+', array($this, 'ok'));
        $this->request->url = '/num/1234';

        $this->check('OK');
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

        $this->check('OK');
    }

    // Check if route object was passed
    function testRouteObjectPassing(){
        $this->router->map('/yes_route', function($route){
            $this->assertTrue(is_object($route));
            $this->assertTrue(is_array($route->methods));
            $this->assertTrue(is_array($route->params));
            $this->assertEquals(sizeof($route->params), 0);
            $this->assertEquals($route->regex, null);
            $this->assertEquals($route->splat, '');
            $this->assertTrue($route->pass);
        }, true);
        $this->request->url = '/yes_route';

        $this->check();

        $this->router->map('/no_route', function($route = null){
            $this->assertTrue(is_null($route));
        }, false);
        $this->request->url = '/no_route';

        $this->check();
    }

    function testRouteWithParameters() {
        $this->router->map('/@one/@two', function($one, $two, $route){
            $this->assertEquals(sizeof($route->params), 2);
            $this->assertEquals($route->params['one'], $one);
            $this->assertEquals($route->params['two'], $two);
        }, true);
        $this->request->url = '/1/2';

        $this->check();
    }

    // Test splat
    function testSplatWildcard(){
        $this->router->map('/account/*', function($route){
            echo $route->splat;
        }, true);
        $this->request->url = '/account/456/def/xyz';

        $this->check('456/def/xyz');
    }

    // Test splat without trailing slash
    function testSplatWildcardTrailingSlash(){
        $this->router->map('/account/*', function($route){
            echo $route->splat;
        }, true);
        $this->request->url = '/account';

        $this->check();
    }

    // Test splat with named parameters
    function testSplatNamedPlusWildcard(){
        $this->router->map('/account/@name/*', function($name, $route){
                echo $route->splat;
                $this->assertEquals('abc', $name);
            }, true);
        $this->request->url = '/account/abc/456/def/xyz';

        $this->check('456/def/xyz');
    }

    // Test not found
    function testNotFound() {
        $this->router->map('/does_exist', array($this, 'ok'));
        $this->request->url = '/does_not_exist';

        $this->check('404');
    }

    // Test case sensitivity
    function testCaseSensitivity() {
        $this->router->map('/hello', array($this, 'ok'));
        $this->request->url = '/HELLO';
        $this->router->case_sensitive = true;

        $this->check('404');
    }
}
