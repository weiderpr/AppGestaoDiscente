<?php
/**
 * Vértice Acadêmico — Pesquisa de Satisfação (Pública)
 */
require_once __DIR__ . '/config/database.php';

$db = getDB();
$conselhoId = (int)($_GET['c'] ?? 0);
$avaliacaoId = (int)($_GET['a'] ?? 0);

if (!$conselhoId || !$avaliacaoId) {
    header('Content-Type: text/html; charset=utf-8');
    die("<div style='text-align:center;padding:50px;font-family:sans-serif;'><h2>Acesso inválido.</h2><p>Parâmetros de pesquisa incompletos.</p></div>");
}

// Busca o conselho e a avaliação para validar e exibir nomes
$st = $db->prepare("
    SELECT cc.descricao as conselho_nome, t.description as turma_nome, a.nome as avaliacao_nome, inst.name as inst_nome
    FROM conselhos_classe cc
    JOIN turmas t ON cc.turma_id = t.id
    JOIN avaliacoes a ON cc.avaliacao_id = a.id
    JOIN institutions inst ON cc.institution_id = inst.id
    WHERE cc.id = ? AND a.id = ?
");
$st->execute([$conselhoId, $avaliacaoId]);
$info = $st->fetch();

if (!$info) {
    header('Content-Type: text/html; charset=utf-8');
    die("<div style='text-align:center;padding:50px;font-family:sans-serif;'><h2>Pesquisa não encontrada.</h2><p>Este link pode ter expirado ou estar incorreto.</p></div>");
}

// Busca as perguntas
$stP = $db->prepare("SELECT id, texto_pergunta, ordem FROM perguntas WHERE avaliacao_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY ordem ASC");
$stP->execute([$avaliacaoId]);
$perguntas = $stP->fetchAll();

$submitted = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comentario = trim($_POST['comentario'] ?? '');
    $notas = $_POST['notas'] ?? [];
    $dispositivo = $_SERVER['HTTP_USER_AGENT'];

    if (empty($notas)) {
        $error = "Por favor, responda as perguntas da avaliação.";
    } else {
        $db->beginTransaction();
        try {
            $st = $db->prepare("INSERT INTO respostas_avaliacao (avaliacao_id, conselho_id, comentario, dispositivo) VALUES (?, ?, ?, ?)");
            $st->execute([$avaliacaoId, $conselhoId, $comentario, $dispositivo]);
            $respostaId = $db->lastInsertId();

            $stInsertP = $db->prepare("INSERT INTO respostas_perguntas (resposta_id, pergunta_id, nota) VALUES (?, ?, ?)");
            foreach ($perguntas as $p) {
                $nota = (int)($notas[$p['id']] ?? 0);
                $stInsertP->execute([$respostaId, $p['id'], $nota]);
            }
            $db->commit();
            $submitted = true;
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erro ao processar sua resposta. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Avaliação — <?= htmlspecialchars($info['conselho_nome']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg: #0f172a;
            --card: rgba(30, 41, 59, 0.7);
            --border: rgba(255, 255, 255, 0.1);
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --star-off: #334155;
            --star-on: #fbbf24;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0, transparent 50%), 
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.15) 0, transparent 50%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 500px;
            background: var(--card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .header { text-align: center; margin-bottom: 32px; }
        .logo { font-size: 2rem; margin-bottom: 8px; }
        .inst-name { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--primary); font-weight: 700; margin-bottom: 4px; display: block; }
        .title { font-size: 1.25rem; font-weight: 700; color: var(--text); margin-bottom: 4px; }
        .subtitle { font-size: 0.875rem; color: var(--text-muted); }

        .question-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .question-text { font-size: 0.9375rem; font-weight: 500; margin-bottom: 16px; color: #e2e8f0; }

        /* Estrelas */
        .stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 8px;
        }
        .stars input { display: none; }
        .stars label {
            font-size: 28px;
            color: var(--star-off);
            cursor: pointer;
            transition: color 0.2s, transform 0.2s;
        }
        .stars label:hover,
        .stars label:hover ~ label,
        .stars input:checked ~ label {
            color: var(--star-on);
        }
        .stars label:active { transform: scale(1.2); }

        .form-group { margin-bottom: 24px; }
        .label { display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-muted); }
        .textarea {
            width: 100%;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 16px;
            color: var(--text);
            font-family: inherit;
            font-size: 0.875rem;
            resize: vertical;
            min-height: 100px;
            outline: none;
            transition: border-color 0.2s;
        }
        .textarea:focus { border-color: var(--primary); }

        .btn {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.4);
        }
        .btn:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4); }
        .btn:active { transform: translateY(0); }

        .success-msg { text-align: center; padding: 20px 0; }
        .success-icon { font-size: 4rem; margin-bottom: 16px; display: block; }
        .success-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; }

        .footer-note { text-align: center; font-size: 0.75rem; color: var(--text-muted); margin-top: 32px; }

        @media (max-width: 480px) {
            .container { padding: 24px; }
            .stars label { font-size: 24px; }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($submitted): ?>
        <div class="success-msg">
            <span class="success-icon">✨</span>
            <h2 class="success-title">Obrigado!</h2>
            <p class="subtitle">Sua avaliação foi registrada com sucesso. Sua opinião é muito importante para nós.</p>
            <div style="margin-top: 32px;">
                <button class="btn" onclick="window.close();">Fechar</button>
            </div>
        </div>
    <?php else: ?>
        <div class="header">
            <span class="inst-name"><?= htmlspecialchars($info['inst_nome']) ?></span>
            <h1 class="title"><?= htmlspecialchars($info['avaliacao_nome']) ?></h1>
            <p class="subtitle"><?= htmlspecialchars($info['conselho_nome']) ?> • <?= htmlspecialchars($info['turma_nome']) ?></p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; padding: 12px; border-radius: 12px; margin-bottom: 20px; font-size: 0.875rem; text-align: center;">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php foreach ($perguntas as $p): ?>
                <div class="question-card">
                    <div class="question-text"><?= htmlspecialchars($p['texto_pergunta']) ?></div>
                    <div class="stars">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="notas[<?= $p['id'] ?>]" id="star-<?= $p['id'] ?>-<?= $i ?>" value="<?= $i ?>" required>
                            <label for="star-<?= $p['id'] ?>-<?= $i ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="form-group">
                <label class="label">Comentários Adicionais (Opcional)</label>
                <textarea name="comentario" class="textarea" placeholder="Sua opinião sincera ajuda a melhorar nossos processos..."></textarea>
            </div>

            <button type="submit" class="btn">Enviar Avaliação</button>
        </form>
    <?php endif; ?>

    <div class="footer-note">
        Powered by Vértice Acadêmico • Avaliação Anônima
    </div>
</div>

</body>
</html>
