<?php

declare(strict_types=1);

namespace tests;

use flight\util\Json;
use PHPUnit\Framework\TestCase;
use Exception;

class JsonTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear any previous JSON errors
        json_encode(['clear' => 'error']);
    }

    // Test basic encoding
    public function testEncode(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $result = Json::encode($data);
        $this->assertIsString($result);
        $this->assertJson($result);
    }

    // Test encoding with custom options
    public function testEncodeWithOptions(): void
    {
        $data = ['url' => 'https://example.com/path'];
        $result = Json::encode($data, JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('https://example.com/path', $result);
    }

    // Test encoding with invalid data
    public function testEncodeInvalidData(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('JSON encoding failed');

        // Create a resource that cannot be encoded
        $resource = fopen('php://memory', 'r');
        Json::encode($resource);
        fclose($resource);
    }

    // Test basic decoding
    public function testDecode(): void
    {
        $json = '{"name":"John","age":30}';
        $result = Json::decode($json);
        $this->assertIsObject($result);
        $this->assertEquals('John', $result->name);
        $this->assertEquals(30, $result->age);
    }

    // Test decoding to associative array
    public function testDecodeAssociative(): void
    {
        $json = '{"name":"John","age":30}';
        $result = Json::decode($json, true);
        $this->assertIsArray($result);
        $this->assertEquals('John', $result['name']);
        $this->assertEquals(30, $result['age']);
    }

    // Test decoding with custom depth
    public function testDecodeWithDepth(): void
    {
        $json = '{"level1":{"level2":{"level3":"value"}}}';
        $result = Json::decode($json, true, 512);
        $this->assertEquals('value', $result['level1']['level2']['level3']);
    }

    // Test decoding invalid JSON
    public function testDecodeInvalidJson(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('JSON decoding failed');

        Json::decode('{"invalid": json}');
    }

    // Test JSON validation with valid JSON
    public function testIsValidWithValidJson(): void
    {
        $validJson = '{"name":"John","age":30}';
        $this->assertTrue(Json::isValid($validJson));
    }

    // Test JSON validation with invalid JSON
    public function testIsValidWithInvalidJson(): void
    {
        $invalidJson = '{"invalid": json}';
        $this->assertFalse(Json::isValid($invalidJson));
    }

    // Test JSON validation with empty string
    public function testIsValidWithEmptyString(): void
    {
        $this->assertFalse(Json::isValid(''));
    }

    // Test pretty print functionality
    public function testPrettyPrint(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $result = Json::prettyPrint($data);
        $this->assertStringContainsString("\n", $result);
        $this->assertStringContainsString('    ', $result); // Should contain indentation
    }

    // Test pretty print with additional options
    public function testPrettyPrintWithAdditionalOptions(): void
    {
        $data = ['html' => '<script>alert("test")</script>'];
        $result = Json::prettyPrint($data, JSON_HEX_TAG);
        $this->assertStringContainsString('\u003C', $result); // Should escape < character
    }

    // Test getLastError when no error
    public function testGetLastErrorNoError(): void
    {
        // Perform a valid JSON operation first
        Json::encode(['valid' => 'data']);
        $this->assertEquals('', Json::getLastError());
    }

    // Test getLastError when there is an error
    public function testGetLastErrorWithError(): void
    {
        // Trigger a JSON error by using json_decode directly with invalid JSON
        // This bypasses our Json class exception handling to test getLastError()
        json_decode('{"invalid": json}');

        $errorMessage = Json::getLastError();
        $this->assertNotEmpty($errorMessage);
        $this->assertIsString($errorMessage);
    }

    // Test encoding arrays
    public function testEncodeArray(): void
    {
        $data = [1, 2, 3, 'four'];
        $result = Json::encode($data);
        $this->assertEquals('[1,2,3,"four"]', $result);
    }

    // Test encoding null
    public function testEncodeNull(): void
    {
        $result = Json::encode(null);
        $this->assertEquals('null', $result);
    }

    // Test encoding boolean values
    public function testEncodeBoolean(): void
    {
        $this->assertEquals('true', Json::encode(true));
        $this->assertEquals('false', Json::encode(false));
    }

    // Test encoding strings
    public function testEncodeString(): void
    {
        $result = Json::encode('Hello World');
        $this->assertEquals('"Hello World"', $result);
    }

    // Test encoding numbers
    public function testEncodeNumbers(): void
    {
        $this->assertEquals('42', Json::encode(42));
        $this->assertEquals('3.14', Json::encode(3.14));
    }

    // Test decoding arrays
    public function testDecodeArray(): void
    {
        $json = '[1,2,3,"four"]';
        $result = Json::decode($json, true);
        $this->assertEquals([1, 2, 3, 'four'], $result);
    }

    // Test decoding nested objects
    public function testDecodeNestedObjects(): void
    {
        $json = '{"user":{"name":"John","profile":{"age":30}}}';
        $result = Json::decode($json, true);
        $this->assertEquals('John', $result['user']['name']);
        $this->assertEquals(30, $result['user']['profile']['age']);
    }

    // Test default encoding options are applied
    public function testDefaultEncodingOptions(): void
    {
        $data = ['url' => 'https://example.com/path'];
        $result = Json::encode($data);
        // Should not escape slashes due to JSON_UNESCAPED_SLASHES
        $this->assertStringContainsString('https://example.com/path', $result);
    }

    // Test round trip encoding/decoding
    public function testRoundTrip(): void
    {
        $original = [
            'string' => 'test',
            'number' => 42,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'object' => ['nested' => 'value']
        ];

        $encoded = Json::encode($original);
        $decoded = Json::decode($encoded, true);

        $this->assertEquals($original, $decoded);
    }
}
