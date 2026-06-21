/**
 * Vértice Acadêmico — Grade de Somativas
 * Drag-and-drop scheduler + smart search + slot modal
 */

(function () {
    'use strict';

    const CSRF       = window.csrfToken;
    const CAN_EDIT   = !!(window.AppPermissions && window.AppPermissions['somativas.update']);
    const gd         = window.GradeData || {};

    let draggedCard  = null; // card sendo arrastado
    let currentStId  = gd.currentStId;

    // ──────────────────────────────────────────────────────────
    // Init
    // ──────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        initTurmaTabs();
        initDragAndDrop();
        initSlotModal();
        initSmartSearchAll();
        initSuggestionsToggle();
        initAnalysisModal();
        initSidebarToggle();
        renderSuggestions(currentStId);
    });

    // ──────────────────────────────────────────────────────────
    // Turma Tabs
    // ──────────────────────────────────────────────────────────
    function initTurmaTabs() {
        const savedStId = sessionStorage.getItem('active_somativa_st_id_' + gd.somativaId);
        if (savedStId) {
            const stId = parseInt(savedStId);
            const tabExists = Array.from(document.querySelectorAll('.grade-turma-tab'))
                .some(btn => parseInt(btn.dataset.stId) === stId);
            if (tabExists) {
                switchTurma(stId);
            }
        }

        document.querySelectorAll('.grade-turma-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                const stId = parseInt(btn.dataset.stId);
                switchTurma(stId);
            });
        });
    }

    function switchTurma(stId) {
        currentStId = stId;

        sessionStorage.setItem('active_somativa_st_id_' + gd.somativaId, stId);

        document.querySelectorAll('.grade-turma-tab').forEach(b => {
            b.classList.toggle('active', parseInt(b.dataset.stId) === stId);
        });
        document.querySelectorAll('.grade-panel').forEach(p => {
            p.classList.toggle('active', parseInt(p.dataset.stId) === stId);
        });
        document.querySelectorAll('.gs-turma-disc').forEach(d => {
            d.classList.toggle('active', parseInt(d.id.replace('gs-disc-', '')) === stId);
        });

        renderSuggestions(stId);
    }

    function renderSuggestions(stId) {
        const listContainer = document.getElementById('gs-suggestions-container');
        const panel = document.getElementById('gs-suggestions-panel');
        const btn = document.getElementById('btn-suggestions');
        const countSpan = document.getElementById('sug-count');

        if (!listContainer || !panel) return;

        const allSug = window.GradeSuggestions || [];
        const filtered = allSug.filter(s => {
            if (!s.somativa_turma_ids || s.somativa_turma_ids.length === 0) {
                return true;
            }
            return s.somativa_turma_ids.includes(parseInt(stId));
        });

        if (btn) {
            if (filtered.length > 0) {
                btn.style.display = 'inline-block';
                if (countSpan) countSpan.textContent = filtered.length;
            } else {
                btn.style.display = 'none';
            }
        }

        if (filtered.length === 0) {
            panel.hidden = true;
            listContainer.innerHTML = '';
            return;
        }

        const categoriasMap = {
            'logistica': '🚚 Logística',
            'pedagogica': '🧠 Pedagógica',
            'professores': '👤 Professores'
        };

        listContainer.innerHTML = filtered.map(s => {
            const satisf = s.atendida ? 'satisfied' : '';
            const star = s.atendida ? '<span class="gs-suggestion-star" title="Sugestão atendida! ⭐">⭐</span>' : '';
            const badgeClass = `gs-badge-${s.categoria || 'logistica'}`;
            const categoryLabel = categoriasMap[s.categoria] || '💡 Sugestão';

            const helpIcon = (!s.atendida && s.detalhes) ? `
                <span class="gs-suggestion-help">❓
                    <span class="tooltip-content">${s.detalhes}</span>
                </span>
            ` : '';

            return `
                <div class="gs-suggestion-item ${satisf} gs-sug-${s.categoria || 'logistica'}">
                    <div class="gs-suggestion-header">
                        <span class="gs-suggestion-badge ${badgeClass}">${categoryLabel}</span>
                        ${helpIcon}
                    </div>
                    <div class="gs-suggestion-body">
                        <div class="gs-suggestion-text">${escHtml(s.mensagem)}</div>
                        ${star}
                    </div>
                </div>
            `;
        }).join('');

        panel.hidden = false;
    }

    function initSidebarToggle() {
        const toggleBtn = document.getElementById('btn-toggle-sidebar');
        const sidebarWrapper = document.querySelector('.grade-sidebar-wrapper');

        if (!toggleBtn || !sidebarWrapper) return;

        const isCollapsed = sessionStorage.getItem('grade_sidebar_collapsed') === 'true';
        if (isCollapsed) {
            sidebarWrapper.classList.add('collapsed');
            const span = toggleBtn.querySelector('span');
            if (span) span.textContent = '‹';
        }

        toggleBtn.addEventListener('click', () => {
            const collapsed = sidebarWrapper.classList.toggle('collapsed');
            sessionStorage.setItem('grade_sidebar_collapsed', collapsed ? 'true' : 'false');
            const span = toggleBtn.querySelector('span');
            if (span) span.textContent = collapsed ? '‹' : '›';
        });
    }

    // ──────────────────────────────────────────────────────────
    // Drag-and-Drop
    // ──────────────────────────────────────────────────────────
    function initDragAndDrop() {
        // Cards arrastáveis (sidebar)
        document.querySelectorAll('.disc-drag-card').forEach(card => {
            setupDragSource(card);
        });

        // Cards já alocados na grade
        document.querySelectorAll('.grade-card').forEach(card => {
            setupDragSource(card);
        });

        // Células da grade como alvos
        document.querySelectorAll('.grade-cell').forEach(cell => {
            if (!cell.classList.contains('weekend')) {
                setupDropTarget(cell);
            }
        });

        // Sidebar como alvo para remoção
        const sidebar = document.getElementById('grade-sidebar');
        if (sidebar) {
            setupSidebarDropTarget(sidebar);
        }
    }

    function setupDragSource(card) {
        card.addEventListener('dragstart', (e) => {
            draggedCard = card;
            card.classList.add('dragging');

            const data = {
                somDiscId:          card.dataset.somDiscId || card.dataset.discId || '',
                discCod:            card.dataset.discCod   || '',
                discNome:           card.dataset.discNome  || '',
                tipo:               card.dataset.tipo      || 'Normal',
                profIds:            card.dataset.professorIds || '',
                gradeId:            card.dataset.gradeId   || '',
                aplicadorId:        card.dataset.aplicadorId   || '',
                aplicadorNome:      card.dataset.aplicadorNome || '',
                volanteId:          card.dataset.volanteId     || '',
                volanteNome:        card.dataset.volanteNome   || '',
                naapiAplicadorId:   card.dataset.naapiAplicadorId   || '',
                naapiAplicadorNome: card.dataset.naapiAplicadorNome || '',
                ambienteId:         card.dataset.ambienteId   || '',
                ambienteNome:       card.dataset.ambienteNome || '',
                obs:                card.dataset.obs          || '',
            };
            e.dataTransfer.setData('application/json', JSON.stringify(data));
            e.dataTransfer.effectAllowed = 'move';
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            draggedCard = null;
        });
    }

    function setupDropTarget(cell) {
        cell.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            cell.classList.add('drag-over');
        });

        cell.addEventListener('dragleave', (e) => {
            if (!cell.contains(e.relatedTarget)) {
                cell.classList.remove('drag-over');
            }
        });

        cell.addEventListener('drop', (e) => {
            e.preventDefault();
            cell.classList.remove('drag-over');

            const raw = e.dataTransfer.getData('application/json');
            if (!raw) return;

            let cardData;
            try { cardData = JSON.parse(raw); } catch (err) { return; }

            openSlotModal(cell, cardData);
        });
    }

    function setupSidebarDropTarget(sidebar) {
        sidebar.addEventListener('dragover', (e) => {
            if (draggedCard && draggedCard.classList.contains('grade-card')) {
                e.preventDefault();
                sidebar.classList.add('drag-over');
            }
        });

        sidebar.addEventListener('dragleave', (e) => {
            if (!sidebar.contains(e.relatedTarget)) {
                sidebar.classList.remove('drag-over');
            }
        });

        sidebar.addEventListener('drop', (e) => {
            e.preventDefault();
            sidebar.classList.remove('drag-over');

            if (!draggedCard || !draggedCard.classList.contains('grade-card')) {
                return;
            }

            const gradeId = draggedCard.dataset.gradeId;
            if (gradeId) {
                const cell = draggedCard.closest('.grade-cell');
                clearSlot(gradeId, cell);
            }
        });
    }

    // ──────────────────────────────────────────────────────────
    // Slot Modal
    // ──────────────────────────────────────────────────────────
    const overlay  = document.getElementById('slot-modal-overlay');
    let   modalCell = null;
    let   modalCard = null;

    function initSlotModal() {
        if (!overlay) return;

        // Fecha ao clicar no backdrop
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeSlotModal();
        });

        // ESC fecha
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !overlay.hidden) closeSlotModal();
        });

        // Submit
        document.getElementById('slot-modal-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            await submitSlot(e.target);
        });
    }

    window.openSlotModal = function (cell, cardData) {
        if (!overlay) return;

        modalCell = cell;
        modalCard = cardData || null;

        const date      = cell.dataset.date;
        const slotId    = cell.dataset.slotId;
        const stId      = cell.dataset.stId;
        const turmaId   = cell.dataset.turmaId;
        const somId     = cell.dataset.somativaId;
        const gradeId   = cell.dataset.gradeId || '';

        // Preenche hidden fields
        document.getElementById('smi-st-id').value  = stId;
        document.getElementById('smi-slot-id').value = slotId;
        document.getElementById('smi-data').value    = date;

        const clearGradeIdEl = document.getElementById('smi-clear-grade-id');
        if (clearGradeIdEl) {
            clearGradeIdEl.value = cardData?.gradeId || '';
        }

        // Info exibida no modal
        const dateFormatted = formatDate(date);
        const infoEl = document.getElementById('slot-modal-info');
        infoEl.innerHTML = `
            <span>📅 ${dateFormatted}</span>
            <span class="grade-period-sep">·</span>
            <span>⏰ Slot ${slotId}</span>
            ${cardData ? `<span class="grade-period-sep">·</span><strong>${escHtml(cardData.discNome)}</strong>` : ''}
        `;

        // Tipo
        const tipoSelect = document.getElementById('smi-tipo-select');
        const tipo = cardData?.tipo || 'Normal';
        tipoSelect.value = tipo;
        document.getElementById('smi-tipo').value = tipo;
        tipoSelect.addEventListener('change', () => {
            document.getElementById('smi-tipo').value = tipoSelect.value;
        });

        // Disciplina
        const sdId = cardData?.somDiscId || (cell.querySelector('.grade-card')?.dataset.discId) || '';
        document.getElementById('smi-sd-id').value = sdId;

        // Se há card existente no cell, pré-carrega os valores
        const existingCard = cell.querySelector('.grade-card');
        if (existingCard) {
            const data = {
                aplicadorId: existingCard.dataset.aplicadorId || '',
                aplicadorNome: existingCard.dataset.aplicadorNome || '',
                volanteId:   existingCard.dataset.volanteId   || '',
                volanteNome: existingCard.dataset.volanteNome || '',
                naapiAplicadorId:   existingCard.dataset.naapiAplicadorId   || '',
                naapiAplicadorNome: existingCard.dataset.naapiAplicadorNome || '',
                ambienteId:  existingCard.dataset.ambienteId  || '',
                ambienteNome: existingCard.dataset.ambienteNome || '',
                obs:         existingCard.dataset.obs         || '',
                tipo:        existingCard.dataset.tipo       || 'Normal',
            };
            fillModalFields(data);
        } else if (cardData) {
            fillModalFields(cardData);

            // Tenta obter sugestões de aplicador, volante e ambiente para nova alocação
            const hasDetails = cardData.aplicadorId || cardData.volanteId || cardData.ambienteId;
            if (!hasDetails) {
                fetch(`/api/somativas/grade.php?action=suggest_allocation&somativa_id=${somId}&somativa_turma_id=${stId}&somativa_disciplina_id=${sdId}&data_prova=${date}&slot_config_id=${slotId}`)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success && res.data) {
                            fillModalFields({
                                aplicadorId:        res.data.aplicador_id,
                                aplicadorNome:      res.data.aplicador_nome,
                                volanteId:          res.data.volante_id,
                                volanteNome:        res.data.volante_nome,
                                naapiAplicadorId:   res.data.naapi_aplicador_id,
                                naapiAplicadorNome: res.data.naapi_aplicador_nome,
                                ambienteId:         res.data.ambiente_id,
                                ambienteNome:       res.data.ambiente_desc,
                                tipo:               cardData.tipo || 'Normal',
                                obs:                cardData.obs  || '',
                            });
                        }
                    })
                    .catch(err => console.error("Erro ao buscar sugestões de alocação", err));
            }
        }

        // Suggestions: pré-preenche professor sugerido se disponível
        if (cardData?.profIds) {
            const ids = cardData.profIds.split(',').filter(Boolean);
            if (ids[0]) {
                suggestProfessor(parseInt(ids[0]), cardData.discNome);
            }
        }

        overlay.hidden = false;
        document.querySelector('.slot-modal .smart-search-input')?.focus();
    };

    window.closeSlotModal = function () {
        if (!overlay) return;
        overlay.hidden = true;
        modalCell = null;
        modalCard = null;
        resetModalFields();
    };

    function resetModalFields() {
        document.getElementById('slot-modal-form')?.reset();
        document.getElementById('field-aplicador-id').value = '';
        document.getElementById('field-volante-id').value   = '';
        document.getElementById('field-ambiente-id').value  = '';
        document.getElementById('selected-aplicador').hidden = true;
        document.getElementById('selected-volante').hidden   = true;
        document.getElementById('selected-ambiente').hidden  = true;

        // Campos NAAPI (podem não existir se NAAPI não estiver configurado)
        const naapiField = document.getElementById('field-naapi-aplicador-id');
        const naapiSel   = document.getElementById('selected-naapi-aplicador');
        if (naapiField) naapiField.value = '';
        if (naapiSel)   naapiSel.hidden  = true;

        const clearGradeEl = document.getElementById('smi-clear-grade-id');
        if (clearGradeEl) clearGradeEl.value = '';
        document.querySelectorAll('.slot-modal .smart-search-input').forEach(i => i.value = '');
        document.querySelectorAll('.slot-modal .smart-search-dropdown').forEach(d => d.hidden = true);
    }

    function fillModalFields(data) {
        if (data.aplicadorId) {
            setField('aplicador_id', data.aplicadorId, data.aplicadorNome);
        }
        if (data.volanteId) {
            setField('volante_id', data.volanteId, data.volanteNome);
        }
        if (data.ambienteId) {
            setField('ambiente_id', data.ambienteId, data.ambienteNome);
        }
        if (data.naapiAplicadorId && document.getElementById('field-naapi-aplicador-id')) {
            setField('naapi_aplicador_id', data.naapiAplicadorId, data.naapiAplicadorNome);
        }
        const obsEl = document.getElementById('smi-obs');
        if (obsEl) {
            obsEl.value = data.obs || '';
        }
        const tipoSelect = document.getElementById('smi-tipo-select');
        if (tipoSelect) {
            tipoSelect.value = data.tipo || 'Normal';
            document.getElementById('smi-tipo').value = tipoSelect.value;
        }
    }

    async function submitSlot(form) {
        const btn = document.getElementById('btn-slot-save');
        btn.disabled    = true;
        btn.textContent = 'Salvando...';

        try {
            const fd = new FormData(form);
            const res  = await fetch('/api/somativas/grade.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                Toast.success('Slot salvo!');
                closeSlotModal();
                // Recarrega a página para refletir as mudanças
                setTimeout(() => location.reload(), 600);
            } else {
                Toast.error(data.message || 'Erro ao salvar slot');
            }
        } catch (err) {
            Toast.error('Erro de comunicação');
        } finally {
            btn.disabled    = false;
            btn.textContent = 'Salvar';
        }
    }

    // ──────────────────────────────────────────────────────────
    // Limpar Slot
    // ──────────────────────────────────────────────────────────
    window.clearSlot = function (gradeId, cell) {
        Modal.confirm({
            title:       'Limpar Slot',
            message:     'Remover esta prova do horário?',
            confirmText: 'Limpar',
            confirmClass:'btn-danger',
            onConfirm: async () => {
                const fd = new FormData();
                fd.append('action',     'clear_slot');
                fd.append('csrf_token', CSRF);
                fd.append('grade_id',   gradeId);

                try {
                    const res  = await fetch('/api/somativas/grade.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        Toast.success('Slot limpo.');
                        setTimeout(() => location.reload(), 600);
                    } else {
                        Toast.error(data.message || 'Erro ao limpar');
                    }
                } catch (e) {
                    Toast.error('Erro de comunicação');
                }
            }
        });
    };

    // ──────────────────────────────────────────────────────────
    // Limpar Todos os Cards da Turma Atual
    // ──────────────────────────────────────────────────────────
    const btnLimparCards = document.getElementById('btn-limpar-cards');
    if (btnLimparCards) {
        btnLimparCards.addEventListener('click', () => {
            const activeTab = document.querySelector('.grade-turma-tab.active');
            const turmaDesc = activeTab
                ? (activeTab.querySelector('.gtt-turma')?.textContent || 'esta turma')
                : 'esta turma';

            Modal.confirm({
                title:       'Limpar todos os cards',
                message:     `Remover todas as provas alocadas de <strong>${escHtml(turmaDesc)}</strong>?<br>Esta ação não pode ser desfeita.`,
                confirmText: 'Limpar tudo',
                confirmClass:'btn-danger',
                onConfirm: async () => {
                    const fd = new FormData();
                    fd.append('action',            'clear_all_slots');
                    fd.append('csrf_token',        CSRF);
                    fd.append('somativa_turma_id', currentStId);

                    try {
                        const res  = await fetch('/api/somativas/grade.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            const n = data.removed ?? 0;
                            Toast.success(`${n} card${n !== 1 ? 's' : ''} removido${n !== 1 ? 's' : ''}.`);
                            setTimeout(() => location.reload(), 600);
                        } else {
                            Toast.error(data.message || 'Erro ao limpar cards');
                        }
                    } catch (e) {
                        Toast.error('Erro de comunicação');
                    }
                }
            });
        });
    }

    // ──────────────────────────────────────────────────────────
    // Smart Search (autocomplete no modal)
    // ──────────────────────────────────────────────────────────
    function initSmartSearchAll() {
        document.querySelectorAll('.smart-search-modal').forEach(container => {
            initOneSearch(container);
        });
    }

    function initSuggestionsToggle() {
        const btn = document.getElementById('btn-suggestions');
        const panel = document.getElementById('gs-suggestions-panel');
        if (btn && panel) {
            btn.addEventListener('click', () => {
                panel.open = true;
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }
    }

    function initOneSearch(container) {
        const input    = container.querySelector('.smart-search-input');
        const dropdown = container.querySelector('.smart-search-dropdown');
        const type     = container.dataset.type;
        const fieldId  = container.dataset.field;
        let   timer    = null;

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (!q) { dropdown.hidden = true; return; }
            timer = setTimeout(() => doSearch(q), 250);
        });

        input.addEventListener('focus', () => {
            const q = input.value.trim();
            if (q.length >= 1) doSearch(q);
        });

        document.addEventListener('click', (e) => {
            if (!container.contains(e.target)) dropdown.hidden = true;
        });

        async function doSearch(q) {
            try {
                const res  = await fetch(`/api/somativas/search.php?type=${type}&q=${encodeURIComponent(q)}`);
                const data = await res.json();
                renderDrop(data.results || []);
            } catch (e) {
                dropdown.hidden = true;
            }
        }

        function renderDrop(results) {
            if (!results.length) { dropdown.hidden = true; return; }
            dropdown.innerHTML = results.map(r => `
                <div class="smart-result" data-id="${r.id}" data-label="${escHtml(r.label)}">
                    <span class="sr-label">${escHtml(r.label)}</span>
                    ${r.sub ? `<span class="sr-sub">${escHtml(r.sub)}</span>` : ''}
                </div>
            `).join('');
            dropdown.querySelectorAll('.smart-result').forEach(el => {
                el.addEventListener('click', () => pick(el.dataset.id, el.dataset.label));
            });
            dropdown.hidden = false;
        }

        function pick(id, label) {
            dropdown.hidden = true;
            input.value     = '';
            setField(fieldId, id, label);
        }
    }

    // Converte fieldId (ex: "aplicador_id", "naapi_aplicador_id") para a chave dos elementos HTML
    function fieldKey(fieldId) {
        return fieldId.replace(/_id$/, '').replace(/_/g, '-');
    }

    function setField(fieldId, id, label) {
        const key         = fieldKey(fieldId);
        const hiddenInput = document.getElementById(`field-${key}-id`);
        const selectedDiv = document.getElementById(`selected-${key}`);

        if (hiddenInput) hiddenInput.value = id;
        if (selectedDiv) {
            selectedDiv.textContent = '';

            const span = document.createElement('span');
            span.textContent = `✓ ${label}`;
            selectedDiv.appendChild(span);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'clear-selection';
            btn.textContent = '✕';
            btn.addEventListener('click', () => {
                window.clearField(fieldId);
            });
            selectedDiv.appendChild(btn);

            selectedDiv.hidden = false;
        }
    }

    window.clearField = function (fieldId) {
        const key         = fieldKey(fieldId);
        const hiddenInput = document.getElementById(`field-${key}-id`);
        const selectedDiv = document.getElementById(`selected-${key}`);
        if (hiddenInput) hiddenInput.value = '';
        if (selectedDiv) selectedDiv.hidden = true;

        const container = document.getElementById(`search-${key}`);
        if (container) {
            const input = container.querySelector('.smart-search-input');
            if (input) input.value = '';
        }
    };

    function suggestProfessor(profId, discNome) {
        // Pré-preenche o volante com o professor da disciplina (sugestão)
        if (!profId) return;
        fetch(`/api/somativas/search.php?type=professor&q=&id=${profId}`)
            .then(r => r.json())
            .catch(() => {});
    }

    // ──────────────────────────────────────────────────────────
    // Utilitários
    // ──────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDate(ymd) {
        if (!ymd) return '';
        const [y, m, d] = ymd.split('-');
        const days = ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        const dt   = new Date(ymd + 'T12:00:00');
        return `${days[dt.getDay() === 0 ? 7 : dt.getDay()]} ${d}/${m}/${y}`;
    }

    // ──────────────────────────────────────────────────────────
    // Auto-Alocação Automática
    // ──────────────────────────────────────────────────────────

    const aaOverlay  = document.getElementById('autoalocar-overlay');
    const aaLoading  = document.getElementById('autoalocar-loading');
    const aaResult   = document.getElementById('autoalocar-result');
    let   aaProposta = [];  // alocações propostas pelo engine (editável pelo usuário)

    if (aaOverlay) {
        document.getElementById('autoalocar-close')?.addEventListener('click', closeAutoAlocar);
        document.getElementById('autoalocar-cancel')?.addEventListener('click', closeAutoAlocar);
        aaOverlay.addEventListener('click', (e) => { if (e.target === aaOverlay) closeAutoAlocar(); });
        document.getElementById('autoalocar-confirm')?.addEventListener('click', confirmAutoAlocar);
    }

    const btnAuto = document.getElementById('btn-auto-alocar');
    if (btnAuto) {
        btnAuto.addEventListener('click', () => runAutoAlocar());
    }

    async function runAutoAlocar() {
        if (!gd.somativaId) return;

        openAutoAlocar();
        setLoadingSub('Executando alocação inteligente...');

        try {
            const fd = new FormData();
            fd.append('action',      'preview');
            fd.append('somativa_id', gd.somativaId);
            fd.append('csrf_token',  CSRF);

            const res  = await fetch('/api/somativas/auto_alocar.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                closeAutoAlocar();
                Toast.error(data.message || 'Erro ao gerar alocação');
                return;
            }

            renderAutoAlocarResult(data);

        } catch (err) {
            closeAutoAlocar();
            Toast.error('Erro de comunicação com o servidor');
        }
    }

    function renderAutoAlocarResult(data) {
        aaProposta = data.alocacoes || [];

        // Resumo
        const total     = aaProposta.length;
        const conflitos = (data.conflitos || []).length;
        const avisos    = (data.avisos   || []).length;

        document.getElementById('autoalocar-summary').innerHTML = `
            <span class="aa-badge aa-badge-ok">✅ ${total} alocaç${total === 1 ? 'ão' : 'ões'} geradas</span>
            ${conflitos > 0 ? `<span class="aa-badge aa-badge-warn">⚠️ ${conflitos} conflito${conflitos > 1 ? 's' : ''}</span>` : ''}
            ${avisos    > 0 ? `<span class="aa-badge aa-badge-info">ℹ️ ${avisos} aviso${avisos > 1 ? 's' : ''}</span>` : ''}
        `;

        // Provider IA
        const provEl = document.getElementById('autoalocar-provider');
        if (provEl) provEl.textContent = data.provider ? `Otimizado por IA: ${data.provider}` : '';

        // Avisos
        const warnEl = document.getElementById('autoalocar-warnings');
        if ((data.avisos || []).length > 0) {
            warnEl.innerHTML = `<div class="aa-warnings">${data.avisos.map(w => `<p>ℹ️ ${escHtml(w)}</p>`).join('')}</div>`;
            warnEl.hidden = false;
        }

        // Conflitos
        const confEl = document.getElementById('autoalocar-conflicts');
        if (conflitos > 0) {
            confEl.innerHTML = `<div class="aa-conflicts">
                <strong>Não alocados (exigem alocação manual):</strong>
                <ul>${data.conflitos.map(c => `<li>${escHtml(c.disc_nome)} — ${escHtml(c.turma_desc)}: ${escHtml(c.motivo)}</li>`).join('')}</ul>
            </div>`;
            confEl.hidden = false;
        }

        // Tabela de alocações
        const tbody = document.getElementById('autoalocar-tbody');
        tbody.innerHTML = aaProposta.map((aloc, idx) => {
            const aiTag = aloc.ai_resolved ? '<span class="aa-ai-tag" title="Resolvido por IA">🤖</span>' : '';
            return `
            <tr data-idx="${idx}">
                <td>${escHtml(aloc.turma_desc || '')}</td>
                <td>${escHtml(aloc.disc_nome  || '')} ${aiTag}</td>
                <td>${formatDate(aloc.data_prova)}</td>
                <td>${escHtml(aloc.slot_label || aloc.horario_inicio?.substring(0,5) || '')}${aloc.horario_inicio ? '–' + aloc.horario_fim?.substring(0,5) : ''}</td>
                <td class="aa-sala">${aloc.ambiente_id ? `#${aloc.ambiente_id}` : '<span class="aa-sala-vazia" title="Sala não definida na turma">—</span>'}</td>
                <td>
                    <button type="button" class="gc-btn-clear" title="Remover desta proposta"
                            onclick="removeAlocacaoProposta(${idx})">✕</button>
                </td>
            </tr>`;
        }).join('');

        aaLoading.hidden = true;
        aaResult.hidden  = false;

        // Botão de confirmar: desativa se não há alocações
        document.getElementById('autoalocar-confirm').disabled = aaProposta.length === 0;
    }

    window.removeAlocacaoProposta = function(idx) {
        aaProposta.splice(idx, 1);
        // Re-renderiza apenas o tbody
        const tbody = document.getElementById('autoalocar-tbody');
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach((tr, i) => {
                if (i === idx) tr.remove();
                else tr.dataset.idx = i < idx ? i : i - 1;
            });
        }
        // Atualiza badge de contagem
        const badge = document.querySelector('.aa-badge-ok');
        if (badge) badge.textContent = `✅ ${aaProposta.length} alocaç${aaProposta.length === 1 ? 'ão' : 'ões'} geradas`;

        document.getElementById('autoalocar-confirm').disabled = aaProposta.length === 0;
    };

    async function confirmAutoAlocar() {
        if (!aaProposta.length) return;

        const btn = document.getElementById('autoalocar-confirm');
        btn.disabled    = true;
        btn.textContent = 'Salvando...';

        try {
            const fd = new FormData();
            fd.append('action',      'confirm');
            fd.append('somativa_id', gd.somativaId);
            fd.append('csrf_token',  CSRF);
            fd.append('alocacoes',   JSON.stringify(aaProposta));

            const res  = await fetch('/api/somativas/auto_alocar.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                const msg = `${data.saved} prova${data.saved !== 1 ? 's' : ''} alocada${data.saved !== 1 ? 's' : ''}!`;
                Toast.success(msg);
                closeAutoAlocar();
                setTimeout(() => location.reload(), 700);
            } else {
                Toast.error(data.message || 'Erro ao confirmar');
                btn.disabled    = false;
                btn.textContent = '✅ Confirmar e Salvar';
            }
        } catch {
            Toast.error('Erro de comunicação');
            btn.disabled    = false;
            btn.textContent = '✅ Confirmar e Salvar';
        }
    }

    function openAutoAlocar() {
        aaLoading.hidden = false;
        aaResult.hidden  = true;
        document.getElementById('autoalocar-warnings').hidden  = true;
        document.getElementById('autoalocar-conflicts').hidden = true;
        document.getElementById('autoalocar-summary').innerHTML = '';
        document.getElementById('autoalocar-tbody').innerHTML   = '';
        aaOverlay.hidden = false;
    }

    function closeAutoAlocar() {
        aaOverlay.hidden = true;
        aaProposta       = [];
    }

    function setLoadingSub(msg) {
        const el = document.getElementById('autoalocar-loading-sub');
        if (el) el.textContent = msg;
    }

    // ──────────────────────────────────────────────────────────
    // Modal de Análise de Professores
    // ──────────────────────────────────────────────────────────
    const anOverlay = document.getElementById('analysis-modal-overlay');
    let analysisData = null;

    function initAnalysisModal() {
        const btnAnalisar = document.getElementById('btn-analisar-professores');
        if (btnAnalisar) {
            btnAnalisar.addEventListener('click', openAnalysisModal);
        }

        // Eventos de clique nas Abas
        document.querySelectorAll('.analysis-tab').forEach(tabBtn => {
            tabBtn.addEventListener('click', () => {
                const targetTab = tabBtn.dataset.tab;
                switchAnalysisTab(targetTab);
            });
        });

        // Evento de fechar ao clicar no backdrop do modal
        if (anOverlay) {
            anOverlay.addEventListener('click', (e) => {
                if (e.target === anOverlay) closeAnalysisModal();
            });
        }

        // Formulário de equalização submit
        document.getElementById('equalizacao-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            await applyEqualization();
        });

        // Checkbox de selecionar todos
        document.getElementById('chk-select-all-swaps')?.addEventListener('change', (e) => {
            const chkAll = e.target;
            document.querySelectorAll('.swap-checkbox').forEach(chk => {
                chk.checked = chkAll.checked;
            });
            updateEqualizeButtonState();
        });

        // Aba Inteligência
        document.getElementById('btn-processar-inteligente')?.addEventListener('click', processIntelligentAllocation);
        document.getElementById('btn-intel-reset')?.addEventListener('click', resetIntelligentTab);
        document.getElementById('btn-intel-confirm')?.addEventListener('click', confirmIntelligentAllocation);
    }

    window.openAnalysisModal = function () {
        if (!anOverlay) return;
        anOverlay.hidden = false;
        loadAnalysisData();
    };

    window.closeAnalysisModal = function () {
        if (!anOverlay) return;
        anOverlay.hidden = true;
        analysisData = null;
        resetIntelligentTab();
    };

    function switchAnalysisTab(tabId) {
        document.querySelectorAll('.analysis-tab').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabId);
        });
        document.querySelectorAll('.analysis-tab-panel').forEach(panel => {
            if (panel.id === tabId) {
                panel.removeAttribute('hidden');
                panel.classList.add('active');
            } else {
                panel.setAttribute('hidden', '');
                panel.classList.remove('active');
            }
        });
    }

    async function loadAnalysisData() {
        showLoadingState();

        try {
            const res = await fetch(`/api/somativas/analise_professores.php?action=load_analysis&somativa_id=${gd.somativaId}`);
            const data = await res.json();

            if (data.success) {
                analysisData = data;
                renderAnalysis();
            } else {
                Toast.error(data.message || 'Erro ao carregar dados de análise');
                closeAnalysisModal();
            }
        } catch (err) {
            Toast.error('Erro de comunicação com o servidor');
            closeAnalysisModal();
        }
    }

    function showLoadingState() {
        const loadingHtml = `<tr><td colspan="7" class="text-center loading-td"><div class="spinner spinner-margin"></div>Carregando dados...</td></tr>`;
        const loadingCarga = `<div class="loading-div-grid"><div class="spinner spinner-margin"></div>Carregando dados...</div>`;
        const loadingAgendas = `<div class="loading-div"><div class="spinner spinner-margin"></div>Carregando dados...</div>`;

        const desTbody = document.getElementById('desacordos-tbody');
        if (desTbody) desTbody.innerHTML = loadingHtml.replace('colspan="7"', 'colspan="5"');

        const eqTbody = document.getElementById('equalizacao-tbody');
        if (eqTbody) eqTbody.innerHTML = loadingHtml;

        const cargaGrid = document.getElementById('carga-grid');
        if (cargaGrid) cargaGrid.innerHTML = loadingCarga;

        const agendasCont = document.getElementById('agendas-container');
        if (agendasCont) agendasCont.innerHTML = loadingAgendas;
    }

    function renderAnalysis() {
        if (!analysisData) return;

        // ── Aba 1: Horários em Desacordo ───────────────────────
        const conflicts = analysisData.conflitos || [];
        const desTbody = document.getElementById('desacordos-tbody');
        if (desTbody) {
            if (conflicts.length === 0) {
                desTbody.innerHTML = `<tr><td colspan="5" class="text-center success-td">✅ Nenhum professor alocado fora do horário convencional!</td></tr>`;
            } else {
                desTbody.innerHTML = conflicts.map(c => {
                    const tagsHtml = (c.turmas_disciplinas || []).map(td => `
                        <span class="conflict-tag">
                            <strong>${escHtml(td.turma_desc)}</strong> &middot; ${escHtml(td.disc_name)}
                        </span>
                    `).join('');

                    let roleBadgeClass = 'badge-neutral';
                    if (c.papel.includes('Aplicador') && !c.papel.includes('Volante')) {
                        roleBadgeClass = 'badge-warning';
                    } else if (c.papel.includes('Volante') && !c.papel.includes('Aplicador')) {
                        roleBadgeClass = 'badge-success badge-volante';
                    }

                    return `
                        <tr>
                            <td class="font-semibold-primary">${escHtml(c.professor_name)}</td>
                            <td>${formatDate(c.data_prova)} (${c.dia_semana_br})</td>
                            <td><span class="badge badge-neutral">${escHtml(c.slot_label)}</span></td>
                            <td><span class="badge ${roleBadgeClass} badge-role">${escHtml(c.papel)}</span></td>
                            <td>
                                <div class="conflict-tags-list">
                                    ${tagsHtml}
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }

        // ── Aba 2: Agenda dos Professores ──────────────────────
        const agendas = analysisData.agendas || [];
        const agendasCont = document.getElementById('agendas-container');
        const profSelect = document.getElementById('agenda-prof-select');

        if (agendasCont && profSelect) {
            const selectedVal = profSelect.value;
            profSelect.innerHTML = `<option value="">-- Todos os Professores (${agendas.length}) --</option>` +
                agendas.map(ag => `<option value="${ag.professor_id}">${escHtml(ag.professor_name)}</option>`).join('');
            profSelect.value = selectedVal;

            if (agendas.length === 0) {
                agendasCont.innerHTML = `<p class="agenda-empty text-center agenda-empty-pad">Nenhuma alocação registrada para o período somativo.</p>`;
            } else {
                renderAgendaList(agendas, selectedVal);

                profSelect.onchange = () => {
                    renderAgendaList(agendas, profSelect.value);
                };
            }
        }

        // ── Aba 3: Carga de Trabalho ───────────────────────────
        const workloads = analysisData.cargas || [];
        const cargaGrid = document.getElementById('carga-grid');
        if (cargaGrid) {
            if (workloads.length === 0) {
                cargaGrid.innerHTML = `<p class="text-center loading-div-grid">Nenhum professor registrado na instituição.</p>`;
            } else {
                const maxVal = Math.max(4, ...workloads.map(w => w.total));

                cargaGrid.innerHTML = workloads.map(w => {
                    const pct = Math.min(100, Math.round((w.total / maxVal) * 100));
                    let barClass = 'load-low';
                    if (w.total >= 5) {
                        barClass = 'load-high';
                    } else if (w.total >= 3) {
                        barClass = 'load-medium';
                    }

                    return `
                        <div class="carga-card">
                            <div class="carga-card-header">
                                <span class="carga-prof-name">${escHtml(w.professor_name)}</span>
                                <span class="carga-total-badge">${w.total} aloc.</span>
                            </div>
                            <div class="carga-bar-container">
                                <div class="carga-bar ${barClass}" style="width: ${pct}%;"></div>
                            </div>
                            <div class="carga-details">
                                <span class="carga-detail-item">Aplicador: <strong>${w.aplicador}</strong></span>
                                <span class="carga-detail-item">Volante: <strong>${w.volante}</strong></span>
                                <span class="carga-detail-item">NAAPI: <strong>${w.naapi}</strong></span>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // ── Aba 4: Equalização de Carga ────────────────────────
        const suggestions = analysisData.sugestoes || [];
        const eqTbody = document.getElementById('equalizacao-tbody');
        const applyBtn = document.getElementById('btn-aplicar-equalizacao');

        if (eqTbody && applyBtn) {
            const chkAll = document.getElementById('chk-select-all-swaps');
            if (chkAll) chkAll.checked = false;

            if (suggestions.length === 0) {
                eqTbody.innerHTML = `<tr><td colspan="7" class="text-center success-td">✨ Distribuição de carga equilibrada! Nenhuma sugestão de troca necessária.</td></tr>`;
                applyBtn.disabled = true;
            } else {
                eqTbody.innerHTML = suggestions.map((s, idx) => `
                    <tr>
                        <td class="align-center-middle">
                            <input type="checkbox" class="swap-checkbox cursor-pointer" data-idx="${idx}" onchange="updateEqualizeButtonState()">
                        </td>
                        <td>
                            <div class="font-semibold-primary">${escHtml(s.turma_desc)}</div>
                            <div class="font-xs-muted">${escHtml(s.disc_nome)}</div>
                        </td>
                        <td>
                            <div>${formatDate(s.data_prova)} (${s.dia_semana_br})</div>
                            <div class="font-xs-muted">${escHtml(s.slot_label)}</div>
                        </td>
                        <td>
                            <span class="badge ${s.role_field === 'aplicador_id' ? 'badge-warning' : (s.role_field === 'volante_id' ? 'badge-success badge-volante' : 'badge-neutral')} badge-role">
                                ${escHtml(s.role_label)}
                            </span>
                        </td>
                        <td>
                            <div class="font-medium-secondary">${escHtml(s.current_teacher_name)}</div>
                            <div class="detail-muted">Carga: ${s.current_teacher_load} aloc.</div>
                        </td>
                        <td>
                            <div class="font-semibold-color-primary">${escHtml(s.suggested_teacher_name)}</div>
                            <div class="detail-muted">Carga: ${s.suggested_teacher_load} aloc.</div>
                        </td>
                        <td class="reason-td">
                            ${escHtml(s.reason)}
                        </td>
                    </tr>
                `).join('');
                applyBtn.disabled = true;
            }
        }
    }

    function renderAgendaList(agendas, filterProfId) {
        const agendasCont = document.getElementById('agendas-container');
        if (!agendasCont) return;

        let filtered = agendas;
        if (filterProfId) {
            filtered = agendas.filter(ag => ag.professor_id === parseInt(filterProfId));
        }

        agendasCont.innerHTML = filtered.map(ag => `
            <div class="agenda-prof-section" data-prof-id="${ag.professor_id}">
                <div class="agenda-prof-header">${escHtml(ag.professor_name)}</div>
                ${ag.agenda.length === 0 ? `
                    <p class="agenda-empty">Nenhum horário de prova alocado para este professor.</p>
                ` : `
                    <div class="analysis-table-wrap">
                        <table class="analysis-table">
                            <thead>
                                <tr>
                                    <th class="th-w-20">Data / Dia</th>
                                    <th class="th-w-20">Horário (Slot)</th>
                                    <th class="th-w-15">Papel</th>
                                    <th class="th-w-25">Turma</th>
                                    <th class="th-w-20">Sala / Ambiente</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${ag.agenda.map(a => `
                                    <tr>
                                        <td><strong>${formatDate(a.data_prova)}</strong> (${a.dia_semana_br})</td>
                                        <td><span class="badge badge-neutral">${escHtml(a.slot_label)}</span></td>
                                        <td><span class="badge ${a.papel === 'Aplicador' ? 'badge-warning' : (a.papel === 'Volante' ? 'badge-success badge-volante' : 'badge-neutral')} badge-role">${escHtml(a.papel)}</span></td>
                                        <td>
                                            <div class="font-semibold-primary">${escHtml(a.turma_desc)}</div>
                                            <div class="font-xs-muted">${escHtml(a.disc_nome)}</div>
                                        </td>
                                        <td>🏫 ${escHtml(a.ambiente_desc)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `}
            </div>
        `).join('');
    }

    window.updateEqualizeButtonState = function () {
        const anyChecked = document.querySelectorAll('.swap-checkbox:checked').length > 0;
        const applyBtn = document.getElementById('btn-aplicar-equalizacao');
        if (applyBtn) applyBtn.disabled = !anyChecked;
    };

    window.printAgendas = function () {
        const container = document.getElementById('agendas-container');
        if (!container) return;

        if (container.querySelector('.spinner')) {
            Toast.warning("Aguarde o carregamento dos dados para imprimir.");
            return;
        }

        const htmlContent = container.innerHTML;
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            Toast.error("Não foi possível abrir a janela de impressão. Verifique se o bloqueador de pop-ups está ativado.");
            return;
        }

        printWindow.document.write(`
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Agenda de Provas por Professor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #1e293b;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .agenda-prof-section {
            page-break-before: always;
            break-before: page;
            margin-bottom: 2cm;
        }
        .agenda-prof-section:first-child {
            page-break-before: avoid;
            break-before: avoid;
        }
        .agenda-prof-header {
            font-size: 16pt;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .analysis-table-wrap {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
        }
        .analysis-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            text-align: left;
        }
        .analysis-table th,
        .analysis-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .analysis-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8.5pt;
            letter-spacing: 0.05em;
        }
        .analysis-table td {
            color: #334155;
            vertical-align: middle;
        }
        .analysis-table tbody tr:last-child td {
            border-bottom: none;
        }
        .analysis-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .badge {
            font-size: 7.5pt;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 999px;
            text-transform: uppercase;
            white-space: nowrap;
            display: inline-block;
        }
        .badge-neutral { background: #f1f5f9; color: #475569; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-volante { background: #d1fae5; color: #065f46; }
        
        .font-semibold-primary {
            font-weight: 600;
            color: #0f172a;
        }
        .font-xs-muted {
            font-size: 8.5pt;
            color: #64748b;
        }
        .agenda-empty {
            font-style: italic;
            color: #64748b;
            font-size: 10pt;
        }
        @page {
            size: A4 portrait;
            margin: 1.5cm;
        }
    </style>
</head>
<body>
    ${htmlContent}
    <script>
        window.onload = function() {
            window.print();
            setTimeout(function() { window.close(); }, 500);
        };
    </script>
</body>
</html>
        `);
        printWindow.document.close();
    };

    async function applyEqualization() {
        const applyBtn = document.getElementById('btn-aplicar-equalizacao');
        if (!applyBtn || applyBtn.disabled) return;

        const selectedCheckboxes = document.querySelectorAll('.swap-checkbox:checked');
        if (selectedCheckboxes.length === 0) return;

        const selectedSwaps = [];
        selectedCheckboxes.forEach(chk => {
            const idx = parseInt(chk.dataset.idx);
            if (analysisData && analysisData.sugestoes[idx]) {
                const sugg = analysisData.sugestoes[idx];
                selectedSwaps.push({
                    grade_id:             sugg.grade_id,
                    role_field:           sugg.role_field,
                    suggested_teacher_id: sugg.suggested_teacher_id
                });
            }
        });

        applyBtn.disabled = true;
        const originalText = applyBtn.textContent;
        applyBtn.textContent = 'Aplicando...';

        try {
            const fd = new FormData();
            fd.append('action', 'apply_equalization');
            fd.append('somativa_id', gd.somativaId);
            fd.append('csrf_token', CSRF);
            fd.append('swaps', JSON.stringify(selectedSwaps));

            const res = await fetch('/api/somativas/analise_professores.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                Toast.success('Trocas aplicadas com sucesso!');
                closeAnalysisModal();
                setTimeout(() => location.reload(), 700);
            } else {
                Toast.error(data.message || 'Erro ao aplicar trocas');
                applyBtn.disabled = false;
                applyBtn.textContent = originalText;
            }
        } catch (err) {
            Toast.error('Erro de comunicação');
            applyBtn.disabled = false;
            applyBtn.textContent = originalText;
        }
    }

    // ──────────────────────────────────────────────────────────
    // Aba Inteligência — Alocação Inteligente de Aplicadores
    // ──────────────────────────────────────────────────────────
    let intelProposals = [];

    async function processIntelligentAllocation() {
        const initial = document.getElementById('intel-initial');
        const loading = document.getElementById('intel-loading');
        const result  = document.getElementById('intel-result');
        if (initial) initial.hidden = true;
        if (loading) loading.hidden = false;
        if (result)  result.hidden  = true;

        try {
            const res  = await fetch(`/api/somativas/analise_professores.php?action=preview_intelligent&somativa_id=${gd.somativaId}`);
            const data = await res.json();

            if (!data.success) {
                Toast.error(data.message || 'Erro ao gerar proposta de alocação');
                resetIntelligentTab();
                return;
            }

            intelProposals = data.proposals || [];
            renderIntelligentResult(data);

        } catch (err) {
            Toast.error('Erro de comunicação com o servidor');
            resetIntelligentTab();
        }
    }

    function resetIntelligentTab() {
        const initial = document.getElementById('intel-initial');
        const loading = document.getElementById('intel-loading');
        const result  = document.getElementById('intel-result');
        if (initial) initial.hidden = false;
        if (loading) loading.hidden = true;
        if (result)  result.hidden  = true;
        intelProposals = [];
    }

    function renderIntelligentResult(data) {
        const proposals  = data.proposals  || [];
        const stats      = data.stats      || {};
        const naapiAtivo = data.naapi_ativo;
        const loadDist   = data.load_dist  || [];

        const summaryEl = document.getElementById('intel-summary');
        if (summaryEl) {
            let badges = `
                <span class="aa-badge aa-badge-ok">✅ ${stats.total || 0} prova${stats.total !== 1 ? 's' : ''} processadas</span>
                <span class="aa-badge aa-badge-info">👤 ${stats.com_volante || 0} volantes definidos</span>
                <span class="aa-badge aa-badge-info">📋 ${stats.com_aplicador || 0} aplicadores definidos</span>
            `;
            if (naapiAtivo && stats.com_naapi !== null) {
                badges += `<span class="aa-badge aa-badge-info">♿ ${stats.com_naapi} aplic. NAAPI</span>`;
            }
            if (loadDist.length > 0) {
                badges += `<span class="aa-badge" style="background:var(--bg-surface-2nd);color:var(--text-secondary);">⚖️ máx. ${loadDist[0].load} aloc./professor</span>`;
            }
            summaryEl.innerHTML = badges;
        }

        const tbody = document.getElementById('intel-tbody');
        if (tbody) {
            if (proposals.length === 0) {
                const cols = naapiAtivo ? 5 : 4;
                tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center" style="padding:2rem;color:var(--text-muted);">Nenhuma prova normal encontrada na grade para alocar.</td></tr>`;
            } else {
                tbody.innerHTML = proposals.map(p => {
                    const volanteHtml = p.volante_nome
                        ? `<span class="badge badge-role" style="background:rgba(16,185,129,.12);color:#10b981;">Vol.</span> ${escHtml(p.volante_nome)}`
                        : `<span style="color:var(--text-muted);font-style:italic;">—</span>`;

                    const aplicadorHtml = p.aplicador_nome
                        ? escHtml(p.aplicador_nome)
                        : `<span style="color:var(--color-warning,#f59e0b);font-style:italic;">Não encontrado</span>`;

                    const naapiHtml = p.naapi_aplicador_nome
                        ? escHtml(p.naapi_aplicador_nome)
                        : `<span style="color:var(--text-muted);font-style:italic;">—</span>`;

                    return `
                    <tr>
                        <td>
                            <strong>${formatDate(p.data_prova)}</strong>
                            <div style="font-size:.75rem;color:var(--text-muted);">${escHtml(p.slot_label)}</div>
                        </td>
                        <td>
                            <div style="font-weight:600;">${escHtml(p.turma_desc)}</div>
                            <div style="font-size:.75rem;color:var(--text-muted);">${escHtml(p.disc_nome)}</div>
                        </td>
                        <td>${volanteHtml}</td>
                        <td>${aplicadorHtml}</td>
                        ${naapiAtivo ? `<td>${naapiHtml}</td>` : ''}
                    </tr>`;
                }).join('');
            }
        }

        const loading = document.getElementById('intel-loading');
        const result  = document.getElementById('intel-result');
        if (loading) loading.hidden = true;
        if (result)  result.hidden  = false;

        const confirmBtn = document.getElementById('btn-intel-confirm');
        if (confirmBtn) confirmBtn.disabled = proposals.length === 0;
    }

    async function confirmIntelligentAllocation() {
        if (!intelProposals.length) return;

        const n = intelProposals.length;

        Modal.confirm({
            title:       'Confirmar Alocação Inteligente',
            message:     `Esta ação irá substituir os aplicadores e volantes de <strong>${n} prova${n !== 1 ? 's' : ''}</strong>. Deseja continuar?`,
            confirmText: 'Confirmar e Aplicar',
            confirmClass:'btn-primary',
            onConfirm: async () => {
                const btn = document.getElementById('btn-intel-confirm');
                if (btn) { btn.disabled = true; btn.textContent = 'Aplicando...'; }

                try {
                    const fd = new FormData();
                    fd.append('action',      'apply_intelligent');
                    fd.append('somativa_id', gd.somativaId);
                    fd.append('csrf_token',  CSRF);
                    fd.append('proposals',   JSON.stringify(intelProposals));

                    const res  = await fetch('/api/somativas/analise_professores.php', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.success) {
                        Toast.success(`${data.updated} prova${data.updated !== 1 ? 's' : ''} atualizadas com sucesso!`);
                        closeAnalysisModal();
                        setTimeout(() => location.reload(), 800);
                    } else {
                        Toast.error(data.message || 'Erro ao aplicar alocação');
                        if (btn) { btn.disabled = false; btn.textContent = '✅ Confirmar e Aplicar'; }
                    }
                } catch (err) {
                    Toast.error('Erro de comunicação com o servidor');
                    if (btn) { btn.disabled = false; btn.textContent = '✅ Confirmar e Aplicar'; }
                }
            }
        });
    }

})();
