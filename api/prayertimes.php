<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/http.php';
require __DIR__ . '/../includes/prayer-provider.php';

header('Content-Type: application/json; charset=UTF-8');
http_set_cors_headers();

$locationId = (int) ($_GET['location_id'] ?? 0);
app_debug_log('api.prayertimes.request', ['location_id' => $locationId]);

if ($locationId <= 0) {
    http_response_code(400);
    app_debug_log('api.prayertimes.error', ['error' => 'location_id parametresi gerekli.']);
    echo json_encode(['error' => 'location_id parametresi gerekli.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $normalized = ['value' => app_prayer_provider_get_prayer_times($locationId)];
    app_debug_log('api.prayertimes.success', [
        'location_id' => $locationId,
        'count' => is_array($normalized['value'] ?? null) ? count($normalized['value']) : 0,
    ]);
    echo json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(502);
    app_debug_log('api.prayertimes.exception', [
        'location_id' => $locationId,
        'error' => $error->getMessage(),
    ]);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
