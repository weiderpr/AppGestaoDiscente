/**
 * Vértice Acadêmico — Lógica do Popover de Sanções Disciplinares (Independente)
 */

window.SancaoPopover = (function() {
    let popoverEl = null;
    let currentTrigger = null;
    let dataCache = {};
    let hideTimeout = null;

    function init() {
        if (popoverEl) return;

        // Criar o elemento do popover
        popoverEl = document.createElement('div');
        popoverEl.className = 'sancao-popover';
        popoverEl.innerHTML = `
            <div class="sancao-popover-header">
                <span class="sancao-popover-title">Detalhamento Disciplinar</span>
                <span id="sancao-popover-count" style="font-size: 0.7rem; font-weight: 700; color: #ef4444; background: #fff; padding: 1px 6px; border-radius: 10px; border: 1px solid #fee2e2;">0</span>
            </div>
            <div class="sancao-popover-content" id="sancao-popover-list">
                <div style="padding: 1rem; text-align: center; color: var(--text-muted); font-size: 0.8rem;">Carregando...</div>
            </div>
            <div class="sancao-popover-footer">
                Vértice Acadêmico • Histórico Unificado
            </div>
        `;
        document.body.appendChild(popoverEl);

        // Listeners globais (delegação)
        document.body.addEventListener('mouseover', handleMouseOver);
        document.body.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('scroll', hidePopover, true);
        
        // Persistência quando o mouse entra no próprio popover
        popoverEl.addEventListener('mouseenter', () => clearTimeout(hideTimeout));
        popoverEl.addEventListener('mouseleave', hidePopover);
    }

    function handleMouseOver(e) {
        const trigger = e.target.closest('.sancao-popover-trigger');
        if (!trigger) return;

        clearTimeout(hideTimeout);
        currentTrigger = trigger;
        const alunoId = trigger.dataset.alunoId;

        if (!alunoId) return;

        showPopover(trigger);
        loadData(alunoId);
    }

    function handleMouseMove(e) {
        if (!currentTrigger && !popoverEl.classList.contains('show')) return;
        
        // Verifica se o mouse saiu do gatilho e não entrou no popover
        const trigger = e.target.closest('.sancao-popover-trigger');
        const popover = e.target.closest('.sancao-popover');
        
        if (!trigger && !popover) {
            hidePopover();
        }
    }

    function showPopover(trigger) {
        popoverEl.classList.add('show');
        positionPopover(trigger);
    }

    function hidePopover() {
        hideTimeout = setTimeout(() => {
            popoverEl.classList.remove('show');
            currentTrigger = null;
        }, 300);
    }

    function positionPopover(trigger) {
        const rect = trigger.getBoundingClientRect();
        const popoverRect = popoverEl.getBoundingClientRect();
        
        let top = rect.bottom + window.scrollY + 8;
        let left = rect.left + window.scrollX;

        // Ajuste se sair da tela por baixo
        if (top + popoverRect.height > window.innerHeight + window.scrollY) {
            top = rect.top + window.scrollY - popoverRect.height - 8;
            popoverEl.style.setProperty('--pointer-top', 'auto');
            popoverEl.style.setProperty('--pointer-bottom', '-6px');
            popoverEl.classList.add('popover-top');
        } else {
            popoverEl.classList.remove('popover-top');
        }

        // Ajuste se sair da tela pela direita
        if (left + popoverRect.width > window.innerWidth) {
            left = window.innerWidth - popoverRect.width - 15;
        }

        popoverEl.style.top = `${top}px`;
        popoverEl.style.left = `${left}px`;
    }

    async function loadData(alunoId) {
        const listEl = document.getElementById('sancao-popover-list');
        const countEl = document.getElementById('sancao-popover-count');

        // Check Cache
        if (dataCache[alunoId]) {
            renderList(dataCache[alunoId]);
            return;
        }

        listEl.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--text-muted); font-size: 0.8rem;">🔍 Buscando registros...</div>';

        try {
            const resp = await fetch(`/sancao/ajax.php?action=get_history&aluno_id=${alunoId}`);
            const result = await resp.json();

            if (result.status === 'success') {
                dataCache[alunoId] = result.data;
                renderList(result.data);
            } else {
                listEl.innerHTML = '<div style="padding: 1rem; text-align: center; color: #ef4444; font-size: 0.8rem;">Erro ao carregar histórico.</div>';
            }
        } catch (e) {
            listEl.innerHTML = '<div style="padding: 1rem; text-align: center; color: #ef4444; font-size: 0.8rem;">Erro de conexão.</div>';
        }
    }

    function renderList(sancoes) {
        const listEl = document.getElementById('sancao-popover-list');
        const countEl = document.getElementById('sancao-popover-count');
        
        countEl.innerText = sancoes.length;

        if (sancoes.length === 0) {
            listEl.innerHTML = '<div style="padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.8125rem;">Nenhum registro encontrado.</div>';
            return;
        }

        let html = '';
        sancoes.forEach(s => {
            const dateStr = new Date(s.data_sancao + 'T00:00:00').toLocaleDateString('pt-BR');
            const statusClass = getStatusClass(s.status);
            
            html += `
                <div class="sancao-popover-item">
                    <div class="sancao-popover-item-header">
                        <span class="sancao-popover-date">${dateStr}</span>
                        <span class="sancao-popover-status ${statusClass}">${s.status}</span>
                    </div>
                    <div class="sancao-popover-type">${s.tipo_titulo}</div>
                    ${s.observacoes ? `<div class="sancao-popover-obs" title="${s.observacoes.replace(/"/g, '&quot;')}">${s.observacoes}</div>` : ''}
                </div>
            `;
        });
        listEl.innerHTML = html;
        
        // Re-position if content height changed significantly
        if (currentTrigger) positionPopover(currentTrigger);
    }

    function getStatusClass(status) {
        switch(status) {
            case 'Concluído': return 'badge-success';
            case 'Cancelado': return 'badge-danger';
            default: return 'badge-warning';
        }
    }

    return {
        init: init
    };
})();

// Auto-init on load
document.addEventListener('DOMContentLoaded', () => SancaoPopover.init());
