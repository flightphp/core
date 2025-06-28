<?php
namespace app\commands;

use Ahc\Cli\Input\Option;
use Ahc\Cli\Output\Writer;
use Ahc\Cli\IO\Interactor;
use Ahc\Cli\Input\Command;

class AiInitCommand extends Command
{
    public function __construct()
    {
        parent::__construct('ai:init', 'Initialize LLM API credentials and settings');
    }

    /**
     * Executes the function
     *
     * @return void
     */
    public function execute()
    {
		$io = $this->app()->io();

        $io->info('Welcome to AI Init!', true);

		// if runway creds already exist, prompt to overwrite
		$baseDir = getcwd() . DIRECTORY_SEPARATOR;
		$runwayCredsFile = $baseDir . '.runway-creds.json';

		// make sure the .runway-creds.json file is not already present
		if (file_exists($runwayCredsFile)) {
			$io->error('.runway-creds.json file already exists. Please remove it before running this command.', true);
			// prompt to overwrite
			$overwrite = $io->confirm('Do you want to overwrite the existing .runway-creds.json file?', 'n');
			if ($overwrite === false) {
				$io->info('Exiting without changes.', true);
				return 0;
			}
		}

		// Prompt for API provider with validation
		do {
			$api = $io->prompt('Which LLM API do you want to use? (openai, grok, claude) [openai]', 'openai');
			$api = strtolower(trim($api));
			if (!in_array($api, ['openai', 'grok', 'claude'], true)) {
			$io->error('Invalid API provider. Please enter one of: openai, grok, claude.', true);
			$api = '';
			}
		} while (empty($api));

		// Prompt for base URL with validation
		do {
			switch($api) {
				case 'openai':
					$defaultBaseUrl = 'https://api.openai.com';
					break;
				case 'grok':
					$defaultBaseUrl = 'https://api.x.ai';
					break;
				case 'claude':
					$defaultBaseUrl = 'https://api.anthropic.com';
					break;
				default:
					$defaultBaseUrl = '';
			}
			$baseUrl = $io->prompt('Enter the base URL for the LLM API', $defaultBaseUrl);
			$baseUrl = trim($baseUrl);
			if (empty($baseUrl) || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
			$io->error('Base URL cannot be empty and must be a valid URL.', true);
			$baseUrl = '';
			}
		} while (empty($baseUrl));

        // Validate API key input
        do {
            $apiKey = $io->prompt('Enter your API key for ' . $api);
            if (empty(trim($apiKey))) {
                $io->error('API key cannot be empty. Please enter a valid API key.', true);
            }
        } while (empty(trim($apiKey)));

        // Validate model input
        do {
			switch($api) {
				case 'openai':
					$defaultModel = 'gpt-4o';
					break;
				case 'grok':
					$defaultModel = 'grok-3-beta';
					break;
				case 'claude':
					$defaultModel = 'claude-3-opus';
					break;
				default:
					$defaultModel = '';
			}
            $model = $io->prompt('Enter the model name you want to use (e.g. gpt-4, claude-3-opus, etc)', $defaultModel);
            if (empty(trim($model))) {
                $io->error('Model name cannot be empty. Please enter a valid model name.', true);
            }
        } while (empty(trim($model)));

        $creds = [
            'provider' => $api,
            'api_key' => $apiKey,
            'model' => $model,
			'base_url' => $baseUrl,
        ];

        $json = json_encode($creds, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $file = $runwayCredsFile;
        if (file_put_contents($file, $json) === false) {
            $io->error('Failed to write credentials to ' . $file, true);
            return 1;
        }
        $io->ok('Credentials saved to ' . $file, true);

		// run a check to make sure that the creds file is in the .gitignore file
		$gitignoreFile = $baseDir . '.gitignore';
		if (!file_exists($gitignoreFile)) {
			// create the .gitignore file if it doesn't exist
			file_put_contents($gitignoreFile, ".runway-creds.json\n");
			$io->info('.gitignore file created and .runway-creds.json added to it.', true);
		} else {
			// check if the .runway-creds.json file is already in the .gitignore file
			$gitignoreContents = file_get_contents($gitignoreFile);
			if (strpos($gitignoreContents, '.runway-creds.json') === false) {
				// add the .runway-creds.json file to the .gitignore file
				file_put_contents($gitignoreFile, "\n.runway-creds.json\n", FILE_APPEND);
				$io->info('.runway-creds.json added to .gitignore file.', true);
			} else {
				$io->info('.runway-creds.json is already in the .gitignore file.', true);
			}
		}

        return 0;
    }
}
