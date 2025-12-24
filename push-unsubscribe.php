<?php

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$raw = (string) file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido.']);
    exit;
}

$endpoint = trim((string) ($payload['endpoint'] ?? ''));
if ($endpoint === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Endpoint requerido.']);
    exit;
}

if (function_exists('nammu_remove_push_subscription')) {
    nammu_remove_push_subscription($endpoint);
}

echo json_encode(['ok' => true]);
