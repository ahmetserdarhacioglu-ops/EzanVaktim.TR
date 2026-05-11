<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/http.php';

header('Content-Type: application/json; charset=UTF-8');
http_set_cors_headers();

function resolve_cache_path(): string
{
    return dirname(__DIR__) . '/storage/cache/location-resolve.json';
}

function load_resolve_cache(): array
{
    $path = resolve_cache_path();

    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (!is_array($decoded)) {
        return [];
    }

    $items = [];

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $key = md5(json_encode([
            $item['display_name'] ?? null,
            $item['lat'] ?? null,
            $item['lon'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $items[$key] = $item;
    }

    return $items;
}

function save_resolve_cache(array $items): void
{
    $path = resolve_cache_path();
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function find_cached_reverse_geocode_payload(string $lat, string $lon, float $threshold = 0.02): ?array
{
    $targetLat = (float) $lat;
    $targetLon = (float) $lon;

    if (!is_finite($targetLat) || !is_finite($targetLon)) {
        return null;
    }

    $bestMatch = null;
    $bestDistance = INF;

    foreach (load_resolve_cache() as $item) {
        $itemLat = isset($item['lat']) ? (float) $item['lat'] : NAN;
        $itemLon = isset($item['lon']) ? (float) $item['lon'] : NAN;

        if (!is_finite($itemLat) || !is_finite($itemLon)) {
            continue;
        }

        $distance = sqrt((($itemLat - $targetLat) ** 2) + (($itemLon - $targetLon) ** 2));

        if ($distance > $threshold || $distance >= $bestDistance) {
            continue;
        }

        $bestDistance = $distance;
        $bestMatch = $item;
    }

    return $bestMatch;
}

function cache_reverse_geocode_payload(array $payload): void
{
    $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];

    if ($address === []) {
        return;
    }

    $cache = load_resolve_cache();
    $item = [
        'display_name' => $payload['display_name'] ?? null,
        'address' => $address,
        'lat' => isset($payload['lat']) ? (string) $payload['lat'] : null,
        'lon' => isset($payload['lon']) ? (string) $payload['lon'] : null,
    ];
    $key = md5(json_encode([
        $item['display_name'] ?? null,
        $item['lat'] ?? null,
        $item['lon'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $cache[$key] = $item;
    save_resolve_cache($cache);
}

$lat = trim((string) ($_GET['lat'] ?? ''));
$lon = trim((string) ($_GET['lon'] ?? ''));
app_debug_log('api.reverse-geocode.request', ['lat' => $lat, 'lon' => $lon]);

if ($lat === '' || $lon === '') {
    http_response_code(400);
    app_debug_log('api.reverse-geocode.error', ['error' => 'lat ve lon parametreleri gerekli.']);
    echo json_encode(['error' => 'lat ve lon parametreleri gerekli.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $cachedPayload = find_cached_reverse_geocode_payload($lat, $lon);

    if ($cachedPayload !== null) {
        app_debug_log('api.reverse-geocode.cache-hit', [
            'lat' => $lat,
            'lon' => $lon,
            'display_name' => $cachedPayload['display_name'] ?? null,
        ]);
        echo json_encode($cachedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $payload = http_fetch_json(
        'https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=tr&lat='
        . rawurlencode($lat)
        . '&lon='
        . rawurlencode($lon),
        ['Referer: http://localhost']
    );
    cache_reverse_geocode_payload($payload);

    app_debug_log('api.reverse-geocode.success', [
        'lat' => $lat,
        'lon' => $lon,
        'display_name' => $payload['display_name'] ?? null,
    ]);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    $cachedPayload = find_cached_reverse_geocode_payload($lat, $lon);

    if ($cachedPayload !== null) {
        app_debug_log('api.reverse-geocode.cache-fallback', [
            'lat' => $lat,
            'lon' => $lon,
            'error' => $error->getMessage(),
            'display_name' => $cachedPayload['display_name'] ?? null,
        ]);
        echo json_encode($cachedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(502);
    app_debug_log('api.reverse-geocode.exception', [
        'lat' => $lat,
        'lon' => $lon,
        'error' => $error->getMessage(),
    ]);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
