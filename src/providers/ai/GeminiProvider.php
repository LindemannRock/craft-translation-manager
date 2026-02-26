<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Gemini provider adapter
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\providers\ai;

use Craft;
use lindemannrock\translationmanager\interfaces\AiTranslationProviderInterface;

/**
 * Gemini provider
 *
 * @since 5.22.0
 */
class GeminiProvider implements AiTranslationProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.0-flash',
    ) {
    }

    public function getHandle(): string
    {
        return 'gemini';
    }

    public function getDisplayName(): string
    {
        return 'Google Gemini';
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
                'Content-Type' => 'application/json',
            ],
        ]);

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $this->model,
            rawurlencode($this->apiKey)
        );

        $response = $client->post($endpoint, [
            'json' => [
                'generationConfig' => [
                    'temperature' => 0,
                    'maxOutputTokens' => 300,
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ],
        ]);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getBody(), true) ?? [];
        $parts = $data['candidates'][0]['content']['parts'] ?? null;

        if (!is_array($parts)) {
            throw new \RuntimeException('Gemini returned an empty response.');
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        $content = trim(implode("\n", $chunks));
        if ($content === '') {
            throw new \RuntimeException('Gemini returned an empty response.');
        }

        return $content;
    }
}
