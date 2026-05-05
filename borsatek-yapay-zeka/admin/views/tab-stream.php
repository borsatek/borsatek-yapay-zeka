<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var BorsatekQueue $queue */
/** @var BorsatekStats $stats */

if ( isset( $_GET['setup'] ) && $_GET['setup'] === 'done' ) : ?>
  <div class="notice notice-success is-dismissible">
    <p><strong>✓ Kurulum tamamlandı!</strong> Borsatek Yapay Zeka kullanıma hazır. RSS taraması otomatik başlayacak veya "Şimdi Tara" butonuna basabilirsiniz.</p>
  </div>
<?php endif;

$lastScanTime = (int) get_option( 'borsatek_ai_last_scan_time', 0 );
$lastScanText = $lastScanTime > 0 ? human_time_diff( $lastScanTime, time() ) . ' önce' : 'Henüz taranmadı';

// Filtreler
$filterFeed   = sanitize_text_field( $_GET['filter_feed']      ?? '' );
$filterSearch = sanitize_text_field( $_GET['filter_search']    ?? '' );
$filterFrom   = sanitize_text_field( $_GET['filter_from']      ?? '' );
$filterTo     = sanitize_text_field( $_GET['filter_to']        ?? '' );
$currentPage  = max( 1, (int) ( $_GET['qpage'] ?? 1 ) );

// Tarih-saat filtresi: datetime-local → 'Y-m-d H:i' formatı
$dateFrom = ! empty( $filterFrom ) ? sanitize_text_field( $filterFrom ) : '';
$dateTo   = ! empty( $filterTo )   ? sanitize_text_field( $filterTo )   : '';

$feedsRaw  = (array) get_option( 'borsatek_ai_rss_feeds', [] );
$queueData = $queue->getItems( [
    'feed'     => $filterFeed,
    'dateFrom' => $dateFrom,
    'dateTo'   => $dateTo,
    'search'   => $filterSearch,
    'page'     => $currentPage,
    'perPage'  => 50,
] );

$items      = $queueData['items']      ?? [];
$totalItems = $queueData['total']      ?? 0;
$totalPages = $queueData['totalPages'] ?? 1;

$asyncJobId = absint( $_GET['async_job_id'] ?? 0 );
?>

