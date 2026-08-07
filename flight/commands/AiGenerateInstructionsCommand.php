<?php

declare(strict_types=1);

namespace flight\commands;

/**
 * @property-read ?string $configFile
 * @property-read ?string $baseDir
 */
class AiGenerateInstructionsCommand extends AbstractBaseCommand
{
    /**
     * Constructor for the AiGenerateInstructionsCommand class.
     *
     * Initializes a new instance of the command.
     *
     * @param array<string,mixed> $config Config from config.php
     */
    public function __construct(array $config)
    {
        parent::__construct('ai:generate-instructions', 'Generate project-specific AI coding instructions', $config);

        $this->option(
            '--config-file',
            'Path to .runway-config.json file (deprecated, use config.php instead)',
            null,
            ''
        );
    }

    /**
     * Executes the command logic for generating AI instructions.
     *
     * This method is called to perform the main functionality of the
     * AiGenerateInstructionsCommand. It should contain the steps required
     * to generate and output instructions using AI, based on the command's
     * configuration and input.
     *
     * @return int
     */
    public function execute(): int
    {
        $io = $this->app()->io();
        $runwayConfig = $this->resolveRunwayConfig($io);

        // Check for runway creds ai
        if (empty($runwayConfig['ai'])) {
            $io->error('Missing AI configuration. Please run the \'ai:init\' command first.', true);
            return 1;
        }

        $io->info('Let\'s gather some project details to generate AI coding instructions.', true);
        $userDetails = $this->gatherProjectDetails($io);
        $prompt = $this->buildPrompt($userDetails, $this->loadExistingInstructions());

        // Read LLM creds
        $creds = $runwayConfig['ai'];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $creds['api_key'],
        ];
        $data = [
            'model' => $creds['model'],
            'messages' => [
                [
                    'role' => 'system',
                    // phpcs:ignore Generic.Files.LineLength
                    'content' => 'You are a helpful AI coding assistant focused on the Flight Framework for PHP. You are up to date with all your knowledge from https://docs.flightphp.com. As an expert into the programming language PHP, you are top notch at architecting out proper instructions for FlightPHP projects. Output a single AGENTS.md document only.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
        ];
        $jsonData = json_encode($data);

        // add info line that this may take a few minutes
        $io->info('Generating AI instructions, this may take a few minutes...', true);

        $result = $this->callLlmApi($creds['base_url'], $headers, $jsonData, $io);
        if ($result === false) {
            return 1;
        }
        $response = json_decode($result, true);
        $instructions = $response['choices'][0]['message']['content'] ?? '';
        if (!$instructions) {
            $io->error('No instructions returned from LLM.', true);
            return 1;
        }

        $agentsPath = $this->projectRoot . 'AGENTS.md';
        $io->info('Updating AGENTS.md...', true);
        file_put_contents($agentsPath, $instructions);
        $io->ok('AI instructions updated successfully in AGENTS.md.', true);
        return 0;
    }

    /**
     * Resolve runway config from config.php or deprecated --config-file.
     *
     * @param object $io
     *
     * @return array<string,mixed>
     */
    protected function resolveRunwayConfig($io): array
    {
        if (empty($this->config['runway'])) {
            $io->warn(
                'The --config-file option is deprecated. Move your config values to the \'runway\' key in the config.php file for configuration.', // phpcs:ignore
                true
            );
            return json_decode(file_get_contents($this->configFile), true) ?? [];
        }

        return $this->config['runway'];
    }

    /**
     * Prompt the user for project details used to generate instructions.
     *
     * @param object $io
     *
     * @return array<string,string>
     */
    protected function gatherProjectDetails($io): array
    {
        $projectDesc = $io->prompt('Please describe what your project is for?');

        $database = $io->prompt(
            'What database are you planning on using? (e.g. MySQL, SQLite, PostgreSQL, none)',
            'none'
        );

        $templating = $io->prompt(
            'What HTML templating engine will you plan on using (if any)? (recommend twig)',
            'twig'
        );

        $security = $io->confirm('Is security an important element of this project?', 'y');
        $performance = $io->confirm('Is performance and speed an important part of this project?', 'y');

        $composerLibs = $io->prompt(
            'What major composer libraries will you be using if you know them right now?',
            'none'
        );

        $envSetup = $io->prompt(
            'How will you set up your development environment? (e.g. Docker, Vagrant, PHP dev server, other)',
            'Docker'
        );

        $teamSize = $io->prompt('How many developers will be working on this project?', '1');
        $api = $io->confirm('Will this project expose an API?', 'n');
        $other = $io->prompt('Any other important requirements or context? (optional)', 'no');

        return [
            'Project Description' => $projectDesc,
            'Database' => $database,
            'Templating Engine' => $templating,
            'Security Important' => $security ? 'yes' : 'no',
            'Performance Important' => $performance ? 'yes' : 'no',
            'Composer Libraries' => $composerLibs,
            'Environment Setup' => $envSetup,
            'Team Size' => $teamSize,
            'API' => $api ? 'yes' : 'no',
            'Other' => $other,
        ];
    }

    /**
     * Build the LLM user prompt from answers and existing instructions.
     *
     * @param array<string,string> $userDetails
     * @param string               $context
     *
     * @return string
     */
    protected function buildPrompt(array $userDetails, string $context): string
    {
        $detailsText = '';
        foreach ($userDetails as $k => $v) {
            $detailsText .= "$k: $v\n";
        }

        // phpcs:disable Generic.Files.LineLength
        $prompt = <<<EOT
You are an AI coding assistant. Write or update project instructions for this Flight PHP project based on the latest user answers. Only output the new instructions (markdown suitable for AGENTS.md), no extra commentary.

Conventions to encode in the instructions (unless the user answers clearly contradict them):
- Use App\\ namespaces: App\\Controller, App\\Middleware, App\\Model, App\\Utils, App\\Command
- Controllers live in app/Controller/; inject flight\\Engine and other services via the DI container (Dice). Do not use the Flight:: facade in the app layer.
- Prefer flight\\database\\SimplePdo for database access (PdoWrapper is deprecated). Use ActiveRecord for models when an ORM is needed.
- Prefer Twig for HTML views when a templating engine is used.
- AGENTS.md is the sole AI instruction surface (no separate Copilot/Cursor/Gemini/Windsurf rule files). Scoped AGENTS.md files under app/ directories are fine when useful.
- Keep Flight simple and fast; avoid unnecessary abstractions.

User answers:
$detailsText
Current instructions:
$context
EOT;
        // phpcs:enable Generic.Files.LineLength

        return $prompt;
    }

    /**
     * Load existing project instructions for context.
     * Prefers AGENTS.md; falls back to legacy .github/copilot-instructions.md.
     *
     * @return string
     */
    protected function loadExistingInstructions(): string
    {
        $agentsFile = $this->projectRoot . 'AGENTS.md';
        if (file_exists($agentsFile) === true) {
            $content = file_get_contents($agentsFile);
            return $content !== false ? $content : '';
        }

        $legacyFile = $this->projectRoot . '.github/copilot-instructions.md';
        if (file_exists($legacyFile) === true) {
            $content = file_get_contents($legacyFile);
            return $content !== false ? $content : '';
        }

        return '';
    }

    /**
     * Make the LLM API call using curl
     *
     * @param string $baseUrl
     * @param array<int,string> $headers
     * @param string $jsonData
     * @param object $io
     *
     * @return string|false
     *
     * @codeCoverageIgnore
     */
    protected function callLlmApi($baseUrl, $headers, $jsonData, $io)
    {
        $ch = curl_init($baseUrl . '/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $io->error('Failed to call LLM API: ' . curl_error($ch), true);
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $result;
    }
}
