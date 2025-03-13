<?php

declare(strict_types=1);

namespace tests\commands;

use Ahc\Cli\Application;
use Ahc\Cli\IO\Interactor;
use flight\commands\ControllerCommand;
use PHPUnit\Framework\TestCase;

class ControllerCommandTest extends TestCase
{
    protected static $in = '';
    protected static $ou = '';

    public function setUp(): void
    {
        // Need dynamic filenames to avoid unlink() issues with windows.
        static::$in = __DIR__ . DIRECTORY_SEPARATOR . 'input.test' . uniqid('', true) . '.txt';
        static::$ou = __DIR__ . DIRECTORY_SEPARATOR . 'output.test' . uniqid('', true) . '.txt';
        file_put_contents(static::$in, '', LOCK_EX);
        file_put_contents(static::$ou, '', LOCK_EX);
    }

    public function tearDown(): void
    {
        // Make sure we clean up after ourselves:
        if (file_exists(static::$in)) {
            unlink(static::$in);
        }
        if (file_exists(static::$ou)) {
            unlink(static::$ou);
        }

        if (file_exists(__DIR__ . '/controllers/TestController.php')) {
            unlink(__DIR__ . '/controllers/TestController.php');
        }

        if (file_exists(__DIR__ . '/controllers/')) {
            rmdir(__DIR__ . '/controllers/');
        }

        // Thanks Windows
        clearstatcache();
        gc_collect_cycles();
    }

    protected function newApp(string $name, string $version = '')
    {
        $app = @new Application($name, $version ?: '0.0.1', fn () => false);

        return @$app->io(new Interactor(static::$in, static::$ou));
    }

    public function testConfigAppRootNotSet()
    {
        $app = $this->newApp('test', '0.0.1');
        $app->add(new ControllerCommand([]));
        @$app->handle(['runway', 'make:controller', 'Test']);

        $this->assertStringContainsString('app_root not set in .runway-config.json', file_get_contents(static::$ou));
    }

    public function testControllerAlreadyExists()
    {
        $app = $this->newApp('test', '0.0.1');
        mkdir(__DIR__ . '/controllers/');
        file_put_contents(__DIR__ . '/controllers/TestController.php', '<?php class TestController {}');
        $app->add(new ControllerCommand(['app_root' => 'tests/commands/']));
        $app->handle(['runway', 'make:controller', 'Test']);

        $this->assertStringContainsString('TestController already exists.', file_get_contents(static::$ou));
    }

    public function testCreateController()
    {
        $app = $this->newApp('test', '0.0.1');
        $app->add(new ControllerCommand(['app_root' => 'tests/commands/']));
        $app->handle(['runway', 'make:controller', 'Test']);

        $this->assertFileExists(__DIR__ . '/controllers/TestController.php');
    }
}
