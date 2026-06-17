<?php
/**
 * Vértice Acadêmico — Provedor de IA: Google Gemini
 *
 * Tier gratuito: 1 500 req/dia, modelo Gemini 1.5 Flash.
 * Documentação: https://ai.google.dev/gemini-api/docs
 */

namespace App\AI\Providers;

class GeminiProvider implements AIProviderInterface
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    private const TIMEOUT = 30;

    public function __construct(private readonly string $apiKey) {}

    public function getName(): string
    {
        return 'gemini';
    }

    public function call(string $systemPrompt, string $userPrompt): string
    {
        $payload = json_encode([
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 1500,
                'temperature'     => 0.3,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $url = self::API_URL . '?key=' . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Gemini cURL error: ' . $curlError);
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("Gemini HTTP $httpCode: " . substr($response, 0, 200));
        }

        $data    = json_decode($response, true);
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($content === null) {
            throw new \RuntimeException('Gemini: resposta sem conteúdo válido');
        }

        return $content;
    }
}
