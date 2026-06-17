<?php
/**
 * Vértice Acadêmico — Contrato para provedores de IA
 */

namespace App\AI\Providers;

interface AIProviderInterface
{
    /**
     * Envia os prompts ao provedor e retorna o texto gerado.
     *
     * @throws \RuntimeException se a chamada falhar ou retornar resposta inválida
     */
    public function call(string $systemPrompt, string $userPrompt): string;

    public function getName(): string;
}
