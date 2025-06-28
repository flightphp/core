<?php

declare(strict_types=1);

namespace tests\commands;

use Ahc\Cli\Application;
use Ahc\Cli\IO\Interactor;
use flight\commands\AiInitCommand;
use PHPUnit\Framework\TestCase;

class AiInitCommandTest extends TestCase
{
    protected static $in;
    protected static $ou;
    protected $baseDir;
    protected $runwayCredsFile;
    protected $gitignoreFile;

    public function setUp(): void
    {
        self::$in = __DIR__ . DIRECTORY_SEPARATOR . 'input.test' . uniqid('', true) . '.txt';
        self::$ou = __DIR__ . DIRECTORY_SEPARATOR . 'output.test' . uniqid('', true) . '.txt';
        file_put_contents(self::$in, '');
        file_put_contents(self::$ou, '');
        $this->baseDir = getcwd() . DIRECTORY_SEPARATOR;
        $this->runwayCredsFile = __DIR__ . DIRECTORY_SEPARATOR . 'dummy-creds-' . uniqid('', true) . '.json';
        $this->gitignoreFile = __DIR__ . DIRECTORY_SEPARATOR . 'dummy-gitignore-' . uniqid('', true);
        if (file_exists($this->runwayCredsFile)) {
            unlink($this->runwayCredsFile);
        }
        if (file_exists($this->gitignoreFile)) {
            unlink($this->gitignoreFile);
        }
    }

    public function tearDown(): void
    {
        if (file_exists(self::$in)) {
            unlink(self::$in);
        }
        if (file_exists(self::$ou)) {
            unlink(self::$ou);
        }
        if (file_exists($this->runwayCredsFile)) {
            if (is_dir($this->runwayCredsFile)) {
                rmdir($this->runwayCredsFile);
            } else {
                unlink($this->runwayCredsFile);
            }
        }
        if (file_exists($this->gitignoreFile)) {
            unlink($this->gitignoreFile);
        }
    }

    protected function newApp(): Application
    {
        $app = new Application('test', '0.0.1', function ($exitCode) {
            return $exitCode;
        });
        $app->io(new Interactor(self::$in, self::$ou));
        return $app;
    }

    protected function setInput(array $lines): void
    {
        file_put_contents(self::$in, implode("\n", $lines) . "\n");
    }

    public function testInitCreatesCredsAndGitignore()
    {
        $this->setInput([
            '1', // provider
            '', // accept default base url
            'test-key', // api key
            '', // accept default model
        ]);
        $app = $this->newApp();
        $app->add(new AiInitCommand());
        $result = $app->handle([
            'runway', 'ai:init',
            '--creds-file=' . $this->runwayCredsFile,
            '--gitignore-file=' . $this->gitignoreFile
        ]);
        $this->assertSame(0, $result);
        $this->assertFileExists($this->runwayCredsFile);
        $creds = json_decode(file_get_contents($this->runwayCredsFile), true);
        $this->assertSame('openai', $creds['provider']);
        $this->assertSame('test-key', $creds['api_key']);
        $this->assertSame('gpt-4o', $creds['model']);
        $this->assertSame('https://api.openai.com', $creds['base_url']);
        $this->assertFileExists($this->gitignoreFile);
        $this->assertStringContainsString(basename($this->runwayCredsFile), file_get_contents($this->gitignoreFile));
    }

    public function testInitWithExistingCredsNoOverwrite()
    {
        file_put_contents($this->runwayCredsFile, '{}');
        $this->setInput([
            'n', // do not overwrite
        ]);
        $app = $this->newApp();
        $app->add(new AiInitCommand());
        $result = $app->handle([
            'runway', 'ai:init',
            '--creds-file=' . $this->runwayCredsFile,
            '--gitignore-file=' . $this->gitignoreFile
        ]);
        $this->assertSame(0, $result);
        $this->assertSame('{}', file_get_contents($this->runwayCredsFile));
    }

    public function testInitWithExistingCredsOverwrite()
    {
        file_put_contents($this->runwayCredsFile, '{}');
        $this->setInput([
            'y', // overwrite
            '2', // provider
            '', // accept default base url
            'grok-key', // api key
            '', // accept default model
        ]);
        $app = $this->newApp();
        $app->add(new AiInitCommand());
        $result = $app->handle([
            'runway', 'ai:init',
            '--creds-file=' . $this->runwayCredsFile,
            '--gitignore-file=' . $this->gitignoreFile
        ]);
        $this->assertSame(0, $result);
        $creds = json_decode(file_get_contents($this->runwayCredsFile), true);
        $this->assertSame('grok', $creds['provider']);
        $this->assertSame('grok-key', $creds['api_key']);
        $this->assertSame('grok-3-beta', $creds['model']);
        $this->assertSame('https://api.x.ai', $creds['base_url']);
    }

