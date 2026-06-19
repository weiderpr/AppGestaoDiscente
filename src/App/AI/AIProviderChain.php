<?php
/**
 * Vértice Acadêmico — Chain de Provedores de IA com Fallback
 *
 * Tenta cada provedor em ordem. Se um falhar, passa para o próximo.
 * Lança RuntimeException apenas quando todos os provedores falharem.
 */

namespace App\AI;

use App\AI\Providers\AIProviderInterface;
use App\AI\Providers\GroqProvider;
use App\AI\Providers\GeminiProvider;
use App\AI\Providers\OpenRouterProvider;

class AIProviderChain
{
    /** @var AIProviderInterface[] */
    private array $providers = [];

    /**
     * @param array $configs Array de ['provider' => 'groq|gemini|openrouter', 'key' => 'string', 'model' => 'string (opcional)']
     */
    public function __construct(array $configs)
    {
        foreach ($configs as $cfg) {
            $key   = trim($cfg['key']   ?? '');
            $model = trim($cfg['model'] ?? '') ?: null;

            $provider = match ($cfg['provider'] ?? '') {
                'groq'       => $key ? new GroqProvider($key, $model)       : null,
                'gemini'     => $key ? new GeminiProvider($key, $model)     : null,
                'openrouter' => $key ? new OpenRouterProvider($key, $model) : null,
                default      => null,
            };

            if ($provider !== null) {
                $this->providers[] = $provider;
            }
        }
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }

    /**
     * Executa a chamada com fallback automático entre provedores.
     *
     * @return array{content: string, provider: string}
     * @throws \RuntimeException quando todos os provedores falham
     */
    public function call(string $systemPrompt, string $userPrompt): array
    {
        $errors = [];

        foreach ($this->providers as $provider) {
            try {
                $content = $provider->call($systemPrompt, $userPrompt);
                return ['content' => $content, 'provider' => $provider->getName()];
            } catch (\Throwable $e) {
                $errors[] = $provider->getName() . ': ' . $e->getMessage();
                error_log('[AIProviderChain] ' . $provider->getName() . ' falhou — ' . $e->getMessage());
            }
        }

        throw new \RuntimeException(
            'Todos os provedores de IA falharam. Detalhes: ' . implode(' | ', $errors)
        );
    }
}
