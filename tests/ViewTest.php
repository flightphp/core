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
        $this->view = new View(__DIR__ . '/views');
    }

    public function testVariables(): void
    {
        $this->view->set('test', 123);

        self::assertSame(123, $this->view->get('test'));
        self::assertTrue($this->view->has('test'));
        self::assertFalse($this->view->has('unknown'));

        $this->view->clear('test');

        self::assertNull($this->view->get('test'));
    }

    public function testMultipleVariables(): void
    {
        $this->view->set(['test' => 123, 'foo' => 'bar']);

        self::assertSame(123, $this->view->get('test'));
        self::assertSame('bar', $this->view->get('foo'));

        $this->view->clear();

        self::assertNull($this->view->get('test'));
        self::assertNull($this->view->get('foo'));
    }

    public function testTemplateExists(): void
    {
        self::assertTrue($this->view->exists('hello.php'));
        self::assertFalse($this->view->exists('unknown.php'));
    }

    public function testRender(): void
    {
        $this->view->render('hello', ['name' => 'Bob']);

        self::expectOutputString('Hello, Bob!');
    }

    public function testRenderBadFilePath(): void
    {
        $exception_message = sprintf(
            'Template file not found: %s%sviews%sbadfile.php',
            __DIR__,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
        );

        self::expectException(Exception::class);
        self::expectExceptionMessage($exception_message);

        $this->view->render('badfile');
    }

    public function testFetch(): void
    {
        $output = $this->view->fetch('hello', ['name' => 'Bob']);

        self::assertSame('Hello, Bob!', $output);
    }

    public function testTemplateWithExtension(): void
    {
        $this->view->render('hello.php', ['name' => 'Bob']);

        self::expectOutputString('Hello, Bob!');
    }

    public function testTemplateWithCustomExtension(): void
    {
        self::expectOutputString('Hello world, Bob!');

        $this->view->extension = '.html';
        $this->view->render('world', ['name' => 'Bob']);
    }

    public function testGetTemplateAbsolutePath(): void
    {
        $tmpfile = tmpfile();
        $this->view->extension = '';
        $file_path = stream_get_meta_data($tmpfile)['uri'];

        self::assertSame($file_path, $this->view->getTemplate($file_path));
    }

    public function testE(): void
    {
        $expectedString = '&lt;script&gt;';

        self::expectOutputString($expectedString);

        $result = $this->view->e('<script>');

        self::assertSame($expectedString, $result);
    }

    public function testeNoNeedToEscape(): void
    {
        $expectedString = 'script';

        self::expectOutputString($expectedString);

        $result = $this->view->e($expectedString);

        self::assertSame($expectedString, $result);
    }

    public function testNormalizePath(): void
    {
        $viewMock = new class extends View
        {
            public static function normalizePath(
                string $path,
                string $separator = DIRECTORY_SEPARATOR
            ): string {
                return parent::normalizePath($path, $separator);
            }
        };

        self::assertSame(
            'C:/xampp/htdocs/libs/Flight/core/index.php',
            $viewMock::normalizePath('C:\xampp\htdocs\libs\Flight/core/index.php', '/')
        );

        self::assertSame(
            'C:\xampp\htdocs\libs\Flight\core\index.php',
            $viewMock::normalizePath('C:/xampp/htdocs/libs/Flight\core\index.php', '\\')
        );

        self::assertSame(
            'C:°xampp°htdocs°libs°Flight°core°index.php',
            $viewMock::normalizePath('C:/xampp/htdocs/libs/Flight\core\index.php', '°')
        );
    }

    /**
     * @dataProvider renderDataProvider
     * @param array{string, array<string, mixed>} $renderParams
     */
    public function testDoesNotPreserveVarsWhenFlagIsDisabled(
        string $output,
        array $renderParams,
        string $regexp
    ): void {
        $this->view->preserveVars = false;

        self::expectOutputString($output);
        $this->view->render(...$renderParams);

        set_error_handler(static function (int $code, string $message) use ($regexp): void {
            self::assertMatchesRegularExpression($regexp, $message);
        });

        $this->view->render($renderParams[0]);

        restore_error_handler();
    }

    public function testKeepThePreviousStateOfOneViewComponentByDefault(): void
    {
        $html = self::removeLineEndings(<<<'html'
        <div>Hi</div>
        <div>Hi</div>
        <input type="number" />
        <input type="number" />
        html);

        self::expectOutputString($html);

        $this->view->render('myComponent', ['prop' => 'Hi']);
        $this->view->render('myComponent');
        $this->view->render('input', ['type' => 'number']);
        $this->view->render('input');
    }

    public function testKeepThePreviousStateOfDataSettedBySetMethod(): void
    {
        $this->view->preserveVars = false;
        $this->view->set('prop', 'bar');

        $html = self::removeLineEndings(<<<'html'
        <div>qux</div>
        <div>bar</div>
        html);

        self::expectOutputString($html);

        $this->view->render('myComponent', ['prop' => 'qux']);
        $this->view->render('myComponent');
    }

    public static function renderDataProvider(): array
    {
        $html1 = self::removeLineEndings(<<<'html'
        <div>Hi</div>
        <div></div>
        html);

        $html2 = self::removeLineEndings(<<<'html'
        <input type="number" />
        <input type="text" />
        html);

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

    /** @dataProvider pagesDataProvider */
    public function testItRendersComponent(string $page, string $expected): void
    {
        $view = new View(__DIR__ . '/views');
        $view->preserveVars = false;
        $actual = $view->fetch("pages/$page");

        self::assertSame(
            self::removeIndentation(self::removeLineEndings($expected)),
            self::removeIndentation(self::removeLineEndings($actual)),
        );
    }

    public static function pagesDataProvider(): array
    {
        return [
            [
                'page-with-component-with-old-syntax',
                <<<'html'
                my-component
                html,
            ],
            [
                'page-with-component-with-new-syntax',
                <<<'html'
                my-component
                html,
            ],
            [
                'page-with-component-with-subcomponent',
                <<<'html'
                <div>
                    my-component-with-subcomponent
                    subcomponent
                </div>
                html,
            ],
            [
                'page-with-multiple-components',
                <<<'html'
                <ul>
                    <li>my-component</li>
                    <li>my-component</li>
                </ul>
                html,
            ],
            [
                'page-with-functional-component',
                <<<'html'
                my-functional-component
                html,
            ],
            [
                'page-with-class-component',
                <<<'html'
                my-class-component
                html,
            ],
            [
                'page-with-class-component-with-styles',
                <<<'html'
                <span class="my-class-component-with-styles">
                    my-class-component-with-styles
                </span>

                <style>
                    .my-class-component-with-styles {
                        color: red;
                    }
                </style>
                html,
            ],
            [
                'page-with-class-component-with-scripts',
                <<<'html'
                my-class-component-with-scripts

                <script>console.log('my-class-component-with-scripts')</script>
                html,
            ],
            [
                'page-with-class-component-that-extends-another-class-component',
                <<<'html'
                another-class-component extended by my-class-component-that-extends-another-class-component
                html,
            ],
            [
                'page-with-component-with-one-prop',
                <<<'html'
                <html>
                    <body>
                        <h1>Hello, James</h1>
                    </body>
                </html>
                html,
            ],
        ];
    }

    private static function removeLineEndings(string $subject): string
    {
        return str_replace(["\r", "\n"], '', $subject);
    }

    private static function removeIndentation(string $subject): string
    {
        return str_replace('    ', '', $subject);
    }
}
