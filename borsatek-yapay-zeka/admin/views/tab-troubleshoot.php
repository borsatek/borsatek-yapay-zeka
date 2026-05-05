<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wp_version;

$phpVersion     = PHP_VERSION;
$wpVersion      = $wp_version;
$pluginVersion  = BORSATEK_YZ_VERSION;
$lastScanTime   = (int) get_option( 'borsatek_ai_last_scan_time', 0 );
$nextCron       = wp_next_scheduled( 'borsatek_cron_scan_rss' );
$activeProvider = (string) get_option( 'borsatek_ai_provider', 'anthropic' );

$modelMap = [
    'anthropic' => (string) get_option( 'borsatek_ai_anthropic_model', 'claude-sonnet-4-20250514' ),
    'gemini'    => (string) get_option( 'borsatek_ai_gemini_model',    'gemini-2.5-flash' ),
    'openai'    => (string) get_option( 'borsatek_ai_openai_model',    'gpt-4o-mini' ),
    'together'  => (string) get_option( 'borsatek_ai_together_model',  '' ),
];
$activeModel = $modelMap[ $activeProvider ] ?? '—';

$cronStatus  = $nextCron
    ? '<span class="borsatek-badge borsatek-badge-done">✓ Aktif (' . esc_html( date( 'H:i', $nextCron ) ) . ')</span>'
    : '<span class="borsatek-badge borsatek-badge-error">✗ Planlanmamış</span>';

$wpCronDisabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

// API anahtar durumları
$keyStatuses = [
    'Anthropic' => ! empty( get_option( 'borsatek_ai_anthropic_key' ) ),
    'Gemini'    => ! empty( get_option( 'borsatek_ai_gemini_key' ) ),
    'OpenAI'    => ! empty( get_option( 'borsatek_ai_openai_key' ) ),
    'Together'  => ! empty( get_option( 'borsatek_ai_together_key' ) ),
    'Jina'      => ! empty( get_option( 'borsatek_ai_jina_key' ) ),
    'DeepL'     => ! empty( get_option( 'borsatek_ai_deepl_key' ) ),
];
?>

<div class="borsatek-troubleshoot-wrap">

    <div class="borsatek-two-col">

        <!-- Hata Açıklama -->
        <div class="borsatek-card">
            <h2 class="borsatek-card-title">
                <span class="dashicons dashicons-sos"></span>
                AI ile Hata Aç
            </h2>
            <p class="description">
                Karşılaştığınız hata mesajını aşağıya yapıştırın.
                Yapay zeka hatayı Türkçe açıklayıp çözüm önerileri sunacak.
            </p>

            <div class="borsatek-field">
                <label for="borsatek-error-text">Hata Metni</label>
                <textarea id="borsatek-error-text" rows="8" class="large-text borsatek-code-input"
                          placeholder="Fatal error: Uncaught Error: Call to a member function...

veya

