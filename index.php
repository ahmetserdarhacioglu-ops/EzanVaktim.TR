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

$insights = [];
$insightsPath = __DIR__ . '/assets/data/insights.json';

if (is_file($insightsPath)) {
    $decoded = json_decode((string) file_get_contents($insightsPath), true);

    if (is_array($decoded)) {
        $insights = $decoded;
    }
}

if ($insights !== []) {
    $site['insight'] = $insights[random_int(0, count($insights) - 1)];
}

$progress = max(0, min(100, (int) $site['countdown']['progress']));
$circumference = 2 * pi() * 42;
$offset = $circumference - (($progress / 100) * $circumference);
$meta = seo_build_meta($site, [
    'title' => 'Ezan Vakitleri, Namaz Saatleri ve Kıble Yönü | ' . $site['title'],
    'description' => 'Türkiye için şehir ve ilçe bazlı güncel ezan vakitleri, aylık namaz saatleri, kıble yönü ve konuma göre hızlı vakit bilgisi.',
    'path' => '/',
]);
$faqItems = [
    [
        'question' => 'EzanVaktim hangi şehirler için namaz vakitlerini gösterir?',
        'answer' => 'EzanVaktim Türkiye genelindeki şehir ve ilçeler için güncel namaz vakitlerini listeler. Konum izni verirseniz size en yakın uygun sonuç otomatik seçilir.',
    ],
    [
        'question' => 'Namaz vakitleri neye göre hesaplanır?',
        'answer' => 'Uygulama, şehir veya ilçe bazlı vakit verisini harici vakit servislerinden alır ve gün içinde ekranda anlaşılır biçimde sunar. Bu sayede imsak, güneş, öğle, ikindi, akşam ve yatsı saatleri tek ekranda görülebilir.',
    ],
    [
        'question' => 'Kıble yönü özelliği nasıl çalışır?',
        'answer' => 'Konumunuz belirlendiğinde bulunduğunuz noktadan Mekke yönü hesaplanır ve harita ile pusula görünümünde gösterilir. Böylece kıble yönünü hızlıca kontrol edebilirsiniz.',
    ],
    [
        'question' => 'Konum izni vermeden kullanabilir miyim?',
        'answer' => 'Evet. Şehir veya ilçe yazarak manuel arama yapabilir, uygun sonucu seçip namaz vakitlerini anında görüntüleyebilirsiniz.',
    ],
];
$homeSchema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => $meta['url'] . '#organization',
            'name' => $site['title'],
            'url' => $meta['url'],
            'logo' => seo_absolute_url($site['site_url'], 'logo/logo.png'),
            'email' => $site['contact_email'],
        ],
        [
            '@type' => 'WebSite',
            '@id' => $meta['url'] . '#website',
            'url' => $meta['url'],
            'name' => $site['title'],
            'description' => $meta['description'],
            'inLanguage' => 'tr-TR',
            'publisher' => ['@id' => $meta['url'] . '#organization'],
        ],
        [
            '@type' => 'WebPage',
            '@id' => $meta['url'] . '#webpage',
            'url' => $meta['url'],
            'name' => $meta['title'],
            'description' => $meta['description'],
            'isPartOf' => ['@id' => $meta['url'] . '#website'],
            'about' => ['@id' => $meta['url'] . '#app'],
            'primaryImageOfPage' => $meta['image'],
            'inLanguage' => 'tr-TR',
        ],
        [
            '@type' => 'SoftwareApplication',
            '@id' => $meta['url'] . '#app',
            'name' => $site['title'],
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem' => 'Web',
            'url' => $meta['url'],
            'description' => 'Türkiye için ezan vakitleri, namaz saatleri ve kıble yönü gösteren web uygulaması.',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'TRY',
            ],
            'publisher' => ['@id' => $meta['url'] . '#organization'],
        ],
        [
            '@type' => 'FAQPage',
            '@id' => $meta['url'] . '#faq',
            'mainEntity' => array_map(
                static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ],
                $faqItems
            ),
        ],
    ],
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
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-P5MHMK9PFN"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-P5MHMK9PFN');
    </script>
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >
    <link rel="stylesheet" href="assets/css/site.css">
    <link rel="icon" type="image/png" href="logo/icon.png" sizes="32x32">
    <?= seo_render_schema($homeSchema); ?>
