<?php
/**
 * Componente Modal Reutilizável
 * 
 * Uso:
 * include __DIR__ . '/modal.php';
 * 
 * No HTML da página:
 * - Defina os estilos CSS do modal (se necessário)
 * - Use as funções openModal('id') e closeModal('id') ou globalModal('id')
 */

/**
 * Estilos CSS globais para modais
 * Adicione esta função no <head> de cada página que usar modais
 */
function renderModalStyles() {
    ?>
    <style>
    .modal-backdrop { 
        position:fixed; inset:0; z-index:3000; background:rgba(0,0,0,.5);
        backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center;
        padding:1rem; opacity:0; visibility:hidden; transition:all .25s ease; 
    }
    .modal-backdrop.show { opacity:1; visibility:visible; display:flex !important; }
    .modal { 
        background:var(--bg-surface); border:1px solid var(--border-color);
        border-radius:var(--radius-xl); width:100%; max-width:500px;
        max-height:90vh; overflow-y:auto; box-shadow:0 25px 60px rgba(0,0,0,.3);
        transform:translateY(20px) scale(.97); transition:all .25s ease; 
    }
    .modal-backdrop.show .modal { transform:translateY(0) scale(1); }
    .modal-header { 
        padding:1.5rem; border-bottom:1px solid var(--border-color);
        display:flex; align-items:center; justify-content:space-between; 
    }
    .modal-title { font-size:1.0625rem; font-weight:700; color:var(--text-primary); }
    .modal-close { 
        width:32px; height:32px; border-radius:var(--radius-md);
        border:1px solid var(--border-color); background:var(--bg-surface);
        cursor:pointer; display:flex; align-items:center; justify-content:center;
        color:var(--text-muted); font-size:1.125rem; transition:all var(--transition-fast); 
    }
    .modal-close:hover { background:var(--bg-hover); }
    .modal-body { padding:1.5rem; display:flex; flex-direction:column; gap:1rem; }
    .modal-footer { 
        padding:1rem 1.5rem; border-top:1px solid var(--border-color);
        display:flex; gap:.75rem; justify-content:flex-end; 
    }
    </style>
    <?php
}

/**
 * Script JS global para modais
 * Adicione esta função antes do </body> de cada página
 */
function renderModalScripts() {
    ?>
    <script>
    function openModal(id) {
        var modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'flex'; // Garante o display imediato
            setTimeout(function() {
                modal.classList.add('show');
            }, 10);
            document.body.style.overflow = 'hidden';
            document.body.classList.add('modal-open');
        }
    }
    function closeModal(id) {
        // Se id não for passado, tenta fechar o modal aberto mais recente (opcional, mas seguro)
        if (!id) {
            var openModal = document.querySelector('.modal-backdrop.show');
            if (openModal) id = openModal.id;
            else return;
        }

        var modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
            document.body.classList.remove('modal-open');
            
            // Espera a transição acabar para esconder o display
            setTimeout(function() {
                if (!modal.classList.contains('show')) {
                    modal.style.display = 'none';
                }
            }, 250);
        }
    }
    function globalModal(id, action) {
        if (action === 'open') {
            openModal(id);
        } else {
            closeModal(id);
        }
    }
    // Event listeners automáticos (Delegação Global)
    document.addEventListener('DOMContentLoaded', function() {
        // Fechar ao clicar no backdrop (Funciona para modais estáticos e dinâmicos via modal.php)
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-backdrop')) {
                closeModal(e.target.id);
            }
        });
        // Fechar ao pressionar Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.show').forEach(function(modal) {
                    modal.classList.remove('show');
                });
                document.body.style.overflow = '';
            }
        });
    });
    </script>
    <?php
}
