/**
 * Vértice Acadêmico — Sistema de Análise de Sentimento Reutilizável
 */

const VASentiment = {
    // --- Configurações de Vocabulário ---
    keywords: {
        criticalPositivePhrases: ['parabéns','parabens','muito bem','excelente trabalho','superou expectativas','acima da média','notas altas','notas boas','apresenta melhora'],
        strongPositiveWords: ['ótimo','otimo','excelente','maravilhoso','fantástico','fantastico','incrível','incrivel','perfeito','perfeita','exemplar','melhora','evoluiu'],
        moderatePositiveWords: ['bom','boa','legal','gostei','progresso','melhorou','cresceu','dedicado','dedicada'],
        criticalNegativePhrases: ['não consegue','não sabe','reprovou','comportamento ruim','problema','muito ruim','notas baixas','notas ruins','não executa','nao executa','não realiza','nao realiza'],
        strongNegativeWords: ['fraco','fraca','péssimo','péssima','terrível','horrível','pior','dificuldade','insuficiente','ruim','ruins','baixas']
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

        let status = { label: 'Estável', color: 'var(--color-warning)', emoji: '➡️', desc: 'Desempenho constante.' };
        if (diff > 0.5) status = { label: 'Melhorando', color: 'var(--color-success)', emoji: '📈', desc: 'Evolução positiva.' };
        else if (diff < -0.5) status = { label: 'Em Piora', color: 'var(--color-danger)', emoji: '📉', desc: 'Queda no desempenho.' };

        return { history, status, diff, avgOverall: (avgFirst + avgSecond) / 2 };
    },

    // --- Renderização de Componente de Tendência ---
    renderTrend: async function(container, alunoId, turmaId) {
        if (!container) return;
        
        // Se container for string, busca elemento
        const target = typeof container === 'string' ? document.getElementById(container) : container;
        if (!target) return;

        target.innerHTML = '<div style="text-align:center;padding:1rem;color:var(--text-muted);">⏳ Analisando tendência...</div>';

        try {
            const resp = await fetch(`/api/comments.php?aluno_id=${alunoId}&turma_id=${turmaId}`);
            const data = await resp.json();
            
            if (!data.todos_comentarios || data.todos_comentarios.length < 2) {
                target.innerHTML = '<div style="font-size:0.75rem;color:var(--text-muted);">Dados insuficientes para tendência.</div>';
                return;
            }

            const analysis = this.getHistoryAnalysis(data.todos_comentarios);
            const { status, history } = analysis;
            const maxScore = Math.max(...history.map(c => Math.abs(c.score)), 5);

            target.innerHTML = `
                <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;background:var(--bg-surface-2nd);border-radius:var(--radius-md);border:1px solid var(--border-color);">
                    <div style="font-size:1.5rem;">${status.emoji}</div>
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

        } catch (e) {
            console.error('Erro na renderização de tendência:', e);
            target.innerHTML = '';
        }
    }
};
