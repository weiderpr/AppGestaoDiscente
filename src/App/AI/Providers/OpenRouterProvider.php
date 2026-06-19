<?php
/**
 * Vértice Acadêmico — Provedor de IA: OpenRouter
 *
 * Tier gratuito: modelos com sufixo ":free".
 * Referência de modelos gratuitos: https://openrouter.ai/models?q=:free
 * Documentação: https://openrouter.ai/docs
 */

namespace App\AI\Providers;

class OpenRouterProvider implements AIProviderInterface
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const TIMEOUT = 45;

    // Fallback estático caso a descoberta dinâmica falhe
    private const FREE_MODELS = [
        'deepseek/deepseek-r1:free',
        'deepseek/deepseek-chat:free',
        'google/gemma-3-12b-it:free',
        'google/gemma-3-4b-it:free',
        'google/gemma-2-9b-it:free',
        'mistralai/mistral-nemo:free',
        'microsoft/phi-3-mini-128k-instruct:free',
        'meta-llama/llama-3.2-3b-instruct:free',
    ];

    public function __construct(
        private readonly string  $apiKey,
        private readonly ?string $model = null
    ) {}

    public function getName(): string
    {
        return 'openrouter';
    }

    public function call(string $systemPrompt, string $userPrompt): string
    {
        $models    = $this->model ? [$this->model] : $this->resolveModels();
        $lastError = 'nenhum modelo disponível';

        foreach ($models as $model) {
            try {
                return $this->callModel($model, $systemPrompt, $userPrompt);
            } catch (\RuntimeException $e) {
                $lastError = $e->getMessage();
                error_log("[OpenRouterProvider] modelo $model falhou: $lastError");
            }
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * Descobre modelos gratuitos generativos via API do OpenRouter.
     * Exclui modelos de moderação/segurança que não retornam texto livre.
     * Usa lista estática como fallback se a chamada falhar.
     */
    private function resolveModels(): array
    {
        try {
            $ch = curl_init('https://openrouter.ai/api/v1/models');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->apiKey],
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response) {
                $data = json_decode($response, true);
                $models = array_values(array_filter(
                    array_column($data['data'] ?? [], 'id'),
                    fn($id) => str_ends_with((string) $id, ':free')
                        && !preg_match('/safety|moderat|guard|shield|classif|content-filter/i', (string) $id)
                ));

                // Prioriza modelos do fallback estático que aparecerem na lista dinâmica
                $priority = array_intersect(self::FREE_MODELS, $models);
                $rest     = array_diff($models, self::FREE_MODELS);
                $sorted   = array_values(array_merge($priority, $rest));

                if (!empty($sorted)) {
                    return $sorted;
                }
            }
        } catch (\Throwable) {}

        return self::FREE_MODELS;
    }

    private function callModel(string $model, string $systemPrompt, string $userPrompt): string
    {
        $payload = json_encode([
            'model'       => $model,
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

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
