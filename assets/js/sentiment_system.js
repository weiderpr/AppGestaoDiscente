/**
 * Vértice Acadêmico — Sistema de Análise de Sentimento Reutilizável
 */

const VASentiment = {
    // --- Configurações de Vocabulário ---
    keywords: {
        criticalPositivePhrases: [
            'parabéns', 'parabens', 'muito bem', 'excelente trabalho', 'superou expectativas',
            'acima da média', 'notas altas', 'notas boas', 'apresenta melhora', 'evoluiu bastante',
            'participação ativa', 'perguntas pertinentes', 'frequência exemplar'
        ],
        strongPositiveWords: [
            'ótimo', 'otimo', 'excelente', 'maravilhoso', 'fantástico', 'fantastico', 'incrível', 'incrivel',
            'perfeito', 'perfeita', 'exemplar', 'melhora', 'evoluiu', 'destaque', 'brilhante', 'parabéns'
        ],
        moderatePositiveWords: [
            'bom', 'boa', 'legal', 'gostei', 'progresso', 'melhorou', 'cresceu', 'dedicado', 'dedicada',
            'interessado', 'interessada', 'participativo', 'participativa', 'atento', 'atenta', 
            'pertinente', 'pertinentes', 'esforçado', 'esforçada', 'pontual', 'comprometido', 'comprometida'
        ],
        criticalNegativePhrases: [
            'não consegue', 'não sabe', 'reprovou', 'comportamento ruim', 'problema grave', 'muito ruim',
            'notas baixas', 'notas ruins', 'não executa', 'nao executa', 'não realiza', 'nao realiza',
            'não fez', 'nao fez', 'pouco interesse', 'baixo rendimento', 'conversa bastante',
            'conversando muito', 'distraído com', 'distraída com', 'zero interesse', 'abaixo da média',
            'não apresentou', 'nao apresentou', 'não trouxe', 'nao trouxe', 'caderno incompleto',
            'não está interessado', 'nao esta interessado', 'não está interessada', 'nao esta interessada',
            'não demonstrou', 'nao demonstrou', 'pouco comprometimento', 'sem comprometimento',
            'não entendeu', 'nao entendeu', 'falta de interesse'
        ],
        strongNegativeWords: [
            'fraco', 'fraca', 'péssimo', 'péssima', 'terrível', 'horrível', 'pior', 'dificuldade',
            'dificuldades', 'insuficiente', 'ruim', 'ruins', 'baixas', 'baixo', 'baixa', 'atrasos',
            'dorme', 'dormindo', 'desinteressado', 'desinteressada', 'desinteresse', 'indisciplina'
        ],
        moderateNegativeWords: [
            'falta', 'faltou', 'conversa', 'conversando', 'disperso', 'dispersa', 'distraído', 
            'distraída', 'atraso', 'desatento', 'desatenta', 'lento', 'lenta', 'incompleto', 'incompleta'
        ]
    },

    // --- Motor de Análise ---
    analyzeText: function(htmlContent) {
        if (!htmlContent) return 0;
        const rawText = htmlContent.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ');
        const lowerText = rawText.toLowerCase();
        const wordList = lowerText.match(/\b[a-záàâãéèêíìîóòôõúùûç]+\b/g) || [];
        const wordSet = new Set(wordList);
        
        let score = 0;
        this.keywords.criticalPositivePhrases.forEach(p => { if (lowerText.includes(p)) score += 5; });
        this.keywords.strongPositiveWords.forEach(w => { if (wordSet.has(w)) score += 3; });
        this.keywords.moderatePositiveWords.forEach(w => { if (wordSet.has(w)) score += 2; });
        this.keywords.criticalNegativePhrases.forEach(p => { if (lowerText.includes(p)) score -= 5; });
        this.keywords.strongNegativeWords.forEach(w => { if (wordSet.has(w)) score -= 3; });
        this.keywords.moderateNegativeWords.forEach(w => { if (wordSet.has(w)) score -= 2; });
        
        return score;
    },

    // --- Análise de Histórico ---
    getHistoryAnalysis: function(comments) {
        if (!comments || comments.length === 0) return null;

        const history = comments.map(c => ({
            date: new Date(c.created_at),
            score: this.analyzeText(c.conteudo)
        })).sort((a, b) => a.date - b.date);

        const midpoint = Math.floor(history.length / 2);
        const firstHalf = history.slice(0, midpoint);
        const secondHalf = history.slice(midpoint);
        
        const avgFirst = firstHalf.length ? firstHalf.reduce((sum, c) => sum + c.score, 0) / firstHalf.length : 0;
        const avgSecond = secondHalf.length ? secondHalf.reduce((sum, c) => sum + c.score, 0) / secondHalf.length : 0;
        const diff = avgSecond - avgFirst;

        const icons = {
            positive: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>`,
            negative: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>`,
            neutral: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`
        };

        let status = { label: 'Estável', color: 'var(--text-muted)', icon: icons.neutral, emoji: '➡️', desc: 'Desempenho constante.' };
        if (diff > 0.5) status = { label: 'Melhorando', color: '#3b82f6', icon: icons.positive, emoji: '📈', desc: 'Evolução positiva.' };
        else if (diff < -0.5) status = { label: 'Em Piora', color: 'var(--color-danger)', icon: icons.negative, emoji: '📉', desc: 'Queda no desempenho.' };

        return { history, status, diff, avgOverall: (avgFirst + avgSecond) / 2 };
    },

    // --- Renderização de Componente de Tendência ---
    renderTrend: async function(container, alunoId, turmaId, isMini = false) {
        if (!container) return;
        
        // Se container for string, busca elemento
        const target = typeof container === 'string' ? document.getElementById(container) : container;
        if (!target) return;

        const loadingHtml = isMini ? '<span class="skeleton-loading" style="width:20px;display:inline-block;">&nbsp;</span>' : '<div class="skeleton-loading" style="height:40px;margin:0.5rem 0;border-radius:var(--radius-md);"></div>';
        target.innerHTML = loadingHtml;

        try {
            const resp = await fetch(`/api/comments.php?aluno_id=${alunoId}&turma_id=${turmaId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!resp.ok) {
                const errText = await resp.text().catch(() => "Response error");
                console.warn(`Sentiment API Error (${resp.status}):`, errText);
                target.innerHTML = '<div style="font-size:0.75rem;color:var(--text-muted);">—</div>';
                return;
            }

            const data = await resp.json();
            
            if (!data.todos_comentarios || data.todos_comentarios.length < 2) {
                target.innerHTML = '<div style="font-size:0.75rem;color:var(--text-muted);">—</div>';
                return;
            }

            const analysis = this.getHistoryAnalysis(data.todos_comentarios);
            const { status, history } = analysis;
            const maxScore = Math.max(...history.map(c => Math.abs(c.score)), 5);

            if (isMini) {
                target.innerHTML = `
                    <div style="display:inline-flex;align-items:center;gap:0.375rem;color:${status.color};" title="${status.label} (Análise de Comentários)">
                        <div style="display:flex;align-items:center;">${status.icon}</div>
                        <div style="display:flex;align-items:flex-end;gap:1.5px;height:14px;width:40px;">
                            ${history.slice(-8).map(c => {
                                const h = Math.abs((c.score / maxScore) * 100);
                                const color = c.score >= 1 ? 'var(--color-success)' : (c.score <= -1 ? 'var(--color-danger)' : 'var(--color-warning)');
                                return `<div style="flex:1;background:${color};height:${Math.max(20, h)}%;border-radius:1px;opacity:0.6;"></div>`;
                            }).join('')}
                        </div>
                    </div>
                `;
            } else {
                target.innerHTML = `
                    <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;background:var(--bg-surface-2nd);border-radius:var(--radius-md);border:1px solid var(--border-color);">
                        <div style="color:${status.color};display:flex;align-items:center;justify-content:center;">${status.icon}</div>
                        <div style="flex:1;">
                            <div style="font-size:0.625rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);letter-spacing:0.05em;">Tendência</div>
                            <div style="font-size:0.875rem;font-weight:700;color:${status.color};">${status.label}</div>
                        </div>
                        <!-- Mini Sparkline -->
                        <div style="display:flex;align-items:flex-end;gap:2px;height:24px;width:60px;">
                            ${history.slice(-10).map(c => {
                                const h = Math.abs((c.score / maxScore) * 100);
                                const color = c.score >= 1 ? 'var(--color-success)' : (c.score <= -1 ? 'var(--color-danger)' : 'var(--color-warning)');
                                return `<div style="flex:1;background:${color};height:${Math.max(4, h)}%;border-radius:1px;opacity:0.6;"></div>`;
                            }).join('')}
                        </div>
                    </div>
                `;
            }

        } catch (e) {
            console.error('Erro na renderização de tendência:', e);
            target.innerHTML = '';
        }
    }
};

window.VASentiment = VASentiment;
