/**
 * Performance System - Grade Evolution Analysis
 * Modular logic to determine student performance trends based on grades
 */

const VAPerformance = {
    /**
     * Analyze grade evolution across stages
     * @param {Array} stages - Array of stage objects { id, description, media_nota }
     * @param {Array} disciplines - Array of discipline objects { id, descricao, etapas: { [stageId]: { nota } } }
     * @returns {Object} - { trend: 'Improving'|'Stable'|'Worsening', score: number, data: [] }
     */
    analyzeEvolution: function(stages, disciplines) {
        if (!stages || !disciplines || disciplines.length === 0) {
            return { trend: 'Estável', score: 0, status: 'neutral', message: 'Sem dados para análise', averages: [], labels: [] };
        }

        // 1. Calculate average per stage across all disciplines
        const stageAverages = stages.map(stage => {
            let sum = 0;
            let count = 0;
            disciplines.forEach(disc => {
                const grade = disc.etapas[stage.id]?.nota;
                if (grade !== null && grade !== undefined) {
                    sum += parseFloat(grade);
                    count++;
                }
            });
            return count > 0 ? sum / count : null;
        });

        // 2. Filter valid averages (only stages that have grades)
        const validAverages = stageAverages.filter(avg => avg !== null);
        const validLabels = stages.filter((_, i) => stageAverages[i] !== null).map(s => s.description);

        if (validAverages.length === 0) {
            return { trend: 'Estável', score: 0, status: 'neutral', message: 'Nenhuma nota lançada', averages: [], labels: [] };
        }

        if (validAverages.length < 2) {
            return { 
                trend: 'Iniciando', 
                score: 0, 
                status: 'neutral', 
                message: 'Aguardando mais etapas para análise de tendência',
                averages: validAverages,
                labels: validLabels,
                icon: '⏳'
            };
        }

        // 3. Simple Linear Regression Slope (simplified) or just compare last vs previous
        // Let's use weighted difference to give more importance to recent stages
        let totalDiff = 0;
        let diffCount = 0;
        for (let i = 1; i < validAverages.length; i++) {
            const diff = validAverages[i] - validAverages[i - 1];
            // Apply a weight: more recent changes have more impact
            const weight = i / (validAverages.length - 1);
            totalDiff += diff * weight;
            diffCount += weight;
        }

        const avgDiff = totalDiff / diffCount;
        
        let trend = 'Estável';
        let status = 'neutral';
        let icon = '➡️';
        
        if (avgDiff > 0.3) {
            trend = 'Melhorando';
            status = 'positive';
            icon = '📈';
        } else if (avgDiff < -0.3) {
            trend = 'Piorando';
            status = 'negative';
            icon = '📉';
        }

        return {
            trend,
            status,
            score: avgDiff,
            icon,
            averages: validAverages,
            labels: validLabels
        };
    },

    /**
     * Render a performance trend component into a container
     */
    renderPerformanceTrend: function(containerId, stages, disciplines) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container) return;

        const analysis = this.analyzeEvolution(stages, disciplines);
        
        const colors = {
            positive: 'var(--color-success)',
            negative: 'var(--color-danger)',
            neutral: 'var(--text-muted)'
        };

        const bgColors = {
            positive: 'rgba(34, 197, 94, 0.1)',
            negative: 'rgba(239, 68, 68, 0.1)',
            neutral: 'rgba(107, 114, 128, 0.1)'
        };

        let sparkline = '';
        if (analysis.averages && analysis.averages.length > 1) {
            const min = Math.min(...analysis.averages, 5);
            const max = Math.max(...analysis.averages, 10);
            const range = max - min || 1;
            const width = 120;
            const height = 30;
            const pts = analysis.averages.map((v, i) => {
                const x = (i / (analysis.averages.length - 1)) * width;
                const y = height - ((v - min) / range) * height;
                return `${x},${y}`;
            }).join(' ');
            
            sparkline = `
                <svg width="${width}" height="${height}" style="overflow:visible; margin-left:10px;">
                    <polyline points="${pts}" fill="none" stroke="${colors[analysis.status]}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    ${analysis.averages.map((v, i) => {
                        const x = (i / (analysis.averages.length - 1)) * width;
                        const y = height - ((v - min) / range) * height;
                        return `<circle cx="${x}" cy="${y}" r="3" fill="${colors[analysis.status]}" />`;
                    }).join('')}
                </svg>
            `;
        }

        container.innerHTML = `
            <div style="display:flex; align-items:center; gap:0.75rem; padding:0.5rem 0.75rem; background:${bgColors[analysis.status]}; border-radius:var(--radius-md); border:1px solid ${colors[analysis.status]}22;" title="${analysis.message || ''}">
                <div style="font-size:1.25rem;">${analysis.icon}</div>
                <div style="flex:1;">
                    <div style="font-size:0.6875rem; text-transform:uppercase; font-weight:700; color:var(--text-muted); line-height:1;">Desempenho</div>
                    <div style="font-size:0.875rem; font-weight:700; color:${colors[analysis.status]};">${analysis.trend}</div>
                </div>
                ${sparkline}
            </div>
        `;
    },

    /**
     * Render a more detailed performance chart (Progressive)
     */
    renderPerformanceChart: function(containerId, stages, disciplines) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container) return;

        const analysis = this.analyzeEvolution(stages, disciplines);
        if (analysis.message && !analysis.averages) {
            container.innerHTML = `<div style="text-align:center; color:var(--text-muted); font-size:.8125rem;">${analysis.message}</div>`;
            return;
        }

        // Create a simple SVG bar chart for stage averages
        const width = container.offsetWidth || 280;
        const height = 150;
        const padding = 30;
        const chartWidth = width - (padding * 2);
        const chartHeight = height - (padding * 2);
        
        const maxVal = 10;
        const barWidth = Math.min(40, chartWidth / (analysis.averages.length || 1) * 0.6);
        const gap = (chartWidth - (barWidth * analysis.averages.length)) / (analysis.averages.length + 1);

        let svg = `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">`;
        
        // Grid lines
        [0, 5, 10].forEach(val => {
            const y = height - padding - (val / maxVal * chartHeight);
            svg += `<line x1="${padding}" y1="${y}" x2="${width - padding}" y2="${y}" stroke="var(--border-color)" stroke-dasharray="2,2" />`;
            svg += `<text x="${padding - 5}" y="${y + 4}" font-size="9" text-anchor="end" fill="var(--text-muted)">${val}</text>`;
        });

        // Bars
        analysis.averages.forEach((avg, i) => {
            const x = padding + gap + i * (barWidth + gap);
            const h = (avg / maxVal) * chartHeight;
            const y = height - padding - h;
            const color = avg >= 6 ? 'var(--color-success)' : 'var(--color-danger)';
            
            svg += `
                <rect x="${x}" y="${y}" width="${barWidth}" height="${h}" fill="${color}" rx="4" opacity="0.8">
                    <animate attributeName="height" from="0" to="${h}" dur="0.5s" begin="${i * 0.1}s" fill="freeze" />
                    <animate attributeName="y" from="${height - padding}" to="${y}" dur="0.5s" begin="${i * 0.1}s" fill="freeze" />
                </rect>
                <text x="${x + barWidth / 2}" y="${y - 5}" font-size="10" font-weight="700" text-anchor="middle" fill="${color}">${avg.toFixed(1)}</text>
                <text x="${x + barWidth / 2}" y="${height - padding + 15}" font-size="9" text-anchor="middle" fill="var(--text-muted)">${analysis.labels[i].substring(0, 5)}</text>
            `;
        });

        svg += `</svg>`;
        container.innerHTML = svg;
    },

    /**
     * Group disciplines by category and calculate averages
     * @param {Array} disciplines - Array of discipline objects
     * @returns {Array} - Array of { category, average, count }
     */
    analyzeByCategory: function(disciplines) {
        if (!disciplines || disciplines.length === 0) return [];

        const categorySummary = {};
        disciplines.forEach(disc => {
            const cat = disc.categoria || 'Sem Categoria';
            if (!categorySummary[cat]) {
                categorySummary[cat] = { sum: 0, count: 0 };
            }
            
            // Use soma_nota / number of stages that have grades
            let gradeCount = 0;
            let gradeSum = 0;
            Object.values(disc.etapas).forEach(e => {
                if (e.nota !== null) {
                    gradeSum += e.nota;
                    gradeCount++;
                }
            });

            if (gradeCount > 0) {
                categorySummary[cat].sum += (gradeSum / gradeCount);
                categorySummary[cat].count++;
            }
        });

        return Object.entries(categorySummary)
            .map(([category, data]) => ({
                category,
                average: data.count > 0 ? data.sum / data.count : 0
            }))
            .sort((a, b) => b.average - a.average);
    },

    /**
     * Render a horizontal bar chart showing performance by category
     */
    renderCategoryChart: function(containerId, disciplines) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container) return;

        const data = this.analyzeByCategory(disciplines);
        if (data.length === 0) {
            container.innerHTML = `<div style="text-align:center; color:var(--text-muted); font-size:.8125rem;">Nenhum dado de categoria disponível</div>`;
            return;
        }

        const width = container.offsetWidth || 280;
        const rowHeight = 35;
        const padding = 10;
        const labelWidth = 100;
        const chartWidth = width - labelWidth - (padding * 2);
        const height = data.length * rowHeight + padding * 2;
        
        const maxVal = 10;

        let svg = `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">`;
        
        data.forEach((item, i) => {
            const y = padding + i * rowHeight;
            const barW = (item.average / maxVal) * chartWidth;
            const color = item.average >= 6 ? 'var(--color-success)' : 'var(--color-danger)';
            
            // Translate long category names
            const displayCat = item.category.length > 15 ? item.category.substring(0, 13) + '...' : item.category;

            svg += `
                <text x="${labelWidth - 10}" y="${y + 20}" font-size="11" text-anchor="end" fill="var(--text-secondary)" font-weight="500">${displayCat}</text>
                <rect x="${labelWidth}" y="${y + 8}" width="${chartWidth}" height="18" fill="var(--bg-surface-2nd)" rx="9" />
                <rect x="${labelWidth}" y="${y + 8}" width="${barW}" height="18" fill="${color}" rx="9" opacity="0.8">
                    <animate attributeName="width" from="0" to="${barW}" dur="0.6s" begin="${i * 0.1}s" fill="freeze" />
                </rect>
                <text x="${labelWidth + Math.max(25, barW - 5)}" y="${y + 21}" font-size="10" font-weight="700" text-anchor="end" fill="white">${item.average.toFixed(1)}</text>
            `;
        });

        svg += `</svg>`;
        container.innerHTML = svg;
    },

    /**
     * Fetch data and render performance trend for a student (Asynchronous)
     */
    renderTrend: async function(container, alunoId, turmaId) {
        const target = typeof container === 'string' ? document.getElementById(container) : container;
        if (!target) return;

        target.innerHTML = '<div style="font-size:0.75rem;color:var(--text-muted);padding:0.5rem;">⏳...</div>';

        try {
            const resp = await fetch(`/courses/conselho_aluno_detalhes_ajax.php?aluno_id=${alunoId}&turma_id=${turmaId}`);
            const data = await resp.json();
            
            if (data.error || !data.etapas || data.etapas.length === 0) {
                target.innerHTML = '<div style="font-size:0.75rem;color:var(--text-muted);padding:0.5rem;">—</div>';
                return;
            }

            this.renderPerformanceTrend(target, data.etapas, data.disciplinas);

        } catch (e) {
            console.error('Erro na renderização de tendência quantitativa:', e);
            target.innerHTML = '';
        }
    }
};
