<?php

declare(strict_types=1);

namespace flight\net;

use Exception;

class UploadedFile
{
    /**
     * @var string $name The name of the uploaded file.
     */
    private string $name;

    /**
     * @var string $mimeType The MIME type of the uploaded file.
     */
    private string $mimeType;

    /**
     * @var int $size The size of the uploaded file in bytes.
     */
    private int $size;

    /**
     * @var string $tmpName The temporary name of the uploaded file.
     */
    private string $tmpName;

    /**
     * @var int $error The error code associated with the uploaded file.
     */
    private int $error;

    /**
     * @var bool $isPostUploadedFile Indicates if the file was uploaded via POST method.
     */
    private bool $isPostUploadedFile = false;

    /**
     * Constructs a new UploadedFile object.
     *
     * @param string $name The name of the uploaded file.
     * @param string $mimeType The MIME type of the uploaded file.
     * @param int $size The size of the uploaded file in bytes.
     * @param string $tmpName The temporary name of the uploaded file.
     * @param int $error The error code associated with the uploaded file.
     * @param bool|null $isPostUploadedFile Indicates if the file was uploaded via POST method.
     */
    public function __construct(string $name, string $mimeType, int $size, string $tmpName, int $error, ?bool $isPostUploadedFile = null)
    {
        $this->name = $name;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->tmpName = $tmpName;
        $this->error = $error;
        if (is_uploaded_file($tmpName) === true) {
            $this->isPostUploadedFile = true; // @codeCoverageIgnore
        } else {
            $this->isPostUploadedFile = $isPostUploadedFile ?? false;
        }
    }

    /**
     * Retrieves the client-side filename of the uploaded file.
     *
     * @return string The client-side filename.
     */
    public function getClientFilename(): string
    {
        return $this->name;
    }

    /**
     * Retrieves the media type of the uploaded file as provided by the client.
     *
     * @return string The media type of the uploaded file.
     */
    public function getClientMediaType(): string
    {
        return $this->mimeType;
    }

    /**
     * Returns the size of the uploaded file.
     *
     * @return int The size of the uploaded file.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Retrieves the temporary name of the uploaded file.
     *
     * @return string The temporary name of the uploaded file.
     */
    public function getTempName(): string
    {
        return $this->tmpName;
    }

    /**
     * Get the error code associated with the uploaded file.
     *
     * @return int The error code.
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Moves the uploaded file to the specified target path.
     *
     * @param string $targetPath The path to move the file to.
     *
     * @return void
     */
    public function moveTo(string $targetPath): void
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($this->error));
        }

        if (is_writeable(dirname($targetPath)) === false) {
            throw new Exception('Target directory is not writable');
        }

        // Prevent path traversal attacks
        if (strpos($targetPath, '..') !== false) {
            throw new Exception('Invalid target path: contains directory traversal');
        }
        // Prevent absolute paths (basic check for Unix/Windows)
        if ($targetPath[0] === '/' || (strlen($targetPath) > 1 && $targetPath[1] === ':')) {
            throw new Exception('Invalid target path: absolute paths not allowed');
        }

        // Prevent overwriting existing files
        if (file_exists($targetPath)) {
            throw new Exception('Target file already exists');
        }

        // Check if this is a legitimate uploaded file (POST method uploads)
        $isUploadedFile = $this->isPostUploadedFile;

        // Prevent symlink attacks for non-POST uploads
        if (!$isUploadedFile && is_link($this->tmpName)) {
            throw new Exception('Invalid temp file: symlink detected');
        }

        $uploadFunctionToCall = $isUploadedFile === true ?
            // Standard POST upload - use move_uploaded_file for security
            'move_uploaded_file' :
             // Handle non-POST uploads (PATCH, PUT, DELETE) or other valid temp files
            'rename';

        $result = $uploadFunctionToCall($this->tmpName, $targetPath);

        if ($result === false) {
            throw new Exception('Cannot move uploaded file');
        }
    }

    /**
     * Retrieves the error message for a given upload error code.
     *
     * @param int $error The upload error code.
     *
     * @return string The error message.
     */
    protected function getUploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload.';
            default:
                return 'An unknown error occurred. Error code: ' . $error;
        }
    }
}
