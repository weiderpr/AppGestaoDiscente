<?php
/**
 * Vértice Acadêmico — Funções de Autenticação
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Perfis de acesso disponíveis
const PROFILES = [
    'Administrador',
    'Coordenador',
    'Diretor',
    'Professor',
    'Pedagogo',
    'Assistente Social',
    'Naapi',
    'Psicólogo',
    'Outro',
];

/**
 * Verifica se o usuário está logado
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Redireciona para login se não autenticado
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Retorna dados do usuário logado
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;

    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, email, phone, photo, profile, theme FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Tenta autenticar com email e senha
 * Retorna array do usuário ou null em caso de falha
 */
function loginUser(string $email, string $password): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return null;
    }

    // Cria sessão
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_theme'] = $user['theme'];
    // Limpa instituição anterior (será selecionada em select_institution.php)
    unset($_SESSION['current_institution_id'], $_SESSION['current_institution_name'], $_SESSION['current_institution_photo']);

    return $user;
}

/**
 * Registra novo usuário
 * Retorna ['success' => true] ou ['error' => 'mensagem']
 */
function registerUser(array $data): array {
    $db = getDB();

    // Verifica se email já existe
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower(trim($data['email']))]);
    if ($stmt->fetch()) {
        return ['error' => 'Este e-mail já está cadastrado no sistema.'];
    }

    // Upload de foto
    $photoPath = null;
    if (!empty($data['photo_tmp']) && !empty($data['photo_name'])) {
        $ext       = strtolower(pathinfo($data['photo_name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            return ['error' => 'Formato de imagem inválido. Use JPG, PNG, GIF ou WEBP.'];
        }
        $destDir   = __DIR__ . '/../assets/uploads/avatars/';
        $fileName  = uniqid('avatar_', true) . '.' . $ext;
        if (!move_uploaded_file($data['photo_tmp'], $destDir . $fileName)) {
            return ['error' => 'Falha ao salvar a foto de perfil.'];
        }
        $photoPath = 'assets/uploads/avatars/' . $fileName;
    }

    // Insere usuário
    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password, phone, photo, profile, theme)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        trim($data['name']),
        strtolower(trim($data['email'])),
        $hash,
        trim($data['phone'] ?? ''),
        $photoPath,
        $data['profile'],
        'light',
    ]);

    $userId = (int) $db->lastInsertId();

    // Loga automaticamente após registro
    session_regenerate_id(true);
    $_SESSION['user_id']    = $userId;
    $_SESSION['user_name']  = trim($data['name']);
    $_SESSION['user_theme'] = 'light';

    return ['success' => true];
}


/**
 * Retorna a instituição atualmente selecionada na sessão
 */
function getCurrentInstitution(): array {
    return [
        'id'    => $_SESSION['current_institution_id']   ?? null,
        'name'  => $_SESSION['current_institution_name'] ?? null,
        'photo' => $_SESSION['current_institution_photo'] ?? null,
    ];
}

/**
 * Verifica quantas instituições o usuário tem vínculo ativo
 */
function countUserInstitutions(int $userId): int {
    $db = getDB();
    $st = $db->prepare(
        'SELECT COUNT(*) FROM user_institutions ui
         INNER JOIN institutions i ON i.id = ui.institution_id
         WHERE ui.user_id = ? AND i.is_active = 1'
    );
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

/**
 * Encerra a sessão
 */
function logoutUser(): void {
    session_unset();
    session_destroy();
    header('Location: /login.php');
    exit;
}
