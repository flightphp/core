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
        $_SERVER['SERVER_NAME'] = 'test.com';
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

    public function testDefaults(): void
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
        $this->assertEquals('test.com', $this->request->servername);
    }

    public function testIpAddress(): void
    {
        $this->assertEquals('8.8.8.8', $this->request->ip);
        $this->assertEquals('32.32.32.32', $this->request->proxy_ip);
    }

    public function testSubdirectory(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/subdir/index.php';

        $request = new Request();

        $this->assertEquals('/subdir', $request->base);
    }

    public function testQueryParameters(): void
    {
        $_SERVER['REQUEST_URI'] = '/page?id=1&name=bob';

        $request = new Request();

        $this->assertEquals('/page?id=1&name=bob', $request->url);
        $this->assertEquals(1, $request->query->id);
        $this->assertEquals('bob', $request->query->name);
    }

    public function testCollections(): void
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

    public function testJsonWithEmptyBody(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $request = new Request();

        $this->assertSame([], $request->data->getData());
    }

    public function testMethodOverrideWithHeader(): void
    {
        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $request = new Request();

        $this->assertEquals('PUT', $request->method);
    }

    public function testMethodOverrideWithPost(): void
    {
        $_REQUEST['_method'] = 'PUT';

        $request = new Request();

        $this->assertEquals('PUT', $request->method);
    }

    public function testHttps(): void
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

    public function testInitUrlSameAsBaseDirectory(): void
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

    public function testInitNoUrl(): void
    {
        $request = new Request([
            'url' => '',
            'base' => '/vagrant/public',
            'type' => '',
            'method' => 'GET'
        ]);
        $this->assertEquals('/', $request->url);
    }

    public function testInitWithJsonBody(): void
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

    public function testInitWithFormBody(): void
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

    public function testGetHeader(): void
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

    public function testGetHeaders(): void
    {
        $_SERVER = [];
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom header value';
        $request = new Request();
        $this->assertEquals(['X-Custom-Header' => 'custom header value'], $request->getHeaders());
    }

    public function testGetHeadersWithEmptyServer(): void
    {
        $_SERVER = [];
        $request = new Request();
        $this->assertEquals([], $request->getHeaders());
    }

    public function testGetHeadersWithEmptyHeader(): void
    {
        $_SERVER = [];
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = '';
        $request = new Request();
        $this->assertEquals(['X-Custom-Header' => ''], $request->headers());
    }

    public function testGetHeadersWithMultipleHeaders(): void
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

    public function testGetFullUrlNoHttps(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/page?id=1';
        $request = new Request();
        $this->assertEquals('http://example.com/page?id=1', $request->getFullUrl());
    }

    public function testGetFullUrlWithHttps(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        $_SERVER['REQUEST_URI'] = '/page?id=1';
        $_SERVER['HTTPS'] = 'on';
        $request = new Request();
        $this->assertEquals('https://localhost:8000/page?id=1', $request->getFullUrl());
    }

    public function testGetBaseUrlNoHttps(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/page?id=1';
        $request = new Request();
        $this->assertEquals('http://example.com', $request->getBaseUrl());
    }

    public function testGetBaseUrlWithHttps(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        $_SERVER['REQUEST_URI'] = '/page?id=1';
        $_SERVER['HTTPS'] = 'on';
        $request = new Request();
        $this->assertEquals('https://localhost:8000', $request->getBaseUrl());
    }

    public function testGetSingleFileUpload(): void
    {
        $_FILES['file'] = [
            'name' => 'file.txt',
            'type' => 'text/plain',
            'size' => 123,
            'tmp_name' => '/tmp/php123',
            'error' => 0
        ];

        $request = new Request();

        $file = $request->getUploadedFiles()['file'];

        $this->assertEquals('file.txt', $file->getClientFilename());
        $this->assertEquals('text/plain', $file->getClientMediaType());
        $this->assertEquals(123, $file->getSize());
        $this->assertEquals('/tmp/php123', $file->getTempName());
        $this->assertEquals(0, $file->getError());
    }

    public function testGetMultiFileUpload(): void
    {
        // Arrange: Setup multiple file upload arrays
        $_FILES['files_1'] = [
            'name' => 'file1.txt',
            'type' => 'text/plain',
            'size' => 123,
            'tmp_name' => '/tmp/php123',
            'error' => 0
        ];
        $_FILES['files_2'] = [
            'name' => ['file2.txt'],
            'type' => ['text/plain'],
            'size' => [456],
            'tmp_name' => ['/tmp/php456'],
            'error' => [0]
        ];
        $_FILES['files_3'] = [
            'name' => ['file3.txt', 'file4.txt'],
            'type' => ['text/html', 'application/json'],
            'size' => [789, 321],
            'tmp_name' => ['/tmp/php789', '/tmp/php321'],
            'error' => [0, 0]
        ];

        // Act
        $request = new Request();
        $uploadedFiles = $request->getUploadedFiles();

        // Assert: Verify first file group (single file)
        /*
            <input type="file" name="files_1">
        */
        $firstFile = $uploadedFiles['files_1'] ?? null;
        $this->assertNotNull($firstFile, 'First file should exist');
        $this->assertUploadedFile($firstFile, 'file1.txt', 'text/plain', 123, '/tmp/php123', 0);

        // Assert: Verify second file group (array format with single file)
        /*
            <input type="file" name="files_2[]">
        */
        $secondGroup = $uploadedFiles['files_2'] ?? [];
        $this->assertCount(1, $secondGroup, 'Second file group should contain 1 file in array format');

        $this->assertUploadedFile($secondGroup[0], 'file2.txt', 'text/plain', 456, '/tmp/php456', 0);

        // Assert: Verify third file group (multiple files)
        /*
            <input type="file" name="files_3[]">
            <input type="file" name="files_3[]">
        */
        $thirdGroup = $uploadedFiles['files_3'] ?? [];
        $this->assertCount(2, $thirdGroup, 'Third file group should contain 2 files');

        $this->assertUploadedFile($thirdGroup[0], 'file3.txt', 'text/html', 789, '/tmp/php789', 0);
        $this->assertUploadedFile($thirdGroup[1], 'file4.txt', 'application/json', 321, '/tmp/php321', 0);
    }

    /**
     * Helper method to assert uploaded file properties
     */
    private function assertUploadedFile(
        $file,
        string $expectedName,
        string $expectedType,
        int $expectedSize,
        string $expectedTmpName,
        int $expectedError
    ): void {
        $this->assertEquals($expectedName, $file->getClientFilename());
        $this->assertEquals($expectedType, $file->getClientMediaType());
        $this->assertEquals($expectedSize, $file->getSize());
        $this->assertEquals($expectedTmpName, $file->getTempName());
        $this->assertEquals($expectedError, $file->getError());
    }

    public function testUrlWithAtSymbol(): void
    {
        $_SERVER['REQUEST_URI'] = '/user@domain';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $request = new Request();
        $this->assertEquals('/user%40domain', $request->url);
    }

    public function testBaseWithSpaceAndBackslash(): void
    {
        $_SERVER['SCRIPT_NAME'] = '\\dir name\\base folder\\index.php';
        $request = new Request();
        $this->assertEquals('/dir%20name/base%20folder', $request->base);
    }

    public function testParseQueryWithEmptyQueryString(): void
    {
        $result = Request::parseQuery('/foo?');
        $this->assertEquals([], $result);
    }

    public function testNegotiateContentType(): void
    {
        // Find best match first
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $request = new Request();
        $this->assertEquals('application/xml', $request->negotiateContentType(['application/xml', 'application/json', 'text/html']));

        // Find the first match
        $_SERVER['HTTP_ACCEPT'] = 'application/json,text/html';
        $request = new Request();
        $this->assertEquals('application/json', $request->negotiateContentType(['application/json', 'text/html']));

        // No match found
        $_SERVER['HTTP_ACCEPT'] = 'application/xml';
        $request = new Request();
        $this->assertNull($request->negotiateContentType(['application/json', 'text/html']));

        // No header present, return first supported type
        $_SERVER['HTTP_ACCEPT'] = '';
        $request = new Request();
        $this->assertEquals('application/json', $request->negotiateContentType(['application/json', 'text/html']));
    }
}