    public function testEmptyApiKeyPromptsAgain()
    {
        $this->setInput([
            '1',
            '', // accept default base url
            '', // empty api key, should error and exit
        ]);
        $app = $this->newApp();
        $app->add(new AiInitCommand());
        $result = $app->handle([
            'runway', 'ai:init',
            '--creds-file=' . $this->runwayCredsFile,
            '--gitignore-file=' . $this->gitignoreFile
        ]);
        $this->assertSame(1, $result);
        $this->assertFileDoesNotExist($this->runwayCredsFile);
    }

    public function testEmptyModelPrompts()
    {
        $this->setInput([
            '1',
            '',
            'key',
            '', // accept default model (should use default)
        ]);
        $app = $this->newApp();
        $app->add(new AiInitCommand());
        $result = $app->handle([
            'runway', 'ai:init',
            '--creds-file=' . $this->runwayCredsFile,
            '--gitignore-file=' . $this->gitignoreFile
        ]);
        $this->assertSame(0, $result);
        $creds = json_decode(file_get_contents($this->runwayCredsFile), true);
        $this->assertSame('gpt-4o', $creds['model']);
    }

    public function testGitignoreAlreadyHasCreds()
    {
        file_put_contents($this->gitignoreFile, basename($this->runwayCredsFile) . "\n");
        $this->setInput([
            '1',
            '',
            'key',
            '',
        ]);
        $app = $this->newApp();
        $app->add(new AiInitCommand());
        $result = $app->handle([
            'runway', 'ai:init',
            '--creds-file=' . $this->runwayCredsFile,
            '--gitignore-file=' . $this->gitignoreFile
        ]);
        $this->assertSame(0, $result);
        $this->assertFileExists($this->gitignoreFile);
        $lines = file($this->gitignoreFile, FILE_IGNORE_NEW_LINES);
        $this->assertContains(basename($this->runwayCredsFile), $lines);
        $this->assertCount(1, array_filter($lines, function ($l) {
            return trim($l) === basename($this->runwayCredsFile);
        }));
    }

    public function testInitWithClaudeProvider()
    {
        $this->setInput([
            '3', // provider (claude)
            '', // accept default base url
            'claude-key', // api key
            '', // accept default model
        ]);
        $app = $this->newApp();
        $app->add(new AiInitCommand());
        $result = $app->handle([
            'runway', 'ai:init',
            '--creds-file=' . $this->runwayCredsFile,
            '--gitignore-file=' . $this->gitignoreFile
        ]);
        $this->assertSame(0, $result);
        $creds = json_decode(file_get_contents($this->runwayCredsFile), true);
        $this->assertSame('claude', $creds['provider']);
        $this->assertSame('claude-key', $creds['api_key']);
        $this->assertSame('claude-3-opus', $creds['model']);
        $this->assertSame('https://api.anthropic.com', $creds['base_url']);
    }

    public function testAddsCredsFileToExistingGitignoreIfMissing()
    {
        // .gitignore exists but does not contain creds file
        file_put_contents($this->gitignoreFile, "vendor\nnode_modules\n.DS_Store\n");
        $this->setInput([
            '1', // provider
            '', // accept default base url
            'test-key', // api key
            '', // accept default model
        ]);
        $app = $this->newApp();
        $app->add(new AiInitCommand());
        $result = $app->handle([
            'runway', 'ai:init',
            '--creds-file=' . $this->runwayCredsFile,
            '--gitignore-file=' . $this->gitignoreFile
        ]);
        $this->assertSame(0, $result);
        $lines = file($this->gitignoreFile, FILE_IGNORE_NEW_LINES);
        $this->assertContains(basename($this->runwayCredsFile), $lines);
        $this->assertCount(1, array_filter($lines, function ($l) {
            return trim($l) === basename($this->runwayCredsFile);
        }));
    }

    public function testInvalidBaseUrlFails()
    {
        $this->setInput([
            '1', // provider
            'not-a-valid-url', // invalid base url
        ]);
        $app = $this->newApp();
        $app->add(new AiInitCommand());
        $result = $app->handle([
            'runway', 'ai:init',
            '--creds-file=' . $this->runwayCredsFile,
            '--gitignore-file=' . $this->gitignoreFile
        ]);
        $this->assertSame(1, $result);
        $this->assertFileDoesNotExist($this->runwayCredsFile);
    }
}
