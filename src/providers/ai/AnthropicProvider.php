<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Anthropic provider adapter
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\providers\ai;

use Craft;
use lindemannrock\translationmanager\interfaces\AiTranslationProviderInterface;

/**
 * Anthropic provider
 *
 * @since 5.22.0
 */
class AnthropicProvider implements AiTranslationProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-3-haiku-20240307',
    ) {
    }

    public function getHandle(): string
    {
        return 'anthropic';
    }

    public function getDisplayName(): string
    {
        return 'Anthropic Claude';
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
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
        ]);

        $response = $client->post('https://api.anthropic.com/v1/messages', [
            'json' => [
                'model' => $this->model,
                'max_tokens' => 300,
                'temperature' => 0,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ],
        ]);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getBody(), true) ?? [];
        $contentParts = $data['content'] ?? null;

        if (!is_array($contentParts)) {
            throw new \RuntimeException('Anthropic returned an empty response.');
        }

        $chunks = [];
        foreach ($contentParts as $part) {
            if (is_array($part) && ($part['type'] ?? null) === 'text' && isset($part['text']) && is_string($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        $content = trim(implode("\n", $chunks));
        if ($content === '') {
            throw new \RuntimeException('Anthropic returned an empty response.');
        }

        return $content;
    }
}
