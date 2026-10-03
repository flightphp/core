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

    public function testRenderRelativePathThatStaysInsideViews(): void
    {
        $this->view->render('layouts/../hello', ['name' => 'Bob']);

        $this->expectOutputString('Hello, Bob!');
    }

    public function testGetTemplateAbsolutePath(): void
    {
        $tmpfile = tmpfile();
        $this->view->extension = '';
        $file_path = stream_get_meta_data($tmpfile)['uri'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Template path is not allowed.');
        $this->view->getTemplate($file_path);
    }

    public function testRejectsDriveLetterTemplatePath(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Template path is not allowed.');
        $this->view->getTemplate('C:' . DIRECTORY_SEPARATOR . 'outside.php');
    }

    public function testRejectsTemplateThatLeavesViewsDirectory(): void
    {
        $outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flight-view-outside-' . uniqid();
        mkdir($outside);
        $note = $outside . DIRECTORY_SEPARATOR . 'note.php';
        file_put_contents($note, '<?php echo "blocked";');

        $views = realpath($this->view->path);
        $relative = $this->relativePathFrom($views, $note);
        $relative = preg_replace('/\.php$/', '', $relative);

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Template path is not allowed.');
            $this->view->render($relative);
        } finally {
            unlink($note);
            rmdir($outside);
        }
    }

    public function testRejectsTemplateThatResolvesOutsideViewsDirectory(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flight-view-root-' . uniqid();
        $views = $root . DIRECTORY_SEPARATOR . 'views';
        $outside = $root . DIRECTORY_SEPARATOR . 'outside';
        mkdir($root);
        mkdir($views);
        mkdir($outside);
        $note = $outside . DIRECTORY_SEPARATOR . 'note.php';
        file_put_contents($note, '<?php echo "blocked";');
        $link = $views . DIRECTORY_SEPARATOR . 'alias.php';

        if (!@symlink($note, $link)) {
            $this->removeDir($root);
            $this->markTestSkipped('Symlink not available');
        }

        $view = new View($views);

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Template path is not allowed.');
            $view->render('alias');
        } finally {
            $this->removeDir($root);
        }
    }

    public function testRejectsMissingViewsDirectory(): void
    {
        $view = new View(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flight-missing-views-' . uniqid());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Template path is not allowed.');
        $view->render('hello');
    }

    private function relativePathFrom(string $fromDir, string $toFile): string
    {
        $from = explode(DIRECTORY_SEPARATOR, rtrim($fromDir, DIRECTORY_SEPARATOR));
        $to = explode(DIRECTORY_SEPARATOR, $toFile);
        $file = array_pop($to);

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        $up = array_fill(0, count($from), '..');
        return implode(DIRECTORY_SEPARATOR, array_merge($up, $to, [$file]));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_link($path) || is_file($path)) {
                unlink($path);
                continue;
            }
            $this->removeDir($path);
        }

        rmdir($dir);
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
}
