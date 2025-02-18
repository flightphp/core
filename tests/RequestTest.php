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
        $this->assertEquals('/', $this->request->url);
        $this->assertEquals('/', $this->request->base);
        $this->assertEquals('GET', $this->request->method);
        $this->assertEquals('', $this->request->referrer);
        $this->assertTrue($this->request->ajax);
        $this->assertEquals('http', $this->request->scheme);
        $this->assertEquals('', $this->request->type);
        $this->assertEquals(0, $this->request->length);
        $this->assertFalse($this->request->secure);
        $this->assertEquals('', $this->request->accept);
        $this->assertEquals('example.com', $this->request->host);
    }

    public function testIpAddress()
    {
        $this->assertEquals('8.8.8.8', $this->request->ip);
        $this->assertEquals('32.32.32.32', $this->request->proxy_ip);
    }

    public function testSubdirectory()
    {
        $_SERVER['SCRIPT_NAME'] = '/subdir/index.php';

        $request = new Request();

        $this->assertEquals('/subdir', $request->base);
    }

    public function testQueryParameters()
    {
        $_SERVER['REQUEST_URI'] = '/page?id=1&name=bob';

        $request = new Request();

        $this->assertEquals('/page?id=1&name=bob', $request->url);
        $this->assertEquals(1, $request->query->id);
        $this->assertEquals('bob', $request->query->name);
    }

    public function testCollections()
    {
        $_SERVER['REQUEST_URI'] = '/page?id=1';

        $_GET['q'] = 1;
        $_POST['q'] = 1;
        $_COOKIE['q'] = 1;
        $_FILES['q'] = 1;

        $request = new Request();

        $this->assertEquals(1, $request->query->q);
        $this->assertEquals(1, $request->query->id);
        $this->assertEquals(1, $request->data->q);
        $this->assertEquals(1, $request->cookies->q);
        $this->assertEquals(1, $request->files->q);
    }

    public function testJsonWithEmptyBody()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $request = new Request();

        $this->assertSame([], $request->data->getData());
    }

    public function testMethodOverrideWithHeader()
    {
        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $request = new Request();

        $this->assertEquals('PUT', $request->method);
    }

    public function testMethodOverrideWithPost()
    {
        $_REQUEST['_method'] = 'PUT';

        $request = new Request();

        $this->assertEquals('PUT', $request->method);
    }

    public function testHttps()
    {
        $_SERVER['HTTPS'] = 'on';
        $request = new Request();
        $this->assertEquals('https', $request->scheme);
        $_SERVER['HTTPS'] = 'off';
        $request = new Request();
        $this->assertEquals('http', $request->scheme);

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $request = new Request();
        $this->assertEquals('https', $request->scheme);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        $request = new Request();
        $this->assertEquals('http', $request->scheme);

        $_SERVER['HTTP_FRONT_END_HTTPS'] = 'on';
        $request = new Request();
        $this->assertEquals('https', $request->scheme);
        $_SERVER['HTTP_FRONT_END_HTTPS'] = 'off';
        $request = new Request();
        $this->assertEquals('http', $request->scheme);

        $_SERVER['REQUEST_SCHEME'] = 'https';
        $request = new Request();
        $this->assertEquals('https', $request->scheme);
        $_SERVER['REQUEST_SCHEME'] = 'http';
        $request = new Request();
        $this->assertEquals('http', $request->scheme);
    }

    public function testInitUrlSameAsBaseDirectory()
    {
        $request = new Request([
            'url' => '/vagrant/public/flightphp',
            'base' => '/vagrant/public',
            'query' => new Collection(),
            'type' => '',
			'method' => 'GET'
        ]);
        $this->assertEquals('/flightphp', $request->url);
    }

    public function testInitNoUrl()
    {
        $request = new Request([
            'url' => '',
            'base' => '/vagrant/public',
            'type' => '',
			'method' => 'GET'
        ]);
        $this->assertEquals('/', $request->url);
    }

    public function testInitWithJsonBody()
    {
        // create dummy file to pull request body from
        $tmpfile = tmpfile();
        $stream_path = stream_get_meta_data($tmpfile)['uri'];
        file_put_contents($stream_path, '{"foo":"bar"}');
        $request = new Request([
            'url' => '/something/fancy',
            'base' => '/vagrant/public',
            'type' => 'application/json',
            'length' => 13,
            'data' => new Collection(),
            'query' => new Collection(),
            'stream_path' => $stream_path,
			'method' => 'POST'
        ]);
        $this->assertEquals([ 'foo' => 'bar' ], $request->data->getData());
        $this->assertEquals('{"foo":"bar"}', $request->getBody());
    }

	public function testInitWithFormBody()
    {
        // create dummy file to pull request body from
        $tmpfile = tmpfile();
        $stream_path = stream_get_meta_data($tmpfile)['uri'];
        file_put_contents($stream_path, 'foo=bar&baz=qux');
        $request = new Request([
            'url' => '/something/fancy',
            'base' => '/vagrant/public',
            'type' => 'application/x-www-form-urlencoded',
            'length' => 15,
            'data' => new Collection(),
            'query' => new Collection(),
            'stream_path' => $stream_path,
			'method' => 'PATCH'
        ]);
        $this->assertEquals([ 
			'foo' => 'bar', 
			'baz' => 'qux' 
		], $request->data->getData());
        $this->assertEquals('foo=bar&baz=qux', $request->getBody());
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

    public function testGetSingleFileUpload()
    {
        $_FILES['file'] = [
            'name' => 'file.txt',
            'type' => 'text/plain',
            'size' => 123,
            'tmp_name' => '/tmp/php123',
            'error' => 0
        ];

        $request = new Request([
            'method' => 'PATCH'
        ]);

        $file = $request->getUploadedFiles()['file'];

        $this->assertEquals('file.txt', $file->getClientFilename());
        $this->assertEquals('text/plain', $file->getClientMediaType());
        $this->assertEquals(123, $file->getSize());
        $this->assertEquals('/tmp/php123', $file->getTempName());
        $this->assertEquals(0, $file->getError());
    }

    public function testGetMultiFileUpload()
    {
        $_FILES['files'] = [
            'name' => ['file1.txt', 'file2.txt'],
            'type' => ['text/plain', 'text/plain'],
            'size' => [123, 456],
            'tmp_name' => ['/tmp/php123', '/tmp/php456'],
            'error' => [0, 0]
        ];

        $request = new Request([
            'method' => 'PATCH'
        ]);

        $files = $request->getUploadedFiles()['files'];

        $this->assertCount(2, $files);

        $this->assertEquals('file1.txt', $files[0]->getClientFilename());
        $this->assertEquals('text/plain', $files[0]->getClientMediaType());
        $this->assertEquals(123, $files[0]->getSize());
        $this->assertEquals('/tmp/php123', $files[0]->getTempName());
        $this->assertEquals(0, $files[0]->getError());

        $this->assertEquals('file2.txt', $files[1]->getClientFilename());
        $this->assertEquals('text/plain', $files[1]->getClientMediaType());
        $this->assertEquals(456, $files[1]->getSize());
        $this->assertEquals('/tmp/php456', $files[1]->getTempName());
        $this->assertEquals(0, $files[1]->getError());
    }
}
