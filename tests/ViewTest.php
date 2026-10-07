<?php

declare(strict_types=1);

namespace tests;

use Exception;
use flight\template\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $this->view = new View();
        $this->view->path = __DIR__ . '/views';
    }

    // Set template variables
    public function testVariables(): void
    {
        $this->view->set('test', 123);

        $this->assertEquals(123, $this->view->get('test'));

        $this->assertTrue($this->view->has('test'));
        $this->assertTrue(!$this->view->has('unknown'));

        $this->view->clear('test');

        $this->assertNull($this->view->get('test'));
    }

    public function testMultipleVariables(): void
    {
        $this->view->set([
            'test' => 123,
            'foo' => 'bar'
        ]);

        $this->assertEquals(123, $this->view->get('test'));
        $this->assertEquals('bar', $this->view->get('foo'));

        $this->view->clear();

        $this->assertNull($this->view->get('test'));
        $this->assertNull($this->view->get('foo'));
    }

    // Check if template files exist
    public function testTemplateExists(): void
    {
        $this->assertTrue($this->view->exists('hello.php'));
        $this->assertTrue(!$this->view->exists('unknown.php'));
    }

    // Render a template
    public function testRender(): void
    {
        $this->view->render('hello', ['name' => 'Bob']);

        $this->expectOutputString('Hello, Bob!');
    }

    public function testRenderBadFilePath(): void
    {
        $this->expectException(Exception::class);
        $exception_message = sprintf(
            'Template file not found: %s%sviews%sbadfile.php',
            __DIR__,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR
        );
        $this->expectExceptionMessage($exception_message);

        $this->view->render('badfile');
    }

    // Fetch template output
    public function testFetch(): void
    {
        $output = $this->view->fetch('hello', ['name' => 'Bob']);

        $this->assertEquals('Hello, Bob!', $output);
    }

    // Default extension
    public function testTemplateWithExtension(): void
    {
        $this->view->set('name', 'Bob');

        $this->view->render('hello.php');

        $this->expectOutputString('Hello, Bob!');
    }

    // Custom extension
    public function testTemplateWithCustomExtension(): void
    {
        $this->view->set('name', 'Bob');
        $this->view->extension = '.html';

        ob_start();
        $this->view->render('world');
        $html = ob_get_clean();
        $html = str_replace(["\r\n", "\n"], '', $html);
        echo $html;

        $this->expectOutputString("Hello world, Bob!");
    }

    public function testGetTemplateAbsolutePath(): void
    {
        $tmpfile = tmpfile();
        $this->view->extension = '';
        $file_path = stream_get_meta_data($tmpfile)['uri'];
        $this->assertEquals($file_path, $this->view->getTemplate($file_path));
    }

    public function testE(): void
    {
        $this->expectOutputString('&lt;script&gt;');
        $result = $this->view->e('<script>');
        $this->assertEquals('&lt;script&gt;', $result);
    }

    public function testeNoNeedToEscape(): void
    {
        $this->expectOutputString('script');
        $result = $this->view->e('script');
        $this->assertEquals('script', $result);
    }

    public function testEEscapesSingleQuote(): void
    {
        $expected = htmlentities("'", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->expectOutputString($expected);
        $result = $this->view->e("'");

        $this->assertEquals($expected, $result);
        $this->assertEquals('&#039;', $result);
    }

    public function testNormalizePath(): void
    {
        $viewMock = new class extends View
        {
            public static function normalizePath(string $path, string $separator = DIRECTORY_SEPARATOR): string
            {
                return parent::normalizePath($path, $separator);
            }
        };

        $this->assertSame(
            'C:/xampp/htdocs/libs/Flight/core/index.php',
            $viewMock::normalizePath('C:\xampp\htdocs\libs\Flight/core/index.php', '/')
        );
        $this->assertSame(
            'C:\xampp\htdocs\libs\Flight\core\index.php',
            $viewMock::normalizePath('C:/xampp/htdocs/libs/Flight\core\index.php', '\\')
        );
        $this->assertSame(
            'C:°xampp°htdocs°libs°Flight°core°index.php',
            $viewMock::normalizePath('C:/xampp/htdocs/libs/Flight\core\index.php', '°')
        );
    }

    /** @dataProvider renderDataProvider */
    public function testDoesNotPreserveVarsWhenFlagIsDisabled(
        string $output,
        array $renderParams,
        string $regexp
    ): void {
        $this->view->preserveVars = false;

        $this->expectOutputString($output);
        $this->view->render(...$renderParams);

        set_error_handler(function (int $code, string $message) use ($regexp): void {
            $this->assertMatchesRegularExpression($regexp, $message);
        });

        $this->view->render($renderParams[0]);

        restore_error_handler();
    }

    public function testKeepThePreviousStateOfOneViewComponentByDefault(): void
    {
        $html = <<<'html'
        <div>Hi</div>
        <div>Hi</div>
        <input type="number" />
        <input type="number" />
        html; // phpcs:ignore

        // if windows replace \n with \r\n
        $html = str_replace(["\n", "\r"], '', $html);

        $this->expectOutputString($html);

        $this->view->render('myComponent', ['prop' => 'Hi']);
        $this->view->render('myComponent');
        $this->view->render('input', ['type' => 'number']);
        $this->view->render('input');
    }

    public function testKeepThePreviousStateOfDataSettedBySetMethod(): void
    {
        $this->view->preserveVars = false;

        $this->view->set('prop', 'bar');

        $html = <<<'html'
        <div>qux</div>
        <div>bar</div>
        html; // phpcs:ignore

        $html = str_replace(["\n", "\r"], '', $html);

        $this->expectOutputString($html);

        $this->view->render('myComponent', ['prop' => 'qux']);
        $this->view->render('myComponent');
    }

    public static function renderDataProvider(): array
    {
        $html1 = <<<'html'
        <div>Hi</div>
        <div></div>
        html; // phpcs:ignore

        $html2 = <<<'html'
        <input type="number" />
        <input type="text" />
        html; // phpcs:ignore

        $html1 = str_replace(["\n", "\r"], '', $html1);
        $html2 = str_replace(["\n", "\r"], '', $html2);

        return [
            [
                $html1,
                ['myComponent', ['prop' => 'Hi']],
                '/^Undefined variable:? \$?prop$/'
            ],
            [
                $html2,
                ['input', ['type' => 'number']],
                '/^.*$/'
            ],
        ];
    }

    public function testAbsolutePathStillRendersByDefault(): void
    {
        $file = $this->makeOutsideTemplate('outside');

        try {
            $this->expectOutputString('outside');
            $this->view->render($file);
            $this->assertTrue($this->view->exists($file));
        } finally {
            $this->removeDir(dirname($file));
        }
    }

    public function testRestrictToPathIsOffByDefault(): void
    {
        $this->assertFalse((new View())->restrictToPath);
    }

    public function testRestrictToPathRendersViewInsidePath(): void
    {
        $this->view->restrictToPath = true;

        $this->expectOutputString('Hello, Bob!');
        $this->view->render('hello', ['name' => 'Bob']);
        $this->assertTrue($this->view->exists('hello'));
        $this->assertTrue($this->view->exists('layouts/layout'));
    }

    public function testRestrictToPathAllowsAbsolutePathInsidePath(): void
    {
        $this->view->restrictToPath = true;
        $file = (string) realpath(__DIR__ . '/views/hello.php');

        $this->expectOutputString('Hello, Bob!');
        $this->view->render($file, ['name' => 'Bob']);
    }

    public function testRestrictToPathRejectsRelativePathOutsidePath(): void
    {
        $this->view->restrictToPath = true;
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'restrict-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'note.php', 'outside');
        $name = '..' . DIRECTORY_SEPARATOR . basename($dir) . DIRECTORY_SEPARATOR . 'note';

        try {
            $this->assertFalse($this->view->exists($name));
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Template file is outside the views path.');
            $this->view->render($name);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testRestrictToPathRejectsAbsolutePathOutsidePath(): void
    {
        $this->view->restrictToPath = true;
        $file = $this->makeOutsideTemplate('outside');

        try {
            $this->assertFalse($this->view->exists($file));
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Template file is outside the views path.');
            $this->view->render($file);
        } finally {
            $this->removeDir(dirname($file));
        }
    }

    public function testRestrictToPathRejectsSymlinkOutsidePath(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flight-view-' . uniqid();
        mkdir($root . DIRECTORY_SEPARATOR . 'views', 0777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'outside');
        $target = $root . DIRECTORY_SEPARATOR . 'outside' . DIRECTORY_SEPARATOR . 'note.php';
        file_put_contents($target, 'outside');

        if (!@symlink($target, $root . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'alias.php')) {
            $this->removeDir($root);
            $this->markTestSkipped('Symlinks are not available.');
        }

        $view = new View($root . DIRECTORY_SEPARATOR . 'views');
        $view->restrictToPath = true;

        try {
            $this->assertFalse($view->exists('alias'));
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Template file is outside the views path.');
            $view->render('alias');
        } finally {
            $this->removeDir($root);
        }
    }

    public function testRestrictToPathKeepsNotFoundMessage(): void
    {
        $this->view->restrictToPath = true;

        $this->assertFalse($this->view->exists('badfile'));
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Template file not found:');
        $this->view->render('badfile');
    }

    private function makeOutsideTemplate(string $content): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flight-view-' . uniqid();
        mkdir($dir);
        $file = $dir . DIRECTORY_SEPARATOR . 'note.php';
        file_put_contents($file, $content);

        return $file;
    }

    private function removeDir(string $dir): void
    {
        foreach ((array) scandir($dir) as $item) {
            if ($item === '.' || $item === '..' || $item === false) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path) && !is_link($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
