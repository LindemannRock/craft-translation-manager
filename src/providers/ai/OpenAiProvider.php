<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * OpenAI provider adapter
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\providers\ai;

use Craft;
use lindemannrock\translationmanager\interfaces\AiTranslationProviderInterface;

/**
 * OpenAI provider
 *
 * @since 5.22.0
 */
class OpenAiProvider implements AiTranslationProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
    ) {
    }

    public function getHandle(): string
    {
        return 'openai';
    }

    public function getDisplayName(): string
    {
        return 'OpenAI';
    }

    public function testConnection(): array
    {
        $reply = $this->send('Reply with exactly: TM_OK');

        return [
            'success' => str_contains($reply, 'TM_OK'),
            'provider' => $this->getDisplayName(),
            'model' => $this->model,
            'message' => trim($reply),
        ];
    }

    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        $prompt = "Translate the following text from {$sourceLanguage} to {$targetLanguage}. " .
            'Return only the translated text. Preserve placeholders such as {name}, {{ value }}, and %s exactly.' .
            "\n\nText:\n{$text}";

        return trim($this->send($prompt));
    }

    private function send(string $prompt): string
    {
        $client = Craft::createGuzzleClient([
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'json' => [
                'model' => $this->model,
                'temperature' => 0,
                'max_tokens' => 300,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a precise translation assistant.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ],
        ]);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getBody(), true) ?? [];
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('OpenAI returned an empty response.');
        }

        return $content;
    }
}
