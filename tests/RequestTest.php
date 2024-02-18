<?php

declare(strict_types=1);

namespace tests;

use flight\net\Request;
use flight\util\Collection;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        $_SERVER = [];
        $_REQUEST = [];
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

    protected function tearDown(): void
    {
        unset($_REQUEST);
        unset($_SERVER);
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

    public function testInitUrlSameAsBaseDirectory()
    {
        $request = new Request([
            'url' => '/vagrant/public/flightphp',
            'base' => '/vagrant/public',
            'query' => new Collection(),
            'type' => ''
        ]);
        $this->assertEquals('/flightphp', $request->url);
    }

    public function testInitNoUrl()
    {
        $request = new Request([
            'url' => '',
            'base' => '/vagrant/public',
            'type' => ''
        ]);
        $this->assertEquals('/', $request->url);
    }

    public function testInitWithJsonBody()
    {
        // create dummy file to pull request body from
        $tmpfile = tmpfile();
        $stream_path = stream_get_meta_data($tmpfile)['uri'];
        file_put_contents($stream_path, '{"foo":"bar"}');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request([
            'url' => '/something/fancy',
            'base' => '/vagrant/public',
            'type' => 'application/json',
            'length' => 13,
            'data' => new Collection(),
            'query' => new Collection(),
            'stream_path' => $stream_path
        ]);
        $this->assertEquals([ 'foo' => 'bar' ], $request->data->getData());
        $this->assertEquals('{"foo":"bar"}', $request->getBody());
    }

    public function testGetHeader()
    {
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom header value';
        $request = new Request();
        $this->assertEquals('custom header value', $request->getHeader('X-Custom-Header'));

        // or the headers that are already in $_SERVER
        $this->assertEquals('XMLHttpRequest', $request->getHeader('X-REqUesTed-WiTH'));
        $this->assertEquals('32.32.32.32', $request->header('X-Forwarded-For'));

        // default values
        $this->assertEquals('default value', $request->header('X-Non-Existent-Header', 'default value'));
    }

    public function testGetHeaders()
    {
        $_SERVER = [];
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom header value';
        $request = new Request();
        $this->assertEquals(['X-Custom-Header' => 'custom header value'], $request->getHeaders());
    }

    public function testGetHeadersWithEmptyServer()
    {
        $_SERVER = [];
        $request = new Request();
        $this->assertEquals([], $request->getHeaders());
    }

    public function testGetHeadersWithEmptyHeader()
    {
        $_SERVER = [];
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = '';
        $request = new Request();
        $this->assertEquals(['X-Custom-Header' => ''], $request->headers());
    }

    public function testGetHeadersWithMultipleHeaders()
    {
        $_SERVER = [];
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom header value';
        $_SERVER['HTTP_X_CUSTOM_HEADER2'] = 'custom header value 2';
        $request = new Request();
        $this->assertEquals([
            'X-Custom-Header' => 'custom header value',
            'X-Custom-Header2' => 'custom header value 2'
        ], $request->getHeaders());
    }

    public function testGetFullUrlNoHttps()
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/page?id=1';
        $request = new Request();
        $this->assertEquals('http://example.com/page?id=1', $request->getFullUrl());
    }

    public function testGetFullUrlWithHttps()
    {
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        $_SERVER['REQUEST_URI'] = '/page?id=1';
        $_SERVER['HTTPS'] = 'on';
        $request = new Request();
        $this->assertEquals('https://localhost:8000/page?id=1', $request->getFullUrl());
    }

    public function testGetBaseUrlNoHttps()
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/page?id=1';
        $request = new Request();
        $this->assertEquals('http://example.com', $request->getBaseUrl());
    }

    public function testGetBaseUrlWithHttps()
    {
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        $_SERVER['REQUEST_URI'] = '/page?id=1';
        $_SERVER['HTTPS'] = 'on';
        $request = new Request();
        $this->assertEquals('https://localhost:8000', $request->getBaseUrl());
    }
}
