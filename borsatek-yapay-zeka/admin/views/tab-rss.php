<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var BorsatekRssScanner $scanner */

$currentFeeds   = (array) get_option( 'borsatek_ai_rss_feeds', [] );
$currentRaw     = implode( "\n", $currentFeeds );
$scanInterval   = (string) get_option( 'borsatek_ai_scan_interval', 'borsatek_30min' );
$lastScanTime   = (int) get_option( 'borsatek_ai_last_scan_time', 0 );
$nextCron       = wp_next_scheduled( 'borsatek_cron_scan_rss' );
$healthReport   = $scanner->getFeedHealthReport();
$rssBackup      = (array) get_option( 'borsatek_ai_rss_feeds_backup', [] );
$rssBackupAt    = (int) get_option( 'borsatek_ai_rss_feeds_backup_saved_at', 0 );
?>

<div class="borsatek-rss-wrap">

    <?php if ( ! empty( $rssBackup ) ) : ?>
        <div class="notice notice-info" style="margin:12px 0;">
            <p>
                <strong>RSS yedeği:</strong>
                <?php echo esc_html( (string) count( $rssBackup ) ); ?> adres kayıtlı
                <?php if ( $rssBackupAt > 0 ) : ?>
                    (<?php echo esc_html( wp_date( 'd.m.Y H:i', $rssBackupAt ) ); ?>)
                <?php endif; ?>.
                Liste yanlışlıkla silindiyse aşağıdan tek tıkla önceki kaydı geri yükleyebilirsiniz.
            </p>
            <form method="post" action="" style="margin:.5em 0 0;">
                <?php wp_nonce_field( 'borsatek_settings' ); ?>
                <input type="hidden" name="borsatek_save_action" value="restore_rss_backup">
                <button type="submit" class="button button-secondary">
                    Son yedeği geri yükle
                </button>
            </form>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="borsatek-form">
        <?php wp_nonce_field( 'borsatek_settings' ); ?>
        <input type="hidden" name="borsatek_save_action" value="save_rss">

        <!-- Feed Listesi -->
        <div class="borsatek-card">
            <h2 class="borsatek-card-title">
                <span class="dashicons dashicons-rss"></span>
                RSS Feed Kaynakları
            </h2>

            <div class="borsatek-field">
                <label for="borsatek_rss_feeds_raw">
                    Feed URL'leri <small>(her satıra bir URL)</small>
                </label>
                <textarea id="borsatek_rss_feeds_raw" name="borsatek_rss_feeds_raw"
                          rows="10" class="large-text code"
                          placeholder="https://www.investing.com/rss/news_25.rss&#10;https://feeds.finance.yahoo.com/rss/2.0/headline?s=^GSPC&#10;https://www.borsatr.com/feed/"><?php echo esc_textarea( $currentRaw ); ?></textarea>
                <p class="description">
                    Desteklenen kaynaklar: Investing.com, Yahoo Finance, Borsatr.com ve RSS destekleyen diğer finans siteleri.
                </p>
            </div>

            <div class="borsatek-field">
                <label for="borsatek_scan_interval">Otomatik Tarama Aralığı</label>
                <select id="borsatek_scan_interval" name="borsatek_scan_interval">
                    <option value="borsatek_15min" <?php selected( $scanInterval, 'borsatek_15min' ); ?>>Her 15 Dakika</option>
                    <option value="borsatek_30min" <?php selected( $scanInterval, 'borsatek_30min' ); ?>>Her 30 Dakika</option>
                    <option value="hourly"         <?php selected( $scanInterval, 'hourly' );         ?>>Her 1 Saat</option>
                    <option value="twicedaily"     <?php selected( $scanInterval, 'twicedaily' );     ?>>Günde 2 Kez</option>
                    <option value="daily"          <?php selected( $scanInterval, 'daily' );          ?>>Günde 1 Kez</option>
                </select>
            </div>

            <!-- Cron durumu -->
            <div class="borsatek-cron-status">
                <table class="borsatek-info-table">
                    <tr>
                        <td><strong>Son tarama:</strong></td>
                        <td><?php echo $lastScanTime > 0 ? esc_html( date( 'd.m.Y H:i', $lastScanTime ) ) . ' (' . human_time_diff( $lastScanTime ) . ' önce)' : 'Henüz yapılmadı'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Sonraki tarama:</strong></td>
                        <td><?php echo $nextCron ? esc_html( date( 'd.m.Y H:i', $nextCron ) ) . ' (' . human_time_diff( $nextCron ) . ' sonra)' : '<span class="borsatek-badge borsatek-badge-error">Planlanmamış</span>'; ?></td>
                    </tr>
                </table>
            </div>

            <p>
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-saved"></span> Ayarları Kaydet
                </button>
            </p>
        </div>

    </form>

    <!-- Feed Sağlık Raporu -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-heart"></span>
            Feed Sağlık Raporu
        </h2>

        <?php if ( empty( $healthReport ) ) : ?>
            <p class="description">Henüz tarama yapılmadı. "Şimdi Tara" butonuna tıklayarak başlatın.</p>
        <?php else : ?>
            <table class="borsatek-queue-table wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Feed URL</th>
                        <th>Son Kontrol</th>
                        <th>Durum</th>
                        <th>Son Hata</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $healthReport as $feedUrl => $info ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( $feedUrl ); ?>" target="_blank" class="borsatek-feed-url">
                                    <?php echo esc_html( parse_url( $feedUrl, PHP_URL_HOST ) ?: $feedUrl ); ?>
                                </a>
                                <br><small class="borsatek-muted"><?php echo esc_html( $feedUrl ); ?></small>
                            </td>
                            <td>
                                <?php echo ! empty( $info['lastCheck'] )
                                    ? esc_html( human_time_diff( $info['lastCheck'] ) . ' önce' )
                                    : '—'; ?>
                            </td>
                            <td>
                                <?php if ( $info['ok'] ) : ?>
                                    <span class="borsatek-badge borsatek-badge-done">✓ Aktif</span>
                                <?php else : ?>
                                    <span class="borsatek-badge borsatek-badge-error">✗ Hata</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! empty( $info['message'] ) ) : ?>
                                    <span class="borsatek-error-text" title="<?php echo esc_attr( $info['message'] ); ?>">
                                        <?php echo esc_html( mb_substr( $info['message'], 0, 80 ) ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="borsatek-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- VPS Cron Notu -->
    <div class="borsatek-notice-box">
        <span class="dashicons dashicons-performance"></span>
        <div>
            <strong>VPS / Dedike Sunucu Kullanıcıları İçin:</strong>
            <p>WP-Cron varsayılan olarak ziyaretçi bağlantısına bağlıdır. Daha güvenilir tarama için:</p>
            <ol>
                <li><code>wp-config.php</code> dosyasına <code>define('DISABLE_WP_CRON', true);</code> ekleyin.</li>
                <li>Sistem cron'u ile her dakika çalıştırın:<br>
                    <code>* * * * * wget -q -O - <?php echo esc_url( site_url( '/wp-cron.php?doing_wp_cron' ) ); ?> &gt;/dev/null 2&gt;&1</code>
                </li>
            </ol>
        </div>
    </div>

</div>
