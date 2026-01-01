<?php

declare(strict_types=1);

namespace flight\commands;

/**
 * @property-read ?string $credsFile Deprecated, use config.php instead
 */
class AiInitCommand extends AbstractBaseCommand
{
    /**
     * Constructor for the AiInitCommand class.
     *
     * Initializes the command instance and sets up any required dependencies.
     *
     * @param array<string,mixed> $config Config from config.php
     */
    public function __construct(array $config)
    {
        parent::__construct('ai:init', 'Initialize LLM API credentials and settings', $config);
        $this->option('--creds-file', 'Path to .runway-creds.json file (deprecated, use config.php instead)', null, '');
    }

    /**
     * Executes the function
     *
     * @return int
     */
    public function execute(): int
    {
        $io = $this->app()->io();

        $io->info('Welcome to AI Init!', true);

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
                $defaultModel = 'gpt-5';
                break;
            case 'grok':
                $defaultModel = 'grok-4.1-fast-non-reasoning';
                break;
            case 'claude':
                $defaultModel = 'claude-sonnet-4-5';
                break;
        }
        $model = trim($io->prompt('Enter the model name you want to use (e.g. gpt-5, claude-sonnet-4-5, etc)', $defaultModel));

        $runwayAiConfig = [
            'provider' => $api,
            'api_key' => $apiKey,
            'model' => $model,
            'base_url' => $baseUrl,
        ];
        $this->setRunwayConfigValue('ai', $runwayAiConfig);

        $io->ok('Credentials saved to app/config/config.php', true);

        return 0;
    }
}
