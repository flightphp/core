<?php

use flight\template\View;

/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */
class ViewTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var \flight\template\View
     */
    private $view;

    protected function setUp(): void
    {
        $this->view = new \flight\template\View();
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

	public function testMultipleVariables() {
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

	public function testRenderBadFilePath() {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Template file not found: ' . __DIR__ . DIRECTORY_SEPARATOR . 'views'. DIRECTORY_SEPARATOR . 'badfile.php');

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

	public function testGetTemplateAbsolutePath() {
		$tmpfile = tmpfile();
		$this->view->extension = '';
		$file_path = stream_get_meta_data($tmpfile)['uri'];
		$this->assertEquals($file_path, $this->view->getTemplate($file_path));
	}

	public function testE() {
		$this->expectOutputString('&lt;script&gt;');
		$result = $this->view->e('<script>');
		$this->assertEquals('&lt;script&gt;', $result);
	}

	public function testENoNeedToEscape() {
		$this->expectOutputString('script');
		$result = $this->view->e('script');
		$this->assertEquals('script', $result);
	}

    public function testNormalizePath(): void
    {
        $viewMock = new class extends View {
            public static function normalizePath($path, $separator = DIRECTORY_SEPARATOR)
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
}
