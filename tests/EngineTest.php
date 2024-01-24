<?php

use flight\Engine;
use flight\net\Response;

/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2024, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */


class EngineTest extends PHPUnit\Framework\TestCase
{
	public function setUp(): void {
		$_SERVER = [];
	}

	public function tearDown(): void {
		$_SERVER = [];
	}
	public function testInitBeforeStart() {
		$engine = new class extends Engine {
			public function getInitializedVar() {
				return $this->initialized;
			}
		};
		$this->assertTrue($engine->getInitializedVar());
		$engine->start();

		// this is necessary cause it doesn't actually send the response correctly
		ob_end_clean();

		$this->assertFalse($engine->router()->case_sensitive);
		$this->assertTrue($engine->response()->content_length);
	}

	public function testHandleErrorNoErrorNumber() {
		$engine = new Engine();
		$result = $engine->handleError(0, '', '', 0);
		$this->assertFalse($result);
	}

	public function testHandleErrorWithException() {
		$engine = new Engine();
		$this->expectException(Exception::class);
		$this->expectExceptionCode(5);
		$this->expectExceptionMessage('thrown error message');
		$engine->handleError(5, 'thrown error message', '', 0);
	}

	public function testHandleException() {
		$engine = new Engine();
		$regex_message = preg_quote('<h1>500 Internal Server Error</h1><h3>thrown exception message (20)</h3>');
		$this->expectOutputRegex('~'.$regex_message.'~');
		$engine->handleException(new Exception('thrown exception message', 20));
	}

	public function testMapExistingMethod() {
		$engine = new Engine();
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Cannot override an existing framework method.');
		$engine->map('_start', function() {});
	}

	public function testRegisterExistingMethod() {
		$engine = new Engine();
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Cannot override an existing framework method.');
		$engine->register('_error', 'stdClass');
	}

	public function testSetArrayOfValues() {
		$engine = new Engine();
		$engine->set([ 'key1' => 'value1', 'key2' => 'value2']);
		$this->assertEquals('value1', $engine->get('key1'));
		$this->assertEquals('value2', $engine->get('key2'));
	}

	public function testStartWithRoute() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/someRoute';

