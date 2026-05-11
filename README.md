# EzanVaktim

[**ezanvaktim.tr**](https://ezanvaktim.tr) — Türkiye için şehir ve ilçe bazlı **güncel ezan vakitleri**, aylık **namaz saatleri tablosu** ve konuma göre **kıble yönü** gösteren açık kaynak web uygulaması.

Üniversite bitirme tezi kapsamında geliştirilmiştir. Veriler Diyanet tarafından sağlanmaktadır.

🌐 **Canlı site:** <https://ezanvaktim.tr>

## Özellikler

- 🕌 Şehir ve ilçe araması ile **anlık ezan vakitleri** — imsak, güneş, öğle, ikindi, akşam, yatsı
- 📍 IP veya tarayıcı konumundan **otomatik konum** tespiti
- 📅 **Aylık namaz vakitleri tablosu**
- 🧭 Konuma göre **kıble yönü** hesaplama ve harita üzerinde gösterim
- 🔒 JSON API ile güvenilir kaynaklardan veriler
- 📱 Mobil uyumlu, hafif arayüz

## Canlı Sayfalar

| Sayfa | URL |
|-------|-----|
| Ana sayfa | <https://ezanvaktim.tr/> |
| Hakkımızda | <https://ezanvaktim.tr/hakkimizda.php> |
| Destek ve İletişim | <https://ezanvaktim.tr/destek.php> |
| Gizlilik Politikası | <https://ezanvaktim.tr/gizlilik.php> |
| Kullanım Koşulları | <https://ezanvaktim.tr/kullanim-kosullari.php> |

## Gereksinimler

- **PHP 8.1+**

## Dizin Yapısı

```
api/         Ön uç tarafından çağrılan JSON uç noktaları
assets/      CSS, JS ve statik veri dosyaları
includes/    PHP modülleri (bootstrap, SEO, HTTP, vakit sağlayıcı)
logo/        Logo ve ikon dosyaları
storage/     Çalışma zamanı önbellek ve log klasörleri (git'e dahil değil)
```

## Kullanılan Servisler

- [ezanvakti.emushaf.net](https://ezanvakti.emushaf.net) — Birincil namaz vakti API'si (Diyanet Api)
- [Nominatim (OpenStreetMap)](https://nominatim.openstreetmap.org) — Ters coğrafi kodlama
- [ipapi.co](https://ipapi.co) — IP tabanlı konum tespiti
- [Leaflet](https://leafletjs.com) — Kıble yönü haritası

## SSS

**EzanVaktim hangi şehirler için namaz vakitlerini gösterir?**
Türkiye genelindeki şehir ve ilçeler için güncel namaz vakitlerini listeler. Detay için [ezanvaktim.tr](https://ezanvaktim.tr) adresini ziyaret edin.

**Kıble yönü nasıl hesaplanır?**
Konum belirlendiğinde, bulunduğunuz noktadan Mekke yönü trigonometrik olarak hesaplanır ve harita ile pusula görünümünde gösterilir.

**Konum izni vermeden kullanabilir miyim?**
Evet. Şehir veya ilçe yazarak manuel arama yapabilirsiniz.

## Lisans

Tüm hakları saklıdır. Bu proje akademik amaçlarla geliştirilmiştir.

## Geliştirici

**Ahmet Serdar Hacıoğlu** — [ezanvaktim.tr](https://ezanvaktim.tr)
