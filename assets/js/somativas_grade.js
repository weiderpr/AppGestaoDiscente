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
            selectedDiv.innerHTML = `
                <span>✓ ${escHtml(label)}</span>
                <button type="button" class="clear-selection" onclick="clearField('${fieldId}')">✕</button>
            `;
            selectedDiv.hidden = false;
        }
    }

    window.clearField = function (fieldId) {
        const key         = fieldKey(fieldId);
        const hiddenInput = document.getElementById(`field-${key}-id`);
        const selectedDiv = document.getElementById(`selected-${key}`);
        if (hiddenInput) hiddenInput.value = '';
        if (selectedDiv) selectedDiv.hidden = true;
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

})();
