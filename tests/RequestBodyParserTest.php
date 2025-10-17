<?php

declare(strict_types=1);

namespace tests;

use flight\net\Request;
use flight\util\Collection;
use PHPUnit\Framework\TestCase;

class RequestBodyParserTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [];
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        unset($_REQUEST);
        unset($_SERVER);
        unset($_GET);
        unset($_POST);
        unset($_COOKIE);
        unset($_FILES);
    }

    private function createRequestConfig(string $method, string $contentType, string $body, &$tmpfile = null): array
    {
        $tmpfile = tmpfile();
        $stream_path = stream_get_meta_data($tmpfile)['uri'];
        file_put_contents($stream_path, $body);

        return [
            'url' => '/',
            'base' => '/',
            'method' => $method,
            'referrer' => '',
            'ip' => '127.0.0.1',
            'ajax' => false,
            'scheme' => 'http',
            'user_agent' => 'Test',
            'type' => $contentType,
            'length' => strlen($body),
            'secure' => false,
            'accept' => '',
            'proxy_ip' => '',
            'host' => 'localhost',
            'servername' => 'localhost',
            'stream_path' => $stream_path,
            'data' => new Collection(),
            'query' => new Collection(),
            'cookies' => new Collection(),
            'files' => new Collection()
        ];
    }

    private function assertUrlEncodedParsing(string $method): void
    {
        $body = 'foo=bar&baz=qux&key=value';
        $tmpfile = null;
        $config = $this->createRequestConfig($method, 'application/x-www-form-urlencoded', $body, $tmpfile);
        
        $request = new Request($config);

        $expectedData = [
            'foo' => 'bar',
            'baz' => 'qux',
            'key' => 'value'
        ];
        $this->assertEquals($expectedData, $request->data->getData());

        fclose($tmpfile);
    }

    private function createMultipartBody(string $boundary, array $fields, array $files = []): string
    {
        $body = '';
        
        // Add form fields
        foreach ($fields as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $body .= "--{$boundary}\r\n";
                    $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n";
                    $body .= "\r\n";
                    $body .= "{$item}\r\n";
                }
            } else {
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n";
                $body .= "\r\n";
                $body .= "{$value}\r\n";
            }
        }

        // Add files
        foreach ($files as $name => $file) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$file['filename']}\"\r\n";
            $body .= "Content-Type: {$file['type']}\r\n";
            $body .= "\r\n";
            $body .= "{$file['content']}\r\n";
        }

        $body .= "--{$boundary}--\r\n";
        
        return $body;
    }

    public function testParseUrlEncodedBodyForPutMethod(): void
    {
        $this->assertUrlEncodedParsing('PUT');
    }

    public function testParseUrlEncodedBodyForPatchMethod(): void
    {
        $this->assertUrlEncodedParsing('PATCH');
    }

    public function testParseUrlEncodedBodyForDeleteMethod(): void
    {
        $this->assertUrlEncodedParsing('DELETE');
    }

    public function testParseMultipartFormDataWithFiles(): void
    {
        $boundary = 'boundary123456789';
        $fields = ['title' => 'Test Document'];
        $files = [
            'file' => [
                'filename' => 'file.txt',
                'type' => 'text/plain',
                'content' => 'This is test file content'
            ]
        ];

        $body = $this->createMultipartBody($boundary, $fields, $files);
        $config = $this->createRequestConfig('PUT', "multipart/form-data; boundary={$boundary}", $body, $tmpfile);
        $request = new Request($config);

        $this->assertEquals(['title' => 'Test Document'], $request->data->getData());
        
        $file = $request->getUploadedFiles()['file'];
        $this->assertEquals('file.txt', $file->getClientFilename());
        $this->assertEquals('text/plain', $file->getClientMediaType());
        $this->assertEquals(strlen('This is test file content'), $file->getSize());
        $this->assertEquals(UPLOAD_ERR_OK, $file->getError());
        $this->assertNotNull($file->getTempName());

        fclose($tmpfile);
    }

    public function testParseMultipartFormDataWithQuotedBoundary(): void
    {
        $boundary = 'boundary123456789';
        $fields = ['foo' => 'bar'];

        $body = $this->createMultipartBody($boundary, $fields);
        $config = $this->createRequestConfig('PATCH', "multipart/form-data; boundary=\"{$boundary}\"", $body, $tmpfile);
        $request = new Request($config);

        $this->assertEquals($fields, $request->data->getData());

        fclose($tmpfile);
    }

    public function testParseMultipartFormDataWithArrayFields(): void
    {
        $boundary = 'boundary123456789';
        $fields = ['name[]' => ['foo', 'bar']];
        $expectedData = ['name' => ['foo', 'bar']];

        $body = $this->createMultipartBody($boundary, $fields);
        $config = $this->createRequestConfig('PUT', "multipart/form-data; boundary={$boundary}", $body, $tmpfile);
        $request = new Request($config);

        $this->assertEquals($expectedData, $request->data->getData());

        fclose($tmpfile);
    }

    public function testParseEmptyBody(): void
    {
        $config = $this->createRequestConfig('PUT', 'application/x-www-form-urlencoded', '', $tmpfile);
        $request = new Request($config);

        $this->assertEquals([], $request->data->getData());

        fclose($tmpfile);
    }

    public function testParseInvalidMultipartWithoutBoundary(): void
    {
        $originalData = ['foo foo' => 'bar bar', 'baz baz' => 'qux'];
        $body = http_build_query($originalData);
        $expectedData = ['foo_foo' => 'bar bar', 'baz_baz' => 'qux'];

        $config = $this->createRequestConfig('PUT', 'multipart/form-data', $body, $tmpfile); // no boundary
        $request = new Request($config);

        // should fall back to URL encoding and parse correctly
        $this->assertEquals($expectedData, $request->data->getData());

        fclose($tmpfile);
    }

    public function testParseMultipartWithLargeFile(): void
    {
        $boundary = 'boundary123456789';
        $largeContent = str_repeat('A', 10000); // 10KB content
        $files = [
            'file' => [
                'filename' => 'large.txt',
                'type' => 'text/plain',
                'content' => $largeContent
            ]
        ];

        $body = $this->createMultipartBody($boundary, [], $files);
        $config = $this->createRequestConfig('PUT', "multipart/form-data; boundary={$boundary}", $body, $tmpfile);
        $request = new Request($config);

        $file = $request->getUploadedFiles()['file'];
        $this->assertArrayHasKey('file', $request->getUploadedFiles());
        $this->assertEquals('large.txt', $file->getClientFilename());
        $this->assertEquals(10000, $file->getSize());
        $this->assertEquals(UPLOAD_ERR_OK, $file->getError());
        $this->assertNotNull($file->getTempName());

        fclose($tmpfile);
    }

    public function testGetMethodDoesNotTriggerParsing(): void
    {
        $body = 'foo=bar&baz=qux&key=value';
        $config = $this->createRequestConfig('GET', 'application/x-www-form-urlencoded', $body, $tmpfile);
        $request = new Request($config);

        // GET method should not trigger parsing
        $this->assertEquals([], $request->data->getData());

        fclose($tmpfile);
    }

    public function testPostMethodDoesNotTriggerParsing(): void
    {
        $body = 'foo=bar&baz=qux&key=value';
        $config = $this->createRequestConfig('POST', 'application/x-www-form-urlencoded', $body, $tmpfile);
        $request = new Request($config);

        // POST method should not trigger this parsing (uses $_POST instead)
        $this->assertEquals([], $request->data->getData());

        fclose($tmpfile);
    }
}