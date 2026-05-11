<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/http.php';

header('Content-Type: application/json; charset=UTF-8');
http_set_cors_headers();

try {
    app_debug_log('api.ip-geolocation.request');
    $payload = http_fetch_json('https://ipapi.co/json/');
    app_debug_log('api.ip-geolocation.success', [
        'city' => $payload['city'] ?? null,
        'region' => $payload['region'] ?? null,
        'latitude' => $payload['latitude'] ?? null,
        'longitude' => $payload['longitude'] ?? null,
    ]);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(502);
    app_debug_log('api.ip-geolocation.exception', ['error' => $error->getMessage()]);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
