<?php

declare(strict_types=1);

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/http.php';

function app_prayer_provider_settings(): array
{
    $settings = app_settings();
    $providerKey = (string) ($settings['prayer_api_provider'] ?? 'emushaf');
    $providers = is_array($settings['prayer_api_providers'] ?? null) ? $settings['prayer_api_providers'] : [];
    $provider = is_array($providers[$providerKey] ?? null) ? $providers[$providerKey] : [];

    if ($provider === []) {
        throw new RuntimeException('Namaz vakti saglayicisi ayarlanmamis.');
    }

    return [
        'key' => $providerKey,
        'config' => $provider,
    ];
}

function app_prayer_provider_search_locations(string $query): array
{
    $provider = app_prayer_provider_settings();

    return match ($provider['key']) {
        'emushaf' => app_prayer_provider_emushaf_search_locations($query, $provider['config']),
        default => throw new RuntimeException('Desteklenmeyen namaz vakti saglayicisi secildi.'),
    };
}

function app_prayer_provider_get_prayer_times(int $locationId): array
{
    $provider = app_prayer_provider_settings();

    return match ($provider['key']) {
        'emushaf' => app_prayer_provider_emushaf_get_prayer_times($locationId, $provider['config']),
        default => throw new RuntimeException('Desteklenmeyen namaz vakti saglayicisi secildi.'),
    };
}

function app_prayer_provider_normalize_text(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');

    if (class_exists('Normalizer')) {
        $value = Normalizer::normalize($value, Normalizer::FORM_D) ?: $value;
    }

    $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
    $value = strtr($value, [
        'ç' => 'c',
        'ğ' => 'g',
        'ı' => 'i',
        'ö' => 'o',
        'ş' => 's',
        'ü' => 'u',
    ]);
    $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function app_prayer_provider_cache_dir(): string
{
    return dirname(__DIR__) . '/storage/cache';
}

function app_prayer_provider_emushaf_countries_cache_path(): string
{
    return app_prayer_provider_cache_dir() . '/emushaf-countries.json';
}

function app_prayer_provider_emushaf_cities_cache_path(): string
{
    return app_prayer_provider_cache_dir() . '/emushaf-cities.json';
}

function app_prayer_provider_emushaf_locations_cache_path(): string
{
    return app_prayer_provider_cache_dir() . '/emushaf-locations.json';
}

function app_prayer_provider_emushaf_state_cache_path(): string
{
    return app_prayer_provider_cache_dir() . '/emushaf-cache-state.json';
}

function app_prayer_provider_load_cache_file(string $path, int $ttl): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $content = file_get_contents($path);

    if (!is_string($content) || $content === '') {
        return null;
    }

    $decoded = json_decode($content, true);

    if (!is_array($decoded)) {
        return null;
    }

    $generatedAt = strtotime((string) ($decoded['generated_at'] ?? ''));

    if ($generatedAt === false || ($ttl > 0 && ($generatedAt + $ttl) < time())) {
        return null;
    }

    return is_array($decoded['items'] ?? null) ? $decoded['items'] : null;
}

