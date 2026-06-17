<?php
/**
 * Vértice Acadêmico — Provedor de IA: OpenRouter
 *
 * Tier gratuito: modelos com sufixo ":free" (Llama 3.1 8B, Mistral, etc.).
 * Documentação: https://openrouter.ai/docs
 */

namespace App\AI\Providers;

class OpenRouterProvider implements AIProviderInterface
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MODEL   = 'meta-llama/llama-3.1-8b-instruct:free';
    private const TIMEOUT = 45;

    public function __construct(private readonly string $apiKey) {}

    public function getName(): string
    {
        return 'openrouter';
    }

    public function call(string $systemPrompt, string $userPrompt): string
    {
        $payload = json_encode([
            'model'       => self::MODEL,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'max_tokens'  => 1500,
            'temperature' => 0.3,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: https://vertice-academico.com.br',
                'X-Title: Vertice Academico',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('OpenRouter cURL error: ' . $curlError);
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("OpenRouter HTTP $httpCode: " . substr($response, 0, 200));
        }

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new \RuntimeException('OpenRouter: resposta sem conteúdo válido');
        }

        return $content;
    }
}
