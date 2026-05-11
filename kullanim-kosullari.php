<?php

/**
 * EzanVaktim — Güncel ezan vakitleri, aylık namaz tablosu ve konuma göre kıble yönü.
 *
 * Üniversite bitirme tezi kapsamında geliştirilmiştir.
 * Geliştirici: Ahmet Serdar Hacıoğlu
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/seo.php';

header('Content-Type: text/html; charset=UTF-8');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$meta = seo_build_meta($site, [
    'title' => 'Kullanım Koşulları | ' . $site['title'],
    'description' => 'EzanVaktim kullanım koşulları, hizmet kapsamı ve kullanıcı sorumlulukları.',
    'path' => '/kullanim-kosullari.php',
]);
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $meta['title'],
    'url' => $meta['url'],
    'description' => $meta['description'],
    'inLanguage' => 'tr-TR',
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title><?= e($meta['title']); ?></title>
    <?= seo_render_meta($meta); ?>
    <meta name="theme-color" content="#246b4a">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/site.css">
    <link rel="icon" type="image/png" href="logo/icon.png" sizes="32x32">
    <?= seo_render_schema($schema); ?>
</head>
<body class="info-page">
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="index.php" aria-label="<?= e($site['title']); ?>">
                <img class="brand-logo" src="logo/logo.svg" alt="<?= e($site['title']); ?> logosu">
            </a>
        </div>
    </header>

    <main class="container info-main">
        <section class="info-hero">
            <h1>Kullanım Koşulları</h1>
            <p class="info-copy">Bu sayfa, EzanVaktim kullanımına dair temel çerçeveyi açıklar. Amaç, hizmetin kapsamını ve sınırlarını sade bir dille görünür kılmaktır.</p>
        </section>

        <section class="info-section">
            <h2>Bilgilendirme Amacı</h2>
            <p class="info-copy">EzanVaktim, ezan vakitlerini ve kıble yönü bilgisini dijital ortamda hızlı erişilebilir hale getirmek için sunulur. Vakit verileri harici servislerden alınabildiği için zaman zaman güncelleme farkları oluşabilir.</p>
        </section>

        <section class="info-section">
            <h2>Kullanıcı Sorumluluğu</h2>
            <p class="info-copy">Kullanıcı, manuel konum seçimi ve cihaz izinleri gibi alanlarda doğru tercih yapmaktan sorumludur. Özellikle yaklaşık IP konumu kullanıldığında ilçe bazında sonucun kontrol edilmesi önerilir.</p>
        </section>

        <section class="info-section">
            <h2>Hizmette Değişiklik</h2>
            <p class="info-copy">Uygulama içeriği, tasarımı ve teknik altyapısı geliştirme sürecine bağlı olarak güncellenebilir. Gerektiğinde bu koşullar anlaşılır biçimde revize edilir.</p>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <div class="footer-brand">
                <span><?= e($site['title']); ?></span>
                <small>© 2026. Tüm hakları saklıdır.</small>
            </div>
            <nav class="footer-nav" aria-label="Alt menü">
                <?php foreach ($site['footer_links'] as $link): ?>
                    <a href="<?= e($link['href']); ?>"><?= e($link['label']); ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="container footer-credit">Bu proje Serdar Hacıoğlu'na aittir.</div>
    </footer>
</body>
</html>
