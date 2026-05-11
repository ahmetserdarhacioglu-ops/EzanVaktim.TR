<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/http.php';
require __DIR__ . '/../includes/prayer-provider.php';

header('Content-Type: application/json; charset=UTF-8');
http_set_cors_headers();

$query = trim((string) ($_GET['q'] ?? ''));
app_debug_log('api.location-search.request', ['query' => $query]);

if ($query === '') {
    http_response_code(400);
    app_debug_log('api.location-search.error', ['error' => 'q parametresi gerekli.']);
    echo json_encode(['error' => 'q parametresi gerekli.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $results = app_prayer_provider_search_locations($query);

    app_debug_log('api.location-search.success', [
        'query' => $query,
        'count' => count($results),
        'provider' => app_prayer_provider_settings()['key'],
    ]);

    echo json_encode(['value' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(502);
    app_debug_log('api.location-search.exception', [
        'query' => $query,
        'error' => $error->getMessage(),
    ]);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
