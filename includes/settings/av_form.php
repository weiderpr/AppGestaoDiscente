<?php
/**
 * Vértice Acadêmico — Partial: Formulário de Avaliação
 */
$id = (int)($_GET['id'] ?? 0);
$avaliacao = null;
$perguntaList = [];

if ($id) {
    $st = $db->prepare("SELECT * FROM avaliacoes WHERE id = ? AND deleted_at IS NULL");
    $st->execute([$id]);
    $avaliacao = $st->fetch();
    
    if ($avaliacao) {
        $stP = $db->prepare("SELECT * FROM perguntas WHERE avaliacao_id = ? AND deleted_at IS NULL ORDER BY ordem ASC");
        $stP->execute([$id]);
        $perguntaList = $stP->fetchAll();
    }
}

// Tipos para o select
$tipos = $db->query("SELECT id, nome FROM tipos_avaliacao WHERE deleted_at IS NULL ORDER BY nome ASC")->fetchAll();
?>

<style>
.form-shell-row { display: grid; grid-template-columns: 1fr 280px; gap: 1.5rem; align-items: start; }
@media (max-width: 992px) { .form-shell-row { grid-template-columns: 1fr; } }
.question-item { 
    background: var(--bg-surface); border: 1px solid var(--border-color); 
    border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 1rem;
    position: relative; display: flex; gap: 1rem; align-items: flex-start;
    box-shadow: var(--shadow-sm); transition: transform .2s ease;
}
.question-item:hover { transform: translateY(-2px); border-color: var(--color-primary-light); }
.question-number {
    width: 28px; height: 28px; border-radius: 50%; background: var(--color-primary);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: .75rem; font-weight: 700; flex-shrink: 0; margin-top: .25rem;
}
.question-content { flex: 1; }
.remove-question {
    width: 28px; height: 28px; border-radius: var(--radius-md);
    background: var(--bg-surface-2nd); border: 1px solid var(--border-color);
    color: var(--text-muted); cursor: pointer; display: flex;
    align-items: center; justify-content: center; transition: all .2s;
    font-size: .875rem; flex-shrink: 0;
}
.remove-question:hover { background: #fef2f2; color: var(--color-danger); border-color: var(--color-danger); }
</style>

<form id="evaluationForm" class="fade-in">
    <input type="hidden" name="id" value="<?= $id ?>">
    <?= csrf_field() ?>

    <div class="card settings-card" style="margin-bottom:1.5rem;">
        <div class="settings-card-header">
            <div class="settings-card-icon">📝</div>
            <div style="flex:1;">
                <div class="settings-card-title"><?= $id ? '✏️ Editar' : '➕ Cadastrar' ?> Avaliação</div>
                <div class="settings-card-desc">Configure o nome, tipo e as perguntas do questionário.</div>
            </div>
            <div>
                <a href="?section=avaliacoes&sub=lista" class="btn btn-secondary btn-sm">Cancelar</a>
            </div>
        </div>

        <div class="card-body">
            <div class="form-shell-row">
                <div class="form-shell-main">
                    <div class="form-group">
                        <label class="form-label">Nome da Avaliação <span class="required">*</span></label>
                        <input type="text" name="nome" class="form-control" placeholder="Ex: Pesquisa de Satisfação" 
                               value="<?= htmlspecialchars($avaliacao['nome'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tipo de Avaliação <span class="required">*</span></label>
                        <select name="tipo_id" class="form-control" required>
                            <option value="" disabled <?= !$id ? 'selected' : '' ?>>Selecione o tipo...</option>
                            <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($avaliacao['tipo_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-shell-side">
                    <div style="background:var(--bg-surface-2nd);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:1.25rem;display:flex;flex-direction:column;gap:1.25rem;position:sticky;top:1rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span class="text-muted" style="font-size:.875rem;">Total de Perguntas:</span>
                            <span id="questionCount" style="font-weight:700;color:var(--color-primary);font-size:1.125rem;">0</span>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="btnSave">
                            💾 <?= $id ? 'Salvar Alterações' : 'Salvar Avaliação' ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card settings-card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;border-bottom:1px solid var(--border-color);">
            <span class="card-title" style="font-size:1rem;font-weight:600;">Área de Perguntas</span>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addQuestion()">➕ Adicionar Pergunta</button>
        </div>
        <div class="card-body">
            <div id="questionsList" class="questions-container"></div>
            <div id="emptyQuestions" style="display:none; text-align:center; padding:3rem; color:var(--text-muted); border:2px dashed var(--border-color); border-radius:var(--radius-lg);">
                <div style="font-size:2rem;margin-bottom:1rem;">📋</div>
                Nenhuma pergunta adicionada ainda.<br>Clique no botão acima para começar.
            </div>
        </div>
    </div>
</form>

<script>
(function() {
    const list = document.getElementById('questionsList');
    const empty = document.getElementById('emptyQuestions');
    const counter = document.getElementById('questionCount');
    const form = document.getElementById('evaluationForm');
    const btnSave = document.getElementById('btnSave');

    let questions = <?= json_encode(array_map(function($p) {
        return ['id' => $p['id'], 'text' => $p['texto_pergunta']];
    }, $perguntaList)) ?>;

    // Inicializar IDs para novas perguntas
    let nextId = questions.length > 0 ? Math.max(...questions.map(q => q.id || 0)) + 1 : 1;

    function renderQuestions() {
        list.innerHTML = '';
        if (questions.length === 0) {
            empty.style.display = 'block';
        } else {
            empty.style.display = 'none';
            questions.forEach((q, idx) => {
                const div = document.createElement('div');
                div.className = 'question-item fade-in';
                div.innerHTML = `
                    <div class="question-number">${idx + 1}</div>
                    <div class="question-content">
                        <textarea name="perguntas[]" class="form-control" rows="2" placeholder="Digite a pergunta..." required>${q.text}</textarea>
                    </div>
                    <button type="button" class="remove-question" onclick="confirmRemoveQuestion(${q.id})" title="Remover">✕</button>
                `;
                list.appendChild(div);
            });
        }
        counter.textContent = questions.length;
    }

    window.addQuestion = function() {
        // Antes de adicionar, capturar os textos atuais para não perder o que foi digitado
        syncQuestions();
        questions.push({ id: nextId++, text: '' });
        renderQuestions();
        // Focar na nova pergunta
        const textareas = list.querySelectorAll('textarea');
        if (textareas.length > 0) textareas[textareas.length - 1].focus();
    };

    window.confirmRemoveQuestion = function(id) {
        confirmModal({
            title: 'Remover Pergunta',
            message: 'Deseja realmente remover esta pergunta?',
            confirmText: 'Remover',
            confirmClass: 'btn-danger',
            onConfirm: () => {
                syncQuestions();
                questions = questions.filter(q => q.id !== id);
                renderQuestions();
            }
        });
    };

    function syncQuestions() {
        const textareas = list.querySelectorAll('textarea');
        textareas.forEach((ta, idx) => {
            if (questions[idx]) questions[idx].text = ta.value;
        });
    }

    renderQuestions();

    // Submit via AJAX
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (questions.length === 0) {
            showError('Adicione pelo menos uma pergunta.');
            return;
        }

        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner spinner-sm"></span> Salvando...';

        const formData = new FormData(this);

        fetch('/avaliacoes/ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location = '?section=avaliacoes&sub=lista&success=' + encodeURIComponent(data.message);
            } else {
                showError(data.message || 'Erro ao salvar avaliação.');
                btnSave.disabled = false;
                btnSave.innerHTML = '💾 <?= $id ? "Salvar Alterações" : "Salvar Avaliação" ?>';
            }
        })
        .catch(err => {
            console.error(err);
            showError('Erro na requisição. Tente novamente.');
            btnSave.disabled = false;
            btnSave.innerHTML = '💾 <?= $id ? "Salvar Alterações" : "Salvar Avaliação" ?>';
        });
    });
})();
</script>
