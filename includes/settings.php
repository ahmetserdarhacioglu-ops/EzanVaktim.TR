<?php

declare(strict_types=1);

function app_settings(): array
{
    return [
        'prayer_api_provider' => 'emushaf',
        'prayer_api_providers' => [
            'emushaf' => [
                'label' => 'Ezan Vakti API',
                'base_url' => 'https://ezanvakti.emushaf.net', //Diyanet tarafindan saglanan API
                'country_id' => 2,
                'cache_ttl' => 2592000,
                'search_batch_size' => 8,
                'city_hydration_limit' => 10,
                'popular_country_ids' => [2, 13, 15, 21, 11, 4, 35, 33, 52, 59],
                'popular_city_names' => ['istanbul', 'eskisehir', 'konya', 'ankara', 'izmir', 'bursa', 'berlin', 'paris', 'brussel', 'amsterdam', 'vienna', 'london'],
                'notes' => [
                    'Ulke adlarinda ve Ingilizce isimlerde kaynak kaynakli tutarsizliklar olabilir; uygulama aramada Turkce alanlari esas alir.',
                    'MiladiTarihUzunIso8601 alanindaki saat dilimi guvenilmez oldugu icin tarih hesaplarinda kullanilmaz.',
                    'Bazi ulkelerde sehirler ilce gibi listelenebilir; uygulama secilen konumu son seviye lokasyon olarak ele alir.',
                ],
            ],
        ],
    ];
}