<div class="borsatek-stream-wrap">

    <!-- Üst araç çubuğu -->
    <div class="borsatek-toolbar">
        <div class="borsatek-toolbar-left">
            <button id="borsatek-force-scan-btn" class="button button-primary">
                <span class="dashicons dashicons-update"></span> Şimdi Tara
            </button>
            <span id="borsatek-scan-spinner" class="borsatek-spinner" style="display:none;"></span>
            <span class="borsatek-last-scan">Son tarama: <strong><?php echo esc_html( $lastScanText ); ?></strong></span>
        </div>
        <div class="borsatek-toolbar-right">
            <span class="borsatek-queue-count">Toplam: <strong><?php echo esc_html( $totalItems ); ?></strong> öğe</span>
        </div>
    </div>

    <!-- Filtre satırı -->
    <form method="get" action="" class="borsatek-filter-bar">
        <input type="hidden" name="page" value="borsatek-ai-news-app">
        <input type="hidden" name="tab" value="stream">

        <select name="filter_feed" class="borsatek-filter-select">
            <option value="">Tüm Kaynaklar</option>
            <?php foreach ( $feedsRaw as $feedUrl ) : ?>
                <option value="<?php echo esc_attr( $feedUrl ); ?>" <?php selected( $filterFeed, $feedUrl ); ?>>
                    <?php echo esc_html( parse_url( $feedUrl, PHP_URL_HOST ) ?: $feedUrl ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="borsatek-date-range">
            <label class="borsatek-date-label">Başlangıç:</label>
            <input type="datetime-local" name="filter_from"
                   value="<?php echo esc_attr( str_replace( ' ', 'T', $filterFrom ) ); ?>"
                   class="borsatek-filter-date">
            <label class="borsatek-date-label">Bitiş:</label>
            <input type="datetime-local" name="filter_to"
                   value="<?php echo esc_attr( str_replace( ' ', 'T', $filterTo ) ); ?>"
                   class="borsatek-filter-date">
        </div>

        <input type="text" name="filter_search" value="<?php echo esc_attr( $filterSearch ); ?>"
               placeholder="Başlıkta ara..." class="borsatek-filter-input">

        <button type="submit" class="button">Filtrele</button>

        <?php if ( ! empty( $filterFeed ) || ! empty( $filterFrom ) || ! empty( $filterTo ) || ! empty( $filterSearch ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=borsatek-ai-news-app&tab=stream' ) ); ?>"
               class="button">Temizle</a>
        <?php endif; ?>
    </form>

    <!-- Async durum alanı -->
    <?php if ( $asyncJobId > 0 ) : ?>
        <input type="hidden" id="borsatek-async-job-id" value="<?php echo esc_attr( $asyncJobId ); ?>">
        <div id="borsatek-async-status" class="borsatek-async-notice">
            <span class="borsatek-spinner"></span> İşleniyor...
        </div>
    <?php endif; ?>

    <!-- Toplu işlem -->
    <div class="borsatek-bulk-bar">
        <label>
            <input type="checkbox" name="borsatek_select_all"> Tümünü Seç
        </label>
        <button id="borsatek-bulk-convert-btn" class="button button-primary" disabled>
            Seçilenleri Dönüştür
        </button>
        <button id="borsatek-bulk-delete-btn" class="button borsatek-btn-danger" disabled>
            Seçilenleri Sil
        </button>
        <div id="borsatek-bulk-progress" class="borsatek-progress" style="display:none;">
            Hazırlanıyor...
        </div>
    </div>

    <!-- Kuyruk tablosu -->
    <?php if ( empty( $items ) ) : ?>
        <div class="borsatek-empty-state">
            <span class="dashicons dashicons-inbox"></span>
            <p>Kuyrukta haber yok. RSS feed'lerinizi ekleyip "Şimdi Tara" butonuna tıklayın.</p>
        </div>
    <?php else : ?>
        <table class="borsatek-queue-table wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" name="borsatek_select_all"></th>
                    <th>Başlık</th>
                    <th>Kaynak</th>
                    <th>Tarih</th>
                    <th>Öncelik</th>
                    <th>Odak Kelime</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ) :
                    $statusClass = 'borsatek-row-' . esc_attr( $item['asyncStatus'] );
                    $shortTitle  = mb_strlen( $item['title'] ) > 120
                                  ? mb_substr( $item['title'], 0, 120 ) . '…'
                                  : $item['title'];
                    $feedHost    = parse_url( $item['sourceFeed'], PHP_URL_HOST ) ?: $item['sourceFeed'];
                    $feedHost    = $feedHost ?: '—';
                    $focusKwRow  = isset( $item['focusKeyword'] ) ? trim( (string) $item['focusKeyword'] ) : '';
                ?>
                    <tr class="<?php echo esc_attr( $statusClass ); ?>">
                        <td class="check-column">
                            <input type="checkbox" class="borsatek-queue-checkbox" value="<?php echo esc_attr( $item['id'] ); ?>">
                        </td>
                        <td>
                            <strong><?php echo esc_html( $shortTitle ); ?></strong>
                            <?php if ( ! empty( $item['sourceLink'] ) ) : ?>
                                <a href="<?php echo esc_url( $item['sourceLink'] ); ?>" target="_blank" title="Kaynağı Aç"
                                   class="borsatek-source-link">↗</a>
                            <?php endif; ?>
                            <?php if ( $item['asyncStatus'] === 'done' && $item['asyncDraftId'] > 0 ) : ?>
                                <br><small><a href="<?php echo esc_url( get_edit_post_link( $item['asyncDraftId'] ) ); ?>">
                                    Taslağı Düzenle
                                </a></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="borsatek-feed-host" title="<?php echo esc_attr( $item['sourceFeed'] ); ?>">
                                <?php echo esc_html( $feedHost ); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $ts = $item['displayDate'] ? strtotime( $item['displayDate'] ) : 0;
                            echo $ts
                                ? '<span title="' . esc_attr( $item['displayDate'] ) . '">'
                                  . esc_html( wp_date( 'd.m.Y', $ts ) )
                                  . '<br><small style="color:#777;">' . esc_html( wp_date( 'H:i', $ts ) ) . '</small>'
                                  . '</span>'
                                : '—';
                            ?>
                        </td>
                        <td>
                            <?php
                            $priority      = $item['priority'] ?? 'normal';
                            $priorityLabel = [ 'high' => 'Yüksek', 'normal' => 'Normal', 'low' => 'Düşük' ];
                            ?>
                            <span class="borsatek-badge borsatek-priority-<?php echo esc_attr( $priority ); ?>">
                                <?php echo esc_html( $priorityLabel[ $priority ] ?? 'Normal' ); ?>
                            </span>
                        </td>
                        <td class="borsatek-focus-cell">
                            <div class="borsatek-focus-inline-wrap">
                                <label class="screen-reader-text" for="borsatek-focus-<?php echo esc_attr( (string) $item['id'] ); ?>">
                                    Odak kelime
                                </label>
                                <input type="text"
                                       id="borsatek-focus-<?php echo esc_attr( (string) $item['id'] ); ?>"
                                       class="regular-text borsatek-inline-focus-keyword"
                                       value="<?php echo esc_attr( $focusKwRow ); ?>"
                                       placeholder="Örn: altın fiyatları"
                                       maxlength="120"
                                       autocomplete="off"
                                       data-queue-id="<?php echo esc_attr( $item['id'] ); ?>">
                                <button type="button"
                                        class="button button-small borsatek-save-focus-inline"
                                        data-queue-id="<?php echo esc_attr( $item['id'] ); ?>">
                                    Kaydet
                                </button>
                            </div>
                            <?php if ( $focusKwRow === '' ) : ?>
                                <p class="borsatek-focus-hint description">Dönüştürmeden önce kutuya yazıp Kaydet’e basın (isteğe bağlı).</p>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusLabels = [
                                'queued'  => 'Bekliyor',
                                'running' => 'İşleniyor',
                                'done'    => 'Tamamlandı',
                                'error'   => 'Hata',
                            ];
                            $asyncStatus = $item['asyncStatus'];
                            ?>
                            <span class="borsatek-badge borsatek-badge-<?php echo esc_attr( $asyncStatus ); ?>">
                                <?php if ( $asyncStatus === 'running' ) : ?>
                                    <span class="borsatek-spinner-inline"></span>
                                <?php endif; ?>
                                <?php echo esc_html( $statusLabels[ $asyncStatus ] ?? $asyncStatus ); ?>
                            </span>
                            <?php if ( $asyncStatus === 'error' && ! empty( $item['asyncError'] ) ) : ?>
                                <br><small class="borsatek-error-text" title="<?php echo esc_attr( $item['asyncError'] ); ?>">
                                    <?php echo esc_html( mb_substr( $item['asyncError'], 0, 60 ) ); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="borsatek-row-actions">
                                <?php $hasFocusKeyword = $focusKwRow !== ''; ?>

                                <!-- Dönüştür -->
                                <button class="button button-small borsatek-convert-single-btn <?php echo $hasFocusKeyword ? '' : 'borsatek-needs-keyword'; ?>"
                                        data-queue-id="<?php echo esc_attr( $item['id'] ); ?>"
                                        data-focus-keyword="<?php echo esc_attr( $focusKwRow ); ?>"
                                        <?php echo $hasFocusKeyword ? '' : 'title="Önce Odak kelime sütununa yazın veya Kaydet\'e basın"'; ?>>
                                    <?php echo $hasFocusKeyword ? 'Dönüştür' : '⚠️ Dönüştür'; ?>
                                </button>

                                <!-- Önizle -->
                                <button class="button button-small borsatek-preview-btn <?php echo $hasFocusKeyword ? '' : 'borsatek-needs-keyword'; ?>"
                                        data-queue-id="<?php echo esc_attr( $item['id'] ); ?>"
                                        data-focus-keyword="<?php echo esc_attr( $focusKwRow ); ?>"
                                        <?php echo $hasFocusKeyword ? '' : 'title="Önce Odak kelime sütununa yazın veya Kaydet\'e basın"'; ?>>
                                    <?php echo $hasFocusKeyword ? 'Önizle' : '⚠️ Önizle'; ?>
                                </button>

                                <!-- Sil -->
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;"
                                      onsubmit="return confirm('Bu öğeyi silmek istediğinizden emin misiniz?')">
                                    <?php wp_nonce_field( 'borsatek_delete_queue_item' ); ?>
                                    <input type="hidden" name="action"   value="borsatek_delete_queue_item">
                                    <input type="hidden" name="queue_id" value="<?php echo esc_attr( $item['id'] ); ?>">
                                    <button type="submit" class="button button-small borsatek-btn-danger">Sil</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Sayfalama -->
        <?php if ( $totalPages > 1 ) : ?>
            <div class="borsatek-pagination">
                <?php for ( $p = 1; $p <= $totalPages; $p++ ) :
                    $url = add_query_arg( [ 'page' => 'borsatek-ai-news-app', 'tab' => 'stream', 'qpage' => $p ] + $_GET, admin_url( 'admin.php' ) );
                    $activeClass = $p === $currentPage ? ' current' : '';
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="button button-small<?php echo $activeClass; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<!-- Önizleme Modal'ı -->
<div id="borsatek-preview-modal" style="display:none;">
    <div class="borsatek-modal-overlay"></div>
    <div class="borsatek-modal-box">
        <button id="borsatek-preview-modal-close" class="borsatek-modal-close">✕</button>
        <h2>İçerik Önizleme</h2>

        <div class="borsatek-preview-header">
            <div class="borsatek-preview-title-wrap">
                <strong>Başlık:</strong>
                <span id="borsatek-preview-title"></span>
            </div>
            <div class="borsatek-preview-score-wrap">
                <strong>SEO Puanı:</strong>
                <span id="borsatek-preview-score"></span>
            </div>
        </div>

        <div class="borsatek-preview-section">
            <strong>Meta Açıklama:</strong>
            <p id="borsatek-preview-meta" class="borsatek-preview-meta"></p>
        </div>

        <div class="borsatek-preview-section">
            <strong>İçerik Önizlemesi (ilk 500 karakter):</strong>
            <div id="borsatek-preview-body" class="borsatek-preview-body"></div>
        </div>

        <div class="borsatek-preview-section">
            <strong>SEO Kuralları:</strong>
            <div id="borsatek-preview-rules" class="borsatek-preview-rules"></div>
        </div>
    </div>
</div>

<!-- Odak Kelime Modal'ı -->
<div id="borsatek-focus-keyword-modal" style="display:none;">
    <div class="borsatek-modal-overlay"></div>
    <div class="borsatek-modal-box" style="max-width:500px;">
        <button id="borsatek-focus-modal-close" class="borsatek-modal-close">✕</button>
        <h2>Odak Kelime Gerekli</h2>
        <p>SEO optimizasyonu için önce odak kelime belirtmelisiniz:</p>
        
        <form id="borsatek-focus-form">
            <div class="borsatek-field">
                <label for="borsatek-modal-focus-keyword">Odak Kelime <span class="required">*</span></label>
                <input type="text" id="borsatek-modal-focus-keyword" 
                       placeholder="Örn: dolar kuru, enflasyon" class="regular-text" required>
                <p class="description">Bu haberin ana konusunu temsil eden anahtar kelime.</p>
            </div>
            
            <div class="borsatek-modal-actions">
                <button type="button" id="borsatek-focus-cancel" class="button">İptal</button>
                <button type="submit" class="button button-primary">Devam Et</button>
            </div>
        </form>
    </div>
</div>
