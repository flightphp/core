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
            $config = [
                'url'        => $url,
                'base'       => $base,
                'method'     => $this->getMethod(),
                'referrer'   => $this->getVar('HTTP_REFERER'),
                'ip'         => $this->getVar('REMOTE_ADDR'),
                'ajax'       => $this->getVar('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest',
                'scheme'     => $scheme,
                'user_agent' => $this->getVar('HTTP_USER_AGENT'),
                'type'       => $this->getVar('CONTENT_TYPE'),
                'length'     => intval($this->getVar('CONTENT_LENGTH', 0)),
                'query'      => new Collection($_GET ?? []),
                'data'       => new Collection($_POST ?? []),
                'cookies'    => new Collection($_COOKIE ?? []),
                'files'      => new Collection($_FILES ?? []),
                'secure'     => $scheme === 'https',
                'accept'     => $this->getVar('HTTP_ACCEPT'),
                'proxy_ip'   => $this->getProxyIpAddress(),
                'host'       => $this->getVar('HTTP_HOST'),
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
            $_GET = array_merge($_GET ?? [], self::parseQuery($this->url));

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
        } elseif (in_array($this->method, [ 'PUT', 'DELETE', 'PATCH' ], true) === true) {
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
    public static function header(string $header, $default = '')
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
        $accept = $this->header('Accept') ?? '';
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
     * @return array<string, array<string,UploadedFile>|array<string,array<string,UploadedFile>>> The array of uploaded files.
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
                if ($isArrayFormat) {
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
        
        if ($isMultipart) {
            // Extract boundary more safely
            if (preg_match('/boundary=(["\']?)([^"\';,\s]+)\1/i', $this->type, $matches)) {
                $boundary = $matches[2];
            }
            
            // If no boundary found, it's not valid multipart
            if (empty($boundary)) {
                $isMultipart = false;
            }
        }

        $data = [];
        $file = [];

        // Parse application/x-www-form-urlencoded
        if ($isMultipart === false) {
            parse_str($body, $data);
            $this->data->setData($data);

            return;
        }
        
        // Parse multipart/form-data
        $bodyParts = preg_split('/\\R?-+' . preg_quote($boundary, '/') . '/s', $body);
        array_pop($bodyParts); // Remove last element (empty)
        
        foreach ($bodyParts as $bodyPart) {
            if (empty($bodyPart)) {
                continue;
            }

            // Get the headers and value
            [$header, $value] = preg_split('/\\R\\R/', $bodyPart, 2);
            
            // Check if the header is normal
            if (strpos(strtolower($header), 'content-disposition') === false) {
                continue;
            }

            $value = ltrim($value, "\r\n");

            /**
             * Process Header
             */
            $headers = [];
            // split the headers
            $headerParts = preg_split('/\\R/', $header);
            foreach ($headerParts as $headerPart) {
                if (strpos($headerPart, ':') === false) {
                    continue;
                }

                // Process the header
                [$headerKey, $headerValue] = explode(':', $headerPart, 2);
                
                $headerKey = strtolower(trim($headerKey));
                $headerValue = trim($headerValue);
                
                if (strpos($headerValue, ';') !== false) {
                    $headers[$headerKey] = [];
                    foreach (explode(';', $headerValue) as $headerValuePart) {
                        preg_match_all('/(\w+)=\"?([^";]+)\"?/', $headerValuePart, $headerMatches, PREG_SET_ORDER);

                        foreach ($headerMatches as $headerMatch) {
                            $headerSubKey = strtolower($headerMatch[1]);
                            $headerSubValue = $headerMatch[2];

                            $headers[$headerKey][$headerSubKey] = $headerSubValue;
                        }
                    }
                } else {
                    $headers[$headerKey] = $headerValue;
                }
            }

            /**
             * Process Value
             */
            if (!isset($headers['content-disposition']) || !isset($headers['content-disposition']['name'])) {
                continue;
            }

            $keyName = str_replace("[]", "", $headers['content-disposition']['name']);

            // if is not file
            if (!isset($headers['content-disposition']['filename'])) {
                if (isset($data[$keyName])) {
                    if (!is_array($data[$keyName])) {
                        $data[$keyName] = [$data[$keyName]];
                    }
                    $data[$keyName][] = $value;
                } else {
                    $data[$keyName] = $value;
                }
                continue;
            }

            $tmpFile = [
                'name' => $headers['content-disposition']['filename'],
                'type' => $headers['content-type'] ?? 'application/octet-stream',
                'size' => mb_strlen($value, '8bit'),
                'tmp_name' => '',
                'error' => UPLOAD_ERR_OK,
            ];

            if ($tmpFile['size'] > $this->getUploadMaxFileSize()) {
                $tmpFile['error'] = UPLOAD_ERR_INI_SIZE;
            } else {
                // Create a temporary file
                $tmpName = tempnam(sys_get_temp_dir(), 'flight_tmp_');
                if ($tmpName === false) {
                    $tmpFile['error'] = UPLOAD_ERR_CANT_WRITE;
                } else {
                    // Write the value to a temporary file
                    $bytes = file_put_contents($tmpName, $value);

                    if ($bytes === false) {
                        $tmpFile['error'] = UPLOAD_ERR_CANT_WRITE;
                    } else {
                        // delete the temporary file before ended script
                        register_shutdown_function(function () use ($tmpName): void {
                            if (file_exists($tmpName)) {
                                unlink($tmpName);
                            }
                        });
    
                        $tmpFile['tmp_name'] = $tmpName;
                    }
                }
            }

            foreach ($tmpFile as $key => $value) {
                if (isset($file[$keyName][$key])) {
                    if (!is_array($file[$keyName][$key])) {
                        $file[$keyName][$key] = [$file[$keyName][$key]];
                    }
                    $file[$keyName][$key][] = $value;
                } else {
                    $file[$keyName][$key] = $value;
                }
            }
        }
        
        $this->data->setData($data);
        $this->files->setData($file);
    }

    /**
     * Get the maximum file size that can be uploaded.
     * @return int The maximum file size in bytes.
     */
    protected function getUploadMaxFileSize(): int {
        $value = ini_get('upload_max_filesize');

        $unit = strtolower(preg_replace('/[^a-zA-Z]/', '', $value));
        $value = preg_replace('/[^\d.]/', '', $value);

        switch ($unit) {
            case 'p':	// PentaByte
            case 'pb':
                $value *= 1024;
            case 't':	// Terabyte
                $value *= 1024;
            case 'g':	// Gigabyte
                $value *= 1024;
            case 'm':	// Megabyte
                $value *= 1024;
            case 'k':	// Kilobyte
                $value *= 1024;
            case 'b':	// Byte
                break;
            default:
                return 0;
        }

        return (int)$value;
    }
}
