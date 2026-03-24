<?php
/**
 * Vértice Acadêmico — API: Set Language
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/i18n.php';

header('Content-Type: application/json');

$locale = $_GET['locale'] ?? $_POST['locale'] ?? '';

$supported = I18n::getSupportedLocales();

if (in_array($locale, $supported)) {
    I18n::setLocale($locale);
    echo json_encode(['success' => true, 'locale' => $locale]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid locale']);
}
