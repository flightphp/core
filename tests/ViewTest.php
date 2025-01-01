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
    public function testVariables()
    {
        $this->view->set('test', 123);

        $this->assertEquals(123, $this->view->get('test'));

        $this->assertTrue($this->view->has('test'));
        $this->assertTrue(!$this->view->has('unknown'));

        $this->view->clear('test');

        $this->assertNull($this->view->get('test'));
    }

    public function testMultipleVariables()
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
    public function testTemplateExists()
    {
        $this->assertTrue($this->view->exists('hello.php'));
        $this->assertTrue(!$this->view->exists('unknown.php'));
    }

    // Render a template
    public function testRender()
    {
        $this->view->render('hello', ['name' => 'Bob']);

        $this->expectOutputString('Hello, Bob!');
    }

    public function testRenderBadFilePath()
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
    public function testFetch()
    {
        $output = $this->view->fetch('hello', ['name' => 'Bob']);

        $this->assertEquals('Hello, Bob!', $output);
    }

    // Default extension
    public function testTemplateWithExtension()
    {
        $this->view->set('name', 'Bob');

        $this->view->render('hello.php');

        $this->expectOutputString('Hello, Bob!');
    }

    // Custom extension
    public function testTemplateWithCustomExtension()
    {
        $this->view->set('name', 'Bob');
        $this->view->extension = '.html';

        $this->view->render('world');

        $this->expectOutputString('Hello world, Bob!');
    }

    public function testGetTemplateAbsolutePath()
    {
        $tmpfile = tmpfile();
        $this->view->extension = '';
        $file_path = stream_get_meta_data($tmpfile)['uri'];
        $this->assertEquals($file_path, $this->view->getTemplate($file_path));
    }

    public function testE()
    {
        $this->expectOutputString('&lt;script&gt;');
        $result = $this->view->e('<script>');
        $this->assertEquals('&lt;script&gt;', $result);
    }

    public function testeNoNeedToEscape()
    {
        $this->expectOutputString('script');
        $result = $this->view->e('script');
        $this->assertEquals('script', $result);
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
        $this->expectOutputString(<<<'html'
        <div>Hi</div>
        <div>Hi</div>

        <input type="number" />

        <input type="number" />

        html);

        $this->view->render('myComponent', ['prop' => 'Hi']);
        $this->view->render('myComponent');
        $this->view->render('input', ['type' => 'number']);
        $this->view->render('input');
    }

    public function testKeepThePreviousStateOfDataSettedBySetMethod(): void
    {
        $this->view->preserveVars = false;

        $this->view->set('prop', 'bar');

        $this->expectOutputString(<<<'html'
        <div>qux</div>
        <div>bar</div>

        html);

        $this->view->render('myComponent', ['prop' => 'qux']);
        $this->view->render('myComponent');
    }

    public static function renderDataProvider(): array
    {
        return [
            [
                <<<'html'
                <div>Hi</div>
                <div></div>

                html,
                ['myComponent', ['prop' => 'Hi']],
                '/^Undefined variable:? \$?prop$/'
            ],
            [
                <<<'html'

                <input type="number" />

                <input type="text" />

                html,
                ['input', ['type' => 'number']],
                '/^.*$/'
            ],
        ];
    }
}
