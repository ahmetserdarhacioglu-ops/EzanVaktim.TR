<?php

declare(strict_types=1);

function http_fetch(string $url, array $headers = []): string
{
    $defaultHeaders = [
        'User-Agent: EzanVaktim/1.0',
        'Accept: application/json, text/plain, */*',
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", array_merge($defaultHeaders, $headers)),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $content = @file_get_contents($url, false, $context);

    if ($content === false) {
        throw new RuntimeException('Uzak servis cevabi alinamadi.');
    }

    $statusLine = $http_response_header[0] ?? '';

    if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
        $statusCode = (int) $matches[1];

        if ($statusCode >= 400) {
            throw new RuntimeException('Uzak servis hata dondurdu: HTTP ' . $statusCode);
        }
    }

    return $content;
}

function http_fetch_json(string $url, array $headers = []): array
{
    $content = http_fetch($url, $headers);
    $decoded = json_decode($content, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Uzak servis JSON formati gecersiz.');
    }

    return $decoded;
}

function http_check_origin(array $allowedOrigins = []): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $isProduction = getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod';

    if (empty($allowedOrigins)) {
        $allowedOrigins = ['https://ezanvaktim.tr', 'https://www.ezanvaktim.tr'];

        if (!$isProduction) {
            if (preg_match('/^http:\/\/localhost(:\d+)?$|^http:\/\/127\.0\.0\.1(:\d+)?$/', $origin)) {
                return true;
            }
        }
    }

    if ($origin) {
        foreach ($allowedOrigins as $allowed) {
            if (strcasecmp($origin, $allowed) === 0) {
                return true;
            }
        }
    }

    if ($referer) {
        foreach ($allowedOrigins as $allowed) {
            if (strpos($referer, $allowed) === 0) {
                return true;
            }
        }
    }

    return false;
}

function http_verify_csrf(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_REQUEST['csrf_token'] ?? '';
    return app_verify_csrf_token($token);
}

function http_set_cors_headers(array $allowedOrigins = []): void
{
    if (!http_check_origin($allowedOrigins)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'İzin verilmeyen kaynak.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!http_verify_csrf()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'CSRF token geçersiz.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