</head>
<body>
    <svg class="icon-sprite" aria-hidden="true" focusable="false">
        <symbol id="i-location" viewBox="0 0 24 24"><path d="M12 22s7-6.2 7-13a7 7 0 1 0-14 0c0 6.8 7 13 7 13Z" fill="currentColor"/><circle cx="12" cy="9" r="2.5" fill="#fff"/></symbol>
        <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16 16 4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
        <symbol id="i-target" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="currentColor"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
        <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.34-5.66" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20 4v6h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 3v4m10-4v4M3 10h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
        <symbol id="i-compass" viewBox="0 0 24 24"><path d="m14.5 9.5-2.2 5.1-5.1 2.2 2.2-5.1 5.1-2.2Z" fill="currentColor"/><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/></symbol>
        <symbol id="i-mosque" viewBox="0 0 24 24"><path d="M4 20v-5c0-2.2 1.8-4 4-4 0-2.2 1.8-4 4-4s4 1.8 4 4c2.2 0 4 1.8 4 4v5H4Z" fill="currentColor"/><path d="M8 11V5m0 0 2 2M8 5 6 7" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="i-imsak" viewBox="0 0 24 24"><path d="M4 15h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 15a5 5 0 0 1 10 0" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 4v3m6 1-1.8 1.8M6 9.8 4.2 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
        <symbol id="i-gunes" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" fill="currentColor"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3m-4.2-6.8-2.1 2.1M8.3 15.7l-2.1 2.1m0-11.6 2.1 2.1m7.4 7.4 2.1 2.1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
        <symbol id="i-ogle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="currentColor"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3m-4.2-6.8-2.1 2.1M8.3 15.7l-2.1 2.1m0-11.6 2.1 2.1m7.4 7.4 2.1 2.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
        <symbol id="i-ikindi" viewBox="0 0 24 24"><circle cx="10" cy="10" r="4" fill="currentColor"/><path d="M14.5 14.5a4 4 0 1 0 5 5 4 4 0 0 0-5-5Z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 5 3.5 3.5m13 13 1.5 1.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
        <symbol id="i-aksam" viewBox="0 0 24 24"><path d="M4 16h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="m12 6 5 10H7l5-10Z" fill="currentColor"/></symbol>
        <symbol id="i-yatsi" viewBox="0 0 24 24"><path d="M15.5 4.5a7 7 0 1 0 4 12.7 8 8 0 1 1-4-12.7Z" fill="currentColor"/></symbol>
    </svg>

    <div
        class="page-shell"
        data-search-endpoint="api/location-search.php?q="
        data-prayertimes-endpoint="api/prayertimes.php?location_id="
        data-reverse-endpoint="api/reverse-geocode.php"
    >
        <header class="site-header">
            <div class="container header-inner">
                <a class="brand" href="/" aria-label="<?= e($site['title']); ?>">
                    <img class="brand-logo" src="logo/logo.svg" alt="<?= e($site['title']); ?> logosu">
                </a>
                <div class="header-location">
                    <span class="icon-chip"><svg><use href="#i-location"></use></svg></span>
                    <span id="headerLocation"><?= e($site['location']); ?></span>
                </div>
            </div>
        </header>

        <main class="container page-content">
            <section class="search-panel" aria-label="Konum arama">
                <form class="search-form" id="locationSearchForm" action="#" method="get" novalidate>
                    <div class="search-field-wrap">
                        <label class="search-field" for="locationSearchInput">
                            <span class="sr-only">Şehir veya ilçe ara</span>
                            <svg><use href="#i-search"></use></svg>
                            <input id="locationSearchInput" type="text" name="q" placeholder="<?= e($site['search']['placeholder']); ?>" autocomplete="off">
                        </label>
                        <div class="search-results" id="searchResults" hidden></div>
                    </div>
                    <div class="search-actions">
                        <button class="primary-button search-action-button" id="geoLocateButton" type="button">
                            <svg><use href="#i-target"></use></svg>
                            <span><?= e($site['search']['button']); ?></span>
                        </button>
                        <button class="secondary-button search-action-button" id="changeLocationButton" type="button">
                            <svg><use href="#i-refresh"></use></svg>
                            <span><?= e($site['search']['change_button']); ?></span>
                        </button>
                    </div>
                </form>

                <div class="status-panel" id="statusPanel" role="status" aria-live="polite" hidden>
                    <p class="status-message" id="statusMessage"><?= e($site['status']['loading']); ?></p>
                </div>

                <div class="manual-panel" id="manualPanel" hidden>
                    <strong><?= e($site['search']['manual_title']); ?></strong>
                    <p><?= e($site['search']['manual_help']); ?></p>
                </div>
            </section>

            <div class="dashboard-grid">
                <div class="main-column">
                    <section class="hero-card">
                        <div class="hero-copy">
                            <div class="hero-location">
                                <svg><use href="#i-location"></use></svg>
                                <h2 id="heroLocation"><?= e($site['location']); ?></h2>
                            </div>
                            <div class="hero-time" id="liveClock"><?= e($site['clock']); ?></div>
                            <div class="hero-dates">
                                <span class="hero-date-hijri" id="hijriDate"><?= e($site['hijri_date']); ?></span>
                                <span class="hero-separator"></span>
                                <span id="gregorianDate"><?= e($site['gregorian_date']); ?></span>
                            </div>
                        </div>
                        <aside class="countdown-card" aria-label="Aktif vakit bilgisi">
                            <div class="countdown-ring">
                                <svg viewBox="0 0 100 100" aria-hidden="true">
                                    <circle class="ring-track" cx="50" cy="50" r="42"></circle>
                                    <circle
                                        class="ring-progress"
                                        id="countdownRingProgress"
                                        cx="50"
                                        cy="50"
                                        r="42"
                                        data-circumference="<?= number_format($circumference, 2, '.', ''); ?>"
                                        style="stroke-dasharray: <?= number_format($circumference, 2, '.', ''); ?>; stroke-dashoffset: <?= number_format($offset, 2, '.', ''); ?>;"
                                    ></circle>
                                </svg>
                                <div class="ring-label">
                                    <span><?= e($site['countdown']['label']); ?></span>
                                    <strong id="countdownTime"><?= e($site['countdown']['time']); ?></strong>
                                </div>
                            </div>
                            <div class="countdown-copy">
                                <span class="eyebrow" id="currentPrayerEyebrow">Sonraki Vakit: Yükleniyor</span>
                                <strong id="currentPrayerName"><?= e($site['current_prayer']['end']); ?></strong>
                                <p><span id="currentPrayerWindow">Başlangıç <?= e($site['current_prayer']['start']); ?> • Bitiş <?= e($site['current_prayer']['end']); ?></span></p>
                            </div>
                        </aside>
                    </section>

                    <section class="prayer-section" aria-label="Günlük namaz vakitleri">
                        <div class="prayer-grid" id="prayerGrid">
                            <?php foreach ($site['prayers'] as $prayer): ?>
                                <article class="prayer-card<?= !empty($prayer['active']) ? ' is-active' : ''; ?>" data-prayer-key="<?= e($prayer['icon']); ?>">
                                    <span class="prayer-name"><?= e($prayer['name']); ?></span>
                                    <span class="prayer-icon"><svg><use href="#i-<?= e($prayer['icon']); ?>"></use></svg></span>
                                    <strong class="prayer-time"><?= e($prayer['time']); ?></strong>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <button class="schedule-link schedule-button" id="scheduleToggleButton" type="button" aria-expanded="false">
                            <svg><use href="#i-calendar"></use></svg>
                            <span>Ayrıntılı aylık vakit tablosunu gör</span>
                        </button>

                        <section class="schedule-panel" id="schedulePanel" hidden>
                            <div class="monthly-section">
                                <div class="monthly-head">
                                    <span class="section-kicker">Bu Ay</span>
                                    <h3>Aylık temel namaz vakitleri</h3>
                                </div>
                                <div class="monthly-table-shell">
                                    <div class="monthly-table-wrap">
                                        <table class="monthly-table">
                                            <thead>
                                                <tr>
                                                    <th>Tarih</th>
                                                    <th>İmsak</th>
                                                    <th>Güneş</th>
                                                    <th>Öğle</th>
                                                    <th>İkindi</th>
                                                    <th>Akşam</th>
                                                    <th>Yatsı</th>
                                                </tr>
                                            </thead>
                                            <tbody id="monthlyScheduleTableBody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </section>

                    <section class="insight-grid">
                        <article class="content-card quote-card">
                            <span class="section-kicker"><?= e($site['insight']['title']); ?></span>
                            <blockquote id="insightQuote"><?= e($site['insight']['quote']); ?></blockquote>
                            <p id="insightSource"><?= e($site['insight']['source']); ?></p>
                        </article>
                        <article class="content-card stats-card">
                            <div class="stats-copy">
                                <span class="section-kicker"><?= e($site['sun_journey']['title']); ?></span>
                                <div class="stats-value">
                                    <strong id="sunJourneyValue"><?= e((string) $site['sun_journey']['value']); ?>%</strong>
                                    <span>Bugün</span>
                                </div>
                            </div>
                            <div class="stats-bars" aria-hidden="true">
                                <div class="bar-item">
                                    <span class="bar-track"><span class="bar-fill bar-fill-primary" id="sunriseBar"></span></span>
                                    <small><?= e($site['sun_journey']['left_label']); ?></small>
                                </div>
                                <div class="bar-item">
                                    <span class="bar-track"><span class="bar-fill bar-fill-secondary short" id="sunsetBar"></span></span>
                                    <small><?= e($site['sun_journey']['right_label']); ?></small>
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="content-card seo-copy-card" aria-labelledby="seoContentTitle">
                        <div class="section-heading">
                            <span class="section-kicker">Namaz Vakti Rehberi</span>
                            <h2 id="seoContentTitle">Türkiye için hızlı ezan vakitleri ve kıble bilgisi</h2>
                        </div>
                        <div class="seo-copy-grid">
                            <article>
                                <h3>Şehir ve ilçe bazlı güncel vakitler</h3>
                                <p>EzanVaktim, Türkiye genelinde şehir ve ilçe bazlı namaz saatlerini hızlı şekilde görüntülemek için tasarlandı. İmsak, güneş, öğle, ikindi, akşam ve yatsı saatleri aynı ekranda sade biçimde sunulur.</p>
                            </article>
                            <article>
                                <h3>Aylık tablo ve günlük takip</h3>
                                <p>Bugünün vakitlerine ek olarak aylık tablo görünümüyle yaklaşan günlerin namaz vakitlerini de karşılaştırabilirsiniz. Böylece düzenli ibadet planlaması daha kolay hale gelir.</p>
                            </article>
                            <article>
                                <h3>Konuma göre kıble yönü</h3>
                                <p>Konum belirlendiğinde kıble yönü harita ve pusula görünümüyle gösterilir. Mobil ve masaüstü kullanımda anlaşılır bir yön deneyimi sunarak ibadet hazırlığını hızlandırır.</p>
                            </article>
                        </div>
                    </section>
 

                    <section class="content-card faq-card" aria-labelledby="faqTitle">
                        <div class="section-heading">
                            <span class="section-kicker">Sık Sorulan Sorular</span>
                            <h2 id="faqTitle">EzanVaktim hakkında temel bilgiler</h2>
                        </div>
                        <div class="faq-list">
                            <?php foreach ($faqItems as $faq): ?>
                                <article class="faq-item">
                                    <h3><?= e($faq['question']); ?></h3>
                                    <p><?= e($faq['answer']); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <aside class="side-column">
                    <section class="qibla-card content-card">
                        <div class="qibla-head">
                            <h2><svg><use href="#i-compass"></use></svg><span><?= e($site['qibla']['title']); ?></span></h2>
                            <div class="qibla-badge" id="qiblaAngle"><?= e($site['qibla']['angle_label']); ?></div>
                        </div>
                        <div class="qibla-map" id="qiblaMap" aria-label="Kıble yönü haritası">
                            <div class="qibla-map-canvas" id="qiblaMapCanvas"></div>
                            <div class="qibla-map-placeholder" id="qiblaMapPlaceholder">
                                <span class="map-pin"><svg><use href="#i-location"></use></svg></span>
                                <strong id="qiblaMapStatus">Konum alındığında kıble haritası burada görünecek.</strong>
                                <span><?= e($site['qibla']['destination']); ?></span>
                            </div>
                            <div class="map-footer">
                                <span class="map-dot"></span>
                                <span id="qiblaMapFooter"><?= e($site['qibla']['destination']); ?></span>
                            </div>
                        </div>
                        <div class="compass-panel" data-angle="<?= e((string) $site['qibla']['angle']); ?>">
                            <div class="compass">
                                <span class="compass-letter north">K</span>
                                <span class="compass-letter east">D</span>
                                <span class="compass-letter south">G</span>
                                <span class="compass-letter west">B</span>
                                <div class="compass-needle"></div>
                                <div class="compass-center"><svg><use href="#i-mosque"></use></svg></div>
                            </div>
                        </div>
                    </section>
                </aside>

            <section class="search-intro" aria-labelledby="pageTitle">
                <span class="section-kicker">Türkiye Geneli Namaz Saatleri</span>
                <h1 id="pageTitle">Ezan vakitleri, namaz saatleri ve kıble yönü</h1>
                <p class="info-copy">Şehir veya ilçe seçerek güncel ezan vakitlerini görüntüleyin, aylık tabloyu inceleyin ve bulunduğunuz konuma göre kıble yönünü hızlıca öğrenin.</p>
                <p class="info-copy">Bu web sitesi, üniversite bitirme tezi çalışması kapsamında geliştirilmiştir. Amaç, namaz vakitlerinin dijital ortamda düzenli, erişilebilir ve kullanıcı dostu bir şekilde sunulmasıdır. Proje Sahibi / Geliştirici: Ahmet Serdar Hacıoğlu</p>
            </section>
        </div>
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
            <div class="container footer-credit">Bu proje Ahmet Serdar Hacıoğlu'na aittir.</div>
        </footer>
    </div>

    <script>
        window.EzanVaktimConfig = <?= json_encode([
            'siteUrl' => $site['site_url'],
            'canonicalUrl' => $meta['url'],
            'status' => $site['status'],
            'search' => $site['search'],
            'prayers' => array_column($site['prayers'], 'name', 'icon'),
            'defaultQiblaAngle' => $site['qibla']['angle'],
            'qiblaDestination' => $site['qibla']['destination'],
            'debug' => !(getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod'),
            'csrfToken' => app_csrf_token(),
            'api' => [
                'ipGeolocation' => 'api/ip-geolocation.php',
                'locationResolve' => 'api/location-resolve.php?q=',
                'debugLog' => 'api/debug-log.php',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    <script src="assets/js/site.js?v=<?= urlencode((string) @filemtime(__DIR__ . '/assets/js/site.js')); ?>" charset="utf-8"></script>
</body>
</html>
