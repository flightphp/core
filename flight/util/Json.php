<?php

declare(strict_types=1);

namespace flight\util;

use Exception;
use JsonException;

/**
 * Json utility class for encoding and decoding JSON data.
 *
 * This class provides centralized JSON handling for the FlightPHP framework,
 * with consistent error handling and default options.
 */
class Json
{
    /**
     * Default JSON encoding options
     */
    public const DEFAULT_ENCODE_OPTIONS = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * Default JSON decoding options
     */
    public const DEFAULT_DECODE_OPTIONS = JSON_THROW_ON_ERROR;

    /**
     * Encodes data to JSON string.
     *
     * @param mixed $data Data to encode
     * @param int $options JSON encoding options (bitmask)
     * @param int $depth Maximum depth
     *
     * @return string JSON encoded string
     * @throws Exception If encoding fails
     */
    public static function encode($data, int $options = 0, int $depth = 512): string
    {
        $options = $options | self::DEFAULT_ENCODE_OPTIONS; // Ensure default options are applied
        try {
            return json_encode($data, $options, $depth);
        } catch (JsonException $e) {
            throw new Exception('JSON encoding failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Decodes JSON string to PHP data.
     *
     * @param string $json JSON string to decode
     * @param bool $associative Whether to return associative arrays instead of objects
     * @param int $depth Maximum decoding depth
     * @param int $options JSON decoding options (bitmask)
     *
     * @return mixed Decoded data
     * @throws Exception If decoding fails
     */
    public static function decode(string $json, bool $associative = false, int $depth = 512, int $options = 0)
    {
        $options = $options | self::DEFAULT_DECODE_OPTIONS; // Ensure default options are applied
        try {
            return json_decode($json, $associative, $depth, $options);
        } catch (JsonException $e) {
            throw new Exception('JSON decoding failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Checks if a string is valid JSON.
     *
     * @param string $json String to validate
     *
     * @return bool True if valid JSON, false otherwise
     */
    public static function isValid(string $json): bool
    {
        try {
            json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException $e) {
            return false;
        }
    }

    /**
     * Gets the last JSON error message.
     *
     * @return string Error message or empty string if no error
     */
    public static function getLastError(): string
    {
        $error = json_last_error();
        if ($error === JSON_ERROR_NONE) {
            return '';
        }
        return json_last_error_msg();
    }

    /**
     * Pretty prints JSON data.
     *
     * @param mixed $data Data to encode
     * @param int $additionalOptions Additional options to merge with pretty print
     *
     * @return string Pretty formatted JSON string
     * @throws Exception If encoding fails
     */
    public static function prettyPrint($data, int $additionalOptions = 0): string
    {
        $options = self::DEFAULT_ENCODE_OPTIONS | JSON_PRETTY_PRINT | $additionalOptions;
        return self::encode($data, $options);
    }
}