cURL error 28: Operation timed out..."></textarea>
            </div>

            <button type="button" id="borsatek-explain-btn" class="button button-primary">
                <span class="dashicons dashicons-search"></span>
                AI ile Açıkla
            </button>
            <span id="borsatek-explain-spinner" class="borsatek-spinner" style="display:none;margin-left:8px;"></span>

            <div id="borsatek-explain-result" style="margin-top:15px;"></div>
        </div>

        <!-- Sistem Bilgisi -->
        <div class="borsatek-card">
            <h2 class="borsatek-card-title">
                <span class="dashicons dashicons-desktop"></span>
                Sistem Bilgisi
            </h2>

            <table class="borsatek-info-table">
                <tr>
                    <td><strong>PHP Sürümü:</strong></td>
                    <td>
                        <code><?php echo esc_html( $phpVersion ); ?></code>
                        <?php if ( version_compare( $phpVersion, '7.4', '<' ) ) : ?>
                            <span class="borsatek-badge borsatek-badge-error">Eski!</span>
                        <?php else : ?>
                            <span class="borsatek-badge borsatek-badge-done">✓</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>WordPress Sürümü:</strong></td>
                    <td>
                        <code><?php echo esc_html( $wpVersion ); ?></code>
                        <?php if ( version_compare( $wpVersion, '6.0', '<' ) ) : ?>
                            <span class="borsatek-badge borsatek-badge-error">Eski!</span>
                        <?php else : ?>
                            <span class="borsatek-badge borsatek-badge-done">✓</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Eklenti Sürümü:</strong></td>
                    <td><code><?php echo esc_html( $pluginVersion ); ?></code></td>
                </tr>
                <tr>
                    <td><strong>Son Tarama:</strong></td>
                    <td>
                        <?php echo $lastScanTime > 0
                            ? esc_html( date( 'd.m.Y H:i', $lastScanTime ) . ' (' . human_time_diff( $lastScanTime ) . ' önce)' )
                            : '<em>Henüz yapılmadı</em>'; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>WP-Cron Durumu:</strong></td>
                    <td><?php echo $cronStatus; // already escaped ?></td>
                </tr>
                <tr>
                    <td><strong>DISABLE_WP_CRON:</strong></td>
                    <td>
                        <?php if ( $wpCronDisabled ) : ?>
                            <span class="borsatek-badge borsatek-badge-running">Aktif (Sistem cron gerekli)</span>
                        <?php else : ?>
                            <span class="borsatek-badge borsatek-badge-done">Devre dışı (Normal)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Aktif Sağlayıcı:</strong></td>
                    <td><code><?php echo esc_html( ucfirst( $activeProvider ) ); ?></code></td>
                </tr>
                <tr>
                    <td><strong>Aktif Model:</strong></td>
                    <td><code><?php echo esc_html( $activeModel ); ?></code></td>
                </tr>
            </table>

            <hr style="margin:15px 0;">

            <h3 style="margin-top:0;">API Anahtar Durumu</h3>
            <table class="borsatek-info-table">
                <?php foreach ( $keyStatuses as $service => $hasKey ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $service ); ?>:</strong></td>
                        <td>
                            <?php if ( $hasKey ) : ?>
                                <span class="borsatek-badge borsatek-badge-done">✓ Girilmiş</span>
                            <?php else : ?>
                                <span class="borsatek-badge borsatek-badge-queued">— Girilmemiş</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    </div>

    <!-- Sık Karşılaşılan Sorunlar -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-editor-help"></span>
            Sık Karşılaşılan Sorunlar
        </h2>

        <div class="borsatek-faq">

            <details>
                <summary><strong>WP-Cron çalışmıyor / haberler otomatik gelmiyor</strong></summary>
                <div>
                    <p>WP-Cron varsayılan olarak site ziyaretçilerine bağlıdır. Ziyaretçi yoksa cron çalışmaz.</p>
                    <ol>
                        <li><code>wp-config.php</code>'ye <code>define('DISABLE_WP_CRON', true);</code> ekleyin.</li>
                        <li>Sistem cron'u ile düzenli olarak tetikleyin:<br>
                            <code>*/5 * * * * curl -s <?php echo esc_url( site_url( '/wp-cron.php?doing_wp_cron' ) ); ?> &gt;/dev/null</code>
                        </li>
                        <li>"RSS Kaynakları" sekmesindeki WP-Cron durumunu kontrol edin.</li>
                    </ol>
                </div>
            </details>

            <details>
                <summary><strong>AI hatası: "HTTP 529" veya "overloaded"</strong></summary>
                <div>
                    <p>Anthropic veya Gemini sunucuları aşırı yüklenmiş. Birkaç dakika bekleyin.</p>
                    <p>"Repair Pass" aktifse otomatik olarak diğer sağlayıcıya geçer.
                    "AI & SEO Ayarları"nda fallback sağlayıcı aktif ettiğinizden emin olun.</p>
                </div>
            </details>

            <details>
                <summary><strong>İçerik çekilemiyor: "HTTP 403" veya boş içerik</strong></summary>
                <div>
                    <p>Site bot engelleyicisi kullanıyor olabilir.</p>
                    <ul>
                        <li>Investing.com için Jina Reader API anahtarı girmeniz gereklidir.</li>
                        <li>"AI & SEO Ayarları"ndan Jina API anahtarı ekleyin.</li>
                        <li>Minimum kaynak karakter sayısını düşürün (varsayılan: 300).</li>
                    </ul>
                </div>
            </details>

            <details>
                <summary><strong>AI yanıtı JSON olarak ayrıştırılamadı</strong></summary>
                <div>
                    <p>AI bazen JSON dışı metin üretebilir.</p>
                    <ul>
                        <li>"Repair Pass" açın — AI ikinci kez daha sıkı JSON formatında çalışır.</li>
                        <li>Model değiştirin (karmaşık modeller daha az hata yapar).</li>
                        <li>"Maliyet Modu"nu "Yüksek Kalite"ye alın (daha fazla token).</li>
                    </ul>
                </div>
            </details>

            <details>
                <summary><strong>Taslaklar oluşturuluyor ama yayınlanmıyor</strong></summary>
                <div>
                    <p>Bu normal bir davranıştır — eklenti her zaman <strong>taslak</strong> oluşturur.
                    Taslakları inceleyip kendiniz yayınlamanız gerekir.</p>
                    <p>Otomatik yayınlama için WordPress otomasyon eklentilerini (ör. AutomatorWP) kullanabilirsiniz.</p>
                </div>
            </details>

        </div>
    </div>

</div>
