<?php
/**
 * Vértice Acadêmico — Componente: Análise Pedagógica de Aluno via IA
 *
 * Renderiza um widget autocontido que carrega e exibe a análise gerada pela IA
 * para um aluno em uma turma específica.
 *
 * Uso standalone (fora do modal):
 *   require_once __DIR__ . '/includes/components/StudentAIAnalysis.php';
 *   echo renderStudentAIAnalysis($alunoId, $turmaId);
 *
 * O componente renderiza o container HTML. O JavaScript carrega os dados via
 * /api/ai_analysis.php e preenche o conteúdo de forma assíncrona.
 * A função JS `vaAIAnalysis.init()` é chamada automaticamente via script inline.
 *
 * @param int    $alunoId   ID do aluno
 * @param int    $turmaId   ID da turma
 * @param bool   $autoLoad  Se true, dispara o carregamento automático ao montar
 * @return string           HTML completo do componente
 */
function renderStudentAIAnalysis(int $alunoId, int $turmaId, bool $autoLoad = true): string
{
    $uid = "ai-widget-{$alunoId}-{$turmaId}";

    $html  = '<div class="va-ai-analysis-widget" id="' . $uid . '" ';
    $html .= 'data-aluno-id="' . $alunoId . '" data-turma-id="' . $turmaId . '">';

    // Estado: carregando
    $html .= '<div class="va-ai-state va-ai-loading">';
    $html .= '<div style="font-size:2rem;margin-bottom:.5rem;">🤖</div>';
    $html .= '<div style="font-size:.875rem;color:var(--text-muted);">Carregando análise pedagógica...</div>';
    $html .= '</div>';

    // Estado: sem dados
    $html .= '<div class="va-ai-state va-ai-empty" style="display:none;">';
    $html .= '<div style="font-size:2.5rem;margin-bottom:.75rem;">📝</div>';
    $html .= '<div style="font-size:.9375rem;font-weight:600;color:var(--text-primary);margin-bottom:.25rem;">Sem dados suficientes</div>';
    $html .= '<div style="font-size:.8125rem;color:var(--text-muted);">Adicione comentários ou notas para gerar a análise.</div>';
    $html .= '</div>';

    // Estado: erro
    $html .= '<div class="va-ai-state va-ai-error" style="display:none;">';
    $html .= '<div style="font-size:2rem;margin-bottom:.5rem;">⚠️</div>';
    $html .= '<div class="va-ai-error-msg" style="font-size:.875rem;color:var(--color-danger);margin-bottom:.75rem;"></div>';
    $html .= '<button type="button" class="btn btn-secondary btn-sm" ';
    $html .= 'onclick="vaAIAnalysis.load(\'' . $uid . '\')">Tentar novamente</button>';
    $html .= '</div>';

    // Estado: conteúdo
    $html .= '<div class="va-ai-state va-ai-content" style="display:none;"></div>';

    $html .= '</div>'; // .va-ai-analysis-widget

    if ($autoLoad) {
        $html .= '<script>';
        $html .= 'document.addEventListener("DOMContentLoaded", function() {';
        $html .= ' vaAIAnalysis.load("' . $uid . '");';
        $html .= '});';
        $html .= '</script>';
    }

    return $html;
}
