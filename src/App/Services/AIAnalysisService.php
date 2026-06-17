<?php
/**
 * Vértice Acadêmico — Serviço de Análise Pedagógica via IA
 *
 * Fontes de dados enviadas à IA (sem nome nem matrícula do aluno):
 *  1. Comentários dos professores       (comentarios_professores)
 *  2. Notas por etapa                   (etapa_notas + etapas + disciplinas)
 *  3. Anotações/post-its de conselho    (conselho_registros → conselhos_classe)
 *  4. Encaminhamentos de conselho       (conselho_encaminhamentos → conselhos_classe)
 *  5. Atendimentos profissionais públicos (gestao_atendimentos.descricao_publica)
 *
 * Nunca enviado: nome, matrícula, descricao_profissional, comentários privados.
 *
 * Cache: invalidado quando qualquer um dos cinco contadores muda.
 */

namespace App\Services;

use App\AI\AIProviderChain;

class AIAnalysisService extends Service
{
    private AIProviderChain $chain;

    public function __construct()
    {
        parent::__construct();
        $configs     = defined('AI_PROVIDERS') ? AI_PROVIDERS : [];
        $this->chain = new AIProviderChain($configs);
    }

    public function isConfigured(): bool
    {
        return $this->chain->hasProviders();
    }

    // -------------------------------------------------------------------------
    // Pontos de entrada públicos
    // -------------------------------------------------------------------------

    public function getAnalysis(int $alunoId, int $turmaId): ?array
    {
        $cached = $this->getCached($alunoId, $turmaId);
        $counts = $this->getAllCounts($alunoId, $turmaId);

        if ($this->needsUpdate($cached, $counts)) {
            return $this->recalculate($alunoId, $turmaId, $counts);
        }

        if ($cached === null) {
            return null;
        }

        $analysis             = json_decode($cached['analysis'], true);
        $analysis['provider'] = $cached['provider'];
        $analysis['_cache']   = ['generated_at' => $cached['generated_at']];

        return $analysis;
    }

    public function forceRefresh(int $alunoId, int $turmaId): ?array
    {
        $counts = $this->getAllCounts($alunoId, $turmaId);
        return $this->recalculate($alunoId, $turmaId, $counts);
    }

    // -------------------------------------------------------------------------
    // Cache
    // -------------------------------------------------------------------------

