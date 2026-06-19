<?php
/**
 * Vértice Acadêmico — Alocador de Somativas via IA
 *
 * Usado como fallback quando o SomativaScheduler (greedy) não consegue
 * alocar todas as disciplinas. Envia o estado parcial + conflitos para a
 * AIProviderChain (Groq → Gemini → OpenRouter) e retorna um plano de
 * resolução em JSON.
 *
 * O prompt é estruturado para que a IA receba:
 *  - Conflitos não resolvidos (disc + turma + motivo)
 *  - Slots ainda livres no período
 *  - Restrições ativas da somativa
 *  - Grade já alocada pelo greedy (contexto de estado)
 *
 * A IA retorna JSON com alocações sugeridas para cada conflito.
 */

namespace App\Services;

use App\AI\AIProviderChain;

class SomativaAIScheduler
{
    private AIProviderChain $chain;

    public function __construct()
    {
        $configs     = defined('AI_PROVIDERS') ? AI_PROVIDERS : [];
        $this->chain = new AIProviderChain($configs);
    }

    public function isAvailable(): bool
    {
        return $this->chain->hasProviders();
    }

    // ──────────────────────────────────────────────────────────
    // Resolução de conflitos
    // ──────────────────────────────────────────────────────────

    /**
     * Tenta resolver conflitos de alocação usando a IA configurada.
     *
     * @param array $conflitos      [{som_disc_id, disc_nome, turma_desc, motivo}]
     * @param array $slotsLivres    [{date, slot_id, slot_label, turma_ids_livres}]
     * @param array $restricoes     Restrições ativas da somativa
     * @param array $gradeExistente Alocações já realizadas pelo greedy
     *
     * @return array{resolucoes: array, nao_resolvidos: array, justificativa: string, provider: string}
     */
    public function resolve(
        array $conflitos,
        array $slotsLivres,
        array $restricoes,
        array $gradeExistente
    ): array {
        [$systemPrompt, $userPrompt] = $this->buildPrompt(
            $conflitos, $slotsLivres, $restricoes, $gradeExistente
        );

        $result = $this->chain->call($systemPrompt, $userPrompt);
        $parsed = $this->parseResponse($result['content']);
        $parsed['provider'] = $result['provider'];

        return $parsed;
    }

    // ──────────────────────────────────────────────────────────
    // Construção do prompt
    // ──────────────────────────────────────────────────────────

    private function buildPrompt(
        array $conflitos,
        array $slotsLivres,
        array $restricoes,
        array $gradeExistente
    ): array {
        $systemPrompt = <<<'PROMPT'
Você é um especialista em agendamento de avaliações escolares brasileiras.

Sua tarefa é resolver conflitos de alocação de provas em uma grade horária somativa, respeitando restrições pedagógicas e operacionais.

Retorne EXCLUSIVAMENTE um JSON válido, sem markdown, sem texto antes ou depois do JSON.

Estrutura obrigatória da resposta:
{
  "resolucoes": [
    {
      "somativa_disciplina_id": 10,
      "data_prova": "2026-07-05",
      "slot_config_id": 2,
      "justificativa": "Slot com menor impacto nas restrições"
    }
  ],
  "justificativa_geral": "Explicação das decisões tomadas (2–3 frases)",
  "nao_resolvidos": []
}

Regras obrigatórias:
1. Restrições do tipo "hard" NUNCA podem ser violadas (bloquear_data, professor_indisponivel, mesmo_dia_turmas, mesmo_horario_turmas, mesmo_dia_horario_diferente)
2. Soft constraints devem ser minimizadas mas podem ser violadas se necessário
3. Não aloque duas provas da mesma turma no mesmo slot (data + slot_config_id)
4. Se houver restrição 'evitar_mesmo_dia', prefira dias diferentes para as disciplinas especificadas.
5. Se houver restrição 'mesmo_dia_turmas', use a mesma data para todas as turmas com aquela disciplina.
6. Se houver restrição 'mesmo_horario_turmas', use a mesma data E o mesmo slot (slot_config_id) para todas as turmas com aquela disciplina.
7. Se houver restrição 'mesmo_dia_horario_diferente':
   - Se a regra for 'deve': a disciplina A e a disciplina B devem ocorrer no mesmo dia, mas em slots (horários) diferentes.
   - Se a regra for 'nao_deve': a disciplina A e a disciplina B não podem ocorrer no mesmo dia de forma alguma (devem ocorrer em dias diferentes).
   - O escopo define se essa regra vale na mesma turma ('mesma_turma') ou em turmas diferentes ('turmas_diferentes').
8. Se não conseguir alocar uma disciplina, adicione seu somativa_disciplina_id em "nao_resolvidos"
9. Use apenas slots e datas disponíveis em "slots_livres"
10. Responda sempre em português do Brasil
PROMPT;

        // Filtra campos sensíveis/irrelevantes para a IA
        $gradeResumida = array_map(fn($a) => [
            'somativa_disciplina_id' => $a['somativa_disciplina_id'],
            'somativa_turma_id'      => $a['somativa_turma_id'],
            'data_prova'             => $a['data_prova'],
            'slot_config_id'         => $a['slot_config_id'],
            'disc_nome'              => $a['disc_nome'] ?? '',
            'turma_desc'             => $a['turma_desc'] ?? '',
        ], $gradeExistente);

        $restricoesResumidas = array_map(fn($r) => [
            'tipo'      => $r['tipo'],
            'categoria' => $r['categoria'],
            'params'    => json_decode($r['params'] ?? '{}', true),
            'descricao' => $r['descricao'] ?? '',
        ], $restricoes);

        $userPrompt = json_encode([
            'conflitos'       => $conflitos,
            'slots_livres'    => $slotsLivres,
            'restricoes'      => $restricoesResumidas,
            'grade_existente' => $gradeResumida,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [$systemPrompt, $userPrompt];
    }

    // ──────────────────────────────────────────────────────────
    // Parse da resposta
    // ──────────────────────────────────────────────────────────

    private function parseResponse(string $content): array
    {
        $json = trim($content);
        $json = preg_replace('/^```(?:json)?\s*/m', '', $json);
        $json = preg_replace('/\s*```$/m', '', $json);
        $json = trim($json);

        $data = json_decode($json, true);

        if (!is_array($data)) {
            if (preg_match('/\{.*\}/s', $json, $m)) {
                $data = json_decode($m[0], true);
            }
        }

        if (!is_array($data)) {
            throw new \RuntimeException(
                'A IA não retornou um JSON válido para alocação. Resposta: ' . substr($content, 0, 300)
            );
        }

        // Valida e sanitiza campos esperados
        $resolucoes = [];
        foreach ($data['resolucoes'] ?? [] as $res) {
            if (!isset($res['somativa_disciplina_id'], $res['data_prova'], $res['slot_config_id'])) continue;
            $resolucoes[] = [
                'somativa_disciplina_id' => (int)$res['somativa_disciplina_id'],
                'data_prova'             => (string)$res['data_prova'],
                'slot_config_id'         => (int)$res['slot_config_id'],
                'justificativa'          => $res['justificativa'] ?? 'Resolvido por IA',
            ];
        }

        return [
            'resolucoes'       => $resolucoes,
            'justificativa'    => $data['justificativa_geral'] ?? '',
            'nao_resolvidos'   => array_map('intval', $data['nao_resolvidos'] ?? []),
        ];
    }
}
