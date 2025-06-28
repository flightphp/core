<?php

declare(strict_types=1);

namespace flight\commands;

use Ahc\Cli\Input\Command;

/**
 * @property-read ?string $gitignoreFile
 * @property-read ?string $credsFile
 */
class AiInitCommand extends Command
{
    /**
     * Constructor for the AiInitCommand class.
     *
     * Initializes the command instance and sets up any required dependencies.
     */
    public function __construct()
    {
        parent::__construct('ai:init', 'Initialize LLM API credentials and settings');
        $this
            ->option('--gitignore-file', 'Path to .gitignore file', null, '')
            ->option('--creds-file', 'Path to .runway-creds.json file', null, '');
    }

    /**
     * Executes the function
     *
     * @return int
     */
    public function execute()
    {
        $io = $this->app()->io();

        $io->info('Welcome to AI Init!', true);

        $baseDir = getcwd() . DIRECTORY_SEPARATOR;
        $runwayCredsFile = $this->credsFile ?: $baseDir . '.runway-creds.json';
        $gitignoreFile = $this->gitignoreFile ?: $baseDir . '.gitignore';

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
        $allowedApis = [
            '1' => 'openai',
            '2' => 'grok',
            '3' => 'claude'
        ];
        $apiChoice = strtolower(trim($io->choice('Which LLM API do you want to use?', $allowedApis, '1')));
        $api = $allowedApis[$apiChoice] ?? 'openai';

        // Prompt for base URL with validation
        switch ($api) {
            case 'openai':
                $defaultBaseUrl = 'https://api.openai.com';
                break;
            case 'grok':
                $defaultBaseUrl = 'https://api.x.ai';
                break;
            case 'claude':
                $defaultBaseUrl = 'https://api.anthropic.com';
                break;
        }
        $baseUrl = trim($io->prompt('Enter the base URL for the LLM API', $defaultBaseUrl));
        if (empty($baseUrl) || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $io->error('Base URL cannot be empty and must be a valid URL.', true);
            return 1;
        }

        // Validate API key input
        $apiKey = trim($io->prompt('Enter your API key for ' . $api));
        if (empty($apiKey)) {
            $io->error('API key cannot be empty. Please enter a valid API key.', true);
            return 1;
        }

        // Validate model input
        switch ($api) {
            case 'openai':
                $defaultModel = 'gpt-4o';
                break;
            case 'grok':
                $defaultModel = 'grok-3-beta';
                break;
            case 'claude':
                $defaultModel = 'claude-3-opus';
                break;
        }
        $model = trim($io->prompt('Enter the model name you want to use (e.g. gpt-4, claude-3-opus, etc)', $defaultModel));

        $creds = [
            'provider' => $api,
            'api_key' => $apiKey,
            'model' => $model,
            'base_url' => $baseUrl,
        ];

        $json = json_encode($creds, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $file = $runwayCredsFile;
        file_put_contents($file, $json);

        // change permissions to 600
        chmod($file, 0600);

        $io->ok('Credentials saved to ' . $file, true);

        // run a check to make sure that the creds file is in the .gitignore file
        // use $gitignoreFile instead of hardcoded path
        if (!file_exists($gitignoreFile)) {
            // create the .gitignore file if it doesn't exist
            file_put_contents($gitignoreFile, basename($runwayCredsFile) . "\n");
            $io->info(basename($gitignoreFile) . ' file created and ' . basename($runwayCredsFile) . ' added to it.', true);
        } else {
            // check if the creds file is already in the .gitignore file
            $gitignoreContents = file_get_contents($gitignoreFile);
            if (strpos($gitignoreContents, basename($runwayCredsFile)) === false) {
                // add the creds file to the .gitignore file
                file_put_contents($gitignoreFile, "\n" . basename($runwayCredsFile) . "\n", FILE_APPEND);
                $io->info(basename($runwayCredsFile) . ' added to ' . basename($gitignoreFile) . ' file.', true);
            } else {
                $io->info(basename($runwayCredsFile) . ' is already in the ' . basename($gitignoreFile) . ' file.', true);
            }
        }

        return 0;
    }
}
