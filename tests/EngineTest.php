<?php

declare(strict_types=1);

namespace tests;

use ErrorException;
use Exception;
use flight\database\PdoWrapper;
use flight\Engine;
use flight\net\Request;
use flight\net\Response;
use flight\util\Collection;
use InvalidArgumentException;
use PDOException;
use PHPUnit\Framework\TestCase;
use tests\classes\Container;
use tests\classes\ContainerDefault;

// phpcs:ignoreFile PSR2.Methods.MethodDeclaration.Underscore
class EngineTest extends TestCase
{
    public function setUp(): void
    {
        $_SERVER = [];
    }

    public function tearDown(): void
    {
        $_SERVER = [];
    }

    public function testInitBeforeStart()
    {
        $engine = new class extends Engine {
            public function getInitializedVar()
            {
                return $this->initialized;
            }
        };
        $this->assertTrue($engine->getInitializedVar());

		// we need to setup a dummy route
		$engine->route('/someRoute', function () { });
		$engine->request()->url = '/someRoute';
        $engine->start();

        $this->assertFalse($engine->router()->case_sensitive);
        $this->assertTrue($engine->response()->content_length);
    }

	public function testInitBeforeStartV2OutputBuffering()
    {
        $engine = new class extends Engine {
            public function getInitializedVar()
            {
                return $this->initialized;
            }
        };
		$engine->set('flight.v2.output_buffering', true);
        $this->assertTrue($engine->getInitializedVar());
        $engine->start();

		// This is a necessary evil because of how the v2 output buffer works.
		ob_end_clean();

        $this->assertFalse($engine->router()->case_sensitive);
        $this->assertTrue($engine->response()->content_length);
    }

    public function testHandleErrorNoErrorNumber()
    {
        $engine = new Engine();
        $result = $engine->handleError(0, '', '', 0);
        $this->assertFalse($result);
    }

    public function testHandleErrorWithException()
    {
        $engine = new Engine();
        $this->expectException(Exception::class);
        $this->expectExceptionCode(5);
        $this->expectExceptionMessage('thrown error message');
        $engine->handleError(5, 'thrown error message', '', 0);
    }

    public function testHandleException()
    {
        $engine = new Engine();
        $this->expectOutputRegex('~\<h1\>500 Internal Server Error\</h1\>[\s\S]*\<h3\>thrown exception message \(20\)\</h3\>~');
        $engine->handleException(new Exception('thrown exception message', 20));
    }

    public function testMapExistingMethod()
    {
        $engine = new Engine();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot override an existing framework method.');
        $engine->map('_start', function () {
        });
    }

    public function testRegisterExistingMethod()
    {
        $engine = new Engine();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot override an existing framework method.');
        $engine->register('_error', 'stdClass');
    }

    public function testSetArrayOfValues()
    {
        $engine = new Engine();
        $engine->set([ 'key1' => 'value1', 'key2' => 'value2']);
        $this->assertEquals('value1', $engine->get('key1'));
        $this->assertEquals('value2', $engine->get('key2'));
    }

    public function testStartWithRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/someRoute';

