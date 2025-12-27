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

    public function setUp(): void
    {
        self::$in = __DIR__ . DIRECTORY_SEPARATOR . 'input.test' . uniqid('', true) . '.txt';
        self::$ou = __DIR__ . DIRECTORY_SEPARATOR . 'output.test' . uniqid('', true) . '.txt';
        file_put_contents(self::$in, '');
        file_put_contents(self::$ou, '');
    }

    public function tearDown(): void
    {
        if (file_exists(self::$in)) {
            unlink(self::$in);
        }
        if (file_exists(self::$ou)) {
            unlink(self::$ou);
        }
    }

    protected function newApp($command): Application
    {
        $app = new Application('test', '0.0.1', function ($exitCode) {
            return $exitCode;
        });
        $app->io(new Interactor(self::$in, self::$ou));
        $app->add($command);
        return $app;
    }

    protected function setInput(array $lines): void
    {
        file_put_contents(self::$in, implode("\n", $lines) . "\n");
    }

    public function testInitSavesCreds()
    {
        $this->setInput([
            '1', // provider (openai)
            '', // accept default base url
            'test-key', // api key
            '', // accept default model
        ]);
        $cmd = $this->getMockBuilder(AiInitCommand::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['setRunwayConfigValue'])
            ->getMock();
        $cmd->expects($this->once())
            ->method('setRunwayConfigValue')
            ->with('ai', [
                'provider' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-5',
                'base_url' => 'https://api.openai.com',
            ]);
        $app = $this->newApp($cmd);
        $result = $app->handle(['runway', 'ai:init']);
        $this->assertSame(0, $result);
        $this->assertStringContainsString('Credentials saved', file_get_contents(self::$ou));
    }

    public function testInitWithGrokProvider()
    {
        $this->setInput([
            '2', // provider (grok)
            '', // accept default base url
            'grok-key', // api key
            '', // accept default model
        ]);
        $cmd = $this->getMockBuilder(AiInitCommand::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['setRunwayConfigValue'])
            ->getMock();
        $cmd->expects($this->once())
            ->method('setRunwayConfigValue')
            ->with('ai', [
                'provider' => 'grok',
                'api_key' => 'grok-key',
                'model' => 'grok-4.1-fast-non-reasoning',
                'base_url' => 'https://api.x.ai',
            ]);
        $app = $this->newApp($cmd);
        $result = $app->handle(['runway', 'ai:init']);
        $this->assertSame(0, $result);
    }

    public function testInitWithClaudeProvider()
    {
        $this->setInput([
            '3', // provider (claude)
            '', // accept default base url
            'claude-key', // api key
            '', // accept default model
        ]);
        $cmd = $this->getMockBuilder(AiInitCommand::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['setRunwayConfigValue'])
            ->getMock();
        $cmd->expects($this->once())
            ->method('setRunwayConfigValue')
            ->with('ai', [
                'provider' => 'claude',
                'api_key' => 'claude-key',
                'model' => 'claude-sonnet-4-5',
                'base_url' => 'https://api.anthropic.com',
            ]);
        $app = $this->newApp($cmd);
        $result = $app->handle(['runway', 'ai:init']);
        $this->assertSame(0, $result);
    }

    public function testEmptyApiKeyFails()
    {
        $this->setInput([
            '1',
            '', // accept default base url
            '', // empty api key
        ]);
        $cmd = $this->getMockBuilder(AiInitCommand::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['setRunwayConfigValue'])
            ->getMock();
        $cmd->expects($this->never())
            ->method('setRunwayConfigValue');
        $app = $this->newApp($cmd);
        $result = $app->handle(['runway', 'ai:init']);
        // Since $io->error(..., true) exits, Ahc\Cli will return the exit code.
        // If it exits with 1, it should be 1.
        $this->assertSame(1, $result);
    }

    public function testInvalidBaseUrlFails()
    {
        $this->setInput([
            '1', // provider
            'not-a-valid-url', // invalid base url
        ]);
        $cmd = $this->getMockBuilder(AiInitCommand::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['setRunwayConfigValue'])
            ->getMock();
        $cmd->expects($this->never())
            ->method('setRunwayConfigValue');
        $app = $this->newApp($cmd);
        $result = $app->handle(['runway', 'ai:init']);
        $this->assertSame(1, $result);
    }
}
