# Borsatek Yapay Zeka v2.0.0

WordPress eklentisi — RSS kaynaklarından finans haberleri çeker, Anthropic Claude veya Google Gemini ile SEO kurallarına uygun özgün Türkçe içerik üretir ve WordPress'e taslak kaydeder.

---

## Kurulum

1. `borsatek-yapay-zeka/` klasörünü WordPress'in `wp-content/plugins/` dizinine yükleyin.
2. **Eklentiler** → **Yüklü Eklentiler** sayfasından **Borsatek Yapay Zeka**'yı etkinleştirin.
3. Sol menüde **Borsatek YZ** menüsüne tıklayın.

---

## Zorunlu Ayarlar

**AI & SEO Ayarları** sekmesine gidin:

| Ayar | Açıklama |
|------|----------|
| AI Sağlayıcı | Anthropic veya Gemini seçin |
| API Anahtarı | Seçtiğiniz sağlayıcının API anahtarını girin |
| SEO Kuralları | Serbest metin olarak yazın (isteğe bağlı) |

**RSS Kaynakları** sekmesine gidin:

- Her satıra bir RSS feed URL'si girin
- Tarama aralığını belirleyin (15 dk / 30 dk / 1 saat)
- Kaydet

---

## WP-Cron Notu (Hostinger VPS / Dedike Sunucu)

WP-Cron varsayılan olarak site ziyaretçilerine bağlıdır. Düzenli tarama için sistem cron'u önerilir:

**1. `wp-config.php` dosyasına ekleyin:**
```php
define('DISABLE_WP_CRON', true);
```

**2. Sistem cron'u ekleyin (`crontab -e`):**
```bash
* * * * * wget -q -O - https://siteniz.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

---

## Sınıf Mimarisi

Eklenti tek sorumluluk prensibine göre tasarlanmıştır. `BorsatekPlugin` singleton sınıfı tüm bağımlılıkları oluşturur ve WordPress hook'larını kaydeder. Veri akışı şu şekilde çalışır: `BorsatekRssScanner` → `BorsatekContentFetcher` → `BorsatekQueue` → `BorsatekRewriter` (içinde `BorsatekAiProvider` + `BorsatekSeoEngine` + `BorsatekTranslator`) → `wp_insert_post`.

| Sınıf | Sorumluluk |
|-------|-----------|
| `BorsatekPlugin` | Singleton bootstrap, DI container |
| `BorsatekQueue` | Custom post type üzerinden kuyruk CRUD |
| `BorsatekRssScanner` | WP-Cron ile RSS tarama |
| `BorsatekContentFetcher` | HTTP + Jina Reader ile içerik çekme |
| `BorsatekAiProvider` | Anthropic / Gemini / OpenAI / Together API |
| `BorsatekSeoEngine` | SEO kural ayrıştırma, uygulama, skor |
| `BorsatekRewriter` | Dönüşüm orkestratörü |
| `BorsatekTranslator` | DeepL + AI ile başlık çevirisi |
| `BorsatekStats` | Aylık/günlük işlem istatistikleri |
| `BorsatekWebhook` | REST API endpoint'leri |
| `BorsatekAdmin` | Admin panel, form işlemleri, AJAX |
| `BorsatekPermissions` | Kullanıcı yetki kontrolü |

---

## Webhook Kullanımı

Dış sistemlerden kuyruğa haber eklemek için:

```bash
curl -X POST https://siteniz.com/wp-json/borsatek-ai/v1/queue \
  -H "Authorization: Bearer WEBHOOK_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Haber Başlığı",
    "content": "Haber içeriği buraya...",
    "url": "https://kaynak.com/haber/12345",
    "feed": "https://kaynak.com/rss"
  }'
```

**Yanıtlar:**
- `201 Created` → Başarıyla eklendi, `{"success": true, "id": 123}`
- `400 Bad Request` → title veya content eksik
- `409 Conflict` → Bu URL zaten kuyruğa eklenmiş
- `403 Forbidden` → Token hatalı

---

## Çalışma Zamanı Ayarlarını Okuma

```bash
curl -X GET https://siteniz.com/wp-json/borsatek-ai/v1/settings \
  -H "Cookie: wordpress_logged_in_...=..."
```

---

## Sorun Giderme

### WP-Cron çalışmıyor

1. **Borsatek YZ → Sorun Giderme** sekmesinde WP-Cron durumunu kontrol edin.
2. `DISABLE_WP_CRON` tanımlıysa sistem cron'u eklediğinizden emin olun.
3. **RSS Kaynakları** sekmesinden feed sağlık durumunu inceleyin.

### AI hatası

1. API anahtarının doğru girildiğini kontrol edin (**AI & SEO Ayarları** → ilgili sağlayıcı).
2. **Sorun Giderme** sekmesindeki "Bağlantıyı Test Et" butonunu kullanın.
3. Fallback sağlayıcı için birden fazla API anahtarı girin.
4. Zaman aşımını artırın (varsayılan: 90 sn).

### İçerik çekilemiyor

- Investing.com için **Jina Reader API anahtarı** zorunludur.
- Minimum kaynak karakter sayısını düşürün.
- PHP cURL ve OpenSSL uzantılarının aktif olduğundan emin olun.

### Taslaklar oluşturuluyor ama yayınlanmıyor

Bu beklenen davranıştır. Eklenti her zaman **taslak** oluşturur, inceleme sonrası yayınlama kullanıcıya bırakılır. Otomatik yayın için üçüncü taraf otomasyon eklentisi kullanın.

---

## Geliştirici Notları

- Yapılan geliştirme ve düzeltmeler: [`GELISTIRME_KAYDI.md`](GELISTIRME_KAYDI.md)
- Kaynak kontrolü: Proje kökünde (`Borsatek-Yapay-Zeka/`) Git deposu vardır (`main` dalı). Yedek için GitHub/GitLab’da boş repo oluşturup `git remote add origin <url>` ve `git push -u origin main` kullanın.
- Tüm API çağrıları `wp_remote_post/get` ile yapılır (WordPress HTTP API).
- Custom post type `borsatek_ai_queue` admin panelinde gizlenmiştir (`show_ui = false`).
- SEO meta verileri hem Yoast hem de RankMath formatında kaydedilir.
- Tüm AJAX çağrıları nonce doğrulaması gerektirir.
- `BorsatekStats::reset()` tüm istatistik verilerini siler.

---

## Gereksinimler

- WordPress 6.0+
- PHP 7.4+
- cURL uzantısı
- SimpleXML uzantısı (RSS için)
