<?php
/**
 * Vértice Acadêmico — Helper para respostas AJAX padronizadas
 */

class AjaxResponse {
    public static function success(array $data = [], string $message = 'OK'): void {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    public static function error(string $message, int $httpCode = 400): void {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($httpCode);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }

    public static function json(array $data): void {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }
}

function ajax_success(array $data = [], string $message = 'OK'): void {
    AjaxResponse::success($data, $message);
}

function ajax_error(string $message, int $httpCode = 400): void {
    AjaxResponse::error($message, $httpCode);
}

function ajax_json(array $data): void {
    AjaxResponse::json($data);
}