        $engine = new class extends Engine {
            public function getInitializedVar()
            {
                return $this->initialized;
            }
        };
        $engine->route('/someRoute', function () {
            echo 'i ran';
        }, true);
        $this->expectOutputString('i ran');
        $engine->start();
    }

    // n0nag0n - I don't know why this does what it does, but it's existing framework functionality 1/1/24
    public function testStartWithRouteButReturnedValueThrows404()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/someRoute';

        $engine = new class extends Engine {
            public function getInitializedVar()
            {
                return $this->initialized;
            }
        };
        $engine->route('/someRoute', function () {
            echo 'i ran';
            return true;
        }, true);
        $this->expectOutputString('<h1>404 Not Found</h1><h3>The page you have requested could not be found.</h3>');
        $engine->start();
    }
	
	public function testStartWithRouteButReturnedValueThrows404V2OutputBuffering()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/someRoute';

        $engine = new class extends Engine {
            public function getInitializedVar()
            {
                return $this->initialized;
            }
        };
		$engine->set('flight.v2.output_buffering', true);
        $engine->route('/someRoute', function () {
            echo 'i ran';
            return true;
        }, true);
        $this->expectOutputString('<h1>404 Not Found</h1><h3>The page you have requested could not be found.</h3>');
        $engine->start();
    }

    public function testStopWithCode()
    {
        $engine = new class extends Engine {
            public function getLoader()
            {
                return $this->loader;
            }
        };
        // doing this so we can overwrite some parts of the response
        $engine->getLoader()->register('response', function () {
            return new class extends Response {
                public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): self
                {
                    return $this;
                }
            };
        });
        $engine->response()->write('I am a teapot');
        $this->expectOutputString('I am a teapot');
        $engine->stop(500);
        $this->assertEquals(500, $engine->response()->status());
    }

	public function testStopWithCodeV2OutputBuffering()
    {
        $engine = new class extends Engine {
            public function getLoader()
            {
                return $this->loader;
            }
        };
        // doing this so we can overwrite some parts of the response
        $engine->getLoader()->register('response', function () {
            return new class extends Response {
                public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): self
                {
                    return $this;
                }
            };
        });
		$engine->set('flight.v2.output_buffering', true);
		$engine->route('/testRoute', function () use ($engine) {
			echo 'I am a teapot';
			$engine->stop(500);
		});
		$engine->request()->url = '/testRoute';
		$engine->start();
        $this->expectOutputString('I am a teapot');
        $this->assertEquals(500, $engine->response()->status());
    }

    public function testPostRoute()
    {
        $engine = new Engine();
        $engine->post('/someRoute', function () {
            echo 'i ran';
        }, true);
        $routes = $engine->router()->getRoutes();
        $this->assertEquals('POST', $routes[0]->methods[0]);
        $this->assertEquals('/someRoute', $routes[0]->pattern);
    }

    public function testPutRoute()
    {
        $engine = new Engine();
        $engine->put('/someRoute', function () {
            echo 'i ran';
        }, true);
        $routes = $engine->router()->getRoutes();
        $this->assertEquals('PUT', $routes[0]->methods[0]);
        $this->assertEquals('/someRoute', $routes[0]->pattern);
    }

    public function testPatchRoute()
    {
        $engine = new Engine();
        $engine->patch('/someRoute', function () {
            echo 'i ran';
        }, true);
        $routes = $engine->router()->getRoutes();
        $this->assertEquals('PATCH', $routes[0]->methods[0]);
        $this->assertEquals('/someRoute', $routes[0]->pattern);
    }

    public function testDeleteRoute()
    {
        $engine = new Engine();
        $engine->delete('/someRoute', function () {
            echo 'i ran';
        }, true);
        $routes = $engine->router()->getRoutes();
        $this->assertEquals('DELETE', $routes[0]->methods[0]);
        $this->assertEquals('/someRoute', $routes[0]->pattern);
    }

    public function testHeadRoute()
    {
        $engine = new Engine();
        $engine->route('GET /someRoute', function () {
            echo 'i ran';
        }, true);
        $engine->request()->method = 'HEAD';
        $engine->request()->url = '/someRoute';
        $engine->start();

        // No body should be sent
        $this->expectOutputString('');
    }

    public function testHalt()
    {
        $engine = new class extends Engine {
            public function getLoader()
            {
                return $this->loader;
            }
        };
        // doing this so we can overwrite some parts of the response
        $engine->getLoader()->register('response', function () {
            return new class extends Response {
                public function setRealHeader(
                    string $header_string,
                    bool $replace = true,
                    int $response_code = 0
                ): self {
                    return $this;
                }
            };
        });
        $engine->halt(500, '', false);
        $this->assertEquals(500, $engine->response()->status());
    }

    public function testRedirect()
    {
        $engine = new Engine();
        $engine->redirect('https://github.com', 302);
        $this->assertEquals('https://github.com', $engine->response()->headers()['Location']);
        $this->assertEquals(302, $engine->response()->status());
    }

    public function testRedirectWithBaseUrl()
    {
        $engine = new Engine();
        $engine->set('flight.base_url', '/subdirectory');
        $engine->redirect('/someRoute', 301);
        $this->assertEquals('/subdirectory/someRoute', $engine->response()->headers()['Location']);
        $this->assertEquals(301, $engine->response()->status());
    }

    public function testJsonRequestBody()
    {
        $engine = new Engine();
        $tmpfile = tmpfile();
        $stream_path = stream_get_meta_data($tmpfile)['uri'];
        file_put_contents($stream_path, '{"key1":"value1","key2":"value2"}');

        $engine->register('request', Request::class, [
            [
                'method' => 'POST',
                'url' => '/something/fancy',
                'base' => '/vagrant/public',
                'type' => 'application/json',
                'length' => 13,
                'data' => new Collection(),
                'query' => new Collection(),
                'stream_path' => $stream_path
            ]
        ]);
        $engine->post('/something/fancy', function () use ($engine) {
            echo $engine->request()->data->key1;
            echo $engine->request()->data->key2;
        });
        $engine->start();
        $this->expectOutputString('value1value2');
    }

    public function testJson()
    {
        $engine = new Engine();
        $engine->json(['key1' => 'value1', 'key2' => 'value2']);
        $this->assertEquals('application/json; charset=utf-8', $engine->response()->headers()['Content-Type']);
        $this->assertEquals(200, $engine->response()->status());
		$this->assertEquals('{"key1":"value1","key2":"value2"}', $engine->response()->getBody());
    }

	public function testJsonV2OutputBuffering()
    {
        $engine = new Engine();
		$engine->response()->v2_output_buffering = true;
        $engine->json(['key1' => 'value1', 'key2' => 'value2']);
        $this->expectOutputString('{"key1":"value1","key2":"value2"}');
        $this->assertEquals('application/json; charset=utf-8', $engine->response()->headers()['Content-Type']);
        $this->assertEquals(200, $engine->response()->status());
    }

    public function testJsonP()
    {
        $engine = new Engine();
        $engine->request()->query['jsonp'] = 'whatever';
        $engine->jsonp(['key1' => 'value1', 'key2' => 'value2']);
        $this->assertEquals('application/javascript; charset=utf-8', $engine->response()->headers()['Content-Type']);
        $this->assertEquals(200, $engine->response()->status());
		$this->assertEquals('whatever({"key1":"value1","key2":"value2"});', $engine->response()->getBody());
    }

	public function testJsonPV2OutputBuffering()
    {
        $engine = new Engine();
		$engine->response()->v2_output_buffering = true;
        $engine->request()->query['jsonp'] = 'whatever';
        $engine->jsonp(['key1' => 'value1', 'key2' => 'value2']);
        $this->expectOutputString('whatever({"key1":"value1","key2":"value2"});');
        $this->assertEquals('application/javascript; charset=utf-8', $engine->response()->headers()['Content-Type']);
        $this->assertEquals(200, $engine->response()->status());
    }

    public function testJsonpBadParam()
    {
        $engine = new Engine();
        $engine->jsonp(['key1' => 'value1', 'key2' => 'value2']);
        $this->assertEquals('({"key1":"value1","key2":"value2"});', $engine->response()->getBody());
        $this->assertEquals('application/javascript; charset=utf-8', $engine->response()->headers()['Content-Type']);
        $this->assertEquals(200, $engine->response()->status());
    }

	public function testJsonpBadParamV2OutputBuffering()
    {
        $engine = new Engine();
		$engine->response()->v2_output_buffering = true;
        $engine->jsonp(['key1' => 'value1', 'key2' => 'value2']);
        $this->expectOutputString('({"key1":"value1","key2":"value2"});');
        $this->assertEquals('application/javascript; charset=utf-8', $engine->response()->headers()['Content-Type']);
        $this->assertEquals(200, $engine->response()->status());
    }

    public function testEtagSimple()
    {
        $engine = new Engine();
        $engine->etag('etag');
        $this->assertEquals('"etag"', $engine->response()->headers()['ETag']);
    }

    public function testEtagWithHttpIfNoneMatch()
    {
        $engine = new Engine;
        $_SERVER['HTTP_IF_NONE_MATCH'] = 'etag';
        $engine->etag('etag');
        $this->assertTrue(empty($engine->response()->headers()['ETag']));
        $this->assertEquals(304, $engine->response()->status());
    }

    public function testLastModifiedSimple()
    {
        $engine = new Engine();
        $engine->lastModified(1234567890);
        $this->assertEquals('Fri, 13 Feb 2009 23:31:30 GMT', $engine->response()->headers()['Last-Modified']);
    }

    public function testLastModifiedWithHttpIfModifiedSince()
    {
        $engine = new Engine;
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = 'Fri, 13 Feb 2009 23:31:30 GMT';
        $engine->lastModified(1234567890);
		$this->assertTrue(empty($engine->response()->headers()['Last-Modified']));
        $this->assertEquals(304, $engine->response()->status());
    }

    public function testGetUrl()
    {
        $engine = new Engine();
        $engine->route('/path1/@param:[0-9]{3}', function () {
            echo 'I win';
        }, false, 'path1');
        $url = $engine->getUrl('path1', [ 'param' => 123 ]);
        $this->assertEquals('/path1/123', $url);
    }

    public function testGetUrlComplex()
    {
        $engine = new Engine();
        $engine->route('/item/@item_param:[a-z0-9]{16}/by-status/@token:[a-z0-9]{16}', function () {
            echo 'I win';
        }, false, 'path_item_1');
        $url = $engine->getUrl('path_item_1', [ 'item_param' => 1234567890123456, 'token' => 6543210987654321 ]);
        $this->assertEquals('/item/1234567890123456/by-status/6543210987654321', $url);
    }

    public function testGetUrlInsideRoute()
    {
        $engine = new Engine();
        $engine->route('/path1/@param:[0-9]{3}', function () {
            echo 'I win';
        }, false, 'path1');
        $found_url = '';
        $engine->route('/path1/@param:[0-9]{3}/path2', function () use ($engine, &$found_url) {

            // this should pull the param from the first route
            // since the param names are the same.
            $found_url = $engine->getUrl('path1');
        });
        $engine->request()->url = '/path1/123/path2';
        $engine->start();
        $this->assertEquals('/path1/123', $found_url);
    }

    public function testMiddlewareCallableFunction()
    {
        $engine = new Engine();
        $engine->route('/path1/@id', function ($id) {
            echo 'OK' . $id;
        })
            ->addMiddleware(function ($params) {
                echo 'before' . $params['id'];
            });
        $engine->request()->url = '/path1/123';
        $engine->start();
        $this->expectOutputString('before123OK123');
    }

    public function testMiddlewareCallableFunctionReturnFalse()
    {
        $engine = new class extends Engine {
        };
        $engine->route('/path1/@id', function ($id) {
            echo 'OK' . $id;
        })
            ->addMiddleware(function ($params) {
                echo 'before' . $params['id'];
                return false;
            });
        $engine->request()->url = '/path1/123';
        $engine->start();
        $this->expectOutputString('Forbidden');
        $this->assertEquals(403, $engine->response()->status());
    }

    public function testMiddlewareClassBefore()
    {
        $middleware = new class {
            public function before($params)
            {
                echo 'before' . $params['id'];
            }
        };
        $engine = new Engine();

        $engine->route('/path1/@id', function ($id) {
            echo 'OK' . $id;
        })
            ->addMiddleware($middleware);
        $engine->request()->url = '/path1/123';
        $engine->start();
        $this->expectOutputString('before123OK123');
    }

    public function testMiddlewareClassBeforeAndAfter()
    {
        $middleware = new class {
            public function before($params)
            {
                echo 'before' . $params['id'];
            }
            public function after($params)
            {
                echo 'after' . $params['id'];
            }
        };
        $engine = new Engine();

        $engine->route('/path1/@id', function ($id) {
            echo 'OK' . $id;
        })
            ->addMiddleware($middleware);
        $engine->request()->url = '/path1/123';
        $engine->start();
        $this->expectOutputString('before123OK123after123');
    }

    public function testMiddlewareClassAfter()
    {
        $middleware = new class {
            public function after($params)
            {
                echo 'after' . $params['id'];
            }
        };
        $engine = new Engine();

        $engine->route('/path1/@id', function ($id) {
            echo 'OK' . $id;
        })
            ->addMiddleware($middleware);
        $engine->request()->url = '/path1/123';
        $engine->start();
        $this->expectOutputString('OK123after123');
    }

    public function testMiddlewareClassAfterFailedCheck()
    {
        $middleware = new class {
            public function after($params)
            {
                echo 'after' . $params['id'];
                return false;
            }
        };
        $engine = new class extends Engine {
        };

        $engine->route('/path1/@id', function ($id) {
            echo 'OK' . $id;
        })
            ->addMiddleware($middleware);
        $engine->request()->url = '/path1/123';
        $engine->start();
        $this->assertEquals(403, $engine->response()->status());
        $this->expectOutputString('Forbidden');
    }

    public function testMiddlewareCallableFunctionMultiple()
    {
        $engine = new Engine();
        $engine->route('/path1/@id', function ($id) {
            echo 'OK' . $id;
        })
            ->addMiddleware(function ($params) {
                echo 'before1' . $params['id'];
            })
            ->addMiddleware(function ($params) {
                echo 'before2' . $params['id'];
            });
        $engine->request()->url = '/path1/123';
        $engine->start();
        $this->expectOutputString('before1123before2123OK123');
    }

    // Pay attention to the order on how the middleware is executed in this test.
    public function testMiddlewareClassCallableRouteMultiple()
    {
        $middleware = new class {
            public function before($params)
            {
                echo 'before' . $params['another_id'];
            }
            public function after($params)
            {
                echo 'after' . $params['id'];
            }
        };
        $middleware2 = new class {
            public function before($params)
            {
                echo 'before' . $params['id'];
            }
            public function after($params)
            {
                echo 'after' . $params['id'] . $params['another_id'];
            }
        };
        $engine = new Engine();
        $engine->route('/path1/@id/subpath1/@another_id', function () {
            echo 'OK';
        })->addMiddleware([ $middleware, $middleware2 ]);

        $engine->request()->url = '/path1/123/subpath1/456';
        $engine->start();
        $this->expectOutputString('before456before123OKafter123456after123');
    }

    public function testMiddlewareClassGroupRouteMultipleBooyah()
    {
        $middleware = new class {
            public function before($params)
            {
                echo 'before' . $params['another_id'];
            }
            public function after($params)
            {
                echo 'after' . $params['id'];
            }
        };
        $middleware2 = new class {
            public function before($params)
            {
                echo 'before' . $params['id'];
            }
            public function after($params)
            {
                echo 'after' . $params['id'] . $params['another_id'];
            }
        };
        $engine = new Engine();
        $engine->group('/path1/@id', function ($router) {
            $router->map('/subpath1/@another_id', function () {
                echo 'OK';
            });
            $router->map('/@cool_id', function () {
                echo 'OK';
            });
        }, [ $middleware, $middleware2 ]);

        $engine->request()->url = '/path1/123/subpath1/456';
        $engine->start();
        $this->expectOutputString('before456before123OKafter123456after123');
    }

    public function testContainerBadClass() {
        $engine = new Engine();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("\$containerHandler must be of type callable or instance \\Psr\\Container\\ContainerInterface");
        $engine->registerContainerHandler('BadClass');
    }

    public function testContainerDice() {
        $engine = new Engine();
        $dice = new \Dice\Dice();
        $dice = $dice->addRules([
            PdoWrapper::class => [
                'shared' => true,
                'constructParams' => [ 'sqlite::memory:' ]
            ]
        ]);
        $engine->registerContainerHandler(function ($class, $params) use ($dice) {
            return $dice->create($class, $params);
        });
        
        $engine->route('/container', Container::class.'->testTheContainer');
        $engine->request()->url = '/container';
        $engine->start();

        $this->expectOutputString('yay! I injected a collection, and it has 1 items');
    }

    public function testContainerDicePdoWrapperTest() {
        $engine = new Engine();
        $dice = new \Dice\Dice();
        $dice = $dice->addRules([
            PdoWrapper::class => [
                'shared' => true,
                'constructParams' => [ 'sqlite::memory:' ]
            ]
        ]);
        $engine->registerContainerHandler(function ($class, $params) use ($dice) {
            return $dice->create($class, $params);
        });
        
        $engine->route('/container', Container::class.'->testThePdoWrapper');
        $engine->request()->url = '/container';
        $engine->start();

        $this->expectOutputString('Yay! I injected a PdoWrapper, and it returned the number 5 from the database!');
    }

    public function testContainerDiceFlightEngine() {
        $engine = new Engine();
        $engine->set('test_me_out', 'You got it boss!');
        $dice = new \Dice\Dice();
        $dice = $dice->addRule('*', [
            'substitutions' => [
                Engine::class => $engine
            ]
        ]);
        $engine->registerContainerHandler(function ($class, $params) use ($dice) {
            return $dice->create($class, $params);
        });
        
        $engine->route('/container', ContainerDefault::class.'->echoTheContainer');
        $engine->request()->url = '/container';
        $engine->start();

        $this->expectOutputString('You got it boss!');
    }

    public function testContainerDicePdoWrapperTestBadParams() {
        $engine = new Engine();
        $dice = new \Dice\Dice();
        $engine->registerContainerHandler(function ($class, $params) use ($dice) {
            return $dice->create($class, $params);
        });
        
        $engine->route('/container', Container::class.'->testThePdoWrapper');
        $engine->request()->url = '/container';

        // php 7.4 will throw a PDO exception, but php 8 will throw an ErrorException
        if(version_compare(PHP_VERSION, '8.0.0', '<')) {
            $this->expectException(PDOException::class);
            $this->expectExceptionMessageMatches("/invalid data source name/");
        } else {
            $this->expectException(ErrorException::class);
            $this->expectExceptionMessageMatches("/Passing null to parameter/");
        }

        $engine->start();
    }

    public function testContainerDiceBadClass() {
        $engine = new Engine();
        $dice = new \Dice\Dice();
        $engine->registerContainerHandler(function ($class, $params) use ($dice) {
            return $dice->create($class, $params);
        });
        
        $engine->route('/container', 'BadClass->testTheContainer');
        $engine->request()->url = '/container';
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Class 'BadClass' not found. Is it being correctly autoloaded with Flight::path()?");
        
        $engine->start();
    }

    public function testContainerDiceBadMethod() {
        $engine = new Engine();
        $dice = new \Dice\Dice();
        $dice = $dice->addRules([
            PdoWrapper::class => [
                'shared' => true,
                'constructParams' => [ 'sqlite::memory:' ]
            ]
        ]);
        $engine->registerContainerHandler(function ($class, $params) use ($dice) {
            return $dice->create($class, $params);
        });
        
        $engine->route('/container', Container::class.'->badMethod');
        $engine->request()->url = '/container';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Class found, but method 'tests\classes\Container::badMethod' not found.");

        $engine->start();
    }

    public function testContainerPsr11() {
        $engine = new Engine();
        $container = new \League\Container\Container();
        $container->add(Container::class)->addArgument(Collection::class)->addArgument(PdoWrapper::class);
        $container->add(Collection::class);
        $container->add(PdoWrapper::class)->addArgument('sqlite::memory:');
        $engine->registerContainerHandler($container);
        
        $engine->route('/container', Container::class.'->testTheContainer');
        $engine->request()->url = '/container';
        $engine->start();

        $this->expectOutputString('yay! I injected a collection, and it has 1 items');
    }

    public function testContainerPsr11ClassNotFound() {
        $engine = new Engine();
        $container = new \League\Container\Container();
        $container->add(Container::class)->addArgument(Collection::class);
        $container->add(Collection::class);
        $engine->registerContainerHandler($container);
        
        $engine->route('/container', 'BadClass->testTheContainer');
        $engine->request()->url = '/container';
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Class 'BadClass' not found. Is it being correctly autoloaded with Flight::path()?");
        
        $engine->start();
    }

    public function testContainerPsr11MethodNotFound() {
        $engine = new Engine();
        $container = new \League\Container\Container();
        $container->add(Container::class)->addArgument(Collection::class)->addArgument(PdoWrapper::class);
        $container->add(Collection::class);
        $container->add(PdoWrapper::class)->addArgument('sqlite::memory:');
        $engine->registerContainerHandler($container);
        
        $engine->route('/container', Container::class.'->badMethod');
        $engine->request()->url = '/container';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Class found, but method 'tests\classes\Container::badMethod' not found.");

        $engine->start();
    }
}