    private function getCached(int $alunoId, int $turmaId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM aluno_ai_analysis WHERE aluno_id = ? AND turma_id = ?',
            [$alunoId, $turmaId]
        );
    }

    private function getAllCounts(int $alunoId, int $turmaId): array
    {
        return [
            'comments'        => $this->countComments($alunoId, $turmaId),
            'grade_etapas'    => $this->countGradeEtapas($alunoId, $turmaId),
            'registros'       => $this->countRegistros($alunoId, $turmaId),
            'encaminhamentos' => $this->countEncaminhamentos($alunoId, $turmaId),
            'atendimentos'    => $this->countAtendimentos($alunoId),
        ];
    }

    private function needsUpdate(?array $cached, array $counts): bool
    {
        if ($cached === null) {
            return true;
        }

        return (int) $cached['comment_count']        !== $counts['comments']
            || (int) $cached['grade_etapas']          !== $counts['grade_etapas']
            || (int) $cached['registro_count']        !== $counts['registros']
            || (int) $cached['encaminhamento_count']  !== $counts['encaminhamentos']
            || (int) $cached['atendimento_count']     !== $counts['atendimentos'];
    }

    private function saveAnalysis(
        int    $alunoId,
        int    $turmaId,
        array  $analysis,
        array  $counts,
        string $provider
    ): void {
        $json = json_encode($analysis, JSON_UNESCAPED_UNICODE);

        $this->execute(
            'INSERT INTO aluno_ai_analysis
                (aluno_id, turma_id,
                 comment_count, grade_etapas, registro_count,
                 encaminhamento_count, atendimento_count,
                 analysis, provider, generated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                comment_count        = VALUES(comment_count),
                grade_etapas         = VALUES(grade_etapas),
                registro_count       = VALUES(registro_count),
                encaminhamento_count = VALUES(encaminhamento_count),
                atendimento_count    = VALUES(atendimento_count),
                analysis             = VALUES(analysis),
                provider             = VALUES(provider),
                generated_at         = NOW()',
            [
                $alunoId, $turmaId,
                $counts['comments'], $counts['grade_etapas'],
                $counts['registros'], $counts['encaminhamentos'], $counts['atendimentos'],
                $json, $provider,
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Recálculo
    // -------------------------------------------------------------------------

    private function recalculate(int $alunoId, int $turmaId, array $counts): ?array
    {
        $comments        = $this->fetchComments($alunoId, $turmaId);
        $grades          = $this->fetchGradesByEtapa($alunoId, $turmaId);
        $registros       = $this->fetchConselhoRegistros($alunoId, $turmaId);
        $encaminhamentos = $this->fetchEncaminhamentos($alunoId, $turmaId);
        $atendimentos    = $this->fetchAtendimentosPublicos($alunoId);

        if (empty($comments) && empty($grades) && empty($registros)
            && empty($encaminhamentos) && empty($atendimentos)
        ) {
            return null;
        }

        [$systemPrompt, $userPrompt] = $this->buildPrompt(
            $comments, $grades, $registros, $encaminhamentos, $atendimentos
        );

        $startMs = (int) round(microtime(true) * 1000);

        try {
            $result   = $this->chain->call($systemPrompt, $userPrompt);
            $analysis = $this->parseAnalysis($result['content']);
        } catch (\Throwable $e) {
            $durationMs = (int) round(microtime(true) * 1000) - $startMs;
            $this->auditAICall($alunoId, $turmaId, 'erro', $counts, $durationMs, [
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }

        $durationMs = (int) round(microtime(true) * 1000) - $startMs;

        $analysis['provider'] = $result['provider'];

        $this->saveAnalysis($alunoId, $turmaId, $analysis, $counts, $result['provider']);

        $this->auditAICall($alunoId, $turmaId, 'sucesso', $counts, $durationMs, [
            'provider'            => $result['provider'],
            'genero'              => $analysis['genero']                   ?? 'não_identificado',
            'tendencia_comportam' => $analysis['tendencia_comportamental'] ?? null,
            'tendencia_academica' => $analysis['tendencia_academica']      ?? null,
            'nivel_atencao'       => $analysis['nivel_atencao']            ?? null,
            'areas_dificuldade'   => $analysis['areas_dificuldade']        ?? [],
        ]);

        $analysis['_cache'] = ['generated_at' => date('Y-m-d H:i:s')];

        return $analysis;
    }

    private function auditAICall(
        int    $alunoId,
        int    $turmaId,
        string $status,
        array  $counts,
        int    $durationMs,
        array  $details
    ): void {
        $this->audit(
            'AI_ANALYSIS',
            'aluno_ai_analysis',
            $alunoId,
            null,
            array_merge([
                'turma_id'        => $turmaId,
                'status'          => $status,
                'comentarios'     => $counts['comments'],
                'etapas_nota'     => $counts['grade_etapas'],
                'registros'       => $counts['registros'],
                'encaminhamentos' => $counts['encaminhamentos'],
                'atendimentos'    => $counts['atendimentos'],
                'duracao_ms'      => $durationMs,
            ], $details)
        );
    }

    // -------------------------------------------------------------------------
    // Contadores (usados para invalidação de cache)
    // -------------------------------------------------------------------------

    private function countComments(int $alunoId, int $turmaId): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total
             FROM comentarios_professores
             WHERE aluno_id = ? AND turma_id = ?',
            [$alunoId, $turmaId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countGradeEtapas(int $alunoId, int $turmaId): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(DISTINCT en.etapa_id) AS total
             FROM etapa_notas en
             JOIN etapas e ON e.id = en.etapa_id
             WHERE en.aluno_id = ? AND e.turma_id = ?',
            [$alunoId, $turmaId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countRegistros(int $alunoId, int $turmaId): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total
             FROM conselho_registros cr
             JOIN conselhos_classe cc ON cr.conselho_id = cc.id
             WHERE cr.aluno_id = ? AND cc.turma_id = ?',
            [$alunoId, $turmaId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countEncaminhamentos(int $alunoId, int $turmaId): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total
             FROM conselho_encaminhamentos ce
             JOIN conselhos_classe cc ON ce.conselho_id = cc.id
             WHERE ce.aluno_id = ? AND cc.turma_id = ?',
            [$alunoId, $turmaId]
        );
        return (int) ($row['total'] ?? 0);
    }

    /** Conta atendimentos com descrição pública — não filtra por turma (atendimentos são do aluno) */
    private function countAtendimentos(int $alunoId): int
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total
             FROM gestao_atendimentos
             WHERE aluno_id = ?
               AND deleted_at IS NULL AND is_archived = 0
               AND descricao_publica IS NOT NULL AND descricao_publica <> ''",
            [$alunoId]
        );
        return (int) ($row['total'] ?? 0);
    }

    // -------------------------------------------------------------------------
    // Busca de dados (conteúdo enviado à IA — sem nome/matrícula do aluno)
    // -------------------------------------------------------------------------

    private function fetchComments(int $alunoId, int $turmaId): array
    {
        return $this->fetchAll(
            'SELECT cp.conteudo, cp.created_at, u.profile AS perfil
             FROM comentarios_professores cp
             JOIN users u ON u.id = cp.professor_id
             WHERE cp.aluno_id = ? AND cp.turma_id = ?
             ORDER BY cp.created_at ASC',
            [$alunoId, $turmaId]
        );
    }

    private function fetchGradesByEtapa(int $alunoId, int $turmaId): array
    {
        return $this->fetchAll(
            'SELECT e.description AS etapa, d.descricao AS disciplina, en.nota, en.faltas
             FROM etapa_notas en
             JOIN etapas e      ON e.id = en.etapa_id
             JOIN disciplinas d ON d.codigo = en.disciplina_codigo
             WHERE en.aluno_id = ? AND e.turma_id = ?
             ORDER BY e.id ASC, d.descricao ASC',
            [$alunoId, $turmaId]
        );
    }

    /** Anotações/post-its do conselho de classe para este aluno nesta turma */
    private function fetchConselhoRegistros(int $alunoId, int $turmaId): array
    {
        return $this->fetchAll(
            'SELECT cr.texto, cr.created_at, u.profile AS perfil,
                    cc.descricao AS conselho, cc.data_hora AS data_conselho
             FROM conselho_registros cr
             JOIN conselhos_classe cc ON cr.conselho_id = cc.id
             JOIN users u ON cr.user_id = u.id
             WHERE cr.aluno_id = ? AND cc.turma_id = ?
             ORDER BY cr.created_at ASC',
            [$alunoId, $turmaId]
        );
    }

    /** Encaminhamentos do conselho de classe para este aluno nesta turma */
    private function fetchEncaminhamentos(int $alunoId, int $turmaId): array
    {
        return $this->fetchAll(
            'SELECT ce.texto, ce.created_at, ce.setor_tipo, ce.status, ce.data_expectativa,
                    u.profile AS perfil, cc.descricao AS conselho
             FROM conselho_encaminhamentos ce
             JOIN conselhos_classe cc ON ce.conselho_id = cc.id
             JOIN users u ON ce.author_id = u.id
             WHERE ce.aluno_id = ? AND cc.turma_id = ?
             ORDER BY ce.created_at ASC',
            [$alunoId, $turmaId]
        );
    }

    /**
     * Atendimentos profissionais — apenas descrição PÚBLICA.
     * descricao_profissional e comentários privados NUNCA são enviados à IA.
     * Não filtra por turma: atendimentos são do aluno como um todo.
     */
    private function fetchAtendimentosPublicos(int $alunoId): array
    {
        return $this->fetchAll(
            "SELECT ga.titulo, ga.descricao_publica, ga.status, ga.created_at,
                    u.profile AS perfil
             FROM gestao_atendimentos ga
             JOIN users u ON ga.author_id = u.id
             WHERE ga.aluno_id = ?
               AND ga.deleted_at IS NULL AND ga.is_archived = 0
               AND ga.descricao_publica IS NOT NULL AND ga.descricao_publica <> ''
             ORDER BY ga.created_at ASC",
            [$alunoId]
        );
    }

    // -------------------------------------------------------------------------
    // Construção do prompt
    // -------------------------------------------------------------------------

    private function buildPrompt(
        array $comments,
        array $grades,
        array $registros,
        array $encaminhamentos,
        array $atendimentos
    ): array {
        $systemPrompt = <<<'PROMPT'
Você é um assistente especializado em análise pedagógica de alunos do ensino técnico e médio no Brasil.

Analise todos os dados fornecidos (comentários de professores, notas por etapa, anotações do conselho de classe, encaminhamentos e atendimentos profissionais) e retorne EXCLUSIVAMENTE um JSON válido, sem markdown, sem texto antes ou depois do JSON.

O JSON deve seguir exatamente esta estrutura:
{
  "genero": "masculino|feminino|não_identificado",
  "resumo": "Parágrafo descritivo geral sobre o aluno (2 a 4 frases, tom profissional e pedagógico)",
  "tendencia_comportamental": "melhorando|piorando|estável|sem_dados",
  "tendencia_academica": "melhorando|piorando|estável|sem_dados",
  "nivel_atencao": "normal|atenção|urgente",
  "areas_dificuldade": ["lista de disciplinas ou aspectos com maior dificuldade"],
  "pontos_fortes": ["lista de pontos positivos identificados nos dados"],
  "comentarios_resumo": "Síntese objetiva dos principais pontos citados pelos profissionais (2 a 3 frases)",
  "recomendacoes": ["lista com 2 a 4 recomendações pedagógicas concretas e acionáveis"],
  "evolucao_descritiva": "Descrição textual da evolução do aluno ao longo das etapas com notas disponíveis"
}

Instruções específicas:
- "genero": identifique com base nos pronomes dos textos (ele/dela, aluno/aluna, etc.). Use "não_identificado" quando não for possível.
- Utilize o gênero identificado em todos os campos de texto: se "masculino", use "o aluno"; se "feminino", use "a aluna"; se "não_identificado", use "o(a) aluno(a)".
- "nivel_atencao": considere "urgente" quando houver encaminhamentos pendentes, múltiplos atendimentos profissionais ou padrão de piora consistente; "atenção" para alertas moderados; "normal" para situação estável.
- Use "sem_dados" quando não houver informação suficiente para um campo.
- Responda sempre em português do Brasil.
PROMPT;

        $lines = [];

        // --- Comentários dos professores ---
        $lines[] = '=== COMENTÁRIOS DOS PROFESSORES ===';
        if (empty($comments)) {
            $lines[] = 'Nenhum comentário registrado.';
        } else {
            foreach ($comments as $c) {
                $date  = date('d/m/Y', strtotime($c['created_at']));
                $text  = trim(strip_tags(html_entity_decode($c['conteudo'])));
                $lines[] = "[{$date}] {$c['perfil']}: {$text}";
            }
        }

        // --- Notas por etapa ---
        $lines[] = '';
        $lines[] = '=== NOTAS POR ETAPA ===';
        if (empty($grades)) {
            $lines[] = 'Nenhuma nota registrada ainda.';
        } else {
            $byEtapa = [];
            foreach ($grades as $g) {
                $byEtapa[$g['etapa']][] = $g;
            }
            foreach ($byEtapa as $etapa => $items) {
                $notas = array_filter(array_column($items, 'nota'), fn($n) => $n !== null);
                $media = count($notas) > 0
                    ? number_format(array_sum($notas) / count($notas), 1, '.', '')
                    : '-';
                $lines[] = '';
                $lines[] = "[{$etapa}] — Média: {$media}";
                foreach ($items as $item) {
                    $nota   = $item['nota'] !== null ? $item['nota'] : '-';
                    $faltas = (int) ($item['faltas'] ?? 0);
                    $lines[] = "  {$item['disciplina']}: nota {$nota}, faltas {$faltas}";
                }
            }
        }

        // --- Anotações do conselho de classe ---
        $lines[] = '';
        $lines[] = '=== ANOTAÇÕES DO CONSELHO DE CLASSE ===';
        if (empty($registros)) {
            $lines[] = 'Nenhuma anotação registrada.';
        } else {
            foreach ($registros as $r) {
                $date  = date('d/m/Y', strtotime($r['created_at']));
                $text  = trim(strip_tags(html_entity_decode($r['texto'])));
                $lines[] = "[{$date}] {$r['perfil']}: {$text}";
            }
        }

        // --- Encaminhamentos do conselho ---
        $lines[] = '';
        $lines[] = '=== ENCAMINHAMENTOS DO CONSELHO DE CLASSE ===';
        if (empty($encaminhamentos)) {
            $lines[] = 'Nenhum encaminhamento registrado.';
        } else {
            foreach ($encaminhamentos as $e) {
                $date        = date('d/m/Y', strtotime($e['created_at']));
                $text        = trim(strip_tags(html_entity_decode($e['texto'])));
                $expectativa = $e['data_expectativa']
                    ? ' | Previsão: ' . date('d/m/Y', strtotime($e['data_expectativa']))
                    : '';
                $lines[] = "[{$date}] → {$e['setor_tipo']} ({$e['status']}{$expectativa}): {$text}";
            }
        }

        // --- Atendimentos profissionais (descrição pública) ---
        $lines[] = '';
        $lines[] = '=== ATENDIMENTOS PROFISSIONAIS (REGISTROS PÚBLICOS) ===';
        if (empty($atendimentos)) {
            $lines[] = 'Nenhum atendimento registrado.';
        } else {
            foreach ($atendimentos as $a) {
                $date  = date('d/m/Y', strtotime($a['created_at']));
                $desc  = trim(strip_tags(html_entity_decode($a['descricao_publica'] ?? '')));
                $lines[] = "[{$date}] {$a['perfil']} — {$a['titulo']} (Status: {$a['status']})";
                if ($desc) {
                    $lines[] = "  {$desc}";
                }
            }
        }

        $lines[] = '';
        $lines[] = 'Gere a análise JSON conforme instruído.';

        return [$systemPrompt, implode("\n", $lines)];
    }

    // -------------------------------------------------------------------------
    // Parse da resposta
    // -------------------------------------------------------------------------

    private function parseAnalysis(string $content): array
    {
        $json = trim($content);
        $json = preg_replace('/^```(?:json)?\s*/m', '', $json);
        $json = preg_replace('/\s*```$/m', '', $json);
        $json = trim($json);

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            if (preg_match('/\{.*\}/s', $json, $m)) {
                $data = json_decode($m[0], true);
            }
        }

        if (!is_array($data)) {
            throw new \RuntimeException(
                'A IA não retornou um JSON válido. Resposta: ' . substr($content, 0, 200)
            );
        }

        foreach (['areas_dificuldade', 'pontos_fortes', 'recomendacoes'] as $field) {
            if (!isset($data[$field]) || !is_array($data[$field])) {
                $data[$field] = [];
            }
        }

        if (empty($data['genero'])) {
            $data['genero'] = 'não_identificado';
        }

        return $data;
    }
}
