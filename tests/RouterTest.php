<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\core\Dispatcher;
use flight\net\Request;
use flight\net\Router;

require_once 'vendor/autoload.php';
require_once __DIR__ . '/../flight/autoload.php';

class RouterTest extends PHPUnit\Framework\TestCase
{
    private Router $router;

    private Request $request;

    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->request = new Request();
        $this->dispatcher = new Dispatcher();
    }

    // Simple output
    public function ok()
    {
        echo 'OK';
    }

    // Checks if a route was matched with a given output
    public function check($str = '')
    {
        /*
        $route = $this->router->route($this->request);

        $params = array_values($route->params);

        $this->assertTrue(is_callable($route->callback));

        call_user_func_array($route->callback, $params);
        */

        $this->routeRequest();
        $this->expectOutputString($str);
    }

    public function routeRequest()
    {
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

            if (!$continue) {
                break;
            }

            $this->router->next();

            $dispatched = false;
        }

        if (!$dispatched) {
            echo '404';
        }
    }

    // Default route
    public function testDefaultRoute()
    {
        $this->router->map('/', [$this, 'ok']);
        $this->request->url = '/';

        $this->check('OK');
    }

    // Simple path
    public function testPathRoute()
    {
        $this->router->map('/path', [$this, 'ok']);
        $this->request->url = '/path';

        $this->check('OK');
    }

    // POST route
    public function testPostRoute()
    {
        $this->router->map('POST /', [$this, 'ok']);
        $this->request->url = '/';
        $this->request->method = 'POST';

        $this->check('OK');
    }

    // Either GET or POST route
    public function testGetPostRoute()
    {
        $this->router->map('GET|POST /', [$this, 'ok']);
        $this->request->url = '/';
        $this->request->method = 'GET';

        $this->check('OK');
    }

    // Test regular expression matching
    public function testRegEx()
    {
        $this->router->map('/num/[0-9]+', [$this, 'ok']);
        $this->request->url = '/num/1234';

        $this->check('OK');
    }

    // Passing URL parameters
    public function testUrlParameters()
    {
        $this->router->map('/user/@id', function ($id) {
            echo $id;
        });
        $this->request->url = '/user/123';

        $this->check('123');
    }

    // Passing URL parameters matched with regular expression
    public function testRegExParameters()
    {
        $this->router->map('/test/@name:[a-z]+', function ($name) {
            echo $name;
        });
        $this->request->url = '/test/abc';

        $this->check('abc');
    }

    // Optional parameters
    public function testOptionalParameters()
    {
        $this->router->map('/blog(/@year(/@month(/@day)))', function ($year, $month, $day) {
            echo "$year,$month,$day";
        });
        $this->request->url = '/blog/2000';

        $this->check('2000,,');
    }

    // Regex in optional parameters
    public function testRegexOptionalParameters()
    {
        $this->router->map('/@controller/@method(/@id:[0-9]+)', function ($controller, $method, $id) {
            echo "$controller,$method,$id";
        });
        $this->request->url = '/user/delete/123';

        $this->check('user,delete,123');
    }

    // Regex in optional parameters
    public function testRegexEmptyOptionalParameters()
    {
        $this->router->map('/@controller/@method(/@id:[0-9]+)', function ($controller, $method, $id) {
            echo "$controller,$method,$id";
        });
        $this->request->url = '/user/delete/';

        $this->check('user,delete,');
    }

    // Wildcard matching
    public function testWildcard()
    {
        $this->router->map('/account/*', [$this, 'ok']);
        $this->request->url = '/account/123/abc/xyz';

        $this->check('OK');
    }

    // Check if route object was passed
    public function testRouteObjectPassing()
    {
        $this->router->map('/yes_route', function ($route) {
            $this->assertIsObject($route);
            $this->assertIsArray($route->methods);
            $this->assertIsArray($route->params);
            $this->assertCount(0, $route->params);
            $this->assertNull($route->regex);
            $this->assertEquals('', $route->splat);
            $this->assertTrue($route->pass);
        }, true);
        $this->request->url = '/yes_route';

        $this->check();

        $this->router->map('/no_route', function ($route = null) {
            $this->assertNull($route);
        }, false);
        $this->request->url = '/no_route';

        $this->check();
    }

    public function testRouteWithParameters()
    {
        $this->router->map('/@one/@two', function ($one, $two, $route) {
            $this->assertCount(2, $route->params);
            $this->assertEquals($route->params['one'], $one);
            $this->assertEquals($route->params['two'], $two);
        }, true);
        $this->request->url = '/1/2';

        $this->check();
    }

    // Test splat
    public function testSplatWildcard()
    {
        $this->router->map('/account/*', function ($route) {
            echo $route->splat;
        }, true);
        $this->request->url = '/account/456/def/xyz';

        $this->check('456/def/xyz');
    }

    // Test splat without trailing slash
    public function testSplatWildcardTrailingSlash()
    {
        $this->router->map('/account/*', function ($route) {
            echo $route->splat;
        }, true);
        $this->request->url = '/account';

        $this->check();
    }

    // Test splat with named parameters
    public function testSplatNamedPlusWildcard()
    {
        $this->router->map('/account/@name/*', function ($name, $route) {
            echo $route->splat;
            $this->assertEquals('abc', $name);
        }, true);
        $this->request->url = '/account/abc/456/def/xyz';

        $this->check('456/def/xyz');
    }

    // Test not found
    public function testNotFound()
    {
        $this->router->map('/does_exist', [$this, 'ok']);
        $this->request->url = '/does_not_exist';

        $this->check('404');
    }

    // Test case sensitivity
    public function testCaseSensitivity()
    {
        $this->router->map('/hello', [$this, 'ok']);
        $this->request->url = '/HELLO';
        $this->router->case_sensitive = true;

        $this->check('404');
    }

    // Passing URL parameters matched with regular expression for a URL containing Cyrillic letters:
    public function testRegExParametersCyrillic()
    {
        $this->router->map('/категория/@name:[абвгдеёжзийклмнопрстуфхцчшщъыьэюя]+', function ($name) {
            echo $name;
        });
        $this->request->url = urlencode('/категория/цветя');

        $this->check('цветя');
    }
}
