<?php
namespace app\commands;

use Ahc\Cli\Input\Command;

class AiGenerateInstructionsCommand extends Command
{
    public function __construct()
    {
        parent::__construct('ai:generate-instructions', 'Generate project-specific AI coding instructions');
    }

    public function execute()
    {
        $io = $this->app()->io();
        $baseDir = getcwd() . DIRECTORY_SEPARATOR;
        $runwayCredsFile = $baseDir . '.runway-creds.json';

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
        $prompt = "" .
            "You are an AI coding assistant. Update the following project instructions for this FlightPHP project based on the latest user answers. " .
            "Only output the new instructions, no extra commentary.\n" .
            "User answers:\n$detailsText\n" .
            "Current instructions:\n$context\n";

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

        $ch = curl_init($baseUrl . '/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $io->error('Failed to call LLM API: ' . curl_error($ch), true);
            return 1;
        }
        curl_close($ch);
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
}
