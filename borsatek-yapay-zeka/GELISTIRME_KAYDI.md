# Geliştirme ve düzeltme kaydı

Bu dosya projede yapılan özellik eklemelerini, düzeltmeleri ve önemli teknik kararları tarih sırasıyla tutar. Her Cursor veya yerel çalışma oturumunda açıp güncel tutmanız yeterlidir; isterseniz sohbette `@GELISTIRME_KAYDI.md` ile modele bağlayabilirsiniz.

## Nasıl kullanılır

1. **Yeni bir iş bittiğinde** aşağıdaki tabloya **en üste** bir satır ekleyin (en yeni kayıt üstte).
2. **Tür:** `özellik` | `düzeltme` | `refactor` | `güvenlik` | `doc` | `diğer`
3. **Kapsam:** İlgili dosya veya modül yollarını kısa yazın (`includes/class-queue.php` gibi).

---

## Kayıtlar (yeniden eskiye)

| Tarih | Tür | Kapsam | Özet |
|-------|-----|--------|------|
| 2026-05-05 | özellik | `tab-stream.php`, `borsatek-admin.css` | **👀 GÖRSEL İYİLEŞTİRME: Odak Kelime Sütunu!** Haber Akışı tablosuna "Odak Kelime" sütunu eklendi. Eksik olanlar "⚠️ Eksik" badge'i, var olanlar yeşil badge gösteriyor. Odak kelimesi olmayan haberlerde "⚠️ Dönüştür" turuncu buton ile uyarı. Artık hangi haberin hazır olduğu net görünüyor! |
| 2026-05-05 | düzeltme | `class-rewriter.php`, `class-seo-engine.php` | **🚨 BÜYÜK PROBLEM ÇÖZÜLDÜ: Kaynak Sadakat!** AI artık kafasından metin yazmayacak. Prompt'ta sert kurallar: kaynak metindeki tüm rakamlar korunmalı, kaynakta olmayan bilgi YASAK, hallüsinasyon engelleyici kontroller. SEO engine'de sayısal doğruluk kontrol sistemi eklendi. |
| 2026-05-05 | özellik | **TÜM SEKMELERDE**, `tab-stream.php`, `class-admin.php`, `borsatek-admin.js` | **🔥 KRİTİK: Odak kelime artık her yerde zorunlu!** Haber Akışı, Manuel, Önizleme, Toplu Dönüştürme - hiçbirinde odak kelime olmadan işlem yapılamaz. Modal popup ile kullanıcı odak kelime girmek zorunda. Backend'de de validation var. SEO kuralları artık tam çalışıyor. |
| 2026-05-05 | özellik | `tab-manual.php`, `borsatek-admin.js` | **Odak kelime zorunluluğu:** Manuel sekmede odak kelime alanı artık zorunlu; boş bırakıldığında JavaScript ve HTML5 validation ile "Odak kelime gerekli" uyarısı çıkar. SEO motorunda zaten destekleniyordu. |
| 2026-05-05 | düzeltme | `class-stats.php`, `tab-*.php` | Saat görüntüleme sorunu düzeltildi: `date()` → `wp_date()` dönüştürme ile UTC+3 (WordPress timezone ayarına göre) doğru gösterim sağlandı. |
| 2026-05-05 | güvenlik | `borsatek-yapay-zeka.php` | Aktivasyon varsayılanlarındaki API anahtarları kaldırıldı (yalnızca WordPress ayarlarından girilmeli). Bu anahtarlar daha önce repoda metin olarak bulunduğu için ilgili sağlayıcılarda yenilemeniz önerilir. |
| 2026-05-05 | diğer | `origin` → GitHub | `https://github.com/borsatek/borsatek-yapay-zeka` uzaktan depo eklendi; `main` dalı push edildi (GitHub CLI kuruldu; `gh auth login` isteğe bağlı). |
| 2026-05-05 | diğer | `.git/`, `.gitignore` | Git deposu oluşturuldu (`main` dalı); yedekleme ve sürüm takibi için ilk commit atıldı. |
| 2026-05-05 | doc | `GELISTIRME_KAYDI.md` | Proje geliştirmelerini ve düzeltmeleri tek yerde takip etmek için bu kayıt dosyası oluşturuldu. |

<!-- Yeni satır örneği (kopyalayıp düzenleyin):
| YYYY-MM-DD | düzeltme | `admin/views/tab-seo.php` | SEO sekmesinde ... hatası giderildi. |
-->

---

## 🔄 Değişiklik Sonrası Yapılacaklar

**Her geliştirme/düzeltme sonrası aşağıdaki adımları uygulayın:**

### 📱 **Yerel Geliştirme Ortamında**

#### PHP Değişiklikleri Sonrası:
- WordPress admin panelini yenile (F5)
- İlgili sekmeye git ve işlevselliği test et
- Cache plugin varsa temizle

#### JavaScript/CSS Değişiklikleri Sonrası:
- Tarayıcıda **Hard Refresh** (Ctrl+Shift+R)
- Developer Tools açıp "Disable cache" seç
- Console'da hata var mı kontrol et

#### Ayar/Veritabanı Değişiklikleri Sonrası:
- İlgili admin sekmesini yenile
- Ayarları kaydet ve test et
- Veri doğruluğunu kontrol et

### 🚀 **Canlı Sunucuya Çıkarken**

#### Dosya Yükleme:
```
wp-content/plugins/borsatek-yapay-zeka/
├── admin/ (değişen dosyalar)
├── includes/ (değişen dosyalar)
└── borsatek-yapay-zeka.php (gerekirse)
```

#### Cache Temizleme:
- WordPress cache plugin'i temizle
- CDN cache'ini temizle (Cloudflare vb.)
- Object cache temizle (Redis/Memcached)

#### Test:
- Canlı sitede aynı testleri tekrarla
- Hata loglarını kontrol et
- Kritik işlevleri test et

### ⚠️ **Dikkat Edilecekler**

- **API anahtarları** hiçbir zaman commit etme
- **Veritabanı yedek** al (büyük değişiklikler öncesi)
- **Staging** ortamında önce test et
- **Error logları** kontrol et (`/wp-content/debug.log`)

### 📋 **Hızlı Test Checklist**

- [ ] Admin paneli açılıyor mu?
- [ ] Manuel sekme çalışıyor mu?
- [ ] RSS tarama çalışıyor mu?
- [ ] İstatistikler doğru mu?
- [ ] SEO kuralları uygulanıyor mu?
- [ ] Hatalar console'da yok mu?
