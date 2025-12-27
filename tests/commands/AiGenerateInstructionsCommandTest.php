<?php

declare(strict_types=1);

namespace tests\commands;

use Ahc\Cli\Application;
use flight\commands\AiGenerateInstructionsCommand;
use PHPUnit\Framework\TestCase;
use tests\classes\NoExitInteractor;

class AiGenerateInstructionsCommandTest extends TestCase {
    protected static $in;
    protected static $ou;
    protected $baseDir;

    public function setUp(): void {
        self::$in = __DIR__ . DIRECTORY_SEPARATOR . 'input.test' . uniqid('', true) . '.txt';
        self::$ou = __DIR__ . DIRECTORY_SEPARATOR . 'output.test' . uniqid('', true) . '.txt';
        file_put_contents(self::$in, '');
        file_put_contents(self::$ou, '');
        $this->baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flightphp-test-basedir-' . uniqid('', true) . DIRECTORY_SEPARATOR;
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    public function tearDown(): void {
        if (file_exists(self::$in)) {
            unlink(self::$in);
        }
        if (file_exists(self::$ou)) {
            unlink(self::$ou);
        }
        $this->recursiveRmdir($this->baseDir);
    }

    protected function recursiveRmdir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRmdir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    protected function newApp($command): Application {
        $app = new Application('test', '0.0.1', function ($exitCode) {
            return $exitCode;
        });
        $app->io(new NoExitInteractor(self::$in, self::$ou));
        $app->add($command);
        return $app;
    }

    protected function setInput(array $lines): void {
        file_put_contents(self::$in, implode("\n", $lines) . "\n");
    }

    protected function setProjectRoot($command, $path) {
        $reflection = new \ReflectionClass(get_class($command));
        $property = null;
        $currentClass = $reflection;
        while ($currentClass && !$property) {
            try {
                $property = $currentClass->getProperty('projectRoot');
            } catch (\ReflectionException $e) {
                $currentClass = $currentClass->getParentClass();
            }
        }
        if ($property) {
            // only setAccessible if php 8 or php 7.4
            if (PHP_VERSION_ID < 80100) {
                $property->setAccessible(true);
            }
            $property->setValue($command, $path);
        }
    }

    public function testFailsIfAiConfigMissing() {
        $this->setInput([
            'desc',
            'none',
            'latte',
            'y',
            'y',
            'none',
            'Docker',
            '1',
            'n',
            'no'
        ]);
        // Provide 'runway' with dummy data to avoid deprecated configFile logic
        $cmd = $this->getMockBuilder(AiGenerateInstructionsCommand::class)
            ->setConstructorArgs([['runway' => ['dummy' => true]]])
            ->onlyMethods(['callLlmApi'])
            ->getMock();
        $this->setProjectRoot($cmd, $this->baseDir);
        $app = $this->newApp($cmd);
        $result = $app->handle([
            'runway',
            'ai:generate-instructions',
        ]);
        $this->assertSame(1, $result);
        $this->assertStringContainsString('Missing AI configuration', file_get_contents(self::$ou));
    }

    public function testWritesInstructionsToFiles() {
        $creds = [
            'api_key' => 'key',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com',
        ];
        $this->setInput([
            'desc',
            'mysql',
            'latte',
            'y',
            'y',
            'flight/lib',
            'Docker',
            '2',
            'y',
            'context info'
        ]);
        $mockInstructions = "# Project Instructions\n\nUse MySQL, Latte, Docker.";
        $cmd = $this->getMockBuilder(AiGenerateInstructionsCommand::class)
            ->setConstructorArgs([
                [
                    'runway' => ['ai' => $creds]
                ]
            ])
            ->onlyMethods(['callLlmApi'])
            ->getMock();
        $this->setProjectRoot($cmd, $this->baseDir);
        $cmd->expects($this->once())
            ->method('callLlmApi')
            ->willReturn(json_encode([
                'choices' => [
                    ['message' => ['content' => $mockInstructions]]
                ]
            ]));
        $app = $this->newApp($cmd);
        $result = $app->handle([
            'runway',
            'ai:generate-instructions',
        ]);
        $this->assertSame(0, $result);
        $this->assertFileExists($this->baseDir . '.github/copilot-instructions.md');
        $this->assertFileExists($this->baseDir . '.cursor/rules/project-overview.mdc');
        $this->assertFileExists($this->baseDir . '.gemini/GEMINI.md');
        $this->assertFileExists($this->baseDir . '.windsurfrules');
    }

    public function testNoInstructionsReturnedFromLlm() {
        $creds = [
            'api_key' => 'key',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com',
        ];
        $this->setInput([
            'desc',
            'mysql',
            'latte',
            'y',
            'y',
            'flight/lib',
            'Docker',
            '2',
            'y',
            'context info'
        ]);
        $cmd = $this->getMockBuilder(AiGenerateInstructionsCommand::class)
            ->setConstructorArgs([
                [
                    'runway' => ['ai' => $creds]
                ]
            ])
            ->onlyMethods(['callLlmApi'])
            ->getMock();
        $this->setProjectRoot($cmd, $this->baseDir);
        $cmd->expects($this->once())
            ->method('callLlmApi')
            ->willReturn(json_encode([
                'choices' => [
                    ['message' => ['content' => '']]
                ]
            ]));
        $app = $this->newApp($cmd);
        $result = $app->handle([
            'runway',
            'ai:generate-instructions',
        ]);
        $this->assertSame(1, $result);
    }

    public function testLlmApiCallFails() {
        $creds = [
            'api_key' => 'key',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com',
        ];
        $this->setInput([
            'desc',
            'mysql',
            'latte',
            'y',
            'y',
            'flight/lib',
            'Docker',
            '2',
            'y',
            'context info'
        ]);
        $cmd = $this->getMockBuilder(AiGenerateInstructionsCommand::class)
            ->setConstructorArgs([
                [
                    'runway' => ['ai' => $creds]
                ]
            ])
            ->onlyMethods(['callLlmApi'])
            ->getMock();
        $this->setProjectRoot($cmd, $this->baseDir);
        $cmd->expects($this->once())
            ->method('callLlmApi')
            ->willReturn(false);
        $app = $this->newApp($cmd);
        $result = $app->handle([
            'runway',
            'ai:generate-instructions',
        ]);
        $this->assertSame(1, $result);
    }

    public function testUsesDeprecatedConfigFile() {
        $creds = [
            'ai' => [
                'api_key' => 'key',
                'model' => 'gpt-4o',
                'base_url' => 'https://api.openai.com',
            ]
        ];
        $configFile = $this->baseDir . 'old-config.json';
        file_put_contents($configFile, json_encode($creds));
        $this->setInput([
            'desc',
            'mysql',
            'latte',
            'y',
            'y',
            'flight/lib',
            'Docker',
            '2',
            'y',
            'context info'
        ]);
        $mockInstructions = "# Project Instructions\n\nUse MySQL, Latte, Docker.";
        // runway key is MISSING from config to trigger deprecated logic
        $cmd = $this->getMockBuilder(AiGenerateInstructionsCommand::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['callLlmApi'])
            ->getMock();
        $this->setProjectRoot($cmd, $this->baseDir);
        $cmd->expects($this->once())
            ->method('callLlmApi')
            ->willReturn(json_encode([
                'choices' => [
                    ['message' => ['content' => $mockInstructions]]
                ]
            ]));
        $app = $this->newApp($cmd);
        $result = $app->handle([
            'runway',
            'ai:generate-instructions',
            '--config-file=' . $configFile
        ]);
        $this->assertSame(0, $result);
        $this->assertStringContainsString('The --config-file option is deprecated', file_get_contents(self::$ou));
        $this->assertFileExists($this->baseDir . '.github/copilot-instructions.md');
    }
}
