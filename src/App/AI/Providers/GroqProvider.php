<?php
/**
 * Vértice Acadêmico — Provedor de IA: Groq
 *
 * Tier gratuito por modelo (TPD = tokens por dia):
 *   llama-3.3-70b-versatile  → 100 000 TPD
 *   llama-3.1-8b-instant     → 500 000 TPD  ← bom fallback quando 70B esgota
 * Documentação: https://console.groq.com/docs/openai
 */

namespace App\AI\Providers;

class GroqProvider implements AIProviderInterface
{
    private const API_URL       = 'https://api.groq.com/openai/v1/chat/completions';
    private const DEFAULT_MODEL = 'llama-3.3-70b-versatile';
    private const TIMEOUT       = 30;

    public function __construct(
        private readonly string  $apiKey,
        private readonly ?string $model = null
    ) {}

    public function getName(): string
    {
        return 'groq';
    }

    public function call(string $systemPrompt, string $userPrompt): string
    {
        $payload = json_encode([
            'model'       => $this->model ?? self::DEFAULT_MODEL,
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
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Groq cURL error: ' . $curlError);
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("Groq HTTP $httpCode: " . substr($response, 0, 200));
        }

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new \RuntimeException('Groq: resposta sem conteúdo válido');
        }

        return $content;
    }
}
