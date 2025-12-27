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
        if (file_exists('existing.txt')) {
            unlink('existing.txt');
        }
        if (file_exists('real_file')) {
            unlink('real_file');
        }

        // not found with file_exists...just delete it brute force
        @unlink('tmp_symlink');
    }

    public function testMoveToFalseSuccess(): void
    {
        // This test would have passed in the real world but we can't actually force a post request in unit tests
        file_put_contents('tmp_name', 'test');
        $uploadedFile = new UploadedFile('file.txt', 'text/plain', 4, 'tmp_name', UPLOAD_ERR_OK, true);
        $this->expectExceptionMessage('Cannot move uploaded file');
        $uploadedFile->moveTo('file.txt');
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

    public function testMoveToBadLocation(): void
    {
        file_put_contents('tmp_name', 'test');
        $uploadedFile = new UploadedFile('file.txt', 'text/plain', 4, 'tmp_name', UPLOAD_ERR_OK, true);
        $this->expectExceptionMessage('Target directory is not writable');
        $uploadedFile->moveTo('/root/file.txt');
    }

    public function testMoveToSuccessNonPost(): void
    {
        file_put_contents('tmp_name', 'test');
        $uploadedFile = new UploadedFile('file.txt', 'text/plain', 4, 'tmp_name', UPLOAD_ERR_OK, false);
        $uploadedFile->moveTo('file.txt');
        $this->assertFileExists('file.txt');
        $this->assertEquals('test', file_get_contents('file.txt'));
    }

    public function testMoveToPathTraversal(): void
    {
        file_put_contents('tmp_name', 'test');
        $uploadedFile = new UploadedFile('file.txt', 'text/plain', 4, 'tmp_name', UPLOAD_ERR_OK, false);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid target path: contains directory traversal');
        $uploadedFile->moveTo('../file.txt');
    }

    public function testMoveToAbsolutePath(): void
    {
        file_put_contents('tmp_name', 'test');
        $uploadedFile = new UploadedFile('file.txt', 'text/plain', 4, 'tmp_name', UPLOAD_ERR_OK, false);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid target path: absolute paths not allowed');
        $uploadedFile->moveTo('/tmp/file.txt');
    }

    public function testMoveToOverwrite(): void
    {
        file_put_contents('tmp_name', 'test');
        file_put_contents('existing.txt', 'existing');
        $uploadedFile = new UploadedFile('file.txt', 'text/plain', 4, 'tmp_name', UPLOAD_ERR_OK, false);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Target file already exists');
        $uploadedFile->moveTo('existing.txt');
    }

    public function testMoveToSymlinkNonPost(): void
    {
        file_put_contents('real_file', 'test');
        if (file_exists('tmp_symlink')) {
            unlink('tmp_symlink');
        }
        symlink('real_file', 'tmp_symlink');
        $uploadedFile = new UploadedFile('file.txt', 'text/plain', 4, 'tmp_symlink', UPLOAD_ERR_OK, false);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid temp file: symlink detected');
        $uploadedFile->moveTo('file.txt');
    }
}
