<?php

declare(strict_types=1);

namespace flight\net;

use Exception;

/**
 * The Response class represents an HTTP response. The object
 * contains the response headers, HTTP status code, and response
 * body.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Response
{
    /**
     * Content-Length header.
     */
    public bool $content_length = true;

    /**
     * This is to maintain legacy handling of output buffering
     * which causes a lot of problems. This will be removed
     * in v4
     *
     * @var boolean
     */
    public bool $v2_output_buffering = false;

    /**
     * HTTP status codes
     *
     * @var array<int, ?string> $codes
     */
    public static array $codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',

        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',

        226 => 'IM Used',

        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',

        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',

        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',

        426 => 'Upgrade Required',

        428 => 'Precondition Required',
        429 => 'Too Many Requests',

        431 => 'Request Header Fields Too Large',

        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',

        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * HTTP status
     */
    protected int $status = 200;

    /**
     * HTTP response headers
     *
     * @var array<string,int|string|array<int,string>> $headers
     */
    protected array $headers = [];

    /**
     * HTTP response body
     */
    protected string $body = '';

    /**
     * HTTP response sent
     */
    protected bool $sent = false;

    /**
     * Sets the HTTP status of the response.
     *
     * @param ?int $code HTTP status code.
     *
     * @throws Exception If invalid status code
     *
     * @return int|$this Self reference
     */
    public function status(?int $code = null)
    {
        if (null === $code) {
            return $this->status;
        }

        if (\array_key_exists($code, self::$codes)) {
            $this->status = $code;
        } else {
            throw new Exception('Invalid status code.');
        }

        return $this;
    }

    /**
     * Adds a header to the response.
     *
     * @param array<string, int|string>|string $name  Header name or array of names and values
     * @param ?string  $value Header value
     *
     * @return $this
     */
    public function header($name, ?string $value = null): self
    {
        if (\is_array($name)) {
            foreach ($name as $k => $v) {
                $this->headers[$k] = $v;
            }
        } else {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Gets a single header from the response.
     *
     * @param string $name the name of the header
     *
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        $headers = $this->headers;
        // lowercase all the header keys
        $headers = array_change_key_case($headers, CASE_LOWER);
        return $headers[strtolower($name)] ?? null;
    }

    /**
     * Alias of Response->header(). Adds a header to the response.
     *
     * @param array<string, int|string>|string $name  Header name or array of names and values
     * @param ?string  $value Header value
     *
     * @return $this
     */
    public function setHeader($name, ?string $value): self
    {
        return $this->header($name, $value);
    }

    /**
     * Returns the headers from the response.
     *
     * @return array<string, int|string|array<int, string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Alias for Response->headers(). Returns the headers from the response.
     *
     * @return array<string, int|string|array<int, string>>
     */
    public function getHeaders(): array
    {
        return $this->headers();
    }

    /**
     * Writes content to the response body.
     *
     * @param string $str Response content
     * @param bool $overwrite Overwrite the response body
     *
     * @return $this Self reference
     */
    public function write(string $str, bool $overwrite = false): self
    {
        if ($overwrite === true) {
            $this->clearBody();
        }

        $this->body .= $str;

        return $this;
    }

    /**
     * Clears the response body.
     *
     * @return $this Self reference
     */
    public function clearBody(): self
    {
        $this->body = '';
        return $this;
    }

    /**
     * Clears the response.
     *
     * @return $this Self reference
     */
    public function clear(): self
    {
        $this->status = 200;
        $this->headers = [];
        $this->clearBody();

        // This needs to clear the output buffer if it's on
        if ($this->v2_output_buffering === false && ob_get_length() > 0) {
            ob_clean();
        }

        return $this;
    }

    /**
     * Sets caching headers for the response.
     *
     * @param int|string|false $expires Expiration time as time() or as strtotime() string value
     *
     * @return $this Self reference
     */
    public function cache($expires): self
    {
        if (false === $expires) {
            $this->headers['Expires'] = 'Mon, 26 Jul 1997 05:00:00 GMT';
            $this->headers['Cache-Control'] = [
                'no-store, no-cache, must-revalidate',
                'post-check=0, pre-check=0',
                'max-age=0',
            ];
            $this->headers['Pragma'] = 'no-cache';
        } else {
            $expires = \is_int($expires) ? $expires : strtotime($expires);
            $this->headers['Expires'] = gmdate('D, d M Y H:i:s', $expires) . ' GMT';
            $this->headers['Cache-Control'] = 'max-age=' . ($expires - time());
            if (isset($this->headers['Pragma']) && 'no-cache' == $this->headers['Pragma']) {
                unset($this->headers['Pragma']);
            }
        }

        return $this;
    }

    /**
     * Sends HTTP headers.
     *
     * @return $this Self reference
     */
    public function sendHeaders(): self
    {
        // Send status code header
        if (false !== strpos(\PHP_SAPI, 'cgi')) {
            // @codeCoverageIgnoreStart
            $this->setRealHeader(
                sprintf(
                    'Status: %d %s',
                    $this->status,
                    self::$codes[$this->status]
                ),
                true
            );
            // @codeCoverageIgnoreEnd
        } else {
            $this->setRealHeader(
                sprintf(
                    '%s %d %s',
                    $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
                    $this->status,
                    self::$codes[$this->status]
                ),
                true,
                $this->status
            );
        }

        // Send other headers
        foreach ($this->headers as $field => $value) {
            if (\is_array($value)) {
                foreach ($value as $v) {
                    $this->setRealHeader($field . ': ' . $v, false);
                }
            } else {
                $this->setRealHeader($field . ': ' . $value);
            }
        }

        if ($this->content_length) {
            // Send content length
            $length = $this->getContentLength();

            if ($length > 0) {
                $this->setRealHeader('Content-Length: ' . $length);
            }
        }

        return $this;
    }

    /**
     * Sets a real header. Mostly used for test mocking.
     *
     * @param string $header_string The header string you would pass to header()
     * @param bool $replace The optional replace parameter indicates whether the
     * header should replace a previous similar header, or add a second header of
     * the same type. By default it will replace, but if you pass in false as the
     * second argument you can force multiple headers of the same type.
     * @param int $response_code The response code to send
     *
     * @return self
     *
     * @codeCoverageIgnore
     */
    public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): self
    {
        header($header_string, $replace, $response_code);
        return $this;
    }

    /**
     * Gets the content length.
     */
    public function getContentLength(): int
    {
        return \extension_loaded('mbstring') ?
            mb_strlen($this->body, 'latin1') :
            \strlen($this->body);
    }

    /**
     * Gets the response body
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Gets whether response body was sent.
     */
    public function sent(): bool
    {
        return $this->sent;
    }

    /**
     * Marks the response as sent.
     */
    public function markAsSent(): void
    {
        $this->sent = true;
    }

    /**
     * Sends a HTTP response.
     */
    public function send(): void
    {
        // legacy way of handling this
        if ($this->v2_output_buffering === true) {
            if (ob_get_length() > 0) {
                ob_end_clean(); // @codeCoverageIgnore
            }
        }

        if (!headers_sent()) {
            $this->sendHeaders(); // @codeCoverageIgnore
        }

        echo $this->body;

        $this->sent = true;
    }
}