		$engine = new class extends Engine {
			public function getInitializedVar() {
				return $this->initialized;
			}
		};
		$engine->route('/someRoute', function() { echo 'i ran'; }, true);
		$this->expectOutputString('i ran');
		$engine->start();
	}

	// n0nag0n - I don't know why this does what it does, but it's existing framework functionality 1/1/24
	public function testStartWithRouteButReturnedValueThrows404() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/someRoute';

		$engine = new class extends Engine {
			public function getInitializedVar() {
				return $this->initialized;
			}
		};
		$engine->route('/someRoute', function() { echo 'i ran'; return true; }, true);
		$this->expectOutputString('<h1>404 Not Found</h1><h3>The page you have requested could not be found.</h3>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ');
		$engine->start();
	}

	public function testStopWithCode() {
		$engine = new class extends Engine {
			public function getLoader() {
				return $this->loader;
			}
		};
		// doing this so we can overwrite some parts of the response
		$engine->getLoader()->register('response', function() {
			return new class extends \flight\net\Response {
				public function __construct() {}
				public function setRealHeader($header_string, $replace = true, $response_code = 0)
				{
					return $this;
				}
			};
		});
		// need to add another one of these because _stop() stops and gets clean, but $response->send() does too.....
		ob_start();
		$engine->response()->write('I am a teapot');
		$this->expectOutputString('I am a teapot');
		$engine->stop(500);
		$this->assertEquals(500, $engine->response()->status());
	}

	public function testPostRoute() {
		$engine = new Engine();
		$engine->post('/someRoute', function() { echo 'i ran'; }, true);
		$routes = $engine->router()->getRoutes();
		$this->assertEquals('POST', $routes[0]->methods[0]);
		$this->assertEquals('/someRoute', $routes[0]->pattern);
	}

	public function testPutRoute() {
		$engine = new Engine();
		$engine->put('/someRoute', function() { echo 'i ran'; }, true);
		$routes = $engine->router()->getRoutes();
		$this->assertEquals('PUT', $routes[0]->methods[0]);
		$this->assertEquals('/someRoute', $routes[0]->pattern);
	}

	public function testPatchRoute() {
		$engine = new Engine();
		$engine->patch('/someRoute', function() { echo 'i ran'; }, true);
		$routes = $engine->router()->getRoutes();
		$this->assertEquals('PATCH', $routes[0]->methods[0]);
		$this->assertEquals('/someRoute', $routes[0]->pattern);
	}

	public function testDeleteRoute() {
		$engine = new Engine();
		$engine->delete('/someRoute', function() { echo 'i ran'; }, true);
		$routes = $engine->router()->getRoutes();
		$this->assertEquals('DELETE', $routes[0]->methods[0]);
		$this->assertEquals('/someRoute', $routes[0]->pattern);
	}

	public function testHalt() {
		$engine = new class extends Engine {
			public function getLoader() {
				return $this->loader;
			}
		};
		// doing this so we can overwrite some parts of the response
		$engine->getLoader()->register('response', function() {
			return new class extends \flight\net\Response {
				public function __construct() {}
				public function setRealHeader($header_string, $replace = true, $response_code = 0)
				{
					return $this;
				}
			};
		});
		$this->expectOutputString('skip---exit');
		$engine->halt(500, 'skip---exit');
		$this->assertEquals(500, $engine->response()->status());
	}

	public function testRedirect() {
		$engine = new Engine();
		$engine->redirect('https://github.com', 302);
		$this->assertEquals('https://github.com', $engine->response()->headers()['Location']);
		$this->assertEquals(302, $engine->response()->status());
	}

	public function testRedirectWithBaseUrl() {
		$engine = new Engine();
		$engine->set('flight.base_url', '/subdirectory');
		$engine->redirect('/someRoute', 301);
		$this->assertEquals('/subdirectory/someRoute', $engine->response()->headers()['Location']);
		$this->assertEquals(301, $engine->response()->status());
	}

	public function testJson() {
		$engine = new Engine();
		$engine->json(['key1' => 'value1', 'key2' => 'value2']);
		$this->expectOutputString('{"key1":"value1","key2":"value2"}');
		$this->assertEquals('application/json; charset=utf-8', $engine->response()->headers()['Content-Type']);
		$this->assertEquals(200, $engine->response()->status());
	}

	public function testJsonP() {
		$engine = new Engine();
		$engine->request()->query['jsonp'] = 'whatever';
		$engine->jsonp(['key1' => 'value1', 'key2' => 'value2']);
		$this->expectOutputString('whatever({"key1":"value1","key2":"value2"});');
		$this->assertEquals('application/javascript; charset=utf-8', $engine->response()->headers()['Content-Type']);
		$this->assertEquals(200, $engine->response()->status());
	}

	public function testJsonPBadParam() {
		$engine = new Engine();
		$engine->jsonp(['key1' => 'value1', 'key2' => 'value2']);
		$this->expectOutputString('({"key1":"value1","key2":"value2"});');
		$this->assertEquals('application/javascript; charset=utf-8', $engine->response()->headers()['Content-Type']);
		$this->assertEquals(200, $engine->response()->status());
	}

	public function testEtagSimple() {
		$engine = new Engine();
		$engine->etag('etag');
		$this->assertEquals('"etag"', $engine->response()->headers()['ETag']);
	}

	public function testEtagWithHttpIfNoneMatch() {
		// just need this not to exit...
		$engine = new class extends Engine {
			public function _halt($code = 200, $message = '')
			{
				$this->response()->status($code);
				$this->response()->write($message);
			}
		};
		$_SERVER['HTTP_IF_NONE_MATCH'] = 'etag';
		$engine->etag('etag');
		$this->assertEquals('"etag"', $engine->response()->headers()['ETag']);
		$this->assertEquals(304, $engine->response()->status());
	}

	public function testLastModifiedSimple() {
		$engine = new Engine();
		$engine->lastModified(1234567890);
		$this->assertEquals('Fri, 13 Feb 2009 23:31:30 GMT', $engine->response()->headers()['Last-Modified']);
	}

	public function testLastModifiedWithHttpIfModifiedSince() {
		// just need this not to exit...
		$engine = new class extends Engine {
			public function _halt($code = 200, $message = '')
			{
				$this->response()->status($code);
				$this->response()->write($message);
			}
		};
		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = 'Fri, 13 Feb 2009 23:31:30 GMT';
		$engine->lastModified(1234567890);
		$this->assertEquals('Fri, 13 Feb 2009 23:31:30 GMT', $engine->response()->headers()['Last-Modified']);
		$this->assertEquals(304, $engine->response()->status());
	}

	public function testGetUrl() {
		$engine = new Engine;
		$engine->route('/path1/@param:[0-9]{3}', function() { echo 'I win'; }, false, 'path1');
		$url = $engine->getUrl('path1', [ 'param' => 123 ]);
		$this->assertEquals('/path1/123', $url);
	}

	public function testMiddlewareCallableFunction() {
		$engine = new Engine();
		$engine->route('/path1/@id', function($id) { echo 'OK'.$id; })
			->addMiddleware(function($params) { echo 'before'.$params['id']; });
		$engine->request()->url = '/path1/123';
		$engine->start();
		$this->expectOutputString('before123OK123');
	}

	public function testMiddlewareCallableFunctionReturnFalse() {
		$engine = new class extends Engine {
			public function _halt($code = 200, $message = '')
			{
				$this->response()->status($code);
				$this->response()->write($message);
			}
		};
		$engine->route('/path1/@id', function($id) { echo 'OK'.$id; })
			->addMiddleware(function($params) { echo 'before'.$params['id']; return false; });
		$engine->request()->url = '/path1/123';
		$engine->start();
		$this->expectOutputString('Forbiddenbefore123');
		$this->assertEquals(403, $engine->response()->status());
	}

	public function testMiddlewareClassBefore() {
		$middleware = new class {
			public function before($params) {
				echo 'before'.$params['id'];
			}
		};
		$engine = new Engine();
		
		$engine->route('/path1/@id',  function($id) { echo 'OK'.$id; })
			->addMiddleware($middleware);
		$engine->request()->url = '/path1/123';
		$engine->start();
		$this->expectOutputString('before123OK123');
	}

	public function testMiddlewareClassBeforeAndAfter() {
		$middleware = new class {
			public function before($params) {
				echo 'before'.$params['id'];
			}
			public function after($params) {
				echo 'after'.$params['id'];
			}
		};
		$engine = new Engine();
		
		$engine->route('/path1/@id',  function($id) { echo 'OK'.$id; })
			->addMiddleware($middleware);
		$engine->request()->url = '/path1/123';
		$engine->start();
		$this->expectOutputString('before123OK123after123');
	}

	public function testMiddlewareClassAfter() {
		$middleware = new class {
			public function after($params) {
				
				echo 'after'.$params['id'];
			}
		};
		$engine = new Engine();
		
		$engine->route('/path1/@id',  function($id) { echo 'OK'.$id; })
			->addMiddleware($middleware);
		$engine->request()->url = '/path1/123';
		$engine->start();
		$this->expectOutputString('OK123after123');
	}

	public function testMiddlewareClassAfterFailedCheck() {
		$middleware = new class {
			public function after($params) {
				echo 'after'.$params['id'];
				return false;
			}
		};
		$engine = new class extends Engine {
			public function _halt($code = 200, $message = '')
			{
				$this->response()->status($code);
				$this->response()->write($message);
			}
		};
		
		$engine->route('/path1/@id',  function($id) { echo 'OK'.$id; })
			->addMiddleware($middleware);
		$engine->request()->url = '/path1/123';
		$engine->start();
		$this->assertEquals(403, $engine->response()->status());
		$this->expectOutputString('ForbiddenOK123after123');
	}

	public function testMiddlewareCallableFunctionMultiple() {
		$engine = new Engine();
		$engine->route('/path1/@id', function($id) { echo 'OK'.$id; })
			->addMiddleware(function($params) { echo 'before1'.$params['id']; })
			->addMiddleware(function($params) { echo 'before2'.$params['id']; });
		$engine->request()->url = '/path1/123';
		$engine->start();
		$this->expectOutputString('before1123before2123OK123');
	}

	// Pay attention to the order on how the middleware is executed in this test.
	public function testMiddlewareClassCallableRouteMultiple() {
		$middleware = new class {
			public function before($params) {
				echo 'before'.$params['another_id'];
			}
			public function after($params) {
				echo 'after'.$params['id'];
			}
		};
		$middleware2 = new class {
			public function before($params) {
				echo 'before'.$params['id'];
			}
			public function after($params) {
				echo 'after'.$params['id'].$params['another_id'];
			}
		};
		$engine = new Engine();
		$engine->route('/path1/@id/subpath1/@another_id',  function() { echo 'OK'; })->addMiddleware([ $middleware, $middleware2 ]);
		
		$engine->request()->url = '/path1/123/subpath1/456';
		$engine->start();
		$this->expectOutputString('before456before123OKafter123456after123');
	}

	public function testMiddlewareClassGroupRouteMultipleBooyah() {
		$middleware = new class {
			public function before($params) {
				echo 'before'.$params['another_id'];
			}
			public function after($params) {
				echo 'after'.$params['id'];
			}
		};
		$middleware2 = new class {
			public function before($params) {
				echo 'before'.$params['id'];
			}
			public function after($params) {
				echo 'after'.$params['id'].$params['another_id'];
			}
		};
		$engine = new Engine();
		$engine->group('/path1/@id', function($router) {
			$router->map('/subpath1/@another_id',  function() { echo 'OK'; });
			$router->map('/@cool_id',  function() { echo 'OK'; });
		}, [ $middleware, $middleware2 ]);
		
		$engine->request()->url = '/path1/123/subpath1/456';
		$engine->start();
		$this->expectOutputString('before456before123OKafter123456after123');
	}
}
