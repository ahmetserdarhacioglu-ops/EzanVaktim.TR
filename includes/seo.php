<?php

declare(strict_types=1);

function seo_absolute_url(string $baseUrl, string $path = '/'): string
{
    $normalizedBase = rtrim($baseUrl, '/');

    if ($path === '' || $path === '/') {
        return $normalizedBase . '/';
    }

    return $normalizedBase . '/' . ltrim($path, '/');
}

function seo_build_meta(array $site, array $page = []): array
{
    $baseUrl = (string) ($site['site_url'] ?? '');
    $path = (string) ($page['path'] ?? '/');
    $url = seo_absolute_url($baseUrl, $path);
    $image = seo_absolute_url($baseUrl, (string) ($page['image'] ?? 'logo/logo.png'));
    $title = (string) ($page['title'] ?? $site['title']);
    $description = (string) ($page['description'] ?? $site['description']);
    $type = (string) ($page['type'] ?? 'website');
    $robots = (string) ($page['robots'] ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1');

    return [
        'base_url' => $baseUrl,
        'url' => $url,
        'image' => $image,
        'title' => $title,
        'description' => $description,
        'type' => $type,
        'robots' => $robots,
        'site_name' => (string) $site['title'],
    ];
}

function seo_render_meta(array $meta): string
{
    $tags = [
        '<meta name="description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta name="robots" content="' . htmlspecialchars($meta['robots'], ENT_QUOTES, 'UTF-8') . '">',
        '<link rel="canonical" href="' . htmlspecialchars($meta['url'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta property="og:locale" content="tr_TR">',
        '<meta property="og:type" content="' . htmlspecialchars($meta['type'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta property="og:site_name" content="' . htmlspecialchars($meta['site_name'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta property="og:title" content="' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta property="og:description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta property="og:url" content="' . htmlspecialchars($meta['url'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta property="og:image" content="' . htmlspecialchars($meta['image'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta name="twitter:card" content="summary_large_image">',
        '<meta name="twitter:title" content="' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta name="twitter:description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '">',
        '<meta name="twitter:image" content="' . htmlspecialchars($meta['image'], ENT_QUOTES, 'UTF-8') . '">',
    ];

    return implode("\n    ", $tags);
}

function seo_render_schema(array $payload): string
{
    return '<script type="application/ld+json">' . json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    ) . '</script>';
}
