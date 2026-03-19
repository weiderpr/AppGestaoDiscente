<?php
/**
 * Vértice Acadêmico — Registro desabilitado
 * O cadastro de usuários é feito apenas pelo Administrador.
 */
require_once __DIR__ . '/includes/auth.php';

// Se logado e for admin, redireciona para a gestão de usuários
if (isLoggedIn()) {
    $u = getCurrentUser();
    if ($u && $u['profile'] === 'Administrador') {
        header('Location: /admin/users.php');
    } else {
        header('Location: /dashboard.php');
    }
    exit;
}

// Visitante sem sessão → login
header('Location: /login.php');
exit;
