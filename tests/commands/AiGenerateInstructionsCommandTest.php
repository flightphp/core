<?php

declare(strict_types=1);

namespace tests\commands;

use Ahc\Cli\Application;
use Ahc\Cli\IO\Interactor;
use flight\commands\AiGenerateInstructionsCommand;
use PHPUnit\Framework\TestCase;

class AiGenerateInstructionsCommandTest extends TestCase
{
    protected static $in;
    protected static $ou;
    protected $baseDir;
    protected $runwayCredsFile;

    public function setUp(): void
    {
        self::$in = __DIR__ . DIRECTORY_SEPARATOR . 'input.test' . uniqid('', true) . '.txt';
        self::$ou = __DIR__ . DIRECTORY_SEPARATOR . 'output.test' . uniqid('', true) . '.txt';
        file_put_contents(self::$in, '');
        file_put_contents(self::$ou, '');
        $this->baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flightphp-test-basedir-' . uniqid('', true) . DIRECTORY_SEPARATOR;
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
        $this->runwayCredsFile = $this->baseDir . 'dummy-creds.json';
        if (file_exists($this->runwayCredsFile)) {
            unlink($this->runwayCredsFile);
        }
        @unlink($this->baseDir . '.github/copilot-instructions.md');
        @unlink($this->baseDir . '.cursor/rules/project-overview.mdc');
        @unlink($this->baseDir . '.windsurfrules');
        @rmdir($this->baseDir . '.github');
        @rmdir($this->baseDir . '.cursor/rules');
        @rmdir($this->baseDir . '.cursor');
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
            unlink($this->runwayCredsFile);
        }
        @unlink($this->baseDir . '.github/copilot-instructions.md');
        @unlink($this->baseDir . '.cursor/rules/project-overview.mdc');
        @unlink($this->baseDir . '.windsurfrules');
        @rmdir($this->baseDir . '.github');
        @rmdir($this->baseDir . '.cursor/rules');
        @rmdir($this->baseDir . '.cursor');
        if (is_dir($this->baseDir . '.cursor/rules')) {
            @rmdir($this->baseDir . '.cursor/rules');
        }
        if (is_dir($this->baseDir . '.cursor')) {
            @rmdir($this->baseDir . '.cursor');
        }
        if (is_dir($this->baseDir . '.github')) {
            @rmdir($this->baseDir . '.github');
        }
        if (is_dir($this->baseDir)) {
            @rmdir($this->baseDir);
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

    public function testFailsIfCredsFileMissing()
    {
        $this->setInput([
            'desc', 'none', 'latte', 'y', 'y', 'none', 'Docker', '1', 'n', 'no'
        ]);
        $cmd = $this->getMockBuilder(AiGenerateInstructionsCommand::class)
            ->onlyMethods(['callLlmApi'])
            ->getMock();
        $app = $this->newApp($cmd);
        $result = $app->handle([
            'runway', 'ai:generate-instructions',
            '--creds-file=' . $this->runwayCredsFile,
            '--base-dir=' . $this->baseDir
        ]);
        $this->assertSame(1, $result);
        $this->assertFileDoesNotExist($this->baseDir . '.github/copilot-instructions.md');
    }

    public function testWritesInstructionsToFiles()
    {
        $creds = [
            'api_key' => 'key',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com',
        ];
        file_put_contents($this->runwayCredsFile, json_encode($creds));
        $this->setInput([
            'desc', 'mysql', 'latte', 'y', 'y', 'flight/lib', 'Docker', '2', 'y', 'context info'
        ]);
        $mockInstructions = "# Project Instructions\n\nUse MySQL, Latte, Docker.";
        $cmd = $this->getMockBuilder(AiGenerateInstructionsCommand::class)
            ->onlyMethods(['callLlmApi'])
            ->getMock();
        $cmd->expects($this->once())
            ->method('callLlmApi')
            ->willReturn(json_encode([
                'choices' => [
                    ['message' => ['content' => $mockInstructions]]
                ]
            ]));
        $app = $this->newApp($cmd);
        $result = $app->handle([
            'runway', 'ai:generate-instructions',
            '--creds-file=' . $this->runwayCredsFile,
            '--base-dir=' . $this->baseDir
        ]);
        $this->assertSame(0, $result);
        $this->assertFileExists($this->baseDir . '.github/copilot-instructions.md');
        $this->assertFileExists($this->baseDir . '.cursor/rules/project-overview.mdc');
        $this->assertFileExists($this->baseDir . '.windsurfrules');
        $this->assertStringContainsString('MySQL', file_get_contents($this->baseDir . '.github/copilot-instructions.md'));
        $this->assertStringContainsString('MySQL', file_get_contents($this->baseDir . '.cursor/rules/project-overview.mdc'));
        $this->assertStringContainsString('MySQL', file_get_contents($this->baseDir . '.windsurfrules'));
    }

    public function testNoInstructionsReturnedFromLlm()
    {
        $creds = [
            'api_key' => 'key',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com',
        ];
        file_put_contents($this->runwayCredsFile, json_encode($creds));
        $this->setInput([
            'desc', 'mysql', 'latte', 'y', 'y', 'flight/lib', 'Docker', '2', 'y', 'context info'
        ]);
        $cmd = $this->getMockBuilder(AiGenerateInstructionsCommand::class)
            ->onlyMethods(['callLlmApi'])
            ->getMock();
        $cmd->expects($this->once())
            ->method('callLlmApi')
            ->willReturn(json_encode([
                'choices' => [
                    ['message' => ['content' => '']]
                ]
            ]));
        $app = $this->newApp($cmd);
        $result = $app->handle([
            'runway', 'ai:generate-instructions',
            '--creds-file=' . $this->runwayCredsFile,
            '--base-dir=' . $this->baseDir
        ]);
        $this->assertSame(1, $result);
        $this->assertFileDoesNotExist($this->baseDir . '.github/copilot-instructions.md');
    }

    public function testLlmApiCallFails()
    {
        $creds = [
            'api_key' => 'key',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com',
        ];
        file_put_contents($this->runwayCredsFile, json_encode($creds));
        $this->setInput([
            'desc', 'mysql', 'latte', 'y', 'y', 'flight/lib', 'Docker', '2', 'y', 'context info'
        ]);
        $cmd = $this->getMockBuilder(AiGenerateInstructionsCommand::class)
            ->onlyMethods(['callLlmApi'])
            ->getMock();
        $cmd->expects($this->once())
            ->method('callLlmApi')
            ->willReturn(false);
        $app = $this->newApp($cmd);
        $result = $app->handle([
            'runway', 'ai:generate-instructions',
            '--creds-file=' . $this->runwayCredsFile,
            '--base-dir=' . $this->baseDir
        ]);
        $this->assertSame(1, $result);
        $this->assertFileDoesNotExist($this->baseDir . '.github/copilot-instructions.md');
    }
}
