<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$asyncJobId = absint( $_GET['async_job_id'] ?? 0 );
?>

<div class="borsatek-manual-wrap">

    <div class="borsatek-two-col">

        <!-- URL'den Çek -->
        <div class="borsatek-card">
            <h2 class="borsatek-card-title">
                <span class="dashicons dashicons-admin-links"></span>
                URL'den İçerik Çek
            </h2>
            <p class="borsatek-card-desc">
                Bir haber URL'si girin. Eklenti içeriği otomatik çekip yapay zeka ile işleyecek.
                İşlem arka planda çalışır; sayfa hemen yüklenir.
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="borsatek-form">
                <?php wp_nonce_field( 'borsatek_manual_draft' ); ?>
                <input type="hidden" name="action" value="borsatek_create_manual_draft">

                <div class="borsatek-field">
                    <label for="borsatek_manual_url">Haber URL'si <span class="required">*</span></label>
                    <input type="url" id="borsatek_manual_url" name="borsatek_manual_url"
                           placeholder="https://www.example.com/haber/..." class="regular-text" required>
                </div>

                <div class="borsatek-field">
                    <label for="borsatek_focus_keyword_url">Odak Kelime</label>
                    <input type="text" id="borsatek_focus_keyword_url" name="borsatek_focus_keyword"
                           placeholder="Örn: dolar kuru" class="regular-text">
                    <p class="description">Boş bırakılırsa AI belirler.</p>
                </div>

                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-download"></span>
                    Haber Ekle ve Dönüştür
                </button>
            </form>
        </div>

        <!-- Metin Yapıştır -->
        <div class="borsatek-card">
            <h2 class="borsatek-card-title">
                <span class="dashicons dashicons-clipboard"></span>
                Metin Yapıştır
            </h2>
            <p class="borsatek-card-desc">
                Başlık ve içeriği doğrudan yapıştırın. Dönüştürme işlemi sayfada hemen gerçekleşir.
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="borsatek-form">
                <?php wp_nonce_field( 'borsatek_manual_paste' ); ?>
                <input type="hidden" name="action" value="borsatek_create_manual_paste">

                <div class="borsatek-field">
                    <label for="borsatek_paste_title">Başlık</label>
                    <input type="text" id="borsatek_paste_title" name="borsatek_paste_title"
                           placeholder="Haber başlığı..." class="large-text">
                </div>

                <div class="borsatek-field">
                    <label for="borsatek_paste_content">İçerik <span class="required">*</span></label>
                    <textarea id="borsatek_paste_content" name="borsatek_paste_content"
                              rows="10" class="large-text" required
                              placeholder="Haber metnini buraya yapıştırın..."></textarea>
                </div>

                <div class="borsatek-field">
                    <label for="borsatek_paste_url">Kaynak URL (opsiyonel)</label>
                    <input type="url" id="borsatek_paste_url" name="borsatek_paste_url"
                           placeholder="https://..." class="regular-text">
                </div>

                <div class="borsatek-field">
                    <label for="borsatek_focus_keyword_paste">Odak Kelime</label>
                    <input type="text" id="borsatek_focus_keyword_paste" name="borsatek_focus_keyword"
                           placeholder="Örn: enflasyon" class="regular-text">
                </div>

                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-edit"></span>
                    Dönüştür ve Taslak Oluştur
                </button>
            </form>
        </div>

    </div><!-- .borsatek-two-col -->

    <!-- Async durum alanı -->
    <?php if ( $asyncJobId > 0 ) : ?>
        <input type="hidden" id="borsatek-async-job-id" value="<?php echo esc_attr( $asyncJobId ); ?>">
    <?php endif; ?>

    <div id="borsatek-async-status"<?php echo $asyncJobId > 0 ? '' : ' style="display:none;"'; ?> class="borsatek-async-notice">
        <span class="borsatek-spinner"></span>
        <span id="borsatek-async-status-text">İşleniyor... Lütfen bekleyin.</span>
    </div>

    <!-- Bilgi kutusu -->
    <div class="borsatek-info-box">
        <span class="dashicons dashicons-info"></span>
        <div>
            <strong>Nasıl çalışır?</strong>
            <ul>
                <li><strong>URL'den Çek:</strong> İçerik arka planda çekilir ve AI ile işlenir. İşlem 30–120 saniye sürebilir.</li>
                <li><strong>Metin Yapıştır:</strong> İçerik anında işlenir ve taslak oluşturulur.</li>
                <li>Oluşturulan taslaklar <em>Yazılar → Taslaklar</em> bölümünde görünür.</li>
            </ul>
        </div>
    </div>

</div>
