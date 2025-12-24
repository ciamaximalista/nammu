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

$settings = function_exists('nammu_ads_settings') ? nammu_ads_settings() : [];
if (($settings['push_enabled'] ?? 'off') !== 'on') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Push desactivado.']);
    exit;
}

if (function_exists('nammu_has_stats_consent') && !nammu_has_stats_consent()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Consentimiento requerido.']);
    exit;
}

$raw = (string) file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido.']);
    exit;
}

if (!function_exists('nammu_store_push_subscription')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Sistema no disponible.']);
    exit;
}

$stored = nammu_store_push_subscription($payload);
if (!$stored) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
    exit;
}

$count = function_exists('nammu_push_subscriber_count') ? nammu_push_subscriber_count() : 0;
echo json_encode(['ok' => true, 'count' => $count]);
