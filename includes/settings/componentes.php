<?php
/**
 * Vértice Acadêmico — Configurações: Catálogo de Componentes
 *
 * Documenta os componentes reutilizáveis disponíveis no sistema,
 * com assinatura, exemplo de chamada e explicação de funcionamento.
 */

$aiConfigured = defined('AI_PROVIDERS') && !empty(AI_PROVIDERS);
?>

<style>
.comp-card      { margin-bottom: 2rem; }
.comp-badge     { display:inline-flex; align-items:center; gap:.375rem; padding:.25rem .75rem; border-radius:var(--radius-full); font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.comp-badge-ai  { background:rgba(99,102,241,.12); color:#6366f1; }
.comp-badge-ui  { background:rgba(16,185,129,.12);  color:var(--color-success); }
.comp-badge-new { background:rgba(245,158,11,.12);  color:var(--color-warning); }
.code-block {
    background: var(--bg-surface-2nd);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
    font-family: 'Fira Code', 'Courier New', monospace;
    font-size: .8125rem;
    line-height: 1.7;
    overflow-x: auto;
    white-space: pre;
    color: var(--text-primary);
    margin: .75rem 0;
}
.code-block .kw  { color: #a78bfa; }
.code-block .fn  { color: #60a5fa; }
.code-block .str { color: #34d399; }
.code-block .cmt { color: var(--text-muted); font-style:italic; }
.comp-section-title {
    font-size: .6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-muted);
    margin: 1.5rem 0 .75rem;
}
.comp-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin:.75rem 0; }
.comp-info-item { background:var(--bg-surface-2nd); padding:.875rem 1rem; border-radius:var(--radius-md); border:1px solid var(--border-color); }
.comp-info-label { font-size:.6875rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:.375rem; }
.comp-info-value { font-size:.875rem; color:var(--text-primary); line-height:1.5; }
.comp-preview {
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    background: var(--bg-surface-2nd);
    margin: .75rem 0;
}
.comp-preview-label {
    font-size:.6875rem;
    font-weight:700;
    text-transform:uppercase;
    color:var(--text-muted);
    margin-bottom:1rem;
    display:flex;
    align-items:center;
    gap:.5rem;
}
.ai-config-alert {
    background: rgba(245,158,11,.08);
    border: 1px solid rgba(245,158,11,.3);
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
    margin: .75rem 0;
    font-size: .875rem;
    color: var(--text-primary);
}
</style>

<?php if (!$aiConfigured): ?>
<div class="ai-config-alert">
    ⚙️ <strong>Configuração necessária:</strong> A constante <code>AI_PROVIDERS</code> não está definida em
    <code>config/config.local.php</code>. Os componentes de IA aparecerão, mas retornarão erro 503 ao ser chamados.
    Consulte o exemplo de configuração nesta página.
</div>
<?php endif; ?>


<!-- =========================================================================
     COMPONENTE 1: StudentAIAnalysis
     ======================================================================= -->
<div class="card settings-card comp-card">
    <div class="settings-card-header">
        <div class="settings-card-icon">🤖</div>
        <div style="flex:1;">
            <div class="settings-card-title" style="display:flex;align-items:center;gap:.625rem;">
                StudentAIAnalysis
                <span class="comp-badge comp-badge-ai">IA</span>
                <span class="comp-badge comp-badge-new">Novo</span>
            </div>
            <div class="settings-card-desc">Análise pedagógica de aluno gerada por IA com cache inteligente</div>
        </div>
    </div>
    <div class="card-body">

        <p style="color:var(--text-secondary);font-size:.9375rem;line-height:1.7;margin:0 0 1.25rem;">
            Analisa comentários de professores e notas de etapas de um aluno e gera um relatório pedagógico completo
            usando modelos de linguagem (LLMs) gratuitos. O resultado é cacheado na tabela
            <code>aluno_ai_analysis</code> e só é regenerado quando a quantidade de comentários ou etapas com nota mudar.
        </p>

        <div class="comp-info-grid">
            <div class="comp-info-item">
                <div class="comp-info-label">Arquivo do componente</div>
                <div class="comp-info-value"><code>includes/components/StudentAIAnalysis.php</code></div>
            </div>
            <div class="comp-info-item">
                <div class="comp-info-label">API endpoint</div>
                <div class="comp-info-value"><code>GET/POST /api/ai_analysis.php</code></div>
            </div>
            <div class="comp-info-item">
                <div class="comp-info-label">Serviço PHP</div>
                <div class="comp-info-value"><code>App\Services\AIAnalysisService</code></div>
            </div>
            <div class="comp-info-item">
                <div class="comp-info-label">Permissão necessária</div>
                <div class="comp-info-value"><code>students.comments</code></div>
            </div>
        </div>

        <div class="comp-section-title">Como funciona</div>
        <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.875rem;color:var(--text-secondary);margin-bottom:1.25rem;">
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <span style="background:var(--color-primary-light);color:var(--color-primary);font-weight:700;font-size:.75rem;padding:.125rem .5rem;border-radius:var(--radius-full);flex-shrink:0;margin-top:.1rem;">1</span>
                <span>Ao acessar, faz uma query leve de <code>COUNT</code> na tabela de comentários e etapas com nota.</span>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <span style="background:var(--color-primary-light);color:var(--color-primary);font-weight:700;font-size:.75rem;padding:.125rem .5rem;border-radius:var(--radius-full);flex-shrink:0;margin-top:.1rem;">2</span>
                <span>Se os snapshots baterem com o cache salvo → devolve o cache instantaneamente.</span>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <span style="background:var(--color-primary-light);color:var(--color-primary);font-weight:700;font-size:.75rem;padding:.125rem .5rem;border-radius:var(--radius-full);flex-shrink:0;margin-top:.1rem;">3</span>
                <span>Se houve mudança → monta o prompt com todos os comentários (cronológicos) e notas por etapa → chama a IA.</span>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <span style="background:var(--color-primary-light);color:var(--color-primary);font-weight:700;font-size:.75rem;padding:.125rem .5rem;border-radius:var(--radius-full);flex-shrink:0;margin-top:.1rem;">4</span>
                <span>A IA retorna JSON estruturado com resumo, tendências, áreas de dificuldade e recomendações.</span>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <span style="background:var(--color-primary-light);color:var(--color-primary);font-weight:700;font-size:.75rem;padding:.125rem .5rem;border-radius:var(--radius-full);flex-shrink:0;margin-top:.1rem;">5</span>
                <span>Fallback automático: Groq → Gemini → OpenRouter. Se um provedor falhar, tenta o próximo.</span>
            </div>
        </div>

        <div class="comp-section-title">Uso — Componente standalone</div>
        <div class="code-block"><span class="cmt">// 1. Incluir o componente e o JS base (student_comments.js já inclui o vaAIAnalysis)</span>
<span class="kw">require_once</span> <span class="str">__DIR__ . '/includes/components/StudentAIAnalysis.php'</span>;

<span class="cmt">// 2. Renderizar o widget onde quiser na página</span>
<span class="kw">echo</span> <span class="fn">renderStudentAIAnalysis</span>(<span class="str">$alunoId</span>, <span class="str">$turmaId</span>);

<span class="cmt">// O componente carrega automaticamente via JS ao montar (autoLoad = true por padrão).</span>
<span class="cmt">// Para controle manual: renderStudentAIAnalysis($alunoId, $turmaId, autoLoad: false)</span>
<span class="cmt">// e depois chame: vaAIAnalysis.load('ai-widget-{alunoId}-{turmaId}')</span></div>

        <div class="comp-section-title">Uso — Aba no modal de comentários</div>
        <div class="code-block"><span class="cmt">// Já integrado em includes/student_comment_modal.php como aba "Relatório IA".</span>
<span class="cmt">// Ao clicar na aba, switchCommentTab('ai_report') chama loadAIReport() automaticamente.</span>
<span class="cmt">// O usuário pode forçar a regeneração clicando em "Atualizar análise".</span>

<span class="cmt">// Para usar o modal no seu contexto:</span>
<span class="kw">require_once</span> <span class="str">__DIR__ . '/includes/student_comment_modal.php'</span>;
<span class="cmt">// JavaScript: openCommentModal({id, nome, photo, photo_url}, turmaId, alunoList)</span></div>

        <div class="comp-section-title">Estrutura do JSON retornado pela IA</div>
        <div class="code-block">{
  <span class="str">"resumo"</span>:                   <span class="str">"Parágrafo descritivo (2–4 frases)"</span>,
  <span class="str">"tendencia_comportamental"</span>:  <span class="str">"melhorando | piorando | estável | sem_dados"</span>,
  <span class="str">"tendencia_academica"</span>:       <span class="str">"melhorando | piorando | estável | sem_dados"</span>,
  <span class="str">"nivel_atencao"</span>:             <span class="str">"normal | atenção | urgente"</span>,
  <span class="str">"areas_dificuldade"</span>:         [<span class="str">"Matemática"</span>, <span class="str">"Física"</span>],
  <span class="str">"pontos_fortes"</span>:             [<span class="str">"Participação"</span>, <span class="str">"Português"</span>],
  <span class="str">"comentarios_resumo"</span>:        <span class="str">"Síntese do que os professores citaram"</span>,
  <span class="str">"recomendacoes"</span>:             [<span class="str">"Buscar reforço em Matemática"</span>, ...],
  <span class="str">"evolucao_descritiva"</span>:       <span class="str">"Narrativa da evolução nas etapas"</span>
}</div>

        <div class="comp-section-title">Configuração em config/config.local.php</div>
        <div class="code-block"><span class="cmt">// Provedores em ordem de prioridade — fallback automático entre eles</span>
<span class="kw">define</span>(<span class="str">'AI_PROVIDERS'</span>, [
    [<span class="str">'provider'</span> => <span class="str">'groq'</span>,       <span class="str">'key'</span> => <span class="str">'gsk_...'</span>],       <span class="cmt">// https://console.groq.com</span>
    [<span class="str">'provider'</span> => <span class="str">'groq'</span>,       <span class="str">'key'</span> => <span class="str">'gsk_...'</span>],       <span class="cmt">// segunda chave Groq (rotação)</span>
    [<span class="str">'provider'</span> => <span class="str">'gemini'</span>,     <span class="str">'key'</span> => <span class="str">'AIza...'</span>],      <span class="cmt">// https://ai.google.dev</span>
    [<span class="str">'provider'</span> => <span class="str">'openrouter'</span>, <span class="str">'key'</span> => <span class="str">'sk-or-...'</span>],   <span class="cmt">// https://openrouter.ai</span>
]);

<span class="cmt">// Provedores suportados: 'groq', 'gemini', 'openrouter'</span>
<span class="cmt">// Para obter as chaves gratuitas, acesse os links acima.</span>
<span class="cmt">// Sem chaves configuradas, o componente retorna status 503 ao ser chamado.</span></div>

        <div class="comp-section-title">Preview do componente</div>
        <div class="comp-preview">
            <div class="comp-preview-label">👁️ Exemplo de saída — dados simulados</div>
            <!-- Mini-preview estático mostrando a aparência do componente -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1.25rem;">
                <div style="background:var(--bg-surface);padding:.875rem;border-radius:var(--radius-md);text-align:center;border-top:3px solid var(--color-success);border:1px solid var(--border-color);">
                    <div style="font-size:1.5rem;">📈</div>
                    <div style="font-size:.6875rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;margin-top:.25rem;">Comportamento</div>
                    <div style="font-size:.8125rem;font-weight:700;color:var(--color-success);">melhorando</div>
                </div>
                <div style="background:var(--bg-surface);padding:.875rem;border-radius:var(--radius-md);text-align:center;border-top:3px solid var(--color-warning);border:1px solid var(--border-color);">
                    <div style="font-size:1.5rem;">➡️</div>
                    <div style="font-size:.6875rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;margin-top:.25rem;">Desempenho</div>
                    <div style="font-size:.8125rem;font-weight:700;color:var(--color-warning);">estável</div>
                </div>
                <div style="background:var(--bg-surface);padding:.875rem;border-radius:var(--radius-md);text-align:center;border-top:3px solid var(--color-success);border:1px solid var(--border-color);">
                    <div style="font-size:1.5rem;">✅</div>
                    <div style="font-size:.6875rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;margin-top:.25rem;">Atenção</div>
                    <div style="font-size:.8125rem;font-weight:700;color:var(--color-success);">normal</div>
                </div>
            </div>
            <div style="background:var(--color-primary-light);border-left:4px solid var(--color-primary);padding:.875rem 1rem;border-radius:var(--radius-md);margin-bottom:1rem;">
                <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;color:var(--color-primary);margin-bottom:.375rem;">Resumo Pedagógico</div>
                <div style="font-size:.875rem;line-height:1.6;color:var(--text-primary);">O aluno demonstra evolução significativa na participação e na interação com colegas. Mantém notas regulares, com leve oscilação em disciplinas exatas. Os professores apontam melhora na postura e comprometimento com as atividades.</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div>
                    <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:.5rem;">Dificuldades</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.375rem;">
                        <span style="background:rgba(239,68,68,.1);color:var(--color-danger);padding:.2rem .625rem;border-radius:var(--radius-full);font-size:.75rem;font-weight:600;">Matemática</span>
                        <span style="background:rgba(239,68,68,.1);color:var(--color-danger);padding:.2rem .625rem;border-radius:var(--radius-full);font-size:.75rem;font-weight:600;">Física</span>
                    </div>
                </div>
                <div>
                    <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:.5rem;">Pontos Fortes</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.375rem;">
                        <span style="background:rgba(16,185,129,.1);color:var(--color-success);padding:.2rem .625rem;border-radius:var(--radius-full);font-size:.75rem;font-weight:600;">Português</span>
                        <span style="background:rgba(16,185,129,.1);color:var(--color-success);padding:.2rem .625rem;border-radius:var(--radius-full);font-size:.75rem;font-weight:600;">Participação</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>


<!-- =========================================================================
     COMPONENTE 2: AtendimentoMedals
     ======================================================================= -->
<div class="card settings-card comp-card">
    <div class="settings-card-header">
        <div class="settings-card-icon">🏅</div>
        <div style="flex:1;">
            <div class="settings-card-title" style="display:flex;align-items:center;gap:.625rem;">
                AtendimentoMedals
                <span class="comp-badge comp-badge-ui">UI</span>
            </div>
            <div class="settings-card-desc">Medalhas de profissionais em atendimento ativo com o aluno</div>
        </div>
    </div>
    <div class="card-body">

        <p style="color:var(--text-secondary);font-size:.9375rem;line-height:1.7;margin:0 0 1.25rem;">
            Renderiza um conjunto de ícones compactos (medalhas) representando os profissionais que estão em atendimento
            ativo com o aluno. Medalhas do mesmo tipo de profissional são agrupadas. Ao passar o cursor, exibe um
            popover com nome e foto de cada profissional.
        </p>

        <div class="comp-info-grid">
            <div class="comp-info-item">
                <div class="comp-info-label">Arquivo do componente</div>
                <div class="comp-info-value"><code>includes/components/AtendimentoMedals.php</code></div>
            </div>
            <div class="comp-info-item">
                <div class="comp-info-label">Função</div>
                <div class="comp-info-value"><code>renderAtendimentoMedals(int, array, string)</code></div>
            </div>
            <div class="comp-info-item">
                <div class="comp-info-label">Retorno</div>
                <div class="comp-info-value">HTML string (não imprime, use <code>echo</code>)</div>
            </div>
            <div class="comp-info-item">
                <div class="comp-info-label">Dependências CSS</div>
                <div class="comp-info-value">Estilos de <code>.atendimento-medal</code> no tema global</div>
            </div>
        </div>

        <div class="comp-section-title">Assinatura</div>
        <div class="code-block"><span class="fn">renderAtendimentoMedals</span>(
    <span class="kw">int</span>    <span class="str">$alunoId</span>,       <span class="cmt">// ID do aluno (usado no data-id do wrapper)</span>
    <span class="kw">array</span>  <span class="str">$atendimentos</span>,  <span class="cmt">// Lista de atendimentos com chaves: profile, name, photo</span>
    <span class="kw">string</span> <span class="str">$position</span>      <span class="cmt">// Posição do popover: 'top' (padrão) ou 'bottom'</span>
): <span class="kw">string</span></div>

        <div class="comp-section-title">Uso</div>
        <div class="code-block"><span class="kw">require_once</span> <span class="str">__DIR__ . '/includes/components/AtendimentoMedals.php'</span>;

<span class="cmt">// $atendimentos deve ter a estrutura:</span>
<span class="str">$atendimentos</span> = [
    [<span class="str">'profile'</span> => <span class="str">'Psicólogo'</span>, <span class="str">'name'</span> => <span class="str">'Dra. Ana Lima'</span>, <span class="str">'photo'</span> => <span class="str">'assets/uploads/foto.jpg'</span>],
    [<span class="str">'profile'</span> => <span class="str">'Pedagogo'</span>,  <span class="str">'name'</span> => <span class="str">'Prof. Carlos'</span>,   <span class="str">'photo'</span> => <span class="kw">null</span>],
];

<span class="kw">echo</span> <span class="fn">renderAtendimentoMedals</span>(<span class="str">$alunoId</span>, <span class="str">$atendimentos</span>, <span class="str">'top'</span>);

<span class="cmt">// Popover na parte inferior (útil em tabelas no final da página):</span>
<span class="kw">echo</span> <span class="fn">renderAtendimentoMedals</span>(<span class="str">$alunoId</span>, <span class="str">$atendimentos</span>, <span class="str">'bottom'</span>);</div>

        <div class="comp-section-title">Tipos de profissional reconhecidos automaticamente</div>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0 1.25rem;">
            <span style="display:inline-flex;align-items:center;gap:.375rem;background:var(--bg-surface-2nd);border:1px solid var(--border-color);padding:.375rem .75rem;border-radius:var(--radius-full);font-size:.8125rem;">🧠 Psicólogo</span>
            <span style="display:inline-flex;align-items:center;gap:.375rem;background:var(--bg-surface-2nd);border:1px solid var(--border-color);padding:.375rem .75rem;border-radius:var(--radius-full);font-size:.8125rem;">🎓 Pedagogo</span>
            <span style="display:inline-flex;align-items:center;gap:.375rem;background:var(--bg-surface-2nd);border:1px solid var(--border-color);padding:.375rem .75rem;border-radius:var(--radius-full);font-size:.8125rem;">🤝 Assistente Social</span>
            <span style="display:inline-flex;align-items:center;gap:.375rem;background:var(--bg-surface-2nd);border:1px solid var(--border-color);padding:.375rem .75rem;border-radius:var(--radius-full);font-size:.8125rem;">⚖️ Coordenador</span>
            <span style="display:inline-flex;align-items:center;gap:.375rem;background:var(--bg-surface-2nd);border:1px solid var(--border-color);padding:.375rem .75rem;border-radius:var(--radius-full);font-size:.8125rem;">👤 Outros</span>
        </div>

    </div>
</div>
