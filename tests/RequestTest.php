<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\net\Request;

require_once 'vendor/autoload.php';
require_once __DIR__ . '/../flight/autoload.php';

class RequestTest extends PHPUnit\Framework\TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '32.32.32.32';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['CONTENT_TYPE'] = '';

        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $this->request = new Request();
    }

    public function testDefaults()
    {
        self::assertEquals('/', $this->request->url);
        self::assertEquals('/', $this->request->base);
        self::assertEquals('GET', $this->request->method);
        self::assertEquals('', $this->request->referrer);
        self::assertTrue($this->request->ajax);
        self::assertEquals('http', $this->request->scheme);
        self::assertEquals('', $this->request->type);
        self::assertEquals(0, $this->request->length);
        self::assertFalse($this->request->secure);
        self::assertEquals('', $this->request->accept);
        self::assertEquals('example.com', $this->request->host);
    }

    public function testIpAddress()
    {
        self::assertEquals('8.8.8.8', $this->request->ip);
        self::assertEquals('32.32.32.32', $this->request->proxy_ip);
    }

    public function testSubdirectory()
    {
        $_SERVER['SCRIPT_NAME'] = '/subdir/index.php';

        $request = new Request();

        self::assertEquals('/subdir', $request->base);
    }

    public function testQueryParameters()
    {
        $_SERVER['REQUEST_URI'] = '/page?id=1&name=bob';

        $request = new Request();

        self::assertEquals('/page?id=1&name=bob', $request->url);
        self::assertEquals(1, $request->query->id);
        self::assertEquals('bob', $request->query->name);
    }

    public function testCollections()
    {
        $_SERVER['REQUEST_URI'] = '/page?id=1';

        $_GET['q'] = 1;
        $_POST['q'] = 1;
        $_COOKIE['q'] = 1;
        $_FILES['q'] = 1;

        $request = new Request();

        self::assertEquals(1, $request->query->q);
        self::assertEquals(1, $request->query->id);
        self::assertEquals(1, $request->data->q);
        self::assertEquals(1, $request->cookies->q);
        self::assertEquals(1, $request->files->q);
    }

    public function testJsonWithEmptyBody()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $request = new Request();

        self::assertSame([], $request->data->getData());
    }

    public function testMethodOverrideWithHeader()
    {
        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $request = new Request();

        self::assertEquals('PUT', $request->method);
    }

    public function testMethodOverrideWithPost()
    {
        $_REQUEST['_method'] = 'PUT';

        $request = new Request();

        self::assertEquals('PUT', $request->method);
    }

    public function testHttps()
    {
        $_SERVER['HTTPS'] = 'on';
        $request = new Request();
        self::assertEquals('https', $request->scheme);
        $_SERVER['HTTPS'] = 'off';
        $request = new Request();
        self::assertEquals('http', $request->scheme);

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $request = new Request();
        self::assertEquals('https', $request->scheme);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        $request = new Request();
        self::assertEquals('http', $request->scheme);

        $_SERVER['HTTP_FRONT_END_HTTPS'] = 'on';
        $request = new Request();
        self::assertEquals('https', $request->scheme);
        $_SERVER['HTTP_FRONT_END_HTTPS'] = 'off';
        $request = new Request();
        self::assertEquals('http', $request->scheme);

        $_SERVER['REQUEST_SCHEME'] = 'https';
        $request = new Request();
        self::assertEquals('https', $request->scheme);
        $_SERVER['REQUEST_SCHEME'] = 'http';
        $request = new Request();
        self::assertEquals('http', $request->scheme);
    }
}
