<?php

declare(strict_types=1);

namespace flight\net;

use flight\util\Collection;

/**
 * The Request class represents an HTTP request. Data from
 * all the super globals $_GET, $_POST, $_COOKIE, and $_FILES
 * are stored and accessible via the Request object.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 *
 * The default request properties are:
 *
 *   - **url** - The URL being requested
 *   - **base** - The parent subdirectory of the URL
 *   - **method** - The request method (GET, POST, PUT, DELETE)
 *   - **referrer** - The referrer URL
 *   - **ip** - IP address of the client
 *   - **ajax** - Whether the request is an AJAX request
 *   - **scheme** - The server protocol (http, https)
 *   - **user_agent** - Browser information
 *   - **type** - The content type
 *   - **length** - The content length
 *   - **query** - Query string parameters
 *   - **data** - Post parameters
 *   - **cookies** - Cookie parameters
 *   - **files** - Uploaded files
 *   - **secure** - Connection is secure
 *   - **accept** - HTTP accept parameters
 *   - **proxy_ip** - Proxy IP address of the client
 *   - **host** - The hostname from the request.
 *   - **servername** - The server's hostname. See `$_SERVER['SERVER_NAME']`.
 */
class Request
{
    /**
     * URL being requested
     */
    public string $url;

    /**
     * Parent subdirectory of the URL
     */
    public string $base;

    /**
     * Request method (GET, POST, PUT, DELETE)
     */
    public string $method;

    /**
     * Referrer URL
     */
    public string $referrer;

    /**
     * IP address of the client
     */
    public string $ip;

    /**
     * Whether the request is an AJAX request
     */
    public bool $ajax;

    /**
     * Server protocol (http, https)
     */
    public string $scheme;

    /**
     * Browser information
     */
    public string $user_agent;

    /**
     * Content type
     */
    public string $type;

    /**
     * Content length
     */
    public int $length;

    /**
     * Query string parameters
     */
    public Collection $query;

    /**
     * Post parameters
     */
    public Collection $data;

    /**
     * Cookie parameters
     */
    public Collection $cookies;

    /**
     * Uploaded files
     */
    public Collection $files;

    /**
     * Whether the connection is secure
     */
    public bool $secure;

    /**
     * HTTP accept parameters
     */
    public string $accept;

    /**
     * Proxy IP address of the client
     */
    public string $proxy_ip;

    /**
     * HTTP host name
     */
    public string $host;

    /**
     * Server name
     *
     * CAUTION: Note: Under Apache 2, UseCanonicalName = On and ServerName must be set.
     * Otherwise, this value reflects the hostname supplied by the client, which can be spoofed.
     * It is not safe to rely on this value in security-dependent contexts.
     */
    public string $servername;

    /**
     * Stream path for where to pull the request body from
     */
    private string $stream_path = 'php://input';

    /**
     * Raw HTTP request body
     */
    public string $body = '';

