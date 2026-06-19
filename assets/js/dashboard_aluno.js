/**
 * Vértice Acadêmico — Dashboard do Aluno
 * Módulo autônomo: busca, carregamento progressivo e renderização.
 */
const vaDashboardAluno = (function () {

    // ── Estado ────────────────────────────────────────────────────────────────

    let state = {
        alunoId:  null,
        turmaId:  null,
        turmas:   [],
        searchTimer: null,
        chartInstances: {},
    };

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    function init() {
        _initSearch();

        // Se a URL já tiver aluno_id, carrega automaticamente
        const params = new URLSearchParams(window.location.search);
        const aid = parseInt(params.get('aluno_id') || '0', 10);
        if (aid) {
            state.alunoId = aid;
            _loadHero(aid);
        }
    }

    // ── Busca ─────────────────────────────────────────────────────────────────

    function _initSearch() {
        const input    = document.getElementById('daSearchInput');
        const dropdown = document.getElementById('daSearchDropdown');
        if (!input) return;

        input.addEventListener('input', function () {
            clearTimeout(state.searchTimer);
            const q = this.value.trim();
            if (q.length < 2) { _closeDropdown(); return; }

            this.classList.add('loading');
            state.searchTimer = setTimeout(() => _fetchSearch(q), 300);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { _closeDropdown(); this.blur(); }
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.da-search-box')) _closeDropdown();
        });
    }

    function _fetchSearch(q) {
        const input = document.getElementById('daSearchInput');
        fetch(`/api/students.php?action=search&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(results => {
                input && input.classList.remove('loading');
                _renderSearchResults(results.filter(r => r.type === 'aluno'));
            })
            .catch(() => { input && input.classList.remove('loading'); });
    }

    function _renderSearchResults(items) {
        const dropdown = document.getElementById('daSearchDropdown');
        if (!dropdown) return;

        if (!items.length) {
            dropdown.innerHTML = '<div class="da-search-empty">Nenhum aluno encontrado</div>';
            dropdown.classList.add('open');
            return;
        }

        dropdown.innerHTML = items.map(a => {
            const photoEl = a.foto
                ? `<img class="da-search-result-photo" src="/${a.foto}" alt="" loading="lazy">`
                : `<div class="da-search-result-avatar">${_initial(a.name)}</div>`;

            return `<div class="da-search-result" tabindex="0"
                        data-id="${a.id}" data-turma="${a.turma_id || ''}"
                        onclick="vaDashboardAluno.selectAluno(${a.id}, ${a.turma_id || 0})"
                        onkeydown="if(event.key==='Enter')vaDashboardAluno.selectAluno(${a.id}, ${a.turma_id || 0})">
                    ${photoEl}
                    <div>
                        <div class="da-search-result-name">${_esc(a.name)}</div>
                        <div class="da-search-result-sub">${_esc(a.subtext || '')} &bull; Mat: ${_esc(a.matricula || '')}</div>
                    </div>
                </div>`;
        }).join('');

        dropdown.classList.add('open');
    }

    function _closeDropdown() {
        const d = document.getElementById('daSearchDropdown');
        if (d) d.classList.remove('open');
    }

    // ── Selecionar aluno ──────────────────────────────────────────────────────

    function selectAluno(alunoId, turmaId) {
        state.alunoId = alunoId;
        _closeDropdown();
        const input = document.getElementById('daSearchInput');
        if (input) input.value = '';

        // Atualiza URL sem recarregar
        const url = new URL(window.location.href);
        url.searchParams.set('aluno_id', alunoId);
        window.history.pushState({}, '', url.toString());

        _loadHero(alunoId, turmaId || null);
    }

    // ── Hero ──────────────────────────────────────────────────────────────────

    function _loadHero(alunoId, defaultTurmaId) {
        const container = document.getElementById('daDashboard');
        if (!container) return;

        container.style.display = 'block';
        document.getElementById('daEmptyState').style.display = 'none';

        // Skeleton do hero
        document.getElementById('daHero').innerHTML = _skeletonHero();
        _showCardSkeletons();

        fetch(`/api/dashboard_aluno.php?action=hero&aluno_id=${alunoId}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) { _showError(data.error); return; }
                _renderHero(data, defaultTurmaId);
            })
            .catch(() => _showError('Erro ao carregar dados do aluno.'));
    }

    function _renderHero(data, defaultTurmaId) {
        const { aluno, turmas, badges } = data;
        state.turmas = turmas;

        // Define turma ativa
        const activeTurmaId = defaultTurmaId || (turmas[0] ? turmas[0].id : null);
        state.turmaId = activeTurmaId;

        const photoEl = aluno.photo
            ? `<img class="da-hero-photo" src="/${_esc(aluno.photo)}" alt="Foto de ${_esc(aluno.nome)}">`
            : `<div class="da-hero-avatar">${_initial(aluno.nome)}</div>`;

        const turmaTabs = turmas.map(t =>
            `<button class="da-turma-tab ${t.id == activeTurmaId ? 'active' : ''}"
                     data-turma-id="${t.id}"
                     onclick="vaDashboardAluno.changeTurma(${t.id})"
                     title="${_esc(t.curso_nome || '')}">${_esc(t.nome)}</button>`
        ).join('');

        // Curso da turma ativa
        const turmaAtiva = turmas.find(function(t){ return t.id == activeTurmaId; });
        const cursoLabel = turmaAtiva && turmaAtiva.curso_nome
            ? `<span style="color:var(--text-muted);font-size:.8125rem;">${_esc(turmaAtiva.curso_nome)}</span>`
            : '';

        const alertBadges = [];
        if (badges.sancoes_ativas > 0)
            alertBadges.push(`<span class="da-badge da-badge-danger">⚖️ ${badges.sancoes_ativas} Sanç${badges.sancoes_ativas === 1 ? 'ão' : 'ões'}</span>`);
        if (badges.encaminhamentos_pendentes > 0)
            alertBadges.push(`<span class="da-badge da-badge-warning">📋 ${badges.encaminhamentos_pendentes} Encam.</span>`);
        if (badges.atendimentos_ativos > 0)
            alertBadges.push(`<span class="da-badge da-badge-info">📝 ${badges.atendimentos_ativos} Atend.</span>`);
        if (badges.segunda_chamada_pendente > 0)
            alertBadges.push(`<span class="da-badge da-badge-warning">📄 ${badges.segunda_chamada_pendente} 2ª Chamada</span>`);
        if (!alertBadges.length)
            alertBadges.push(`<span class="da-badge da-badge-success">✓ Sem pendências</span>`);

        document.getElementById('daHero').innerHTML = `
            <div class="da-hero">
                ${photoEl}
                <div class="da-hero-body">
                    <h1 class="da-hero-name">${_esc(aluno.nome)}</h1>
                    <div class="da-hero-meta">
                        Matrícula: ${_esc(aluno.matricula || '—')}
                        ${aluno.email    ? ' &bull; ' + _esc(aluno.email)    : ''}
                        ${aluno.telefone ? ' &bull; ' + _esc(aluno.telefone) : ''}
                    </div>
                    ${cursoLabel}
                    <div class="da-hero-turmas" style="margin-top:.5rem">${turmaTabs}</div>
                </div>
                <div class="da-hero-badges">${alertBadges.join('')}</div>
            </div>
        `;

        // Carrega todos os cards com a turma ativa
        _loadAllCards(state.alunoId, activeTurmaId);
    }

    function changeTurma(turmaId) {
        state.turmaId = turmaId;
        document.querySelectorAll('.da-turma-tab').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.turmaId, 10) === turmaId);
        });

        // Atualiza label do curso conforme turma selecionada
        const turmaAtiva = state.turmas.find(function(t){ return t.id === turmaId; });
        const cursoEl = document.querySelector('.da-hero-body span[style]');
        if (cursoEl && turmaAtiva && turmaAtiva.curso_nome) cursoEl.textContent = turmaAtiva.curso_nome;

        // Recarrega os cards que dependem de turma
        _loadSection('performance', state.alunoId, turmaId);
        _loadSection('conselho',    state.alunoId, turmaId);
        _loadSection('ai',          state.alunoId, turmaId);
    }

    // ── Cards ─────────────────────────────────────────────────────────────────

    function _loadAllCards(alunoId, turmaId) {
        _loadSection('performance',    alunoId, turmaId);
        _loadSection('atendimentos',   alunoId, turmaId);
        _loadSection('conselho',       alunoId, turmaId);
        _loadSection('ai',             alunoId, turmaId);
        _loadSection('comentarios',    alunoId, turmaId);
        _loadSection('sancoes',        alunoId, turmaId);
        _loadSection('segunda_chamada',alunoId, turmaId);
    }

    function _loadSection(section, alunoId, turmaId) {
        const el = document.getElementById('daCard_' + section);
        if (!el) return;

        const bodyEl = el.querySelector('.da-card-body');
        if (!bodyEl) return;

        bodyEl.innerHTML = _skeletonCard();

        let url = `/api/dashboard_aluno.php?action=${section}&aluno_id=${alunoId}`;
        if (turmaId) url += `&turma_id=${turmaId}`;

        // AI usa componente próprio via include do PHP, não via API
        if (section === 'ai') {
            _loadAISection(alunoId, turmaId, bodyEl);
            return;
        }

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    bodyEl.innerHTML = `<div class="da-card-empty"><div class="da-card-empty-icon">⚠️</div>${_esc(data.error)}</div>`;
                    return;
                }
                _renderSection(section, data, bodyEl, alunoId, turmaId);
            })
            .catch(() => {
                bodyEl.innerHTML = '<div class="da-card-empty"><div class="da-card-empty-icon">⚠️</div>Erro ao carregar dados.</div>';
            });
    }

    function _renderSection(section, data, el, alunoId, turmaId) {
        switch (section) {
            case 'performance':   _renderPerformance(data, el);    break;
            case 'atendimentos':  _renderAtendimentos(data, el);   break;
            case 'conselho':      _renderConselho(data, el);       break;
            case 'comentarios':   _renderComentarios(data, el);    break;
            case 'sancoes':       _renderSancoes(data, el);        break;
            case 'segunda_chamada': _renderSegundaChamada(data, el); break;
        }
    }

    // ── Análise IA ────────────────────────────────────────────────────────────

    function _loadAISection(alunoId, turmaId, bodyEl) {
        if (!turmaId) {
            bodyEl.innerHTML = '<div class="da-card-empty"><div class="da-card-empty-icon">ℹ️</div>Selecione uma turma para ver a análise.</div>';
            return;
        }

        if (typeof vaAIAnalysis === 'undefined') {
            bodyEl.innerHTML = '<div class="da-card-empty"><div class="da-card-empty-icon">⚠️</div>Módulo de IA não disponível nesta página.</div>';
            return;
        }

        // vaAIAnalysis.load() precisa da estrutura HTML completa do widget
        const uid = `ai-widget-${alunoId}-${turmaId}`;
        bodyEl.innerHTML = `
            <div class="va-ai-analysis-widget" id="${uid}"
                 data-aluno-id="${alunoId}" data-turma-id="${turmaId}">
                <div class="va-ai-state va-ai-loading">
                    <div style="font-size:2rem;margin-bottom:.5rem;">🤖</div>
                    <div style="font-size:.875rem;color:var(--text-muted);">Carregando análise pedagógica...</div>
                </div>
                <div class="va-ai-state va-ai-empty" style="display:none">
                    <div style="font-size:2.5rem;margin-bottom:.75rem;">📝</div>
                    <div style="font-size:.9375rem;font-weight:600;color:var(--text-primary);margin-bottom:.25rem;">Sem dados suficientes</div>
                    <div style="font-size:.8125rem;color:var(--text-muted);">Adicione comentários ou notas para gerar a análise.</div>
                </div>
                <div class="va-ai-state va-ai-error" style="display:none">
                    <div style="font-size:2rem;margin-bottom:.5rem;">⚠️</div>
                    <div class="va-ai-error-msg" style="font-size:.875rem;color:var(--color-danger);margin-bottom:.75rem;"></div>
                    <button type="button" class="btn btn-secondary btn-sm"
                            onclick="vaAIAnalysis.load('${uid}')">Tentar novamente</button>
                </div>
                <div class="va-ai-state va-ai-content" style="display:none"></div>
            </div>
        `;

        vaAIAnalysis.load(uid);
    }

    // ── Render: Performance ───────────────────────────────────────────────────

    function _renderPerformance(data, el) {
        const { etapas, disciplinas } = data;

        if (!disciplinas.length) {
            el.innerHTML = '<div class="da-card-empty"><div class="da-card-empty-icon">📊</div>Nenhuma disciplina encontrada para esta turma.</div>';
            return;
        }

        // Cabeçalho da tabela
        const etapaHeaders = etapas.map(e =>
            `<th title="${_esc(e.description)}">${_esc(_abrev(e.description))}</th>`
        ).join('');

        const rows = disciplinas.map(d => {
            const cells = d.etapas.map(et => {
                const nota = et.nota;
                if (nota === null) return `<td><span class="da-nota-empty">—</span></td>`;

                const mediaAprov = et.media_aprov || 5;
                const cls = nota >= mediaAprov ? 'da-nota-ok'
                          : nota >= (mediaAprov * 0.7) ? 'da-nota-warn'
                          : 'da-nota-danger';

                const faltasEl = et.faltas > 0
                    ? `<br><span class="da-faltas-chip ${et.faltas >= 15 ? 'over' : ''}">${et.faltas}f</span>`
                    : '';

                return `<td><span class="da-nota-chip ${cls}">${nota.toFixed(1)}</span>${faltasEl}</td>`;
            }).join('');

            const mediaCls = d.media === null ? '' : d.media >= 5 ? 'da-nota-ok' : d.media >= 3.5 ? 'da-nota-warn' : 'da-nota-danger';

            return `<tr>
                <td class="col-disc" title="${_esc(d.descricao)}">${_esc(d.descricao)}</td>
                ${cells}
                <td><span class="da-nota-chip ${mediaCls}">${d.media !== null ? d.media.toFixed(1) : '—'}</span></td>
                <td><span class="da-faltas-chip ${d.total_faltas >= 15 ? 'over' : ''}">${d.total_faltas}</span></td>
            </tr>`;
        }).join('');

        el.innerHTML = `
            ${_renderGradesChart(etapas, disciplinas)}
            <div class="da-notas-table-wrap">
                <table class="da-notas-table">
                    <thead>
                        <tr>
                            <th class="col-disc">Disciplina</th>
                            ${etapaHeaders}
                            <th>Média</th>
                            <th title="Total de faltas">Faltas</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    function _renderGradesChart(etapas, disciplinas) {
        const discComNota = disciplinas.filter(function(d) {
            return d.etapas.some(function(e) { return e.nota !== null; });
        });
        if (!discComNota.length) return '';

        // Extrai nota_maxima e media_nota com fallback seguro (evita string "0" truthy)
        const rawNotaMax    = parseFloat(etapas[0]?.nota_maxima);
        const rawMediaAprov = parseFloat(etapas[0]?.media_nota);
        const mediaAprov = rawMediaAprov > 0 ? rawMediaAprov : 5;

        // Se nota_maxima não estiver configurada, infere a partir das notas reais
        let notaMax;
        if (rawNotaMax > 0) {
            notaMax = rawNotaMax;
        } else {
            const todasNotas = discComNota.flatMap(function(d) {
                return d.etapas.filter(function(e){ return e.nota !== null; }).map(function(e){ return e.nota; });
            });
            const maxObservado = todasNotas.length ? Math.max.apply(null, todasNotas) : 10;
            // Arredonda para cima no múltiplo de 10 mais próximo (ex: 18 → 20, 9 → 10)
            notaMax = Math.ceil(maxObservado / 10) * 10;
        }

        // Etapas que realmente têm pelo menos uma nota
        const etapasAtivas = etapas.filter(function(e) {
            return discComNota.some(function(d) {
                var et = d.etapas.find(function(x) { return x.etapa_id === e.id; });
                return et && et.nota !== null;
            });
        });

        // Dimensões
        const ROW_H  = 20;   // altura de cada barra
        const GAP    = 5;    // espaço entre barras
        const LABEL_W= 140;  // largura da coluna de nomes
        const BAR_W  = 340;  // largura da área de barras
        const VAL_W  = 36;   // largura da coluna de valor
        const PAD_V  = 16;   // padding vertical (espaço para label da linha de referência)
        const W      = LABEL_W + BAR_W + VAL_W + 8;
        const rows   = discComNota.length;
        const H      = PAD_V + rows * (ROW_H + GAP) + PAD_V + 20; // +20 legenda etapas

        // Cores por etapa
        const ETAPA_COLORS = ['#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6'];

        // Linha de referência (mínimo de aprovação) — texto posicionado dentro do viewBox
        const refX = LABEL_W + (mediaAprov / notaMax) * BAR_W;
        const refLine = `
            <line x1="${refX}" y1="${PAD_V - 2}" x2="${refX}" y2="${PAD_V + rows * (ROW_H + GAP)}"
                  stroke="#ef444477" stroke-width="1.5" stroke-dasharray="4,3"/>
            <text x="${refX}" y="${PAD_V - 4}" font-size="8" text-anchor="middle"
                  fill="#ef4444" font-family="inherit">mín ${mediaAprov}</text>`;

        // Barras por disciplina
        const barEls = discComNota.map(function(d, rowIdx) {
            const y    = PAD_V + rowIdx * (ROW_H + GAP);
            const cy   = y + ROW_H / 2;

            // Média da disciplina (média das notas disponíveis)
            const notasValidas = d.etapas.filter(function(e){ return e.nota !== null; });
            const mediaDisc    = notasValidas.length
                ? notasValidas.reduce(function(s, e){ return s + e.nota; }, 0) / notasValidas.length
                : null;

            // Cor da barra baseada na média
            var barColor, barOpacity;
            if (mediaDisc === null) {
                barColor = 'var(--border-color)'; barOpacity = 1;
            } else if (mediaDisc >= mediaAprov) {
                barColor = '#10b981'; barOpacity = 0.25;    // verde
            } else if (mediaDisc >= mediaAprov * 0.75) {
                barColor = '#f59e0b'; barOpacity = 0.25;    // amarelo
            } else {
                barColor = '#ef4444'; barOpacity = 0.25;    // vermelho
            }

            const barLen = mediaDisc !== null ? (mediaDisc / notaMax) * BAR_W : 0;

            // Fundo da trilha
            const track = `<rect x="${LABEL_W}" y="${y + 6}" width="${BAR_W}" height="${ROW_H - 12}"
                                  rx="4" fill="var(--bg-surface-2nd)"/>`;

            // Barra principal (média)
            const bar = mediaDisc !== null
                ? `<rect x="${LABEL_W}" y="${y + 6}" width="${barLen}" height="${ROW_H - 12}"
                          rx="4" fill="${barColor}" fill-opacity="${barOpacity + 0.5}"/>`
                : '';

            // Marcadores por etapa
            const markers = etapasAtivas.map(function(e, eIdx) {
                var et = d.etapas.find(function(x){ return x.etapa_id === e.id; });
                if (!et || et.nota === null) return '';
                var mx    = LABEL_W + (et.nota / notaMax) * BAR_W;
                var color = ETAPA_COLORS[eIdx % ETAPA_COLORS.length];
                return `<circle cx="${mx}" cy="${cy}" r="4" fill="${color}" stroke="#fff" stroke-width="1.5"/>
                        <text x="${mx}" y="${cy + 3}" font-size="6" text-anchor="middle"
                              fill="#fff" font-family="inherit" font-weight="700">${et.nota.toFixed(1)}</text>`;
            }).join('');

            // Label do nome (truncado)
            const nome   = d.descricao.length > 22 ? d.descricao.slice(0, 20) + '…' : d.descricao;
            const label  = `<text x="${LABEL_W - 6}" y="${cy + 4}" font-size="10" text-anchor="end"
                                  fill="var(--text-secondary)" font-family="inherit"
                                  title="${_esc(d.descricao)}">${_esc(nome)}</text>`;

            // Valor da média à direita
            var mediaColor = '#059669';
            if (mediaDisc === null) mediaColor = 'var(--text-muted)';
            else if (mediaDisc < mediaAprov)        mediaColor = '#dc2626';
            else if (mediaDisc < mediaAprov * 1.2)  mediaColor = '#d97706';

            const valLabel = `<text x="${LABEL_W + BAR_W + 6}" y="${cy + 4}" font-size="10" font-weight="700"
                                    fill="${mediaColor}" font-family="inherit">
                                ${mediaDisc !== null ? mediaDisc.toFixed(1) : '—'}
                              </text>`;

            return track + bar + markers + label + valLabel;
        }).join('');

        // Legenda das etapas
        const legendY   = PAD_V + rows * (ROW_H + GAP) + 14;
        const legendItems = etapasAtivas.map(function(e, i) {
            var lx = LABEL_W + i * 80;
            var color = ETAPA_COLORS[i % ETAPA_COLORS.length];
            return `<circle cx="${lx + 5}" cy="${legendY}" r="4" fill="${color}"/>
                    <text x="${lx + 12}" y="${legendY + 4}" font-size="8" fill="var(--text-muted)"
                          font-family="inherit">${_esc(_abrev(e.description))}</text>`;
        }).join('');

        return `<div class="da-chart-wrap" style="margin-bottom:.75rem">
            <svg viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg"
                 aria-label="Notas por disciplina">
                ${refLine}
                ${barEls}
                ${legendItems}
            </svg>
        </div>`;
    }

    // ── Render: Atendimentos ──────────────────────────────────────────────────

    function _renderAtendimentos(data, el) {
        const { atendimentos } = data;

        let html = '<div class="da-timeline">';

        if (!atendimentos.length) {
            el.innerHTML = '<div class="da-card-empty"><div class="da-card-empty-icon">📝</div>Nenhum atendimento registrado.</div>';
            return;
        }

        html += atendimentos.map(a => {
            const statusMap = {
                'Aberto':         ['da-status-aberto',    '●'],
                'Em Atendimento': ['da-status-atendendo', '●'],
                'Finalizado':     ['da-status-finalizado','●'],
            };
            const _statusEntry = statusMap[a.status] || ['da-status-arquivado', '○'];
            const [statusCls, dot] = _statusEntry;

            const textoPublico = a.texto_publico ? a.texto_publico.trim() : '';
            const textoPriv    = a.texto_profissional ? a.texto_profissional.trim() : '';

            const textosHtml = [
                textoPublico ? `<div class="da-timeline-text">${_esc(textoPublico)}</div>` : '',
                textoPriv
                    ? `<div class="da-timeline-private">🔒 Registro profissional (visível apenas para você)</div>
                       <div class="da-timeline-text" style="border-left:3px solid var(--color-primary);background:rgba(99,102,241,.06)">${_esc(textoPriv)}</div>`
                    : '',
            ].filter(Boolean).join('');

            const autorAvatar = a.autor_photo
                ? `<img class="da-inline-avatar" src="/uploads/photos/${_esc(a.autor_photo)}" alt="">`
                : `<span class="da-inline-initial">${_initial(a.autor_nome)}</span>`;

            return `<div class="da-timeline-item">
                <div class="da-timeline-header">
                    <span class="da-timeline-author">
                        ${autorAvatar}${_esc(a.autor_nome)}
                        <span class="da-atend-status ${statusCls}">${dot} ${_esc(a.status)}</span>
                    </span>
                    <span class="da-timeline-date">${_formatDate(a.created_at)}</span>
                </div>
                <div class="da-timeline-perfil">${_esc(a.autor_perfil)}</div>
                ${a.titulo ? `<strong style="font-size:.8125rem;color:var(--text-primary)">${_esc(a.titulo)}</strong>` : ''}
                ${textosHtml || '<div class="da-timeline-text" style="color:var(--text-muted);font-style:italic">Sem descrição</div>'}
            </div>`;
        }).join('');

        html += '</div>';
        el.innerHTML = html;
    }

    // ── Render: Conselho ──────────────────────────────────────────────────────

    function _renderConselho(data, el) {
        const { encaminhamentos, comentarios, registros, conselho_id } = data;

        if (!conselho_id) {
            el.innerHTML = '<div class="da-card-empty"><div class="da-card-empty-icon">🏛</div>Nenhum conselho encontrado para esta turma.</div>';
            return;
        }

        const card = el.closest('.da-card');

        // Subtabs
        var _oldTabs = card.querySelector('.da-subtabs');
        if (_oldTabs) _oldTabs.remove();
        const tabBar = document.createElement('div');
        tabBar.className = 'da-subtabs';
        tabBar.innerHTML = `
            <button class="da-subtab active" onclick="vaDashboardAluno._switchSubtab(this,'daConsCons${conselho_id}')">Encaminhamentos (${encaminhamentos.length})</button>
            <button class="da-subtab" onclick="vaDashboardAluno._switchSubtab(this,'daConsPost${conselho_id}')">Post-its (${comentarios.length})</button>
            <button class="da-subtab" onclick="vaDashboardAluno._switchSubtab(this,'daConsReg${conselho_id}')">Anotações (${registros.length})</button>
        `;
        card.querySelector('.da-card-body').before(tabBar);

        // Pane: Encaminhamentos
        const encHtml = encaminhamentos.length ? encaminhamentos.map(e => {
            const stMap = {
                'Pendente':    'da-enc-status-pendente',
                'Em Andamento':'da-enc-status-andamento',
                'Concluído':   'da-enc-status-concluido',
            };
            return `<div class="da-enc-item">
                <span class="da-enc-setor">${_esc(e.setor_tipo)}</span>
                <div class="da-enc-body">
                    <div class="da-enc-texto">${_esc(e.texto || '—')}</div>
                    <div class="da-enc-meta">${e.responsaveis ? 'Responsável: ' + _esc(e.responsaveis) : ''}
                        ${e.data_expectativa ? ' &bull; Prazo: ' + _formatDate(e.data_expectativa) : ''}
                    </div>
                </div>
                <span class="da-enc-status ${stMap[e.status] || ''}">${_esc(e.status)}</span>
            </div>`;
        }).join('') : '<div class="da-card-empty" style="padding:1rem 0"><div class="da-card-empty-icon">✓</div>Sem encaminhamentos</div>';

        // Pane: Post-its (comentários do conselho)
        const postHtml = comentarios.length ? comentarios.map(c =>
            `<div class="da-postit">
                ${_esc(c.comentario)}
                <div class="da-postit-meta">${_esc(c.autor_nome)} &bull; ${_formatDate(c.created_at)}</div>
            </div>`
        ).join('') : '<div class="da-card-empty" style="padding:1rem 0"><div class="da-card-empty-icon">📌</div>Sem post-its</div>';

        // Pane: Anotações (registros)
        const regHtml = registros.length ? registros.map(r =>
            `<div class="da-postit" style="border-color:#6366f1">
                ${_esc(r.texto)}
                <div class="da-postit-meta">${_esc(r.autor_nome)} &bull; ${_formatDate(r.created_at)}</div>
            </div>`
        ).join('') : '<div class="da-card-empty" style="padding:1rem 0"><div class="da-card-empty-icon">📝</div>Sem anotações</div>';

        el.innerHTML = `
            <div id="daConsCons${conselho_id}" class="da-subtab-pane active">${encHtml}</div>
            <div id="daConsPost${conselho_id}" class="da-subtab-pane">${postHtml}</div>
            <div id="daConsReg${conselho_id}"  class="da-subtab-pane">${regHtml}</div>
        `;
    }

    function _switchSubtab(btn, paneId) {
        const card = btn.closest('.da-card');
        card.querySelectorAll('.da-subtab').forEach(b => b.classList.remove('active'));
        card.querySelectorAll('.da-subtab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        var _pane = document.getElementById(paneId);
        if (_pane) _pane.classList.add('active');
    }

    // ── Render: Comentários ───────────────────────────────────────────────────

    function _renderComentarios(data, el) {
        const { comentarios } = data;

        if (!comentarios.length) {
            el.innerHTML = '<div class="da-card-empty"><div class="da-card-empty-icon">💬</div>Nenhum comentário registrado.</div>';
            return;
        }

        const html = '<div class="da-timeline">' + comentarios.map(c => {
            const avatar = c.autor_photo
                ? `<img class="da-inline-avatar" src="/uploads/photos/${_esc(c.autor_photo)}" alt="">`
                : `<span class="da-inline-initial">${_initial(c.autor_nome)}</span>`;

            return `<div class="da-timeline-item">
                <div class="da-timeline-header">
                    <span class="da-timeline-author">${avatar}${_esc(c.autor_nome)}</span>
                    <span class="da-timeline-date">${_formatDate(c.created_at)}</span>
                </div>
                <div class="da-timeline-perfil">${_esc(c.autor_perfil)} &bull; ${_esc(c.turma_nome)}</div>
                <div class="da-timeline-text">${_esc(c.conteudo)}</div>
            </div>`;
        }).join('') + '</div>';

        el.innerHTML = html;
    }

    // ── Render: Sanções ───────────────────────────────────────────────────────

    function _renderSancoes(data, el) {
        const { sancoes } = data;

        if (!sancoes.length) {
            el.innerHTML = '<div class="da-card-empty"><div class="da-card-empty-icon">✓</div>Nenhuma sanção registrada.</div>';
            return;
        }

        const statusMap = {
            'Ativo':      'da-sancao-ativo',
            'Ativa':      'da-sancao-ativo',
            'Finalizado': 'da-sancao-finaliz',
            'Cancelado':  'da-sancao-cancelado',
        };

        el.innerHTML = sancoes.map(s => {
            return `<div class="da-sancao-item">
                <span class="da-sancao-tipo">${_esc(s.tipo_titulo)}</span>
                <div style="flex:1;min-width:0">
                    <div class="da-sancao-desc" title="${_esc(s.descricao || '')}">${_esc(s.descricao || '—')}</div>
                    ${s.autor_nome ? `<div class="da-sancao-date" style="font-size:.7rem">Registrado por: ${_esc(s.autor_nome)}</div>` : ''}
                </div>
                <span class="da-sancao-date">${_formatDate(s.data_sancao)}</span>
                <span class="da-sancao-status ${statusMap[s.status] || 'da-sancao-ativo'}">${_esc(s.status)}</span>
            </div>`;
        }).join('');
    }

    // ── Render: Segunda Chamada ───────────────────────────────────────────────

    function _renderSegundaChamada(data, el) {
        const { solicitacoes } = data;

        if (!solicitacoes.length) {
            el.innerHTML = '<div class="da-card-empty"><div class="da-card-empty-icon">📄</div>Nenhuma solicitação registrada.</div>';
            return;
        }

        const statusMap = {
            'Pendente':        'da-segch-pendente',
            'Aprovada':        'da-segch-aprovada',
            'Negada':          'da-segch-negada',
            'Realizada':       'da-segch-realizada',
            'Não Aplicado':    'da-segch-naoaplic',
        };

        el.innerHTML = solicitacoes.map(s =>
            `<div class="da-segch-item">
                <div style="flex:1;min-width:0">
                    <div class="da-segch-disc">${_esc(s.disciplina_nome || s.disciplina_codigo || '—')}</div>
                    ${s.atividade_nome ? `<div class="da-segch-ativ">${_esc(s.atividade_nome)}</div>` : ''}
                </div>
                <span class="da-sancao-date">${_formatDate(s.data_solicitacao || s.created_at)}</span>
                <span class="da-segch-status ${statusMap[s.status] || ''}">${_esc(s.status)}</span>
            </div>`
        ).join('');
    }

    // ── Skeletons ─────────────────────────────────────────────────────────────

    function _skeletonHero() {
        return `<div class="da-hero">
            <div class="da-skeleton" style="width:72px;height:72px;border-radius:50%;flex-shrink:0"></div>
            <div style="flex:1">
                <div class="da-skeleton da-skeleton-line w-60" style="margin-bottom:.75rem;height:18px"></div>
                <div class="da-skeleton da-skeleton-line w-40"></div>
                <div class="da-skeleton da-skeleton-line w-40"></div>
            </div>
        </div>`;
    }

    function _skeletonCard() {
        return `<div>
            <div class="da-skeleton da-skeleton-block" style="height:100px"></div>
            <div class="da-skeleton da-skeleton-line w-80"></div>
            <div class="da-skeleton da-skeleton-line w-60"></div>
            <div class="da-skeleton da-skeleton-line w-40"></div>
        </div>`;
    }

    function _showCardSkeletons() {
        const sections = ['performance','atendimentos','conselho','ai','comentarios','sancoes','segunda_chamada'];
        sections.forEach(s => {
            const el = document.getElementById('daCard_' + s);
            if (el) {
                el.querySelector('.da-card-body').innerHTML = _skeletonCard();
            }
        });
    }

    // ── Erro ──────────────────────────────────────────────────────────────────

    function _showError(msg) {
        document.getElementById('daHero').innerHTML = `<div style="padding:1rem;color:var(--color-danger)">${_esc(msg)}</div>`;
    }

    // ── Utilitários ───────────────────────────────────────────────────────────

    function _esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function _initial(name) {
        if (!name) return '?';
        return name.trim().split(' ').slice(0, 2).map(function(w){ return w[0] ? w[0].toUpperCase() : ''; }).join('');
    }

    function _formatDate(str) {
        if (!str) return '—';
        try {
            const d = new Date(str.includes('T') ? str : str.replace(' ', 'T'));
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch { return str; }
    }

    function _abrev(str) {
        if (!str) return '';
        // Abreviações comuns de etapas
        const map = { 'Bimestre': 'Bi', 'Trimestre': 'Tri', 'Semestre': 'Sem', 'Etapa': 'Et', 'Período': 'Per' };
        for (const [word, abbr] of Object.entries(map)) {
            str = str.replace(new RegExp(word, 'i'), abbr);
        }
        return str.length > 8 ? str.slice(0, 8) + '…' : str;
    }

    // ── API pública ───────────────────────────────────────────────────────────

    return {
        init,
        selectAluno,
        changeTurma,
        _switchSubtab,
        getState: () => state,
    };

})();

document.addEventListener('DOMContentLoaded', () => vaDashboardAluno.init());
