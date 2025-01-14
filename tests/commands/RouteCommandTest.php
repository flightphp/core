<?php

declare(strict_types=1);

namespace tests\commands;

use Ahc\Cli\Application;
use Ahc\Cli\IO\Interactor;
use Flight;
use flight\commands\RouteCommand;
use flight\Engine;
use PHPUnit\Framework\TestCase;

class RouteCommandTest extends TestCase
{
    protected static $in = __DIR__ . DIRECTORY_SEPARATOR . 'input.test';
    protected static $ou = __DIR__ . DIRECTORY_SEPARATOR . 'output.test';

    public function setUp(): void
    {
        // Need dynamic filenames to avoid unlink() issues with windows.
        static::$in = __DIR__ . DIRECTORY_SEPARATOR . 'input.test' . uniqid('', true) . '.txt';
        static::$ou = __DIR__ . DIRECTORY_SEPARATOR . 'output.test' . uniqid('', true) . '.txt';
        file_put_contents(static::$in, '');
        file_put_contents(static::$ou, '');
        $_SERVER = [];
        $_REQUEST = [];
        Flight::init();
        Flight::setEngine(new Engine());
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

        if (file_exists(__DIR__ . '/index.php')) {
            unlink(__DIR__ . '/index.php');
        }

        unset($_REQUEST);
        unset($_SERVER);
        Flight::clear();

        // Thanks Windows
        clearstatcache();
        gc_collect_cycles();
    }

    protected function newApp(string $name, string $version = '')
    {
        $app = new Application($name, $version ?: '0.0.1', fn () => false);

        return $app->io(new Interactor(static::$in, static::$ou));
    }

    protected function createIndexFile()
    {
        $index = <<<'PHP'
<?php

require __DIR__ . '/../../vendor/autoload.php';

Flight::route('GET /', function () {});
Flight::post('/post', function () {})->addMiddleware(function() {});
Flight::delete('/delete', function () {});
Flight::put('/put', function () {});
Flight::patch('/patch', function () {})->addMiddleware('SomeMiddleware');
Flight::router()->caseSensitive = true;

Flight::start();
PHP;

        file_put_contents(__DIR__ . '/index.php', $index);
    }

    protected function removeColors(string $str): string
    {
        return preg_replace('/\e\[[\d;]*m/', '', $str);
    }

    public function testConfigIndexRootNotSet()
    {
        $app = $this->newApp('test', '0.0.1');
        $app->add(new RouteCommand([]));
        $app->handle(['runway', 'routes']);

        $this->assertStringContainsString('index_root not set in .runway-config.json', file_get_contents(static::$ou));
    }

    public function testGetRoutes()
    {
        $app = $this->newApp('test', '0.0.1');
        $this->createIndexFile();
        $app->add(new RouteCommand(['index_root' => 'tests/commands/index.php']));
        $app->handle(['runway', 'routes']);

        $this->assertStringContainsString('Routes', file_get_contents(static::$ou));
        $this->assertStringContainsString('+---------+-----------+-------+----------+----------------+
| Pattern | Methods   | Alias | Streamed | Middleware     |
+---------+-----------+-------+----------+----------------+
| /       | GET, HEAD |       | No       | -              |
| /post   | POST      |       | No       | Closure        |
| /delete | DELETE    |       | No       | -              |
| /put    | PUT       |       | No       | -              |
| /patch  | PATCH     |       | No       | Bad Middleware |
+---------+-----------+-------+----------+----------------+', $this->removeColors(file_get_contents(static::$ou)));
    }

    public function testGetPostRoute()
    {
        $app = $this->newApp('test', '0.0.1');
        $this->createIndexFile();
        $app->add(new RouteCommand(['index_root' => 'tests/commands/index.php']));
        $app->handle(['runway', 'routes', '--post']);

        $this->assertStringContainsString('Routes', file_get_contents(static::$ou));
        $this->assertStringContainsString('+---------+---------+-------+----------+------------+
| Pattern | Methods | Alias | Streamed | Middleware |
+---------+---------+-------+----------+------------+
| /post   | POST    |       | No       | Closure    |
+---------+---------+-------+----------+------------+', $this->removeColors(file_get_contents(static::$ou)));
    }
}
