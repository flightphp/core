<?php

declare(strict_types=1);

namespace flight\commands;

use Ahc\Cli\Input\Command;

/**
 * @property-read ?string $credsFile
 * @property-read ?string $baseDir
 */
class AiGenerateInstructionsCommand extends Command
{
    /**
     * Constructor for the AiGenerateInstructionsCommand class.
     *
     * Initializes a new instance of the command.
     */
    public function __construct()
    {
        parent::__construct('ai:generate-instructions', 'Generate project-specific AI coding instructions');
        $this->option('--creds-file', 'Path to .runway-creds.json file', null, '');
        $this->option('--base-dir', 'Project base directory (for testing or custom use)', null, '');
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
    public function execute()
    {
        $io = $this->app()->io();
        $baseDir = $this->baseDir ? rtrim($this->baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : getcwd() . DIRECTORY_SEPARATOR;
        $runwayCredsFile = $this->credsFile ?: $baseDir . '.runway-creds.json';

        // Check for runway creds
        if (!file_exists($runwayCredsFile)) {
            $io->error('Missing .runway-creds.json. Please run the \'ai:init\' command first.', true);
            return 1;
        }

        $io->info('Let\'s gather some project details to generate AI coding instructions.', true);

        // Ask questions
        $projectDesc = $io->prompt('Please describe what your project is for?');
        $database = $io->prompt('What database are you planning on using? (e.g. MySQL, SQLite, PostgreSQL, none)', 'none');
        $templating = $io->prompt('What HTML templating engine will you plan on using (if any)? (recommend latte)', 'latte');
        $security = $io->confirm('Is security an important element of this project?', 'y');
        $performance = $io->confirm('Is performance and speed an important part of this project?', 'y');
        $composerLibs = $io->prompt('What major composer libraries will you be using if you know them right now?', 'none');
        $envSetup = $io->prompt('How will you set up your development environment? (e.g. Docker, Vagrant, PHP dev server, other)', 'Docker');
        $teamSize = $io->prompt('How many developers will be working on this project?', '1');
        $api = $io->confirm('Will this project expose an API?', 'n');
        $other = $io->prompt('Any other important requirements or context? (optional)', 'no');

        // Prepare prompt for LLM
        $contextFile = $baseDir . '.github/copilot-instructions.md';
        $context = file_exists($contextFile) ? file_get_contents($contextFile) : '';
        $userDetails = [
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
        $detailsText = "";
        foreach ($userDetails as $k => $v) {
            $detailsText .= "$k: $v\n";
        }
        $prompt = <<<EOT
			You are an AI coding assistant. Update the following project instructions for this FlightPHP project based on the latest user answers. Only output the new instructions, no extra commentary.
			User answers:
			$detailsText
			Current instructions:
			$context
			EOT; // phpcs:ignore

        // Read LLM creds
        $creds = json_decode(file_get_contents($runwayCredsFile), true);
        $apiKey = $creds['api_key'] ?? '';
        $model = $creds['model'] ?? 'gpt-4o';
        $baseUrl = $creds['base_url'] ?? 'https://api.openai.com';

        // Prepare curl call (OpenAI compatible)
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful AI coding assistant focused on the Flight Framework for PHP. You are up to date with all your knowledge from https://docs.flightphp.com. As an expert into the programming language PHP, you are top notch at architecting out proper instructions for FlightPHP projects.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
        ];
        $jsonData = json_encode($data);

        // add info line that this may take a few minutes
        $io->info('Generating AI instructions, this may take a few minutes...', true);

        $result = $this->callLlmApi($baseUrl, $headers, $jsonData, $io);
        if ($result === false) {
            return 1;
        }
        $response = json_decode($result, true);
        $instructions = $response['choices'][0]['message']['content'] ?? '';
        if (!$instructions) {
            $io->error('No instructions returned from LLM.', true);
            return 1;
        }

        // Write to files
        $io->info('Updating .github/copilot-instructions.md, .cursor/rules/project-overview.mdc, and .windsurfrules...', true);
        if (!is_dir($baseDir . '.github')) {
            mkdir($baseDir . '.github', 0755, true);
        }
        if (!is_dir($baseDir . '.cursor/rules')) {
            mkdir($baseDir . '.cursor/rules', 0755, true);
        }
        file_put_contents($baseDir . '.github/copilot-instructions.md', $instructions);
        file_put_contents($baseDir . '.cursor/rules/project-overview.mdc', $instructions);
        file_put_contents($baseDir . '.windsurfrules', $instructions);
        $io->ok('AI instructions updated successfully.', true);
        return 0;
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
