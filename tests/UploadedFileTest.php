<?php

declare(strict_types=1);

namespace tests;

use Exception;
use flight\net\UploadedFile;
use PHPUnit\Framework\TestCase;

class UploadedFileTest extends TestCase
{
    public function tearDown(): void
    {
        if (file_exists('file.txt')) {
            unlink('file.txt');
        }
        if (file_exists('tmp_name')) {
            unlink('tmp_name');
        }
    }

    public function testMoveToSuccess(): void
    {
        file_put_contents('tmp_name', 'test');
        $uploadedFile = new UploadedFile('file.txt', 'text/plain', 4, 'tmp_name', UPLOAD_ERR_OK);
        $uploadedFile->moveTo('file.txt');
        $this->assertFileExists('file.txt');
    }

    public function getFileErrorMessageTests(): array
    {
        return [
            [ UPLOAD_ERR_INI_SIZE, 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', ],
            [ UPLOAD_ERR_FORM_SIZE, 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', ],
            [ UPLOAD_ERR_PARTIAL, 'The uploaded file was only partially uploaded.', ],
            [ UPLOAD_ERR_NO_FILE, 'No file was uploaded.', ],
            [ UPLOAD_ERR_NO_TMP_DIR, 'Missing a temporary folder.', ],
            [ UPLOAD_ERR_CANT_WRITE, 'Failed to write file to disk.', ],
            [ UPLOAD_ERR_EXTENSION, 'A PHP extension stopped the file upload.', ],
            [ -1, 'An unknown error occurred. Error code: -1' ]
        ];
    }

    /**
     * @dataProvider getFileErrorMessageTests
     */
    public function testMoveToFailureMessages($error, $message)
    {
        file_put_contents('tmp_name', 'test');
        $uploadedFile = new UploadedFile('file.txt', 'text/plain', 4, 'tmp_name', $error);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);
        $uploadedFile->moveTo('file.txt');
    }
}
