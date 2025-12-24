<?php

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if (!function_exists('nammu_dispatch_push_queue')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Sistema no disponible.']);
    exit;
}

$summary = nammu_dispatch_push_queue();
echo json_encode(['ok' => true, 'summary' => $summary], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