    /**
     * Hold tmp file handles created via tmpfile() so they persist for request lifetime
     *
     * @var array<int, resource>
     */
    private array $tmpFileHandles = [];

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config Request configuration
     */
    public function __construct(array $config = [])
    {
        // Default properties
        if (empty($config) === true) {
            $scheme = $this->getScheme();
            $url = $this->getVar('REQUEST_URI', '/');
            if (strpos($url, '@') !== false) {
                $url = str_replace('@', '%40', $url);
            }
            $base = $this->getVar('SCRIPT_NAME', '');
            if (strpos($base, ' ') !== false || strpos($base, '\\') !== false) {
                $base = str_replace(['\\', ' '], ['/', '%20'], $base);
            }
            $base = dirname($base);
            if ($base === '\\') {
                $base = '/'; // @codeCoverageIgnore
            }
            $config = [
                'url' => $url,
                'base' => $base,
                'method' => $this->getMethod(),
                'referrer' => $this->getVar('HTTP_REFERER'),
                'ip' => $this->getVar('REMOTE_ADDR'),
                'ajax' => $this->getVar('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest',
                'scheme' => $scheme,
                'user_agent' => $this->getVar('HTTP_USER_AGENT'),
                'type' => $this->getVar('CONTENT_TYPE'),
                'length' => intval($this->getVar('CONTENT_LENGTH', 0)),
                'query' => new Collection($_GET),
                'data' => new Collection($_POST),
                'cookies' => new Collection($_COOKIE),
                'files' => new Collection($_FILES),
                'secure' => $scheme === 'https',
                'accept' => $this->getVar('HTTP_ACCEPT'),
                'proxy_ip' => $this->getProxyIpAddress(),
                'host' => $this->getVar('HTTP_HOST'),
                'servername' => $this->getVar('SERVER_NAME', ''),
            ];
        }

        $this->init($config);
    }

    /**
     * Initialize request properties.
     *
     * @param array<string, mixed> $properties Array of request properties
     *
     * @return self
     */
    public function init(array $properties = []): self
    {
        // Set all the defined properties
        foreach ($properties as $name => $value) {
            $this->{$name} = $value;
        }

        // Get the requested URL without the base directory
        // This rewrites the url in case the public url and base directories match
        // (such as installing on a subdirectory in a web server)
        // @see testInitUrlSameAsBaseDirectory
        if ($this->base !== '/' && $this->base !== '' && strpos($this->url, $this->base) === 0) {
            $this->url = substr($this->url, strlen($this->base));
        }

        // Default url
        if (empty($this->url) === true) {
            $this->url = '/';
        } else {
            // Merge URL query parameters with $_GET
            $_GET = array_merge($_GET, self::parseQuery($this->url));

            $this->query->setData($_GET);
        }

        // Check for JSON input
        if (strpos($this->type, 'application/json') === 0) {
            $body = $this->getBody();
            if ($body !== '') {
                $data = json_decode($body, true);
                if (is_array($data) === true) {
                    $this->data->setData($data);
                }
            }
            // Check PUT, PATCH, DELETE for application/x-www-form-urlencoded data or multipart/form-data
        } elseif (in_array($this->method, ['PUT', 'DELETE', 'PATCH'], true) === true) {
            $this->parseRequestBodyForHttpMethods();
        }

        return $this;
    }

    /**
     * Gets the body of the request.
     *
     * @return string Raw HTTP request body
     */
    public function getBody(): string
    {
        $body = $this->body;

        if ($body !== '') {
            return $body;
        }

        $method = $this->method ?? $this->getMethod();

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true) === true) {
            $body = file_get_contents($this->stream_path);
        }

        $this->body = $body;

