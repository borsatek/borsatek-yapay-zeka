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
| 2026-05-05 | güvenlik | `borsatek-yapay-zeka.php` | Aktivasyon varsayılanlarındaki API anahtarları kaldırıldı (yalnızca WordPress ayarlarından girilmeli). Bu anahtarlar daha önce repoda metin olarak bulunduğu için ilgili sağlayıcılarda yenilemeniz önerilir. |
| 2026-05-05 | diğer | `origin` → GitHub | `https://github.com/borsatek/borsatek-yapay-zeka` uzaktan depo eklendi; `main` dalı push edildi (GitHub CLI kuruldu; `gh auth login` isteğe bağlı). |
| 2026-05-05 | diğer | `.git/`, `.gitignore` | Git deposu oluşturuldu (`main` dalı); yedekleme ve sürüm takibi için ilk commit atıldı. |
| 2026-05-05 | doc | `GELISTIRME_KAYDI.md` | Proje geliştirmelerini ve düzeltmeleri tek yerde takip etmek için bu kayıt dosyası oluşturuldu. |

<!-- Yeni satır örneği (kopyalayıp düzenleyin):
| YYYY-MM-DD | düzeltme | `admin/views/tab-seo.php` | SEO sekmesinde ... hatası giderildi. |
-->
