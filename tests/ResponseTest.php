<?php

declare(strict_types=1);

use flight\net\Request;
use flight\net\Response;
use flight\util\Collection;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
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
    }

    public function testStatusDefault()
    {
        $response = new Response();
        $this->assertSame(200, $response->status());
    }

    public function testStatusValidCode()
    {
        $response = new Response();
        $response->status(200);
        $this->assertEquals(200, $response->status());
    }

    public function testStatusInvalidCode()
    {
        $response = new Response();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid status code.');
        $response->status(999);
    }

    public function testStatusReturnObject()
    {
        $response = new Response();
        $this->assertEquals($response, $response->status(200));
    }

    public function testHeaderSingle()
    {
        $response = new Response();
        $response->header('Content-Type', 'text/html');
        $this->assertEquals(['Content-Type' => 'text/html'], $response->headers());
    }

    public function testHeaderSingleKeepCaseSensitive()
    {
        $response = new Response();
        $response->header('content-type', 'text/html');
        $response->header('x-test', 'test');
        $this->assertEquals(['content-type' => 'text/html', 'x-test' => 'test'], $response->headers());
    }

    public function testHeaderArray()
    {
        $response = new Response();
        $response->header(['Content-Type' => 'text/html', 'X-Test' => 'test']);
        $this->assertEquals(['Content-Type' => 'text/html', 'X-Test' => 'test'], $response->headers());
    }

    public function testHeaderReturnObject()
    {
        $response = new Response();
        $this->assertEquals($response, $response->header('Content-Type', 'text/html'));
    }

    public function testWrite()
    {
        $response = new Response();
        $response->write('test');
        $this->assertEquals('test', $response->getBody());
    }

    public function testWriteEmptyString()
    {
        $response = new Response();
        $response->write('');
        $this->assertEquals('', $response->getBody());
    }

    public function testWriteReturnObject()
    {
        $response = new Response();
        $this->assertEquals($response, $response->write('test'));
    }

    public function testClear()
    {
        $response = new Response();
        $response->write('test');
        $response->status(404);
        $response->header('Content-Type', 'text/html');
        $response->clear();
        $this->assertEquals('', $response->getBody());
        $this->assertEquals(200, $response->status());
        $this->assertEquals([], $response->headers());
    }

    public function testCacheSimple()
    {
        $response = new Response();
        $cache_time = time() + 60;
        $response->cache($cache_time);
        $this->assertEquals([
            'Expires' => gmdate('D, d M Y H:i:s', $cache_time) . ' GMT',
            'Cache-Control' => 'max-age=60'
        ], $response->headers());
    }

    public function testCacheSimpleWithString()
    {
        $response = new Response();
        $cache_time = time() + 60;
        $response->cache('now +60 seconds');
        $this->assertEquals([
            'Expires' => gmdate('D, d M Y H:i:s', $cache_time) . ' GMT',
            'Cache-Control' => 'max-age=60'
        ], $response->headers());
    }

    public function testCacheSimpleWithPragma()
    {
        $response = new Response();
        $cache_time = time() + 60;
        $response->header('Pragma', 'no-cache');
        $response->cache($cache_time);
        $this->assertEquals([
            'Expires' => gmdate('D, d M Y H:i:s', $cache_time) . ' GMT',
            'Cache-Control' => 'max-age=60'
        ], $response->headers());
    }

    public function testCacheFalseExpiresValue()
    {
        $response = new Response();
        $response->cache(false);
        $this->assertEquals([
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Cache-Control' => [
                'no-store, no-cache, must-revalidate',
                'post-check=0, pre-check=0',
                'max-age=0',
            ],
            'Pragma' => 'no-cache'
        ], $response->headers());
    }

    public function testSendHeadersRegular()
    {
        $response = new class extends Response {
            protected $test_sent_headers = [];

            protected array $headers = [
                'Cache-Control' => [
                    'no-store, no-cache, must-revalidate',
                    'post-check=0, pre-check=0',
                    'max-age=0',
                ]
            ];
            public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): self
            {
                $this->test_sent_headers[] = $header_string;
                return $this;
            }

            public function getSentHeaders(): array
            {
                return $this->test_sent_headers;
            }
        };
        $response->header('Content-Type', 'text/html');
        $response->header('X-Test', 'test');
        $response->write('Something');

        $response->sendHeaders();
        $sent_headers = $response->getSentHeaders();
        $this->assertEquals([
            'HTTP/1.1 200 OK',
            'Cache-Control: no-store, no-cache, must-revalidate',
            'Cache-Control: post-check=0, pre-check=0',
            'Cache-Control: max-age=0',
            'Content-Type: text/html',
            'X-Test: test',
            'Content-Length: 9'
        ], $sent_headers);
    }

    public function testSentDefault()
    {
        $response = new Response();
        $this->assertFalse($response->sent());
    }

    public function testSentTrue()
    {
        $response = new class extends Response {
            protected $test_sent_headers = [];

            public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): self
            {
                $this->test_sent_headers[] = $header_string;
                return $this;
            }
        };
        $response->header('Content-Type', 'text/html');
        $response->header('X-Test', 'test');
        $response->write('Something');

        $this->expectOutputString('Something');
        $response->send();
        $this->assertTrue($response->sent());
    }
}
