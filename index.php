<?php
/**
 * Vértice Acadêmico — Ponto de Entrada
 * Redireciona para dashboard se logado, ou para login
 */

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . getHomepage());
} else {
    header('Location: /login.php');
}
exit;
