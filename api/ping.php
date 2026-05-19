<?php
/**
 * Vértice Acadêmico — Heartbeat Ping Endpoint
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo json_encode(['status' => 'online', 'timestamp' => time()]);
