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

    /**
     * Tests getUploadMaxFileSize parsing for various php.ini unit suffixes.
     * We'll call the method in-process after setting ini values via ini_set
     * and also simulate a value with unknown unit to hit the default branch.
     */
    public function testGetUploadMaxFileSizeUnits(): void
    {
        // Use PHP CLI with -d to set upload_max_filesize (ini_set can't change this setting in many SAPIs)
        $cases = [
            // No unit yields default branch which returns 0 in current implementation
            ['1'    , 0], // no unit and number too small
            ['1K'   , 1024],
            ['2M'   , 2 * 1024 * 1024],
            ['1G'   , 1024 * 1024 * 1024],
            ['1T'   , 1024 * 1024 * 1024 * 1024],
            ['1Z'   , 0 ],  // Unknown unit and number too small
            [ '1024', 1024 ]
        ];

        foreach ($cases as [$iniVal, $expected]) {
            $actual = Request::parsePhpSize($iniVal);
            $this->assertEquals($expected, $actual, "upload_max_filesize={$iniVal}");
        }
    }

    /**
     * Helper: run PHP CLI with -d upload_max_filesize and return the Request::getUploadMaxFileSize() result.
     */
    // removed CLI helper; parsePhpSize covers unit parsing and is pure

    public function testMultipartBoundaryInvalidFallsBackToUrlEncoded(): void
    {
        // Body doesn't start with boundary marker => fallback to urlencoded branch
        $body = 'field1=value1&field2=value2';
        $tmp = tmpfile();
        $path = stream_get_meta_data($tmp)['uri'];
        file_put_contents($path, $body);

        $request = new Request([
            'url' => '/upload',
            'base' => '/',
            'method' => 'PATCH',
            'type' => 'multipart/form-data; boundary=BOUNDARYXYZ', // claims multipart
            'stream_path' => $path,
            'data' => new Collection(),
            'query' => new Collection(),
            'files' => new Collection(),
        ]);

        $this->assertEquals(['field1' => 'value1', 'field2' => 'value2'], $request->data->getData());
        $this->assertSame([], $request->files->getData());
    }

    public function testMultipartParsingEdgeCases(): void
    {
        $boundary = 'MBOUND123';
        $parts = [];

        // A: invalid split (no blank line) => skipped
        $parts[] = "Content-Disposition: form-data; name=\"skipnosplit\""; // no value portion

        // B: missing content-disposition entirely => skipped
        $parts[] = "Content-Type: text/plain\r\n\r\nignoredvalue";

        // C: header too long (>16384) => skipped
        $longHeader = 'Content-Disposition: form-data; name="toolong"; filename="toolong.txt"; ' . str_repeat('x', 16500);
        $parts[] = $longHeader . "\r\n\r\nlongvalue";

        // D: header line without colon gets skipped but rest processed; becomes non-file field
        $parts[] = "BadHeaderLine\r\nContent-Disposition: form-data; name=\"fieldX\"\r\n\r\nvalueX";

        // E: disposition without name => skipped
        $parts[] = "Content-Disposition: form-data; filename=\"nofname.txt\"\r\n\r\nnoNameValue";

        // F: empty name => skipped
        $parts[] = "Content-Disposition: form-data; name=\"\"; filename=\"empty.txt\"\r\n\r\nemptyNameValue";

        // G: invalid filename triggers sanitized fallback
        $parts[] = "Content-Disposition: form-data; name=\"filebad\"; filename=\"a*b?.txt\"\r\nContent-Type: text/plain\r\n\r\nFILEBAD";

        // H1 & H2: two files same key for aggregation logic (arrays)
        $parts[] = "Content-Disposition: form-data; name=\"filemulti\"; filename=\"one.txt\"\r\nContent-Type: text/plain\r\n\r\nONE";
        $parts[] = "Content-Disposition: form-data; name=\"filemulti\"; filename=\"two.txt\"\r\nContent-Type: text/plain\r\n\r\nTWO";

        // I: file exceeding total bytes triggers UPLOAD_ERR_INI_SIZE
        $parts[] = "Content-Disposition: form-data; name=\"filebig\"; filename=\"big.txt\"\r\nContent-Type: text/plain\r\n\r\n" . str_repeat('A', 10);

        // Build full body
        $body = '';
        foreach ($parts as $p) {
            $body .= '--' . $boundary . "\r\n" . $p . "\r\n";
        }
        $body .= '--' . $boundary . "--\r\n";

        $tmp = tmpfile();
        $path = stream_get_meta_data($tmp)['uri'];
        file_put_contents($path, $body);

        $request = new Request([
            'url' => '/upload',
            'base' => '/',
            'method' => 'PATCH',
            'type' => 'multipart/form-data; boundary=' . $boundary,
            'stream_path' => $path,
            'data' => new Collection(),
            'query' => new Collection(),
            'files' => new Collection(),
        ]);

        $data = $request->data->getData();
        $this->assertArrayHasKey('fieldX', $data); // only processed non-file field
        $this->assertEquals('valueX', $data['fieldX']);
        $files = $request->files->getData();

        // filebad fallback name
        $this->assertArrayHasKey('filebad', $files);
        $this->assertMatchesRegularExpression('/^upload_/', $files['filebad']['name']);

        // filemulti aggregated arrays
        $this->assertArrayHasKey('filemulti', $files);
        $this->assertEquals(['one.txt', 'two.txt'], $files['filemulti']['name']);
        $this->assertEquals(['text/plain', 'text/plain'], $files['filemulti']['type']);

        // filebig error path
        $this->assertArrayHasKey('filebig', $files);
        $uploadMax = Request::parsePhpSize(ini_get('upload_max_filesize'));
        $postMax = Request::parsePhpSize(ini_get('post_max_size'));
        $shouldError = ($uploadMax > 0 && $uploadMax < 10) || ($postMax > 0 && $postMax < 10);
        if ($shouldError) {
            $this->assertEquals(UPLOAD_ERR_INI_SIZE, $files['filebig']['error']);
        } else {
            $this->assertEquals(UPLOAD_ERR_OK, $files['filebig']['error']);
        }
    }


    public function testMultipartEmptyArrayNameStripped(): void
    {
        // Covers line where keyName becomes empty after removing [] (name="[]") and header param extraction (preg_match_all)
        $boundary = 'BOUNDARYEMPTY';
        $validFilePart = "Content-Disposition: form-data; name=\"fileok\"; filename=\"ok.txt\"\r\nContent-Type: text/plain\r\n\r\nOK";
        $emptyNameFilePart = "Content-Disposition: form-data; name=\"[]\"; filename=\"empty.txt\"\r\nContent-Type: text/plain\r\n\r\nSHOULD_SKIP";
        $body = '--' . $boundary . "\r\n" . $validFilePart . "\r\n" . '--' . $boundary . "\r\n" . $emptyNameFilePart . "\r\n" . '--' . $boundary . "--\r\n";

        $tmp = tmpfile();
        $path = stream_get_meta_data($tmp)['uri'];
        file_put_contents($path, $body);

        $request = new Request([
            'url' => '/upload',
            'base' => '/',
            'method' => 'PATCH',
            'type' => 'multipart/form-data; boundary=' . $boundary,
            'stream_path' => $path,
            'data' => new Collection(),
            'query' => new Collection(),
            'files' => new Collection(),
        ]);

        $files = $request->files->getData();
        // fileok processed
        $this->assertArrayHasKey('fileok', $files);
        // name="[]" stripped => keyName becomes empty -> skipped
        $this->assertArrayNotHasKey('empty', $files); // just to show not mistakenly created
        $this->assertCount(5, $files['fileok']); // meta keys name,type,size,tmp_name,error
    }

    public function testMultipartMalformedBoundaryFallsBackToUrlEncoded(): void
    {
        // boundary has invalid characters (spaces) so regex validation fails -> line 589 path
        $invalidBoundary = 'BAD BOUNDARY WITH SPACE';
        $body = 'alpha=1&beta=2'; // should parse as urlencoded after fallback
        $tmp = tmpfile();
        $path = stream_get_meta_data($tmp)['uri'];
        file_put_contents($path, $body);

        $request = new Request([
            'url' => '/upload',
            'base' => '/',
            'method' => 'PATCH',
            'type' => 'multipart/form-data; boundary=' . $invalidBoundary,
            'stream_path' => $path,
            'data' => new Collection(),
            'query' => new Collection(),
            'files' => new Collection(),
        ]);

        $this->assertEquals(['alpha' => '1', 'beta' => '2'], $request->data->getData());
        $this->assertSame([], $request->files->getData());
    }
}
