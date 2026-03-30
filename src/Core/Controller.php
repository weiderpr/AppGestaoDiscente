<?php
/**
 * Vértice Acadêmico — Controller Base
 */

namespace Core;

abstract class Controller {
    protected function render(string $view, array $data = []): void {
        extract($data);
        
        $viewFile = __DIR__ . '/../../views/' . $view . '.php';
        
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            $this->error("View não encontrada: {$view}");
        }
    }

    protected function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function success(string $message = 'OK', array $data = []): void {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    protected function error(string $message, int $statusCode = 400): void {
        $this->json([
            'success' => false,
            'message' => $message
        ], $statusCode);
    }

    protected function redirect(string $uri): void {
        header("Location: {$uri}");
        exit;
    }

    protected function back(): void {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    protected function requireLogin(): void {
        require_once __DIR__ . '/../../includes/auth.php';
        
        if (!isLoggedIn()) {
            $this->redirect('/login.php');
        }
    }

    protected function getCurrentUser(): ?array {
        require_once __DIR__ . '/../../includes/auth.php';
        return getCurrentUser();
    }

    protected function getCurrentInstitution(): array {
        require_once __DIR__ . '/../../includes/auth.php';
        return getCurrentInstitution();
    }

    protected function hasPermission(array $profiles): void {
        $user = $this->getCurrentUser();
        
        if (!$user || !in_array($user['profile'], $profiles)) {
            $this->redirect('/dashboard.php');
        }
    }

    protected function checkPermission(string $resource): void {
        require_once __DIR__ . '/../../includes/auth.php';
        hasDbPermission($resource, true); // true = auto redirect if failed
    }

    protected function input(string $key, $default = null) {
        return $_REQUEST[$key] ?? $default;
    }

    protected function get(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }

    protected function post(string $key, $default = null) {
        return $_POST[$key] ?? $default;
    }

    protected function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
}
