/**
 * Performance System - Grade Evolution Analysis
 * Modular logic to determine student performance trends based on grades
 */

const VAPerformance = {
    // Cache de dados para re-renderização (ex: troca de abas)
    cache: {},
    /**
     * Analyze grade evolution across stages
     * @param {Array} stages - Array of stage objects { id, description, media_nota }
     * @param {Array} disciplines - Array of discipline objects { id, descricao, etapas: { [stageId]: { nota } } }
     * @returns {Object} - { trend: 'Improving'|'Stable'|'Worsening', score: number, data: [] }
     */
    analyzeEvolution: function(stages, disciplines) {
        if (!stages || !disciplines || disciplines.length === 0) {
            return { trend: 'Estável', score: 0, status: 'neutral', message: 'Sem dados para análise', averages: [], labels: [], icon: '➡️' };
        }

        // 1. Calculate average per stage across all disciplines
        const stageAverages = stages.map(stage => {
            let sumRel = 0;
            let count = 0;
            const max = parseFloat(stage.nota_maxima) || 10;
            disciplines.forEach(disc => {
                const grade = (disc.etapas && disc.etapas[stage.id]) ? disc.etapas[stage.id].nota : undefined;
                if (grade !== null && grade !== undefined) {
                    // Normalizamos para base 10 para manter a consistência dos cálculos de tendência
                    sumRel += (parseFloat(grade) / max) * 10;
                    count++;
                }
            });
            return count > 0 ? sumRel / count : null;
        });

        // 2. Filter valid averages (only stages that have grades)
        const validAverages = stageAverages.filter(avg => avg !== null);
        const validLabels = stages.filter((_, i) => stageAverages[i] !== null).map(s => s.description);

        if (validAverages.length === 0) {
            return { trend: 'Estável', score: 0, status: 'neutral', message: 'Nenhuma nota lançada', averages: [], labels: [], icon: '➡️' };
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
        if (avgDiff > 0.15) { // Threshold um pouco mais sensível
            trend = 'Melhorando';
            status = 'positive';
            icon = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>`;
        } else if (avgDiff < -0.15) {
            trend = 'Piorando';
            status = 'negative';
            icon = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>`;
        } else {
            icon = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`;
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
    renderPerformanceTrend: function(containerId, stages, disciplines, isMini = false) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container) return;
 
        const analysis = this.analyzeEvolution(stages, disciplines);
        
        const colors = {
            positive: '#3b82f6', // Azul para melhora
            negative: 'var(--color-danger)',
            neutral: 'var(--text-muted)'
        };
 
        const bgColors = {
            positive: 'rgba(59, 130, 246, 0.1)',
            negative: 'rgba(239, 68, 68, 0.1)',
            neutral: 'rgba(107, 114, 128, 0.1)'
        };
 
        let sparkline = '';
        if (analysis.averages && analysis.averages.length > 1) {
            const min = Math.min(...analysis.averages, 5);
            const max = Math.max(...analysis.averages, 10);
            const range = max - min || 1;
            const width = isMini ? 60 : 120;
            const height = isMini ? 15 : 30;
            const pts = analysis.averages.map((v, i) => {
                const x = (i / (analysis.averages.length - 1)) * width;
                const y = height - ((v - min) / range) * height;
                return `${x},${y}`;
            }).join(' ');
            
            sparkline = `
                <svg width="${width}" height="${height}" style="overflow:visible; margin-left:${isMini ? '5px' : '10px'};">
                    <polyline points="${pts}" fill="none" stroke="${colors[analysis.status]}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    ${isMini ? '' : analysis.averages.map((v, i) => {
                        const x = (i / (analysis.averages.length - 1)) * width;
                        const y = height - ((v - min) / range) * height;
                        return `<circle cx="${x}" cy="${y}" r="3" fill="${colors[analysis.status]}" />`;
                    }).join('')}
                </svg>
            `;
        }
 
        if (isMini) {
            container.innerHTML = `
                <div style="display:inline-flex; align-items:center; gap:0.25rem; font-size:0.875rem; color:${colors[analysis.status]};" title="${analysis.trend} (Análise de Notas)">
                    ${analysis.icon}
                    ${sparkline}
                </div>
            `;
        } else {
            container.innerHTML = `
                <div style="display:flex; align-items:center; gap:0.75rem; padding:0.5rem 0.75rem; background:${bgColors[analysis.status]}; border-radius:var(--radius-md); border:1px solid ${colors[analysis.status]}44;" title="${analysis.message || ''}">
                    <div style="color:${colors[analysis.status]}; display:flex; align-items:center; justify-content:center;">${analysis.icon}</div>
                    <div style="flex:1;">
                        <div style="font-size:0.6875rem; text-transform:uppercase; font-weight:700; color:var(--text-muted); line-height:1;">Desempenho</div>
                        <div style="font-size:0.875rem; font-weight:700; color:${colors[analysis.status]};">${analysis.trend}</div>
                    </div>
                    ${sparkline}
                </div>
            `;
        }
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
        const padding = 35; // Increased padding for % labels
        const chartWidth = width - (padding * 2);
        const chartHeight = height - (padding * 2);
        
        const maxVal = 100;
        const barWidth = Math.min(40, chartWidth / (analysis.averages.length || 1) * 0.6);
        const gap = (chartWidth - (barWidth * analysis.averages.length)) / (analysis.averages.length + 1);

        let svg = `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">`;
        
        // Grid lines
        [0, 50, 100].forEach(val => {
            const y = height - padding - (val / maxVal * chartHeight);
            svg += `<line x1="${padding}" y1="${y}" x2="${width - padding}" y2="${y}" stroke="var(--border-color)" stroke-dasharray="2,2" />`;
            svg += `<text x="${padding - 5}" y="${y + 4}" font-size="9" text-anchor="end" fill="var(--text-muted)">${val}%</text>`;
        });

        // Bars
        analysis.averages.forEach((avg, i) => {
            const percent = avg * 10; // Normalizado para base 100
            const x = padding + gap + i * (barWidth + gap);
            const h = (percent / maxVal) * chartHeight;
            const y = height - padding - h;
            const color = percent >= 60 ? 'var(--color-success)' : 'var(--color-danger)';
            
            svg += `
                <rect x="${x}" y="${y}" width="${barWidth}" height="${h}" fill="${color}" rx="4" opacity="0.8">
                    <animate attributeName="height" from="0" to="${h}" dur="0.5s" begin="${i * 0.1}s" fill="freeze" />
                    <animate attributeName="y" from="${height - padding}" to="${y}" dur="0.5s" begin="${i * 0.1}s" fill="freeze" />
                </rect>
                <text x="${x + barWidth / 2}" y="${y - 5}" font-size="10" font-weight="700" text-anchor="middle" fill="${color}">${percent.toFixed(0)}%</text>
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
     * Render a Radar Chart showing performance by category (Area of Knowledge)
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
        const height = 260; // Slightly shorter to fit better
        const centerX = width / 2;
        const centerY = height / 2;
        const padding = 65; // Increased padding for longer labels
        const maxRadius = Math.min(width, height) / 2 - padding;
        const numAxes = data.length;
        const angleStep = (2 * Math.PI) / (numAxes < 3 ? 3 : numAxes);
        const maxVal = 10;

        let svg = `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="overflow:visible;">`;
        
        // 1. Grid Levels (concentric polygons)
        [2.5, 5, 7.5, 10].forEach(level => {
            let gridPoints = [];
            const axesToDraw = numAxes < 3 ? 3 : numAxes;
            for (let i = 0; i < axesToDraw; i++) {
                const angle = i * angleStep - Math.PI / 2;
                const r = (level / maxVal) * maxRadius;
                gridPoints.push(`${centerX + r * Math.cos(angle)},${centerY + r * Math.sin(angle)}`);
            }
            svg += `<polygon points="${gridPoints.join(' ')}" fill="none" stroke="var(--border-color)" stroke-width="1" stroke-dasharray="2,2" opacity="0.4" />`;
            // Level labels (only on the vertical axis if it exists or first axis)
            svg += `<text x="${centerX + 4}" y="${centerY - (level / maxVal * maxRadius) + 3}" font-size="7" fill="var(--text-muted)" opacity="0.6">${level}</text>`;
        });

        // 2. Axes and Labels
        const dataPoints = [];
        data.forEach((item, i) => {
            const angle = i * angleStep - Math.PI / 2;
            const axisX = centerX + maxRadius * Math.cos(angle);
            const axisY = centerY + maxRadius * Math.sin(angle);
            
            // Axis line
            svg += `<line x1="${centerX}" y1="${centerY}" x2="${axisX}" y2="${axisY}" stroke="var(--border-color)" stroke-width="1" opacity="0.5" />`;
            
            // Label positioning logic
            const labelR = maxRadius + 15;
            const lx = centerX + labelR * Math.cos(angle);
            const ly = centerY + labelR * Math.sin(angle);
            
            let anchor = "middle";
            const cos = Math.cos(angle);
            if (cos > 0.3) anchor = "start";
            else if (cos < -0.3) anchor = "end";
            
            let vOffset = "0.3em";
            const sin = Math.sin(angle);
            if (sin > 0.8) vOffset = "1.1em";
            else if (sin < -0.8) vOffset = "-0.4em";

            // Trim labels even more to ensure they fit
            const displayCat = item.category.length > 13 ? item.category.substring(0, 11) + '..' : item.category;
            const color = item.average >= 6 ? 'var(--color-success)' : 'var(--color-danger)';

            svg += `
                <text x="${lx}" y="${ly}" dy="${vOffset}" font-size="9" font-weight="600" text-anchor="${anchor}" fill="var(--text-secondary)">${displayCat}</text>
                <text x="${lx}" y="${ly + (vOffset === '1.1em' ? 10 : (vOffset === '-0.4em' ? -10 : 10))}" font-size="9" font-weight="700" text-anchor="${anchor}" fill="${color}">${item.average.toFixed(1)}</text>
            `;

            // Calculate student point
            const dataR = (item.average / maxVal) * maxRadius;
            dataPoints.push({
                x: centerX + dataR * Math.cos(angle),
                y: centerY + dataR * Math.sin(angle),
                color: color
            });
        });

        // 3. Performance Area (Polygon)
        if (dataPoints.length > 1) {
            const pathData = dataPoints.map(p => `${p.x},${p.y}`).join(' ');
            const avg = data.reduce((acc, curr) => acc + curr.average, 0) / data.length;
            const areaFill = avg >= 6 ? 'rgba(34, 197, 94, 0.3)' : 'rgba(239, 68, 68, 0.3)';
            const borderStroke = avg >= 6 ? 'var(--color-success)' : 'var(--color-danger)';

            svg += `<polygon points="${pathData}" fill="${areaFill}" stroke="${borderStroke}" stroke-width="2" stroke-linejoin="round" />`;
        }

        // 4. Data points (dots)
        dataPoints.forEach(p => {
            svg += `<circle cx="${p.x}" cy="${p.y}" r="3" fill="${p.color}" />`;
        });

        svg += `</svg>`;
        container.innerHTML = svg;
    },

    /**
     * Fetch data and render performance trend for a student (Asynchronous)
     */
    renderTrend: async function(container, alunoId, turmaId, isMini = false) {
        const target = typeof container === 'string' ? document.getElementById(container) : container;
        if (!target) return;

        const loadingHtml = isMini ? '<span class="skeleton-loading" style="width:40px;display:inline-block;">&nbsp;</span>' : '<div class="skeleton-loading" style="height:50px;margin:0.5rem 0;border-radius:var(--radius-md);"></div>';
        target.innerHTML = loadingHtml;

        try {
            const resp = await fetch(`conselho_aluno_detalhes_ajax.php?aluno_id=${alunoId}&turma_id=${turmaId}`);
            const data = await resp.json();
            
            if (data.error || !data.etapas || data.etapas.length === 0) {
                target.innerHTML = isMini ? '<span style="font-size:0.75rem;color:var(--text-muted);">—</span>' : '<div style="font-size:0.75rem;color:var(--text-muted);padding:0.5rem;">—</div>';
                return;
            }

            this.renderPerformanceTrend(target, data.etapas, data.disciplinas, isMini);

        } catch (e) {
            console.error('Erro na renderização de tendência quantitativa:', e);
            target.innerHTML = '';
        }
    },

    /**
     * Render a Comparison Chart (Student vs Class Average)
     */
    renderComparisonChart: function(containerId, disciplines, passGrade = 0) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container || !disciplines || disciplines.length === 0) return;

        const width = container.offsetWidth || 300;
        const height = 340;
        const paddingLeft = 50;
        const paddingRight = 30;
        const paddingTop = 40;
        const paddingBottom = 70;
        const chartWidth = width - paddingLeft - paddingRight;
        const chartHeight = height - paddingTop - paddingBottom;

        // Calculate max value for Y axis
        const maxScore = Math.max(...disciplines.map(d => Math.max(parseFloat(d.soma_nota) || 0, parseFloat(d.media_turma) || 0, parseFloat(passGrade) || 0, 10)));
        const yMax = Math.ceil(maxScore / 10) * 10;

        const barWidth = Math.min(40, (chartWidth / disciplines.length) * 0.5);
        const gap = (chartWidth - (barWidth * disciplines.length)) / (disciplines.length + 1);

        let svg = `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="overflow:visible;">`;

        // 1. Grid lines and Y labels
        const gridSteps = 5;
        for (let i = 0; i <= gridSteps; i++) {
            const val = (yMax / gridSteps) * i;
            const y = height - paddingBottom - (val / yMax * chartHeight);
            svg += `<line x1="${paddingLeft}" y1="${y}" x2="${width - paddingRight}" y2="${y}" stroke="var(--border-color)" stroke-width="1" stroke-dasharray="2,2" opacity="0.3" />`;
            svg += `<text x="${paddingLeft - 8}" y="${y + 4}" font-size="10" text-anchor="end" fill="var(--text-muted)">${val.toFixed(0)}</text>`;
        }

        // 2. Reference Lines (Passing Grade & Class Average)
        // Passing Grade Line (Dashed Orange)
        if (passGrade > 0) {
            const passY = height - paddingBottom - (passGrade / yMax * chartHeight);
            svg += `
                <line x1="${paddingLeft}" y1="${passY}" x2="${width - paddingRight}" y2="${passY}" stroke="#f59e0b" stroke-width="2" stroke-dasharray="5,3" opacity="0.8" />
                <text x="${width - paddingRight + 5}" y="${passY + 4}" font-size="10" font-weight="700" fill="#d97706">Média: ${passGrade.toFixed(1)}</text>
            `;
        }

        // 3. Bars
        disciplines.forEach((d, i) => {
            const x = paddingLeft + gap + i * (barWidth + gap);
            const studentScore = parseFloat(d.soma_nota) || 0;
            const classAvg = parseFloat(d.media_turma) || 0;
            
            // Student Bar
            const studentH = (studentScore / yMax) * chartHeight;
            const studentY = height - paddingBottom - studentH;
            
            // Lógica de Cores Intuível:
            // Azul se >= Média de Aprovação
            // Vermelho se < Média de Aprovação
            const passed = studentScore >= (passGrade - 0.01);
            const barColor = passed ? '#3b82f6' : 'var(--color-danger)';
            
            svg += `
                <rect x="${x}" y="${studentY}" width="${barWidth}" height="${studentH}" fill="${barColor}" rx="4" opacity="0.9">
                    <title>${d.descricao}\nAluno: ${studentScore.toFixed(2)}\nMédia Turma: ${classAvg.toFixed(2)}\nMédia Aprovação: ${passGrade.toFixed(2)}</title>
                    <animate attributeName="height" from="0" to="${studentH}" dur="0.6s" begin="${i * 0.05}s" fill="freeze" />
                    <animate attributeName="y" from="${height - paddingBottom}" to="${studentY}" dur="0.6s" begin="${i * 0.05}s" fill="freeze" />
                </rect>
            `;

            // Class Average Marker (Solid Line/Indicator)
            const avgY = height - paddingBottom - (classAvg / yMax * chartHeight);
            svg += `
                <line x1="${x - 5}" y1="${avgY}" x2="${x + barWidth + 5}" y2="${avgY}" stroke="var(--text-primary)" stroke-width="3" stroke-linecap="round" opacity="0.6">
                    <title>Média da Turma: ${classAvg.toFixed(1)}</title>
                </line>
            `;

            // X Labels (Disciplinas) - Rotated if too many
            const labelText = d.descricao.length > 20 ? d.descricao.substring(0, 18) + '..' : d.descricao;
            svg += `
                <text x="${x + barWidth / 2}" y="${height - paddingBottom + 15}" font-size="9" font-weight="600" text-anchor="start" transform="rotate(35, ${x + barWidth / 2}, ${height - paddingBottom + 15})" fill="var(--text-secondary)">${labelText}</text>
            `;
        });

        // 4. Legend
        const legendY = height - 15;
        svg += `
            <g transform="translate(${paddingLeft}, ${legendY})">
                <rect width="12" height="12" fill="#3b82f6" rx="2" />
                <text x="18" y="10" font-size="10" fill="var(--text-muted)">Aluno (Aprovado)</text>
                
                <rect x="110" width="12" height="12" fill="var(--color-danger)" rx="2" />
                <text x="128" y="10" font-size="10" fill="var(--text-muted)">Aluno (Reprovado)</text>

                <line x1="230" y1="6" x2="250" y2="6" stroke="#f59e0b" stroke-width="2" stroke-dasharray="3,2" />
                <text x="255" y="10" font-size="10" fill="var(--text-muted)">Média Mínima</text>
                
                <line x1="330" y1="6" x2="350" y2="6" stroke="var(--text-primary)" stroke-width="3" stroke-linecap="round" opacity="0.6" />
                <text x="355" y="10" font-size="10" fill="var(--text-muted)">Média Turma</text>
            </g>
        `;

        svg += `</svg>`;
        container.innerHTML = svg;
    },

    /**
     * Render a Boxplot chart to visualize grade distribution
     */
    renderBoxPlot: function(containerId, disciplines, distribution) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container || !disciplines || !distribution) return;

        const width = container.offsetWidth || 300;
        const height = 340;
        const paddingLeft = 50;
        const paddingRight = 30;
        const paddingTop = 40;
        const paddingBottom = 70;
        const chartWidth = width - paddingLeft - paddingRight;
        const chartHeight = height - paddingTop - paddingBottom;

        // Find overall max for Y axis
        let yMax = 10;
        Object.values(distribution).forEach(grades => {
            grades.forEach(g => { if (g > yMax) yMax = g; });
        });
        yMax = Math.ceil(yMax / 10) * 10;

        const boxWidth = Math.min(30, (chartWidth / disciplines.length) * 0.4);
        const gap = (chartWidth - (boxWidth * disciplines.length)) / (disciplines.length + 1);

        let svg = `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="overflow:visible;">`;

        // 1. Grid
        const gridSteps = 5;
        for (let i = 0; i <= gridSteps; i++) {
            const val = (yMax / gridSteps) * i;
            const y = height - paddingBottom - (val / yMax * chartHeight);
            svg += `<line x1="${paddingLeft}" y1="${y}" x2="${width - paddingRight}" y2="${y}" stroke="var(--border-color)" stroke-width="1" stroke-dasharray="2,2" opacity="0.3" />`;
            svg += `<text x="${paddingLeft - 8}" y="${y + 4}" font-size="10" text-anchor="end" fill="var(--text-muted)">${val.toFixed(0)}</text>`;
        }

        // 2. Render boxes per discipline
        disciplines.forEach((d, i) => {
            const grades = (distribution[d.codigo] || []).sort((a, b) => a - b);
            if (grades.length === 0) return;

            const x = paddingLeft + gap + i * (boxWidth + gap);

            // Calculate Box Plot stats
            const q1 = this.getPercentile(grades, 25);
            const median = this.getPercentile(grades, 50);
            const q3 = this.getPercentile(grades, 75);
            const iqr = q3 - q1;
            const minAllowed = q1 - 1.5 * iqr;
            const maxAllowed = q3 + 1.5 * iqr;

            const min = Math.max(grades[0], minAllowed);
            const max = Math.min(grades[grades.length - 1], maxAllowed);
            const outliers = grades.filter(g => g < minAllowed || g > maxAllowed);

            const scaleY = val => height - paddingBottom - (val / yMax * chartHeight);

            const yMin = scaleY(min);
            const yMaxBox = scaleY(max);
            const yQ1 = scaleY(q1);
            const yQ3 = scaleY(q3);
            const yMedian = scaleY(median);

            // Whisker line (vertical)
            svg += `<line x1="${x + boxWidth / 2}" y1="${yMin}" x2="${x + boxWidth / 2}" y2="${yMaxBox}" stroke="var(--text-primary)" stroke-width="1.5" />`;
            
            // Whisker caps (horizontal)
            svg += `<line x1="${x + boxWidth * 0.2}" y1="${yMin}" x2="${x + boxWidth * 0.8}" y2="${yMin}" stroke="var(--text-primary)" stroke-width="1.5" />`;
            svg += `<line x1="${x + boxWidth * 0.2}" y1="${yMaxBox}" x2="${x + boxWidth * 0.8}" y2="${yMaxBox}" stroke="var(--text-primary)" stroke-width="1.5" />`;

            // Box (Q1 to Q3)
            svg += `
                <rect x="${x}" y="${yQ3}" width="${boxWidth}" height="${yQ1 - yQ3}" fill="#3b82f6" fill-opacity="0.3" stroke="#3b82f6" stroke-width="2">
                    <title>${d.descricao}\nQ3: ${q3.toFixed(2)}\nMediana: ${median.toFixed(2)}\nQ1: ${q1.toFixed(2)}</title>
                </rect>
            `;

            // Median line
            svg += `<line x1="${x}" y1="${yMedian}" x2="${x + boxWidth}" y2="${yMedian}" stroke="#3b82f6" stroke-width="3" />`;

            // Outliers
            outliers.forEach(g => {
                svg += `<circle cx="${x + boxWidth / 2}" cy="${scaleY(g)}" r="3" fill="var(--color-danger)" opacity="0.7">
                    <title>Outlier: ${g.toFixed(2)}</title>
                </circle>`;
            });

            // X Labels
            const labelText = d.descricao.length > 20 ? d.descricao.substring(0, 18) + '..' : d.descricao;
            svg += `
                <text x="${x + boxWidth / 2}" y="${height - paddingBottom + 15}" font-size="9" font-weight="600" text-anchor="start" transform="rotate(25, ${x + boxWidth / 2}, ${height - paddingBottom + 15})" fill="var(--text-secondary)">${labelText}</text>
            `;
        });

        svg += `</svg>`;
        container.innerHTML = svg;
    },

    /**
     * Helper to calculate percentile
     */
    getPercentile: function(data, percentile) {
        if (data.length === 0) return 0;
        const index = (percentile / 100) * (data.length - 1);
        const lower = Math.floor(index);
        const upper = Math.ceil(index);
        const weight = index - lower;
        if (upper === lower) return data[lower];
        return data[lower] * (1 - weight) + data[upper] * weight;
    }
};