        return $body;
    }

    /**
     * Gets the request method.
     */
    public static function getMethod(): string
    {
        $method = self::getVar('REQUEST_METHOD', 'GET');

        if (self::getVar('HTTP_X_HTTP_METHOD_OVERRIDE') !== '') {
            $method = self::getVar('HTTP_X_HTTP_METHOD_OVERRIDE');
        } elseif (isset($_REQUEST['_method']) === true) {
            $method = $_REQUEST['_method'];
        }

        return strtoupper($method);
    }

    /**
     * Gets the real remote IP address.
     *
     * @return string IP address
     */
    public static function getProxyIpAddress(): string
    {
        $forwarded = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];

        $flags = \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE;

        foreach ($forwarded as $key) {
            $serverVar = self::getVar($key);
            if ($serverVar !== '') {
                sscanf($serverVar, '%[^,]', $ip);
                if (filter_var($ip, \FILTER_VALIDATE_IP, $flags) !== false) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Gets a variable from $_SERVER using $default if not provided.
     *
     * @param string $var     Variable name
     * @param mixed  $default Default value to substitute
     *
     * @return mixed Server variable value
     */
    public static function getVar(string $var, $default = '')
    {
        return $_SERVER[$var] ?? $default;
    }

    /**
     * This will pull a header from the request.
     *
     * @param string $header  Header name. Can be caps, lowercase, or mixed.
     * @param string $default Default value if the header does not exist
     *
     * @return string
     */
    public static function getHeader(string $header, $default = ''): string
    {
        $header = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        return self::getVar($header, $default);
    }

    /**
     * Gets all the request headers
     *
     * @return array<string, string|int>
     */
    public static function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                // converts headers like HTTP_CUSTOM_HEADER to Custom-Header
                $key = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    /**
     * Alias of Request->getHeader(). Gets a single header.
     *
     * @param string $header  Header name. Can be caps, lowercase, or mixed.
     * @param string $default Default value if the header does not exist
     *
     * @return string
     */
    public static function header(string $header, $default = ''): string
    {
        return self::getHeader($header, $default);
    }

    /**
     * Alias of Request->getHeaders(). Gets all the request headers
     *
     * @return array<string, string|int>
     */
    public static function headers(): array
    {
        return self::getHeaders();
    }

    /**
     * Gets the full request URL.
     *
     * @return string URL
     */
    public function getFullUrl(): string
    {
        return $this->scheme . '://' . $this->host . $this->url;
    }

    /**
     * Grabs the scheme and host. Does not end with a /
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->scheme . '://' . $this->host;
    }

    /**
     * Parse query parameters from a URL.
     *
     * @param string $url URL string
     *
     * @return array<string, int|string|array<int|string, int|string>>
     */
    public static function parseQuery(string $url): array
    {
        $queryPos = strpos($url, '?');
        if ($queryPos === false) {
            return [];
        }
        $query = substr($url, $queryPos + 1);
        if ($query === '') {
            return [];
        }
        $params = [];
        parse_str($query, $params);
        return $params;
    }

    /**
     * Gets the URL Scheme
     *
     * @return string 'http'|'https'
     */
    public static function getScheme(): string
    {
        if (
            (strtolower(self::getVar('HTTPS')) === 'on')
            ||
            (self::getVar('HTTP_X_FORWARDED_PROTO') === 'https')
            ||
            (self::getVar('HTTP_FRONT_END_HTTPS') === 'on')
            ||
            (self::getVar('REQUEST_SCHEME') === 'https')
        ) {
            return 'https';
        }

        return 'http';
    }

    /**
     * Negotiates the best content type from the Accept header.
     *
     * @param array<int, string> $supported List of supported content types.
     *
     * @return ?string The negotiated content type.
     */
    public function negotiateContentType(array $supported): ?string
    {
        $accept = $this->header('Accept') ?: '';
        if ($accept === '') {
            return $supported[0];
        }
        foreach ($supported as $type) {
            if (stripos($accept, $type) !== false) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Retrieves the array of uploaded files.
     *
     * @return array<string, UploadedFile|array<int, UploadedFile>> Key is field name; value is either a single UploadedFile or an array of UploadedFile when multiple were uploaded.
     */
    public function getUploadedFiles(): array
    {
        $uploadedFiles = [];
        $correctedFilesArray = $this->reArrayFiles($this->files);
        foreach ($correctedFilesArray as $keyName => $files) {
            // Check if original data was array format (files_name[] style)
            $originalFile = $this->files->getData()[$keyName] ?? null;
            $isArrayFormat = $originalFile && is_array($originalFile['name']);

            foreach ($files as $file) {
                $UploadedFile = new UploadedFile(
                    $file['name'],
                    $file['type'],
                    $file['size'],
                    $file['tmp_name'],
                    $file['error']
                );

                // Always use array format if original data was array, regardless of count
                if ($isArrayFormat === true) {
                    $uploadedFiles[$keyName][] = $UploadedFile;
                } else {
                    $uploadedFiles[$keyName] = $UploadedFile;
                }
            }
        }

        return $uploadedFiles;
    }

    /**
     * Re-arranges the files in the given files collection.
     *
     * @param Collection $filesCollection The collection of files to be re-arranged.
     *
     * @return array<string, array<int, array<string, mixed>>> The re-arranged files collection.
     */
    protected function reArrayFiles(Collection $filesCollection): array
    {
        $fileArray = [];
        foreach ($filesCollection as $fileKeyName => $file) {
            $isMulti = is_array($file['name']) === true;
            $fileCount = $isMulti === true ? count($file['name']) : 1;
            $fileKeys = array_keys($file);

            for ($i = 0; $i < $fileCount; $i++) {
                foreach ($fileKeys as $key) {
                    if ($isMulti === true) {
                        $fileArray[$fileKeyName][$i][$key] = $file[$key][$i];
                    } else {
                        $fileArray[$fileKeyName][$i][$key] = $file[$key];
                    }
                }
            }
        }

        return $fileArray;
    }

    /**
     * Parse request body data for HTTP methods that don't natively support form data (PUT, DELETE, PATCH)
     *
     * @return void
     */
    protected function parseRequestBodyForHttpMethods(): void
    {
        $body = $this->getBody();

        // Empty body
        if ($body === '') {
            return;
        }

        // Check Content-Type for multipart/form-data
        $contentType = strtolower(trim($this->type));
        $isMultipart = strpos($contentType, 'multipart/form-data') === 0;
        $boundary = null;

        if ($isMultipart === true) {
            // Extract boundary more safely
            if (preg_match('/boundary=(["\']?)([^"\';,\s]+)\1/i', $this->type, $matches)) {
                $boundary = $matches[2];
            }

            // If no boundary found, it's not valid multipart
            if (empty($boundary)) {
                $isMultipart = false;
            }

            $firstLine = strtok($body, "\r\n");
            if ($firstLine === false || strpos($firstLine, '--' . $boundary) !== 0) {
                // Does not start with the boundary marker; fall back
                $isMultipart = false;
            }
        }

        // Parse application/x-www-form-urlencoded
        if ($isMultipart === false) {
            parse_str($body, $data);
            $this->data->setData($data);
            return;
        }

        $this->setParsedRequestBodyMultipartFormData($body, $boundary);
    }

    /**
     * Sets the parsed request body for multipart form data requests
     *
     * This method processes and stores multipart form data from the request body,
     * parsing it according to the specified boundary delimiter. It handles the
     * complex parsing of multipart data including file uploads and form fields.
     *
     * @param string $body The raw multipart request body content
     * @param string $boundary The boundary string used to separate multipart sections
     *
     * @return void
     */
    protected function setParsedRequestBodyMultipartFormData(string $body, string $boundary): void
    {

        $data = [];
        $file = [];

        // Parse multipart/form-data
        $bodyParts = preg_split('/\R?-+' . preg_quote($boundary, '/') . '/s', $body);
        array_pop($bodyParts); // Remove last element (closing boundary or empty)

        $partsProcessed = 0;
        $filesTotalBytes = 0;
        // Use ini values directly
        $maxParts = (int) ini_get('max_file_uploads');
        if ($maxParts <= 0) {
            // unlimited parts if not specified
            $maxParts = PHP_INT_MAX; // @codeCoverageIgnore
        }
        $maxTotalBytes = self::derivePostMaxSizeBytes();

        foreach ($bodyParts as $bodyPart) {
            if ($partsProcessed >= $maxParts) {
                // reached part limit from ini
                break; // @codeCoverageIgnore
            }
            if ($bodyPart === '' || $bodyPart === null) {
                continue; // skip empty segments
            }
            $partsProcessed++;

            // Split headers and value; if format invalid, skip early
            $split = preg_split('/\R\R/', $bodyPart, 2);
            if ($split === false || count($split) < 2) {
                continue;
            }
            [$header, $value] = $split;

            // Fast header sanity checks
            if (stripos($header, 'content-disposition') === false) {
                continue;
            }
            if (strlen($header) > 16384) { // 16KB header block guard
                continue;
            }

            $value = ltrim($value, "\r\n");

            // Parse headers (simple approach, fail-fast on anomalies)
            $headers = $this->parseRequestBodyHeadersFromMultipartFormData($header);

            // Required disposition/name
            if (isset($headers['content-disposition']['name']) === false) {
                continue;
            }
            $keyName = str_replace('[]', '', (string) $headers['content-disposition']['name']);
            if ($keyName === '') {
                continue; // avoid empty keys
            }

            // Non-file field
            if (isset($headers['content-disposition']['filename']) === false) {
                if (isset($data[$keyName]) === false) {
                    $data[$keyName] = $value;
                } else {
                    if (is_array($data[$keyName]) === false) {
                        $data[$keyName] = [$data[$keyName]];
                    }
                    $data[$keyName][] = $value;
                }
                continue; // done with this part
            }

            // Sanitize filename early
            $rawFilename = (string) $headers['content-disposition']['filename'];
            $rawFilename = str_replace(["\0", "\r", "\n"], '', $rawFilename);
            $sanitizedFilename = basename($rawFilename);
            $matchCriteria = preg_match('/^[A-Za-z0-9._-]{1,255}$/', $sanitizedFilename);
            if ($sanitizedFilename === '' || $matchCriteria !== 1) {
                $sanitizedFilename = 'upload_' . uniqid('', true);
            }

            $size = mb_strlen($value, '8bit');
            $filesTotalBytes += $size;
            $tmpFile = [
                'name' => $sanitizedFilename,
                'type' => $headers['content-type'] ?? 'application/octet-stream',
                'size' => $size,
                'tmp_name' => '',
                'error' => UPLOAD_ERR_OK,
            ];

            // Fail fast on size constraints
            if ($size > $this->getUploadMaxFileSize() || $filesTotalBytes > $maxTotalBytes) {
                // individual file or total size exceeded
                $tmpFile['error'] = UPLOAD_ERR_INI_SIZE; // @codeCoverageIgnore
            } else {
                $tempResult = $this->createTempFile($value);
                $tmpFile['tmp_name'] = $tempResult['tmp_name'];
                $tmpFile['error'] = $tempResult['error'];
            }

            // Aggregate into synthetic files array
            foreach ($tmpFile as $metaKey => $metaVal) {
                if (!isset($file[$keyName][$metaKey])) {
                    $file[$keyName][$metaKey] = $metaVal;
                    continue;
                }
                if (!is_array($file[$keyName][$metaKey])) {
                    $file[$keyName][$metaKey] = [$file[$keyName][$metaKey]];
                }
                $file[$keyName][$metaKey][] = $metaVal;
            }
        }

        $this->data->setData($data);
        $this->files->setData($file);
    }

    /**
     * Parses request body headers from multipart form data
     *
     * This method extracts and processes headers from a multipart form data section,
     * typically used for file uploads or complex form submissions. It parses the
     * header string and returns an associative array of header name-value pairs.
     *
     * @param string $header The raw header string from a multipart form data section
     *
     * @return array<string,mixed> An associative array containing parsed header name-value pairs
     */
    protected function parseRequestBodyHeadersFromMultipartFormData(string $header): array
    {
        $headers = [];
        foreach (preg_split('/\R/', $header) as $headerLine) {
            if (strpos($headerLine, ':') === false) {
                continue;
            }
            [$headerKey, $headerValue] = explode(':', $headerLine, 2);
            $headerKey = strtolower(trim($headerKey));
            $headerValue = trim($headerValue);
            if (strpos($headerValue, ';') !== false) {
                $headers[$headerKey] = [];
                foreach (explode(';', $headerValue) as $hvPart) {
                    preg_match_all('/(\w+)="?([^";]+)"?/', $hvPart, $matches, PREG_SET_ORDER);
                    foreach ($matches as $m) {
                        $subKey = strtolower($m[1]);
                        $headers[$headerKey][$subKey] = $m[2];
                    }
                }
            } else {
                $headers[$headerKey] = $headerValue;
            }
        }
        return $headers;
    }

    /**
     * Get the maximum file size that can be uploaded.
     *
     * @return int The maximum file size in bytes.
     */
    public function getUploadMaxFileSize(): int
    {
        $value = ini_get('upload_max_filesize');
        return self::parsePhpSize($value);
    }

    /**
     * Parse a PHP shorthand size string (like "1K", "1.5M") into bytes.
     * Returns 0 on unknown or unsupported unit (keeps existing behavior).
     *
     * @param string $size
     *
     * @return int
     */
    public static function parsePhpSize(string $size): int
    {
        $unit = strtolower(preg_replace('/[^a-zA-Z]/', '', $size));
        $value = (int) preg_replace('/[^\d.]/', '', $size);

        // No unit => follow existing behavior and return value directly if > 1024 (1K)
        if ($unit === '' && $value >= 1024) {
            return $value;
        }

        switch ($unit) {
            case 't':
            case 'tb':
                $value *= 1024; // Fall through
            case 'g':
            case 'gb':
                $value *= 1024; // Fall through
            case 'm':
            case 'mb':
                $value *= 1024; // Fall through
            case 'k':
                $value *= 1024;
                break;
            default:
                return 0;
        }

        return $value;
    }

    /**
     * Derive post_max_size in bytes. Returns 0 when unlimited or unparsable.
     */
    private static function derivePostMaxSizeBytes(): int
    {
        $postMax = (string) ini_get('post_max_size');
        $bytes = self::parsePhpSize($postMax);
        return $bytes; // 0 means unlimited
    }

    /**
     * Create a temporary file for uploaded content using tmpfile().
     * Returns array with tmp_name and error code.
     *
     * @param string $content
     *
     * @return array<string,string|int>
     */
    private function createTempFile(string $content): array
    {
        $fp = tmpfile();
        if ($fp === false) {
            return ['tmp_name' => '', 'error' => UPLOAD_ERR_CANT_WRITE]; // @codeCoverageIgnore
        }
        $bytes = fwrite($fp, $content);
        if ($bytes === false) {
            fclose($fp); // @codeCoverageIgnore
            return ['tmp_name' => '', 'error' => UPLOAD_ERR_CANT_WRITE]; // @codeCoverageIgnore
        }
        $meta = stream_get_meta_data($fp);
        $tmpName = isset($meta['uri']) ? $meta['uri'] : '';
        $this->tmpFileHandles[] = $fp; // retain handle for lifecycle
        return ['tmp_name' => $tmpName, 'error' => UPLOAD_ERR_OK];
    }
}
