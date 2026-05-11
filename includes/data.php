<?php

declare(strict_types=1);

require_once __DIR__ . '/settings.php';

$appSettings = app_settings();
$selectedPrayerProvider = (string) ($appSettings['prayer_api_provider'] ?? 'emushaf');
$selectedPrayerProviderConfig = is_array($appSettings['prayer_api_providers'][$selectedPrayerProvider] ?? null)
    ? $appSettings['prayer_api_providers'][$selectedPrayerProvider]
    : [];

$site = [
    'title' => 'EzanVaktim',
    'description' => 'Türkiye geneli güncel ezan vakitleri, aylık namaz saatleri ve kıble yönü bilgisi sunan hızlı namaz vakti uygulaması.',
    'site_url' => 'https://ezanvaktim.tr',
    'contact_email' => 'info@ezanvaktim.tr',
    'default_share_image' => 'logo/logo.png',
    'location' => 'Konum belirleniyor',
    'clock' => '--:--',
    'hijri_date' => 'Hicri tarih hazırlanıyor',
    'gregorian_date' => 'Bugünün tarihi hazırlanıyor',
    'countdown' => [
        'label' => 'Kalan Süre',
        'time' => '--:--',
        'progress' => 0,
    ],
    'current_prayer' => [
        'name' => 'Vakit yükleniyor',
        'start' => '--:--',
        'end' => '--:--',
    ],
    'prayers' => [
        ['name' => 'İmsak', 'time' => '--:--', 'icon' => 'imsak'],
        ['name' => 'Güneş', 'time' => '--:--', 'icon' => 'gunes'],
        ['name' => 'Öğle', 'time' => '--:--', 'icon' => 'ogle', 'active' => true],
        ['name' => 'İkindi', 'time' => '--:--', 'icon' => 'ikindi'],
        ['name' => 'Akşam', 'time' => '--:--', 'icon' => 'aksam'],
        ['name' => 'Yatsı', 'time' => '--:--', 'icon' => 'yatsi'],
    ],
    'insight' => [
        'title' => 'Günün İlhamı',
        'quote' => 'Namazı dosdoğru kıl. Çünkü namaz, insanı hayasızlıktan ve kötülükten alıkoyar.',
        'source' => 'Ankebut Suresi, 45',
    ],
    'sun_journey' => [
        'title' => 'Gün Işığı',
        'value' => 0,
        'left_label' => 'Gün Doğumu',
        'right_label' => 'Gün Batımı',
    ],
    'qibla' => [
        'title' => 'Kıble Yönü',
        'angle_label' => '152.9° Kuzeyden',
        'angle' => 152.9,
        'destination' => 'Mekke, Suudi Arabistan',
    ],
    'search' => [
        'placeholder' => 'Şehir veya ilçe ara...',
        'button' => 'Konumumu Bul',
        'change_button' => 'Konumu Değiştir',
        'helper' => 'Aylık detaylı vakit tablosu yakında burada yer alacak.',
        'manual_title' => 'Elle Konum Seçimi',
        'manual_help' => 'Konum izni vermezseniz şehir ya da ilçe yazarak uygun sonucu seçebilirsiniz.',
    ],
    'status' => [
        'loading' => 'Konum bilgisi ve namaz vakitleri hazırlanıyor.',
        'ready' => 'Bugünün namaz vakitleri güncel.',
        'permission_denied' => 'Konum izni verilmedi. Aşağıdan şehir veya ilçe seçebilirsiniz.',
        'ip_lookup' => 'Konum izni yok. IP adresinden yaklaşık konum tespit ediliyor.',
        'ip_ready' => 'Yaklaşık konum IP adresinden belirlendi ve vakitler güncellendi.',
        'ip_not_found' => 'IP adresinden konum tespit edilemedi. Aşağıdan şehir veya ilçe seçebilirsiniz.',
        'searching' => 'Uygun konum aranıyor...',
        'not_found' => 'Bu aramaya uygun bir ilçe ya da şehir bulunamadı.',
        'api_error' => 'Namaz vakitleri şu anda alınamıyor. Lütfen tekrar deneyin.',
    ],
    'footer_links' => [
        ['label' => 'Gizlilik', 'href' => 'gizlilik.php'],
        ['label' => 'Kullanım Koşulları', 'href' => 'kullanim-kosullari.php'],
        ['label' => 'Hakkımızda', 'href' => 'hakkimizda.php'],
    ],
    'api' => [
        'provider' => $selectedPrayerProvider,
        'provider_label' => $selectedPrayerProviderConfig['label'] ?? $selectedPrayerProvider,
        'reverse_geocode' => 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=tr',
        'ip_geolocation' => 'https://ipapi.co/json/',
    ],
];
