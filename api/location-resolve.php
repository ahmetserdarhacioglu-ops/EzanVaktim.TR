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

function merge_resolve_cache_items(array $items): void
{
    if ($items === []) {
        return;
    }

    $cache = load_resolve_cache();

    foreach ($items as $item) {
        $key = md5(json_encode([
            $item['display_name'] ?? null,
            $item['lat'] ?? null,
            $item['lon'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $cache[$key] = $item;
    }

    save_resolve_cache($cache);
}

function normalize_resolve_text(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
    $map = [
        "\u{00E7}" => 'c',
        "\u{011F}" => 'g',
        "\u{0131}" => 'i',
        'i' => 'i',
        "\u{00F6}" => 'o',
        "\u{015F}" => 's',
        "\u{00FC}" => 'u',
    ];

    $value = strtr($value, $map);
    $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function build_search_pattern(string $query): ?string
{
    $normalized = normalize_resolve_text($query);

    if ($normalized === '') {
        return null;
    }

    $tokens = array_values(array_filter(explode(' ', $normalized), static fn (string $token): bool => $token !== ''));

    if ($tokens === []) {
        return null;
    }

    $escapedTokens = array_map(static fn (string $token): string => preg_quote($token, '/'), $tokens);

    return '/' . implode('.*', $escapedTokens) . '/u';
}

function item_matches_query(array $item, string $query): bool
{
    $pattern = build_search_pattern($query);

    if ($pattern === null) {
        return false;
    }

    $fields = [
        (string) ($item['display_name'] ?? ''),
    ];

    $address = is_array($item['address'] ?? null) ? $item['address'] : [];

    foreach (['town', 'city', 'province', 'county', 'state_district', 'state', 'municipality', 'suburb', 'village', 'country'] as $key) {
        $value = (string) ($address[$key] ?? '');

        if ($value !== '') {
            $fields[] = $value;
        }
    }

    foreach ($fields as $field) {
        if (preg_match($pattern, normalize_resolve_text($field)) === 1) {
            return true;
        }
    }

    return false;
}

function infer_country_code_from_query(string $query): ?string
{
    $normalized = normalize_resolve_text($query);

    if ($normalized === '') {
        return null;
    }

    $countryAliases = [
        'tr' => ['turkiye', 'turkey', 'türkiye'],
        'be' => ['belcika', 'belçika', 'belgium', 'belgie', 'belgique'],
    ];

    foreach ($countryAliases as $countryCode => $aliases) {
        foreach ($aliases as $alias) {
            if (preg_match('/(^|\s)' . preg_quote(normalize_resolve_text($alias), '/') . '(\s|$)/u', $normalized) === 1) {
                return $countryCode;
            }
        }
    }

    return null;
}

function fetch_nominatim_results(string $query, int $limit = 10): array
{
    $countryCode = infer_country_code_from_query($query);
    $countryFilter = $countryCode !== null ? '&countrycodes=' . rawurlencode($countryCode) : '';
    $payload = http_fetch_json(
        'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=' . $limit . '&accept-language=tr' . $countryFilter . '&q='
        . rawurlencode($query),
        ['Referer: http://localhost']
    );

    return is_array($payload) ? $payload : [];
}

function fetch_photon_results(string $query, int $limit = 10): array
{
    $payload = http_fetch_json(
        'https://photon.komoot.io/api/?limit=' . $limit . '&lang=tr&q=' . rawurlencode($query),
        ['Referer: http://localhost']
    );

    return is_array($payload['features'] ?? null) ? $payload['features'] : [];
}

function normalize_nominatim_items(array $payload): array
{
    return array_values(array_filter(array_map(static function (array $item): ?array {
        $address = is_array($item['address'] ?? null) ? $item['address'] : [];

        if ($address === []) {
            return null;
        }

        return [
            'display_name' => $item['display_name'] ?? null,
            'address' => $address,
            'lat' => isset($item['lat']) ? (string) $item['lat'] : null,
            'lon' => isset($item['lon']) ? (string) $item['lon'] : null,
        ];
    }, $payload)));
}

function normalize_photon_items(array $payload): array
{
    return array_values(array_filter(array_map(static function (array $item): ?array {
        $properties = is_array($item['properties'] ?? null) ? $item['properties'] : [];
        $coordinates = is_array($item['geometry']['coordinates'] ?? null) ? $item['geometry']['coordinates'] : null;

        if ($properties === [] || !is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $address = array_filter([
            'suburb' => (string) ($properties['district'] ?? $properties['name'] ?? ''),
            'town' => (string) ($properties['county'] ?? ''),
            'city' => (string) ($properties['city'] ?? $properties['county'] ?? ''),
            'province' => (string) ($properties['state'] ?? ''),
            'state' => (string) ($properties['state'] ?? ''),
            'country' => (string) ($properties['country'] ?? ''),
            'country_code' => (string) ($properties['countrycode'] ?? ''),
        ], static fn (string $value): bool => $value !== '');

        if ($address === []) {
            return null;
        }

        $displayNameParts = array_filter([
            (string) ($properties['name'] ?? ''),
            (string) ($properties['city'] ?? ''),
            (string) ($properties['state'] ?? ''),
            (string) ($properties['country'] ?? ''),
        ], static fn (string $value): bool => $value !== '');

        return [
            'display_name' => implode(', ', array_values(array_unique($displayNameParts))),
            'address' => $address,
            'lat' => isset($coordinates[1]) ? (string) $coordinates[1] : null,
            'lon' => isset($coordinates[0]) ? (string) $coordinates[0] : null,
        ];
    }, $payload)));
}

function search_resolve_cache(string $query): array
{
    return normalize_nominatim_items(array_values(array_filter(
        load_resolve_cache(),
        static fn (array $item): bool => item_matches_query($item, $query)
    )));
}

function build_partial_variant_queries(string $query, string $fallbackQuery): array
{
    $variants = [$query, $fallbackQuery];
    $suffixes = [
        '',
        'a',
        'e',
        'i',
        "\u{0131}",
        'ar',
        'er',
        'ari',
        "ar\u{0131}",
        'eri',
    ];

    if (mb_strlen($fallbackQuery, 'UTF-8') >= 5) {
        foreach ($suffixes as $suffix) {
            $variants[] = $fallbackQuery . $suffix;
        }
    }

    return array_values(array_unique(array_filter(array_map(
        static fn (string $value): string => trim($value),
        $variants
    ))));
}

function resolve_partial_query_items(string $query): array
{
    $normalizedQuery = normalize_resolve_text($query);

    if ($normalizedQuery === '') {
        return [];
    }

    $candidateQueries = array_unique([
        $normalizedQuery,
        preg_replace('/i$/u', '', $normalizedQuery) ?? $normalizedQuery,
        preg_replace('/(ari|ri|i)$/u', '', $normalizedQuery) ?? $normalizedQuery,
    ]);

    $candidates = [];

    foreach ($candidateQueries as $fallbackQuery) {
        $fallbackQuery = trim((string) $fallbackQuery);

        if ($fallbackQuery === '' || mb_strlen($fallbackQuery, 'UTF-8') < 4) {
            continue;
        }

        foreach (build_partial_variant_queries($query, $fallbackQuery) as $variantQuery) {
            $variantItems = [];

            try {
                $variantItems = fetch_nominatim_results($variantQuery, 10);
            } catch (Throwable) {
                try {
                    $variantItems = fetch_photon_results($variantQuery, 10);
                } catch (Throwable) {
                    $variantItems = [];
                }
            }

            foreach ($variantItems as $item) {
                $key = md5(json_encode([
                    $item['display_name'] ?? ($item['properties']['name'] ?? null),
                    $item['lat'] ?? ($item['geometry']['coordinates'][1] ?? null),
                    $item['lon'] ?? ($item['geometry']['coordinates'][0] ?? null),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                $candidates[$key] = $item;
            }

            if ($candidates !== []) {
                break;
            }
        }

        if ($candidates !== []) {
            break;
        }
    }

    $normalizedCandidates = array_merge(
        normalize_nominatim_items(array_values(array_filter(
            $candidates,
            static fn (array $item): bool => array_key_exists('address', $item)
        ))),
        normalize_photon_items(array_values(array_filter(
            $candidates,
            static fn (array $item): bool => array_key_exists('properties', $item)
        )))
    );

    return array_values(array_filter(
        $normalizedCandidates,
        static fn (array $item): bool => item_matches_query($item, $query)
    ));
}

$query = trim((string) ($_GET['q'] ?? ''));
app_debug_log('api.location-resolve.request', ['query' => $query]);

if ($query === '') {
    http_response_code(400);
    echo json_encode(['error' => 'q parametresi gerekli.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $items = search_resolve_cache($query);

    if ($items === []) {
        try {
            $items = normalize_nominatim_items(fetch_nominatim_results($query, 5));
        } catch (Throwable $error) {
            app_debug_log('api.location-resolve.nominatim.error', [
                'query' => $query,
                'error' => $error->getMessage(),
            ]);
            $items = [];
        }
    }

    if ($items === []) {
        try {
            $items = normalize_photon_items(fetch_photon_results($query, 5));
        } catch (Throwable $error) {
            app_debug_log('api.location-resolve.photon.error', [
                'query' => $query,
                'error' => $error->getMessage(),
            ]);
            $items = [];
        }
    }

    if ($items === []) {
        $items = resolve_partial_query_items($query);
    }

    merge_resolve_cache_items($items);

    app_debug_log('api.location-resolve.success', [
        'query' => $query,
        'count' => count($items),
    ]);

    echo json_encode(['value' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(502);
    app_debug_log('api.location-resolve.exception', [
        'query' => $query,
        'error' => $error->getMessage(),
    ]);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
