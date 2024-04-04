<?php

declare(strict_types=1);

namespace tests;

use flight\core\Dispatcher;
use flight\net\Request;
use flight\net\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    private Request $request;

    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $_SERVER = [];
        $_REQUEST = [];
        $this->router = new Router();
        $this->request = new Request();
        $this->dispatcher = new Dispatcher();
    }

    protected function tearDown(): void
    {
        unset($_REQUEST);
        unset($_SERVER);
    }

    // Simple output
    public function ok()
    {
        echo 'OK';
    }

    // Checks if a route was matched with a given output
    public function check($str = '')
    {

        /*$route = $this->router->route($this->request);

        $params = array_values($route->params);

        $this->assertTrue(is_callable($route->callback));

        call_user_func_array($route->callback, $params);*/

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

        if ($this->request->method === 'HEAD') {
            ob_clean();
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

    // Simple path with trailing slash
    public function testPathRouteTrailingSlash()
    {
        $this->router->map('/path/', [$this, 'ok']);
        $this->request->url = '/path';

        $this->check('OK');
    }

    public function testGetRouteShortcut()
    {
        $this->router->get('/path', [$this, 'ok']);
        $this->request->url = '/path';
        $this->request->method = 'GET';

        $this->check('OK');
    }

    public function testHeadRouteShortcut()
    {
        $route = $this->router->get('/path', [$this, 'ok']);
        $this->assertEquals(['GET', 'HEAD'], $route->methods);
        $this->request->url = '/path';
        $this->request->method = 'HEAD';
        $this->check('');
    }

    // POST route
    public function testPostRoute()
    {
        $this->router->map('POST /', [$this, 'ok']);
        $this->request->url = '/';
        $this->request->method = 'POST';

        $this->check('OK');
    }

    public function testPostRouteShortcut()
    {
        $this->router->post('/path', [$this, 'ok']);
        $this->request->url = '/path';
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

    public function testPutRouteShortcut()
    {
        $this->router->put('/path', [$this, 'ok']);
        $this->request->url = '/path';
        $this->request->method = 'PUT';

        $this->check('OK');
    }

    public function testPatchRouteShortcut()
    {
        $this->router->patch('/path', [$this, 'ok']);
        $this->request->url = '/path';
        $this->request->method = 'PATCH';

        $this->check('OK');
    }

    public function testDeleteRouteShortcut()
    {
        $this->router->delete('/path', [$this, 'ok']);
        $this->request->url = '/path';
        $this->request->method = 'DELETE';

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

    public function testUrlParametersWithEncodedSlash()
    {
        $this->router->map('/redirect/@id', function ($id) {
            echo $id;
        });
        $this->request->url = '/redirect/before%2Fafter';

        $this->check('before/after');
    }

    public function testUrlParametersWithRealSlash()
    {
        $this->router->map('/redirect/@id', function ($id) {
            echo $id;
        });
        $this->request->url = '/redirect/before/after';

        $this->check('404');
    }

    public function testUrlParametersWithJapanese()
    {
        $this->router->map('/わたしはひとです', function () {
            echo 'はい';
        });
        $this->request->url = '/わたしはひとです';

        $this->check('はい');
    }

    public function testUrlParametersWithJapaneseAndParam()
    {
        $this->router->map('/わたしはひとです/@name', function ($name) {
            echo $name;
        });
        $this->request->url = '/' . urlencode('わたしはひとです') . '/' . urlencode('ええ');

        $this->check('ええ');
    }

    // Passing URL parameters matched with regular expression for a URL containing Cyrillic letters:
    public function testRegExParametersCyrillic()
    {
        $this->router->map('/категория/@name:[абвгдеёжзийклмнопрстуфхцчшщъыьэюя]+', function ($name) {
            echo $name;
        });
        $this->request->url = '/' . urlencode('категория') . '/' . urlencode('цветя');

        $this->check('цветя');
    }

    public function testRegExOnlyCyrillicUrl()
    {
        $this->router->map('/категория/цветя', function () {
            echo 'цветя';
        });
        $this->request->url = '/категория/цветя';

        $this->check('цветя');
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

    public function testWildcardDuplicate()
    {
        $this->router->map('/account/*', [$this, 'ok']);
        $this->request->url = '/account/account/account';
        $this->check('OK');
    }

    public function testRouteWithLongQueryParamWithMultilineEncoded()
    {
        $this->router->map('GET /api/intune/hey', [$this, 'ok']);

        $error_description = 'error_description=AADSTS65004%3a+User+declined+to+consent+to+access+the';
        $error_description .= '+app.%0d%0aTrace+ID%3a+747c0cc1-ccbd-4e53-8e2f-48812eb24100%0d%0a';
        $error_description .= 'Correlation+ID%3a+362e3cb3-20ef-400b-904e-9983bd989184%0d%0a';
        $error_description .= 'Timestamp%3a+2022-09-08+09%3a58%3a12Z';

        $query_params = [
            'error=access_denied',
            $error_description,
            'error_uri=https%3a%2f%2flogin.microsoftonline.com%2ferror%3fcode%3d65004',
            'admin_consent=True',
            'state=x2EUE0fcSj#'
        ];

        $query_params = join('&', $query_params);

        $this->request->url = "/api/intune/hey?$query_params";
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

    public function testRouteBeingReturned()
    {
        $route = $this->router->map('/hi', function () {
        });
        $route_in_router = $this->router->getRoutes()[0];
        $this->assertSame($route, $route_in_router);
    }

    public function testRouteSetAlias()
    {
        $route = $this->router->map('/hi', function () {
        });
        $route->setAlias('hello');
        $this->assertEquals('hello', $route->alias);
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

    public function testGetAndClearRoutes()
    {
        $this->router->map('/path1', [$this, 'ok']);
        $this->router->map('/path2', [$this, 'ok']);
        $this->router->map('/path3', [$this, 'ok']);
        $this->router->map('/path4', [$this, 'ok']);
        $this->router->map('/path5', [$this, 'ok']);
        $this->router->map('/path6', [$this, 'ok']);
        $this->router->map('/path7', [$this, 'ok']);
        $this->router->map('/path8', [$this, 'ok']);
        $this->router->map('/path9', [$this, 'ok']);

        $routes = $this->router->getRoutes();
        $this->assertEquals(9, count($routes));

        $this->router->clear();

        $this->assertEquals(0, count($this->router->getRoutes()));
    }

    public function testResetRoutes()
    {
        $router = new class extends Router
        {
            public function getIndex()
            {
                return $this->index;
            }
        };

        $router->map('/path1', [$this, 'ok']);
        $router->map('/path2', [$this, 'ok']);
        $router->map('/path3', [$this, 'ok']);
        $router->map('/path4', [$this, 'ok']);
        $router->map('/path5', [$this, 'ok']);
        $router->map('/path6', [$this, 'ok']);
        $router->map('/path7', [$this, 'ok']);
        $router->map('/path8', [$this, 'ok']);
        $router->map('/path9', [$this, 'ok']);

        $router->next();
        $router->next();
        $router->next();

        $this->assertEquals(3, $router->getIndex());
        $router->reset();
        $this->assertEquals(0, $router->getIndex());
    }

    // Passing URL parameters
    public function testGroupRoutes()
    {
        $this->router->group('/user', function (Router $router) {
            $router->map('/@id', function ($id) {
                echo $id;
            });
            $router->map('/@id/@name', function ($id, $name) {
                echo $id . $name;
            });
        });
        $this->request->url = '/user/123';
        $this->check('123');
    }

    public function testGroupRouteWithEmptyMapPath()
    {
        $this->router->group('/user', function (Router $router) {
            $router->map('', function () {
                echo "I'm a little teapot";
            });
        });
        $this->request->url = '/user';
        $this->check('I\'m a little teapot');
    }

    public function testGroupRouteWithEmptyGetPath()
    {
        $this->router->group('/user', function (Router $router) {
            $router->get('', function () {
                echo "I'm a little teapot";
            });
        });
        $this->request->url = '/user';
        $this->request->method = 'GET';
        $this->check('I\'m a little teapot');
    }

    public function testGroupRouteWithEmptyMultipleMethodsPath()
    {
        $this->router->group('/user', function (Router $router) {
            $router->map('GET|POST ', function () {
                echo "I'm a little teapot";
            });
        });
        $this->request->url = '/user';
        $this->request->method = 'GET';
        $this->check('I\'m a little teapot');
    }

    public function testGroupRoutesMultiParams()
    {
        $this->router->group('/user', function (Router $router) {
            $router->map('/@id', function ($id) {
                echo $id;
            });
            $router->map('/@id/@name', function ($id, $name) {
                echo $id . $name;
            });
        });
        $this->request->url = '/user/123/abc';
        $this->check('123abc');
    }

    public function testGroupNestedRoutes()
    {
        $this->router->group('/client', function (Router $router) {
            $router->group('/user', function (Router $router) {
                $router->map('/@id', function ($id) {
                    echo $id;
                });
                $router->map('/@id/@name', function ($id, $name) {
                    echo $id . $name;
                });
            });
        });
        $this->request->url = '/client/user/123/abc';
        $this->check('123abc');
    }

    public function testGroupNestedRoutesWithCustomMethods()
    {
        $this->router->group('/client', function (Router $router) {
            $router->group('/user', function (Router $router) {
                $router->get('/@id', function ($id) {
                    echo $id;
                });
                $router->post('/@id/@name', function ($id, $name) {
                    echo $id . $name;
                });
            });
        });
        $this->request->url = '/client/user/123/abc';
        $this->request->method = 'POST';
        $this->check('123abc');
    }

    public function testGetUrlByAliasBadReferenceButCatchRecommendation()
    {
        $this->router->map('/path1', [$this, 'ok'], false, 'path1');
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No route found with alias: \'path2\'. Did you mean \'path1\'?');
        $this->router->getUrlByAlias('path2');
    }

    public function testRewindAndValid()
    {
        $this->router->map('/path1', [$this, 'ok']);
        $this->router->map('/path2', [$this, 'ok']);
        $this->router->map('/path3', [$this, 'ok']);

        $this->router->next();
        $this->router->next();
        $result = $this->router->valid();
        $this->assertTrue($result);
        $this->router->next();
        $result = $this->router->valid();
        $this->assertFalse($result);

        $this->router->rewind();
        $result = $this->router->valid();
        $this->assertTrue($result);
    }

    public function testGetRootUrlByAlias()
    {
        $this->router->map('/', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1');
        $this->assertEquals('/', $url);
    }

    public function testGetUrlByAliasNoMatches()
    {
        $this->router->map('/path1', [$this, 'ok'], false, 'path1');
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No route found with alias: \'path2\'');
        $this->router->getUrlByAlias('path2');
    }

    public function testGetUrlByAliasNoParams()
    {
        $this->router->map('/path1', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1');
        $this->assertEquals('/path1', $url);
    }

    public function testGetUrlByAliasSimpleParams()
    {
        $this->router->map('/path1/@id', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1', ['id' => 123]);
        $this->assertEquals('/path1/123', $url);
    }

    public function testGetUrlByAliasSimpleParamsWithNumber()
    {
        $this->router->map('/path1/@id1', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1', ['id1' => 123]);
        $this->assertEquals('/path1/123', $url);
    }

    public function testGetUrlByAliasSimpleOptionalParamsWithParam()
    {
        $this->router->map('/path1(/@id)', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1', ['id' => 123]);
        $this->assertEquals('/path1/123', $url);
    }

    public function testGetUrlByAliasSimpleOptionalParamsWithNumberWithParam()
    {
        $this->router->map('/path1(/@id1)', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1', ['id1' => 123]);
        $this->assertEquals('/path1/123', $url);
    }

    public function testGetUrlByAliasSimpleOptionalParamsNoParam()
    {
        $this->router->map('/path1(/@id)', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1');
        $this->assertEquals('/path1', $url);
    }

    public function testGetUrlByAliasSimpleOptionalParamsWithNumberNoParam()
    {
        $this->router->map('/path1(/@id1)', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1');
        $this->assertEquals('/path1', $url);
    }

    public function testGetUrlByAliasMultipleParams()
    {
        $this->router->map('/path1/@id/@name', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1', ['id' => 123, 'name' => 'abc']);
        $this->assertEquals('/path1/123/abc', $url);
    }

    public function testGetUrlByAliasMultipleComplexParams()
    {
        $this->router->map('/path1/@id:[0-9]+/@name:[a-zA-Z0-9]{5}', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1', ['id' => '123', 'name' => 'abc']);
        $this->assertEquals('/path1/123/abc', $url);
    }

    public function testGetUrlByAliasMultipleComplexParamsWithNumbers()
    {
        $this->router->map('/path1/@5id:[0-9]+/@n1ame:[a-zA-Z0-9]{5}', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1', ['5id' => '123', 'n1ame' => 'abc']);
        $this->assertEquals('/path1/123/abc', $url);
    }

    public function testGetUrlByAliasMultipleComplexOptionalParamsMissingOne()
    {
        $this->router->map('/path1(/@id:[0-9]+(/@name(/@crazy:[a-z]{5})))', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1', ['id' => '123', 'name' => 'abc']);
        $this->assertEquals('/path1/123/abc', $url);
    }

    public function testGetUrlByAliasMultipleComplexOptionalParamsAllParams()
    {
        $this->router->map('/path1(/@id:[0-9]+(/@name(/@crazy:[a-z]{5})))', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1', ['id' => '123', 'name' => 'abc', 'crazy' => 'xyz']);
        $this->assertEquals('/path1/123/abc/xyz', $url);
    }

    public function testGetUrlByAliasMultipleComplexOptionalParamsNoParams()
    {
        $this->router->map('/path1(/@id:[0-9]+(/@name(/@crazy:[a-z]{5})))', [$this, 'ok'], false, 'path1');
        $url = $this->router->getUrlByAlias('path1');
        $this->assertEquals('/path1', $url);
    }

    public function testGetUrlByAliasWithGroupSimpleParams()
    {
        $this->router->group('/path1/@id', function ($router) {
            $router->get('/@name', [$this, 'ok'], false, 'path1');
        });
        $url = $this->router->getUrlByAlias('path1', ['id' => 123, 'name' => 'abc']);

        $this->assertEquals('/path1/123/abc', $url);
    }
}