function app_prayer_provider_save_cache_file(string $path, array $items): void
{
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, json_encode([
        'generated_at' => date('c'),
        'items' => array_values($items),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function app_prayer_provider_load_state_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $content = file_get_contents($path);

    if (!is_string($content) || $content === '') {
        return [];
    }

    $decoded = json_decode($content, true);

    return is_array($decoded) ? $decoded : [];
}

function app_prayer_provider_save_state_file(string $path, array $state): void
{
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function app_prayer_provider_emushaf_load_state(): array
{
    $state = app_prayer_provider_load_state_file(app_prayer_provider_emushaf_state_cache_path());

    return [
        'fetched_country_ids' => array_values(array_unique(array_map('intval', is_array($state['fetched_country_ids'] ?? null) ? $state['fetched_country_ids'] : []))),
        'fetched_city_ids' => array_values(array_unique(array_map('intval', is_array($state['fetched_city_ids'] ?? null) ? $state['fetched_city_ids'] : []))),
        'next_country_offset' => max(0, (int) ($state['next_country_offset'] ?? 0)),
        'popular_warmed_at' => (string) ($state['popular_warmed_at'] ?? ''),
    ];
}

function app_prayer_provider_emushaf_save_state(array $state): void
{
    app_prayer_provider_save_state_file(app_prayer_provider_emushaf_state_cache_path(), $state);
}

function app_prayer_provider_emushaf_merge_items(array $existing, array $incoming, string $keyField = 'id'): array
{
    $merged = [];

    foreach (array_merge($existing, $incoming) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $key = (string) ($item[$keyField] ?? '');

        if ($key === '') {
            continue;
        }

        $merged[$key] = $item;
    }

    return array_values($merged);
}

function app_prayer_provider_emushaf_load_countries(int $ttl): array
{
    return app_prayer_provider_load_cache_file(app_prayer_provider_emushaf_countries_cache_path(), $ttl) ?? [];
}

function app_prayer_provider_emushaf_save_countries(array $items): void
{
    app_prayer_provider_save_cache_file(app_prayer_provider_emushaf_countries_cache_path(), $items);
}

function app_prayer_provider_emushaf_load_cities(int $ttl): array
{
    return app_prayer_provider_load_cache_file(app_prayer_provider_emushaf_cities_cache_path(), $ttl) ?? [];
}

function app_prayer_provider_emushaf_save_cities(array $items): void
{
    app_prayer_provider_save_cache_file(app_prayer_provider_emushaf_cities_cache_path(), $items);
}

function app_prayer_provider_emushaf_load_locations(int $ttl): array
{
    return app_prayer_provider_load_cache_file(app_prayer_provider_emushaf_locations_cache_path(), $ttl) ?? [];
}

function app_prayer_provider_emushaf_save_locations(array $items): void
{
    app_prayer_provider_save_cache_file(app_prayer_provider_emushaf_locations_cache_path(), $items);
}

function app_prayer_provider_emushaf_get_countries(array $config): array
{
    $ttl = (int) ($config['cache_ttl'] ?? 2592000);
    $cached = app_prayer_provider_emushaf_load_countries($ttl);

    if ($cached !== []) {
        return $cached;
    }

    $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');

    if ($baseUrl === '') {
        throw new RuntimeException('Emushaf API ayarlari eksik.');
    }

    $payload = http_fetch_json($baseUrl . '/ulkeler');
    $items = array_map(static function (array $country): array {
        $name = trim((string) ($country['UlkeAdi'] ?? ''));
        $nameEn = trim((string) ($country['UlkeAdiEn'] ?? ''));
        $id = (int) ($country['UlkeID'] ?? 0);

        return [
            'id' => $id,
            'name' => $name,
            'nameEn' => $nameEn,
            'searchText' => app_prayer_provider_normalize_text($name . ' ' . $nameEn),
        ];
    }, array_values(array_filter($payload, static fn (mixed $item): bool => is_array($item))));

    app_prayer_provider_emushaf_save_countries($items);

    return $items;
}

function app_prayer_provider_emushaf_get_cached_cities(array $config): array
{
    return app_prayer_provider_emushaf_load_cities((int) ($config['cache_ttl'] ?? 2592000));
}

function app_prayer_provider_emushaf_get_cached_locations(array $config): array
{
    return app_prayer_provider_emushaf_load_locations((int) ($config['cache_ttl'] ?? 2592000));
}

function app_prayer_provider_emushaf_fetch_cities_for_country(array $country, array $config): array
{
    $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
    $countryId = (int) ($country['id'] ?? 0);

    if ($baseUrl === '' || $countryId <= 0) {
        throw new RuntimeException('Emushaf API ayarlari eksik.');
    }

    $payload = http_fetch_json($baseUrl . '/sehirler/' . $countryId);

    return array_map(static function (array $city) use ($country): array {
        $name = trim((string) ($city['SehirAdi'] ?? ''));
        $nameEn = trim((string) ($city['SehirAdiEn'] ?? ''));
        $id = (int) ($city['SehirID'] ?? 0);

        return [
            'id' => $id,
            'countryId' => (int) ($country['id'] ?? 0),
            'country' => (string) ($country['name'] ?? ''),
            'countryEn' => (string) ($country['nameEn'] ?? ''),
            'name' => $name,
            'nameEn' => $nameEn,
            'searchText' => app_prayer_provider_normalize_text($name . ' ' . $nameEn . ' ' . ((string) ($country['name'] ?? ''))),
        ];
    }, array_values(array_filter($payload, static fn (mixed $item): bool => is_array($item))));
}

function app_prayer_provider_emushaf_cache_cities_for_country(array $country, array $config): array
{
    $cities = app_prayer_provider_emushaf_fetch_cities_for_country($country, $config);
    $merged = app_prayer_provider_emushaf_merge_items(app_prayer_provider_emushaf_get_cached_cities($config), $cities);
    app_prayer_provider_emushaf_save_cities($merged);

    $state = app_prayer_provider_emushaf_load_state();
    $state['fetched_country_ids'] = array_values(array_unique(array_merge(
        $state['fetched_country_ids'],
        [(int) ($country['id'] ?? 0)]
    )));
    app_prayer_provider_emushaf_save_state($state);

    return $merged;
}

function app_prayer_provider_emushaf_fetch_locations_for_city(array $city, array $config): array
{
    $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
    $cityId = (int) ($city['id'] ?? 0);

    if ($baseUrl === '' || $cityId <= 0) {
        throw new RuntimeException('Emushaf API ayarlari eksik.');
    }

    $payload = http_fetch_json($baseUrl . '/ilceler/' . $cityId);
    $items = [];

    foreach ($payload as $district) {
        if (!is_array($district)) {
            continue;
        }

        $districtId = (int) ($district['IlceID'] ?? 0);
        $districtName = trim((string) ($district['IlceAdi'] ?? ''));

        if ($districtId <= 0 || $districtName === '') {
            continue;
        }

        $items[] = [
            'id' => $districtId,
            'countryId' => (int) ($city['countryId'] ?? 0),
            'country' => (string) ($city['country'] ?? ''),
            'cityId' => $cityId,
            'city' => (string) ($city['name'] ?? ''),
            'region' => $districtName,
            'displayCity' => (string) ($city['name'] ?? ''),
            'displayRegion' => $districtName,
            'provider' => 'emushaf',
            'searchText' => app_prayer_provider_normalize_text(
                $districtName . ' ' . ((string) ($city['name'] ?? '')) . ' ' . ((string) ($city['country'] ?? ''))
            ),
        ];
    }

    return $items;
}

function app_prayer_provider_emushaf_cache_locations_for_city(array $city, array $config): array
{
    $locations = app_prayer_provider_emushaf_fetch_locations_for_city($city, $config);
    $merged = app_prayer_provider_emushaf_merge_items(app_prayer_provider_emushaf_get_cached_locations($config), $locations);
    app_prayer_provider_emushaf_save_locations($merged);

    $state = app_prayer_provider_emushaf_load_state();
    $state['fetched_city_ids'] = array_values(array_unique(array_merge(
        $state['fetched_city_ids'],
        [(int) ($city['id'] ?? 0)]
    )));
    app_prayer_provider_emushaf_save_state($state);

    return $merged;
}

function app_prayer_provider_emushaf_matches_query(string $haystack, string $normalizedQuery): bool
{
    if ($normalizedQuery === '' || $haystack === '') {
        return false;
    }

    $tokens = array_values(array_filter(explode(' ', $normalizedQuery), static fn (string $token): bool => $token !== ''));

    foreach ($tokens as $token) {
        if (!str_contains($haystack, $token)) {
            return false;
        }
    }

    return true;
}

function app_prayer_provider_emushaf_find_matching_countries(string $normalizedQuery, array $config): array
{
    $countries = app_prayer_provider_emushaf_get_countries($config);

    return array_values(array_filter($countries, static fn (array $country): bool => app_prayer_provider_emushaf_matches_query(
        (string) ($country['searchText'] ?? ''),
        $normalizedQuery
    )));
}

function app_prayer_provider_emushaf_find_matching_cities(string $normalizedQuery, array $config): array
{
    $cities = app_prayer_provider_emushaf_get_cached_cities($config);

    return array_values(array_filter($cities, static fn (array $city): bool => app_prayer_provider_emushaf_matches_query(
        (string) ($city['searchText'] ?? ''),
        $normalizedQuery
    )));
}

function app_prayer_provider_emushaf_find_popular_cities(array $config): array
{
    $popularCityNames = array_values(array_filter(array_map(
        static fn (mixed $name): string => app_prayer_provider_normalize_text((string) $name),
        is_array($config['popular_city_names'] ?? null) ? $config['popular_city_names'] : []
    )));

    if ($popularCityNames === []) {
        return [];
    }

    $cities = app_prayer_provider_emushaf_get_cached_cities($config);
    $matches = [];

    foreach ($popularCityNames as $cityName) {
        foreach ($cities as $city) {
            if (app_prayer_provider_normalize_text((string) ($city['name'] ?? '')) !== $cityName) {
                continue;
            }

            $matches[(string) ($city['id'] ?? '')] = $city;
            break;
        }
    }

    return array_values($matches);
}

function app_prayer_provider_emushaf_find_matching_locations(string $normalizedQuery, array $config): array
{
    $locations = app_prayer_provider_emushaf_get_cached_locations($config);

    return array_values(array_filter($locations, static fn (array $location): bool => app_prayer_provider_emushaf_matches_query(
        (string) ($location['searchText'] ?? ''),
        $normalizedQuery
    )));
}

function app_prayer_provider_emushaf_sort_locations(array $items, string $normalizedQuery): array
{
    usort($items, static function (array $left, array $right) use ($normalizedQuery): int {
        $leftRegion = app_prayer_provider_normalize_text((string) ($left['region'] ?? ''));
        $rightRegion = app_prayer_provider_normalize_text((string) ($right['region'] ?? ''));
        $leftCity = app_prayer_provider_normalize_text((string) ($left['city'] ?? ''));
        $rightCity = app_prayer_provider_normalize_text((string) ($right['city'] ?? ''));

        $leftScore = ($leftRegion === $normalizedQuery ? 100 : 0) + ($leftCity === $normalizedQuery ? 50 : 0);
        $rightScore = ($rightRegion === $normalizedQuery ? 100 : 0) + ($rightCity === $normalizedQuery ? 50 : 0);

        if ($leftScore !== $rightScore) {
            return $rightScore <=> $leftScore;
        }

        return strcmp(
            (string) (($left['city'] ?? '') . ' ' . ($left['region'] ?? '')),
            (string) (($right['city'] ?? '') . ' ' . ($right['region'] ?? ''))
        );
    });

    return $items;
}

function app_prayer_provider_emushaf_warm_popular_metadata(array $config): void
{
    $state = app_prayer_provider_emushaf_load_state();
    $cacheTtl = (int) ($config['cache_ttl'] ?? 2592000);
    $warmedAt = strtotime($state['popular_warmed_at']);

    if ($warmedAt !== false && ($warmedAt + $cacheTtl) >= time()) {
        return;
    }

    $countries = app_prayer_provider_emushaf_get_countries($config);
    $popularCountryIds = array_values(array_filter(array_map('intval', $config['popular_country_ids'] ?? [])));
    foreach ($countries as $country) {
        $countryId = (int) ($country['id'] ?? 0);

        if (!in_array($countryId, $popularCountryIds, true)) {
            continue;
        }

        try {
            app_prayer_provider_emushaf_cache_cities_for_country($country, $config);
        } catch (Throwable $error) {
            app_debug_log('prayer-provider.emushaf.popular-country-cache.error', [
                'country_id' => $countryId,
                'error' => $error->getMessage(),
            ]);
        }
    }

    foreach (app_prayer_provider_emushaf_find_popular_cities($config) as $city) {
        $cityId = (int) ($city['id'] ?? 0);

        if ($cityId <= 0 || in_array($cityId, $state['fetched_city_ids'], true)) {
            continue;
        }

        try {
            app_prayer_provider_emushaf_cache_locations_for_city($city, $config);
            $state['fetched_city_ids'][] = $cityId;
        } catch (Throwable $error) {
            app_debug_log('prayer-provider.emushaf.popular-city-cache.error', [
                'city_id' => $cityId,
                'city_name' => (string) ($city['name'] ?? ''),
                'error' => $error->getMessage(),
            ]);
        }
    }

    $state = app_prayer_provider_emushaf_load_state();
    $state['popular_warmed_at'] = date('c');
    app_prayer_provider_emushaf_save_state($state);
}

function app_prayer_provider_emushaf_hydrate_search_batch(string $normalizedQuery, array $config): void
{
    $countries = app_prayer_provider_emushaf_get_countries($config);
    $state = app_prayer_provider_emushaf_load_state();
    $fetchedCountryIds = $state['fetched_country_ids'];
    $uncachedCountries = array_values(array_filter($countries, static fn (array $country): bool => !in_array((int) ($country['id'] ?? 0), $fetchedCountryIds, true)));

    if ($uncachedCountries === []) {
        return;
    }

    $matchingCountries = array_values(array_filter($uncachedCountries, static fn (array $country): bool => app_prayer_provider_emushaf_matches_query(
        (string) ($country['searchText'] ?? ''),
        $normalizedQuery
    )));

    $batch = [];

    foreach ($matchingCountries as $country) {
        $batch[] = $country;
    }

    $offset = min($state['next_country_offset'], max(0, count($uncachedCountries) - 1));
    $fallbackBatch = array_slice($uncachedCountries, $offset, (int) ($config['search_batch_size'] ?? 8));

    foreach ($fallbackBatch as $country) {
        $countryId = (int) ($country['id'] ?? 0);
        $alreadyInBatch = array_filter($batch, static fn (array $item): bool => (int) ($item['id'] ?? 0) === $countryId);

        if ($alreadyInBatch !== []) {
            continue;
        }

        $batch[] = $country;
    }

    foreach (array_slice($batch, 0, (int) ($config['search_batch_size'] ?? 8)) as $country) {
        app_prayer_provider_emushaf_cache_cities_for_country($country, $config);
    }

    $state = app_prayer_provider_emushaf_load_state();
    $state['next_country_offset'] = ($offset + (int) ($config['search_batch_size'] ?? 8)) % max(1, count($uncachedCountries));
    app_prayer_provider_emushaf_save_state($state);
}

function app_prayer_provider_emushaf_search_locations(string $query, array $config): array
{
    $normalizedQuery = app_prayer_provider_normalize_text($query);

    if ($normalizedQuery === '') {
        return [];
    }

    try {
        app_prayer_provider_emushaf_warm_popular_metadata($config);
    } catch (Throwable $error) {
        app_debug_log('prayer-provider.emushaf.warm-popular-metadata.error', [
            'query' => $query,
            'error' => $error->getMessage(),
        ]);
    }

    $results = app_prayer_provider_emushaf_find_matching_locations($normalizedQuery, $config);

    if ($results !== []) {
        return array_values(array_map(static function (array $item): array {
            unset($item['searchText']);
            return $item;
        }, array_slice(app_prayer_provider_emushaf_sort_locations($results, $normalizedQuery), 0, 50)));
    }

    $matchingCities = app_prayer_provider_emushaf_find_matching_cities($normalizedQuery, $config);

    if ($matchingCities === []) {
        try {
            app_prayer_provider_emushaf_hydrate_search_batch($normalizedQuery, $config);
        } catch (Throwable $error) {
            app_debug_log('prayer-provider.emushaf.hydrate-search-batch.error', [
                'query' => $query,
                'error' => $error->getMessage(),
            ]);
        }
        $matchingCities = app_prayer_provider_emushaf_find_matching_cities($normalizedQuery, $config);
    }

    foreach (array_slice($matchingCities, 0, (int) ($config['city_hydration_limit'] ?? 10)) as $city) {
        $state = app_prayer_provider_emushaf_load_state();

        if (in_array((int) ($city['id'] ?? 0), $state['fetched_city_ids'], true)) {
            continue;
        }

        try {
            app_prayer_provider_emushaf_cache_locations_for_city($city, $config);
        } catch (Throwable $error) {
            app_debug_log('prayer-provider.emushaf.cache-locations-for-city.error', [
                'query' => $query,
                'city_id' => (int) ($city['id'] ?? 0),
                'city_name' => (string) ($city['name'] ?? ''),
                'error' => $error->getMessage(),
            ]);
        }
    }

    $results = app_prayer_provider_emushaf_find_matching_locations($normalizedQuery, $config);

    return array_values(array_map(static function (array $item): array {
        unset($item['searchText']);
        return $item;
    }, array_slice(app_prayer_provider_emushaf_sort_locations($results, $normalizedQuery), 0, 50)));
}

function app_prayer_provider_parse_emushaf_date(array $item): string
{
    $shortDate = trim((string) ($item['MiladiTarihKisa'] ?? ''));
    $parts = explode('.', $shortDate);

    if (count($parts) === 3) {
        return sprintf('%04d-%02d-%02d', (int) $parts[2], (int) $parts[1], (int) $parts[0]);
    }

    throw new RuntimeException('Emushaf vakit tarih alani gecersiz.');
}

function app_prayer_provider_emushaf_get_prayer_times(int $locationId, array $config): array
{
    $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');

    if ($baseUrl === '') {
        throw new RuntimeException('Emushaf API ayarlari eksik.');
    }

    $payload = http_fetch_json($baseUrl . '/vakitler/' . $locationId);
    $items = array_values(array_filter($payload, static fn (mixed $item): bool => is_array($item)));

    return array_map(static function (array $item): array {
        return [
            'date' => app_prayer_provider_parse_emushaf_date($item),
            'fajr' => (string) ($item['Imsak'] ?? ''),
            'sun' => (string) ($item['Gunes'] ?? ''),
            'dhuhr' => (string) ($item['Ogle'] ?? ''),
            'asr' => (string) ($item['Ikindi'] ?? ''),
            'maghrib' => (string) ($item['Aksam'] ?? ''),
            'isha' => (string) ($item['Yatsi'] ?? ''),
            'hijriShort' => (string) ($item['HicriTarihKisa'] ?? ''),
            'hijriLong' => (string) ($item['HicriTarihUzun'] ?? ''),
        ];
    }, $items);
}

