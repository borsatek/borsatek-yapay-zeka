<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WordPress admin panelini, form işlemlerini ve AJAX handler'larını yönetir.
 */
class BorsatekAdmin {

    /** @var BorsatekQueue */
    private BorsatekQueue $queue;

    /** @var BorsatekRssScanner */
    private BorsatekRssScanner $scanner;

    /** @var BorsatekRewriter */
    private BorsatekRewriter $rewriter;

    /** @var BorsatekAiProvider */
    private BorsatekAiProvider $ai;

    /** @var BorsatekStats */
    private BorsatekStats $stats;

    /** @var BorsatekPermissions */
    private BorsatekPermissions $permissions;

    /** @var BorsatekContentFetcher */
    private BorsatekContentFetcher $fetcher;

    /**
     * Constructor.
     */
    public function __construct(
        BorsatekQueue          $queue,
        BorsatekRssScanner     $scanner,
        BorsatekRewriter       $rewriter,
        BorsatekAiProvider     $ai,
        BorsatekStats          $stats,
        BorsatekPermissions    $permissions,
        BorsatekContentFetcher $fetcher
    ) {
        $this->queue       = $queue;
        $this->scanner     = $scanner;
        $this->rewriter    = $rewriter;
        $this->ai          = $ai;
        $this->stats       = $stats;
        $this->permissions = $permissions;
        $this->fetcher     = $fetcher;
    }

    /**
     * Admin menü sayfasını kaydeder.
     */
    public function registerAdminPage(): void {
        add_menu_page(
            'Borsatek Yapay Zeka',
            'Borsatek YZ',
            'edit_posts',
            'borsatek-ai-news-app',
            [ $this, 'renderAdminPage' ],
            'dashicons-rss',
            30
        );
        add_action( 'admin_notices', [ $this, 'maybeShowSetupWizard' ] );
    }

    /**
     * İlk kurulumda sihirbaz sekmesine yönlendir.
     */
    public function maybeShowSetupWizard(): void {
        if ( ! get_transient( 'borsatek_ai_show_setup_wizard' ) ) {
            return;
        }
        delete_transient( 'borsatek_ai_show_setup_wizard' );
        // Zaten setup sayfasındaysak yönlendirme
        if ( ( $_GET['page'] ?? '' ) === 'borsatek-ai-news-app' && ( $_GET['tab'] ?? '' ) === 'setup' ) {
            return;
        }
        wp_redirect( admin_url( 'admin.php?page=borsatek-ai-news-app&tab=setup' ) );
        exit;
    }

    /**
     * Admin sayfasına ait CSS ve JS dosyalarını yükler.
     */
    public function enqueueAdminAssets( string $hook ): void {
        if ( $hook !== 'toplevel_page_borsatek-ai-news-app' ) {
            return;
        }

        wp_enqueue_style(
            'borsatek-admin',
            BORSATEK_YZ_URL . 'admin/css/borsatek-admin.css',
            [],
            BORSATEK_YZ_VERSION
        );

        wp_enqueue_script(
            'borsatek-admin',
            BORSATEK_YZ_URL . 'admin/js/borsatek-admin.js',
            [ 'jquery' ],
            BORSATEK_YZ_VERSION,
            true
        );

        wp_localize_script( 'borsatek-admin', 'borsatekData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'borsatek_ajax' ),
            'version' => BORSATEK_YZ_VERSION,
        ] );
    }

    /**
     * Admin sayfasını render eder.
     */
    public function renderAdminPage(): void {
        if ( ! $this->permissions->currentUserAllowed() ) {
            wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
        }

        $tab        = sanitize_key( $_GET['tab'] ?? 'stream' );
        $validTabs  = [ 'stream', 'manual', 'rss', 'seo', 'stats', 'troubleshoot', 'setup' ];

        if ( ! in_array( $tab, $validTabs, true ) ) {
            $tab = 'stream';
        }

        $tabLabels = [
            'stream'      => 'Haber Akışı',
            'manual'      => 'Manuel Ekle',
            'rss'         => 'RSS Kaynakları',
            'seo'         => 'AI & SEO Ayarları',
            'stats'       => 'İstatistikler',
            'troubleshoot'=> 'Sorun Giderme',
            'setup'       => '⚙ Kurulum',
        ];

        echo '<div class="wrap borsatek-wrap">';
        echo '<h1>Borsatek Yapay Zeka <span class="borsatek-version">v' . esc_html( BORSATEK_YZ_VERSION ) . '</span></h1>';

        // Sekme navigasyonu
        echo '<nav class="borsatek-tabs">';
        foreach ( $tabLabels as $key => $label ) {
            $activeClass = $key === $tab ? ' borsatek-tab-active' : '';
            $url         = admin_url( 'admin.php?page=borsatek-ai-news-app&tab=' . $key );
            echo '<a href="' . esc_url( $url ) . '" class="borsatek-tab' . $activeClass . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';

        echo '<div class="borsatek-tab-content">';

        // Bildirim mesajları
        if ( isset( $_GET['borsatek_msg'] ) ) {
            $msgType = sanitize_key( $_GET['borsatek_type'] ?? 'success' );
            $msgText = sanitize_text_field( urldecode( $_GET['borsatek_msg'] ) );
            $cssClass = $msgType === 'error' ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . esc_attr( $cssClass ) . ' is-dismissible"><p>' . esc_html( $msgText ) . '</p></div>';
        }

        // Sekme içeriğini dahil et
        $viewFile = BORSATEK_YZ_DIR . 'admin/views/tab-' . $tab . '.php';
        if ( file_exists( $viewFile ) ) {
            // View dosyalarına gerekli değişkenleri aktar
            $queue       = $this->queue;
            $scanner     = $this->scanner;
            $stats       = $this->stats;
            $permissions = $this->permissions;
            include $viewFile;
        }

        echo '</div></div>';
    }

    /**
     * Ayarları kaydeder. admin_init hook'unda çalışır.
     */
    public function handleSettingsSave(): void {
        if ( ! isset( $_POST['borsatek_save_action'] ) ) {
            return;
        }

        check_admin_referer( 'borsatek_settings' );

        if ( ! $this->permissions->canAccessSettings() ) {
            wp_die( 'Yetersiz yetki.' );
        }

        $action = sanitize_key( $_POST['borsatek_save_action'] );

        switch ( $action ) {
            case 'save_rss':
                $rawFeeds = sanitize_textarea_field( $_POST['borsatek_rss_feeds_raw'] ?? '' );
                $previous = (array) get_option( 'borsatek_ai_rss_feeds', [] );
                $feeds    = $this->parseRssFeedsFromRaw( $rawFeeds );

                update_option( 'borsatek_ai_scan_interval', sanitize_key( $_POST['borsatek_scan_interval'] ?? 'borsatek_30min' ) );

                // Cron'u yeniden planla
                wp_clear_scheduled_hook( 'borsatek_cron_scan_rss' );
                $newInterval = get_option( 'borsatek_ai_scan_interval' );
                wp_schedule_event( time(), $newInterval, 'borsatek_cron_scan_rss' );

                // Metin doluyken hiç geçerli URL çıkmadıysa listeyi silme (eskiden esc_url_raw hepsini düşürüyordu).
                if ( trim( $rawFeeds ) !== '' && empty( $feeds ) && ! empty( $previous ) ) {
                    $this->redirectWithMessage(
                        'RSS kaydedilemedi: satırlar geçerli URL olarak okunamadı. Önceki liste korundu. Adresleri https:// ile başlatmayı deneyin.',
                        'error',
                        'rss'
                    );
                }

                if ( $feeds !== $previous ) {
                    $this->snapshotRssFeedsBackupFromArray( $previous );
                }
                update_option( 'borsatek_ai_rss_feeds', $feeds );
                break;

            case 'restore_rss_backup':
                $backup = (array) get_option( 'borsatek_ai_rss_feeds_backup', [] );
                if ( empty( $backup ) ) {
                    $this->redirectWithMessage( 'Kullanılabilir RSS yedeği yok.', 'error', 'rss' );
                }
                $currentFeeds = (array) get_option( 'borsatek_ai_rss_feeds', [] );
                $this->snapshotRssFeedsBackupFromArray( $currentFeeds );
                update_option( 'borsatek_ai_rss_feeds', array_values( array_unique( $backup ) ) );
                $this->redirectWithMessage(
                    'RSS listesi son kaydedilen yedekten geri yüklendi (' . count( $backup ) . ' adres).',
                    'success',
                    'rss'
                );

            case 'save_seo':
                // AI Sağlayıcı
                update_option( 'borsatek_ai_provider', sanitize_key( $_POST['borsatek_ai_provider'] ?? 'anthropic' ) );
                update_option( 'borsatek_ai_cost_mode', sanitize_key( $_POST['borsatek_cost_mode'] ?? 'balanced' ) );
                update_option( 'borsatek_ai_timeout', absint( $_POST['borsatek_ai_timeout'] ?? 90 ) );

                // Anthropic
                update_option( 'borsatek_ai_anthropic_key',    sanitize_text_field( $_POST['borsatek_anthropic_key']    ?? '' ) );
                update_option( 'borsatek_ai_anthropic_model',  sanitize_text_field( $_POST['borsatek_anthropic_model']  ?? 'claude-sonnet-4-20250514' ) );
                update_option( 'borsatek_ai_anthropic_active', isset( $_POST['borsatek_anthropic_active'] ) );

                // Gemini
                update_option( 'borsatek_ai_gemini_key',    sanitize_text_field( $_POST['borsatek_gemini_key']    ?? '' ) );
                update_option( 'borsatek_ai_gemini_model',  sanitize_text_field( $_POST['borsatek_gemini_model']  ?? 'gemini-2.5-flash' ) );
                update_option( 'borsatek_ai_gemini_active', isset( $_POST['borsatek_gemini_active'] ) );

                // OpenAI
                update_option( 'borsatek_ai_openai_key',   sanitize_text_field( $_POST['borsatek_openai_key']   ?? '' ) );
                update_option( 'borsatek_ai_openai_model', sanitize_text_field( $_POST['borsatek_openai_model'] ?? 'gpt-4o-mini' ) );

                // Together AI
                update_option( 'borsatek_ai_together_key',   sanitize_text_field( $_POST['borsatek_together_key']   ?? '' ) );
                update_option( 'borsatek_ai_together_model', sanitize_text_field( $_POST['borsatek_together_model'] ?? '' ) );

                // Repair Pass
                update_option( 'borsatek_ai_repair_pass_enabled',   isset( $_POST['borsatek_repair_pass_enabled'] ) );
                update_option( 'borsatek_ai_repair_provider',       sanitize_key( $_POST['borsatek_repair_provider']       ?? 'auto' ) );
                update_option( 'borsatek_ai_repair_min_violations', absint( $_POST['borsatek_repair_min_violations']       ?? 2 ) );

                // SEO Kuralları
                $seoText = sanitize_textarea_field( $_POST['borsatek_seo_rules_text'] ?? '' );
                update_option( 'borsatek_ai_seo_rules', [ 'seo' => $seoText ] );
                update_option( 'borsatek_ai_enforce_seo_rules', isset( $_POST['borsatek_enforce_seo_rules'] ) );
                update_option( 'borsatek_ai_auto_repair_seo', isset( $_POST['borsatek_auto_repair_seo'] ) );

                // Diğer ayarlar
                update_option( 'borsatek_ai_jina_key',           sanitize_text_field( $_POST['borsatek_jina_key']           ?? '' ) );
                update_option( 'borsatek_ai_deepl_key',          sanitize_text_field( $_POST['borsatek_deepl_key']          ?? '' ) );
                update_option( 'borsatek_ai_deepl_enabled',      isset( $_POST['borsatek_deepl_enabled'] ) );
                update_option( 'borsatek_ai_min_source_chars',   absint( $_POST['borsatek_min_source_chars']   ?? 300 ) );
                update_option( 'borsatek_ai_include_source_line',isset( $_POST['borsatek_include_source_line'] ) );
                update_option( 'borsatek_ai_webhook_token',      sanitize_text_field( $_POST['borsatek_webhook_token'] ?? '' ) );
                break;

            case 'save_permissions':
                $allowedRaw  = array_map( 'absint', (array) ( $_POST['borsatek_allowed_users'] ?? [] ) );
                $activeUser  = absint( $_POST['borsatek_active_user'] ?? 0 );
                update_option( 'borsatek_ai_allowed_users', $allowedRaw );
                update_option( 'borsatek_ai_active_user',   $activeUser );
                break;

            case 'save_setup':
                update_option( 'borsatek_ai_provider',          sanitize_text_field( $_POST['borsatek_provider']         ?? 'gemini' ) );
                update_option( 'borsatek_ai_gemini_key',        sanitize_text_field( $_POST['borsatek_gemini_key']        ?? '' ) );
                update_option( 'borsatek_ai_gemini_model',      sanitize_text_field( $_POST['borsatek_gemini_model']      ?? 'gemini-2.5-flash' ) );
                update_option( 'borsatek_ai_anthropic_key',     sanitize_text_field( $_POST['borsatek_anthropic_key']     ?? '' ) );
                update_option( 'borsatek_ai_anthropic_model',   sanitize_text_field( $_POST['borsatek_anthropic_model']   ?? 'claude-sonnet-4-20250514' ) );
                update_option( 'borsatek_ai_openai_key',        sanitize_text_field( $_POST['borsatek_openai_key']        ?? '' ) );
                update_option( 'borsatek_ai_together_key',      sanitize_text_field( $_POST['borsatek_together_key']      ?? '' ) );
                update_option( 'borsatek_ai_jina_key',          sanitize_text_field( $_POST['borsatek_jina_key']          ?? '' ) );
                update_option( 'borsatek_ai_deepl_key',         sanitize_text_field( $_POST['borsatek_deepl_key']         ?? '' ) );
                update_option( 'borsatek_ai_deepl_enabled',     ! empty( $_POST['borsatek_deepl_enabled'] ) );
                update_option( 'borsatek_ai_rewrite_language',  sanitize_text_field( $_POST['borsatek_rewrite_language']  ?? 'Turkce' ) );
                update_option( 'borsatek_ai_max_items_per_run', absint( $_POST['borsatek_max_items']                      ?? 10 ) );
                update_option( 'borsatek_ai_scan_interval',     sanitize_key( $_POST['borsatek_scan_interval']            ?? 'borsatek_30min' ) );
                update_option( 'borsatek_ai_webhook_token',     sanitize_text_field( $_POST['borsatek_webhook_token']     ?? '' ) );
                // RSS feeds — textarea'dan dizi oluştur (RSS sekmesi ile aynı URL normalizasyonu)
                $rawFeeds = sanitize_textarea_field( $_POST['borsatek_rss_feeds_raw'] ?? '' );
                $feeds    = $this->parseRssFeedsFromRaw( $rawFeeds );
                if ( trim( $rawFeeds ) !== '' && empty( $feeds ) ) {
                    wp_redirect(
                        admin_url(
                            'admin.php?page=borsatek-ai-news-app&tab=setup&error=' . rawurlencode(
                                'RSS adresleri okunamadı. Her satırda geçerli bir adres kullanın (örn. https://site.com/feed/).'
                            )
                        )
                    );
                    exit;
                }
                $previousFeeds = (array) get_option( 'borsatek_ai_rss_feeds', [] );
                if ( $feeds !== $previousFeeds ) {
                    $this->snapshotRssFeedsBackupFromArray( $previousFeeds );
                }
                update_option( 'borsatek_ai_rss_feeds', $feeds );
                update_option( 'borsatek_ai_setup_complete', true );
                // Cron'u yeniden planla (scan interval değişmiş olabilir)
                BorsatekRssScanner::clearStaticCron();
                BorsatekRssScanner::scheduleStaticCron();
                wp_redirect( admin_url( 'admin.php?page=borsatek-ai-news-app&tab=stream&setup=done' ) );
                exit;
        }

        $redirect = add_query_arg(
            [ 'borsatek_msg' => rawurlencode( 'Ayarlar kaydedildi.' ), 'borsatek_type' => 'success' ],
            wp_get_referer() ?: admin_url( 'admin.php?page=borsatek-ai-news-app' )
        );
        wp_redirect( $redirect );
        exit;
    }

    /**
     * Kuyruk öğesini dönüştürür ve taslak oluşturur. admin_post callback'i.
     */
    public function handleConvertQueue(): void {
        check_admin_referer( 'borsatek_convert_queue' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_die( 'Yetersiz yetki.' );
        }

        $queueId = absint( $_POST['queue_id'] ?? 0 );
        $item    = $this->queue->getItem( $queueId );

        if ( ! $item ) {
            $this->redirectWithMessage( 'Kuyruk öğesi bulunamadı.', 'error' );
            return;
        }

        $refreshed     = $this->maybeRefreshQueueItemSource( $item, $queueId );
        $sourceContent = $refreshed['content'];
        $sourceTitle   = $refreshed['title'];

        // İçerik hâlâ boşsa kuyruktaki post_title'ı içerik olarak kullan (en kötü senaryo)
        if ( empty( trim( $sourceContent ) ) ) {
            $sourceContent = $sourceTitle;
        }

        $result = $this->rewriter->rewrite(
            $sourceTitle,
            $sourceContent,
            $item['sourceLink'],
            $item['focusKeyword']  ?? '',
            $this->permissions->getActiveUser()
        );

        if ( is_wp_error( $result ) ) {
            $this->redirectWithMessage( 'Dönüştürme hatası: ' . $result->get_error_message(), 'error' );
            return;
        }

        $postId = $this->rewriter->createDraft( $result, $queueId, $this->permissions->getActiveUser() );

        if ( is_wp_error( $postId ) ) {
            $this->redirectWithMessage( 'Taslak oluşturma hatası: ' . $postId->get_error_message(), 'error' );
            return;
        }

        $editUrl = get_edit_post_link( $postId, 'raw' );
        $this->redirectWithMessage( 'Taslak oluşturuldu. <a href="' . esc_url( $editUrl ) . '">Düzenle</a>', 'success' );
    }

    /**
     * URL'den içerik çekip async job olarak kuyruğa ekler. admin_post callback'i.
     */
    public function handleCreateManualDraft(): void {
        check_admin_referer( 'borsatek_manual_draft' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_die( 'Yetersiz yetki.' );
        }

        $url          = esc_url_raw( $_POST['borsatek_manual_url']     ?? '' );
        $focusKeyword = sanitize_text_field( $_POST['borsatek_focus_keyword'] ?? '' );

        if ( empty( $url ) ) {
            $this->redirectWithMessage( 'URL boş olamaz.', 'error', 'manual' );
            return;
        }

        if ( $this->queue->itemExistsBySourceLink( $url ) ) {
            $this->redirectWithMessage( 'Bu URL zaten kuyruğa eklenmiş.', 'error', 'manual' );
            return;
        }

        $queueId = $this->queue->addItem( [
            'title'        => $url,
            'content'      => '',
            'link'         => $url,
            'focusKeyword' => $focusKeyword,
        ] );

        if ( is_wp_error( $queueId ) ) {
            $this->redirectWithMessage( 'Kuyruk hatası: ' . $queueId->get_error_message(), 'error', 'manual' );
            return;
        }

        $this->queue->setAsyncStatus( $queueId, 'queued' );

        // Async job planla
        wp_schedule_single_event( time(), 'borsatek_async_draft_job', [ $queueId ] );

        $redirect = add_query_arg(
            [ 'page' => 'borsatek-ai-news-app', 'tab' => 'manual', 'async_job_id' => $queueId, 'borsatek_msg' => rawurlencode( 'İşlem arka planda başlatıldı.' ), 'borsatek_type' => 'success' ],
            admin_url( 'admin.php' )
        );
        wp_redirect( $redirect );
        exit;
    }

    /**
     * Yapıştırılan metni dönüştürüp taslak oluşturur. admin_post callback'i.
     */
    public function handleCreateManualPaste(): void {
        check_admin_referer( 'borsatek_manual_paste' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_die( 'Yetersiz yetki.' );
        }

        $title        = sanitize_text_field( $_POST['borsatek_paste_title']   ?? '' );
        $content      = sanitize_textarea_field( $_POST['borsatek_paste_content'] ?? '' );
        $sourceUrl    = esc_url_raw( $_POST['borsatek_paste_url']              ?? '' );
        $focusKeyword = sanitize_text_field( $_POST['borsatek_focus_keyword']  ?? '' );

        if ( empty( $content ) ) {
            $this->redirectWithMessage( 'İçerik boş olamaz.', 'error', 'manual' );
            return;
        }

        $result = $this->rewriter->rewrite(
            $title,
            $content,
            $sourceUrl,
            $focusKeyword,
            $this->permissions->getActiveUser()
        );

        if ( is_wp_error( $result ) ) {
            $this->redirectWithMessage( 'Dönüştürme hatası: ' . $result->get_error_message(), 'error', 'manual' );
            return;
        }

        $postId = $this->rewriter->createDraft( $result, 0, $this->permissions->getActiveUser() );

        if ( is_wp_error( $postId ) ) {
            $this->redirectWithMessage( 'Taslak oluşturma hatası: ' . $postId->get_error_message(), 'error', 'manual' );
            return;
        }

        $editUrl = get_edit_post_link( $postId, 'raw' );
        $this->redirectWithMessage( 'Taslak oluşturuldu.', 'success', 'manual' );
    }

    /**
     * Kuyruk öğesini siler. admin_post callback'i.
     */
    public function handleDeleteQueueItem(): void {
        check_admin_referer( 'borsatek_delete_queue_item' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_die( 'Yetersiz yetki.' );
        }

        $queueId = absint( $_POST['queue_id'] ?? 0 );
        $this->queue->deleteItem( $queueId );

        $this->redirectWithMessage( 'Öğe silindi.', 'success' );
    }

    /**
     * Arka planda kuyruk öğesini dönüştürür. Cron callback'i.
     */
    public function runAsyncDraftJob( int $queueId ): void {
        $item = $this->queue->getItem( $queueId );
        if ( ! $item ) {
            return;
        }

        $this->queue->setAsyncStatus( $queueId, 'running' );

        ignore_user_abort( true );
        @set_time_limit( 180 );

        // İçerik boşsa veya makale için şüpheli kısaysa URL'den yenile
        $refreshed = $this->maybeRefreshQueueItemSource( $item, $queueId );
        $content   = $refreshed['content'];
        $title     = $refreshed['title'];

        $result = $this->rewriter->rewrite(
            $title,
            $content,
            $item['sourceLink'] ?? '',
            $item['focusKeyword'] ?? '',
            $this->permissions->getActiveUser()
        );

        if ( is_wp_error( $result ) ) {
            $this->queue->setAsyncStatus( $queueId, 'error', $result->get_error_message() );
            return;
        }

        $postId = $this->rewriter->createDraft( $result, $queueId, $this->permissions->getActiveUser() );

        if ( is_wp_error( $postId ) ) {
            $this->queue->setAsyncStatus( $queueId, 'error', $postId->get_error_message() );
            return;
        }

        update_post_meta( $queueId, BorsatekQueue::META_ASYNC_STATUS,   'done' );
        update_post_meta( $queueId, BorsatekQueue::META_ASYNC_DRAFT_ID, $postId );
    }

    // ─── AJAX Handler'lar ────────────────────────────────────────────────────

    /**
     * Kuyruk anlık görüntüsünü döndürür.
     */
    public function ajaxStreamSnapshot(): void {
        check_ajax_referer( 'borsatek_ajax', 'nonce' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }

        $args   = [
            'feed'        => sanitize_text_field( $_POST['feed']     ?? '' ),
            'search'      => sanitize_text_field( $_POST['search']   ?? '' ),
            'page'        => absint( $_POST['page']                  ?? 1 ),
            'perPage'     => 50,
            'includeLite' => true,
        ];

        $data = $this->queue->getItems( $args );
        wp_send_json_success( $data );
    }

    /**
     * RSS taramasını hemen başlatır.
     */
    public function ajaxForceScan(): void {
        check_ajax_referer( 'borsatek_ajax', 'nonce' );

        if ( ! $this->permissions->currentUserIsAdmin() ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }

        $this->scanner->forceScanNow();
        $health = $this->scanner->getFeedHealthReport();

        wp_send_json_success( [
            'lastScan'   => get_option( 'borsatek_ai_last_scan_time', 0 ),
            'feedHealth' => $health,
        ] );
    }

    /**
     * Önizleme sonucunu döndürür.
     */
    public function ajaxPreview(): void {
        check_ajax_referer( 'borsatek_ajax', 'nonce' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }

        $queueId      = absint( $_POST['queue_id']      ?? 0 );
        $focusKeyword = sanitize_text_field( $_POST['focus_keyword'] ?? '' );

        $item = $this->queue->getItem( $queueId );
        if ( ! $item ) {
            wp_send_json_error( [ 'message' => 'Kuyruk öğesi bulunamadı.' ] );
        }

        // İçerik boşsa veya makale için şüpheli kısaysa URL'den yenile (paywall teaser vb.)
        $refreshed      = $this->maybeRefreshQueueItemSource( $item, $queueId );
        $previewContent = $refreshed['content'];
        $previewTitle   = $refreshed['title'];

        $result = $this->rewriter->preview(
            $previewTitle,
            $previewContent,
            $focusKeyword ?: ( $item['focusKeyword'] ?? '' )
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    /**
     * Toplu dönüştürme yapar.
     */
    public function ajaxBulkConvert(): void {
        check_ajax_referer( 'borsatek_ajax', 'nonce' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }

        $queueIds = array_map( 'absint', (array) ( $_POST['queue_ids'] ?? [] ) );
        $results  = [];

        foreach ( $queueIds as $queueId ) {
            $item = $this->queue->getItem( $queueId );
            if ( ! $item ) {
                $results[] = [ 'id' => $queueId, 'success' => false, 'error' => 'Bulunamadı' ];
                continue;
            }

            $refreshed     = $this->maybeRefreshQueueItemSource( $item, $queueId );
            $sourceContent = $refreshed['content'];
            $sourceTitle   = $refreshed['title'];

            $result = $this->rewriter->rewrite(
                $sourceTitle,
                $sourceContent,
                $item['sourceLink'],
                $item['focusKeyword']  ?? '',
                $this->permissions->getActiveUser()
            );

            if ( is_wp_error( $result ) ) {
                $results[] = [ 'id' => $queueId, 'success' => false, 'error' => $result->get_error_message() ];
                continue;
            }

            $postId = $this->rewriter->createDraft( $result, $queueId, $this->permissions->getActiveUser() );

            if ( is_wp_error( $postId ) ) {
                $results[] = [ 'id' => $queueId, 'success' => false, 'error' => $postId->get_error_message() ];
                continue;
            }

            $results[] = [
                'id'      => $queueId,
                'success' => true,
                'postId'  => $postId,
                'editUrl' => get_edit_post_link( $postId, 'raw' ),
            ];
        }

        wp_send_json_success( [ 'results' => $results ] );
    }

    /**
     * Seçilen kuyruk öğelerini toplu siler.
     */
    public function ajaxBulkDelete(): void {
        check_ajax_referer( 'borsatek_ajax', 'nonce' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }

        $queueIds = array_map( 'absint', (array) ( $_POST['queue_ids'] ?? [] ) );
        $deleted  = 0;

        foreach ( $queueIds as $queueId ) {
            if ( $this->queue->deleteItem( $queueId ) ) {
                $deleted++;
            }
        }

        wp_send_json_success( [ 'deleted' => $deleted ] );
    }

    /**
     * Async işlem durumunu döndürür.
     */
    public function ajaxAsyncStatus(): void {
        check_ajax_referer( 'borsatek_ajax', 'nonce' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }

        $jobId = absint( $_POST['job_id'] ?? 0 );
        $item  = $this->queue->getItem( $jobId );

        if ( ! $item ) {
            wp_send_json_error( [ 'message' => 'İş bulunamadı.' ] );
        }

        $draftId = $item['asyncDraftId'];
        $editUrl = $draftId > 0 ? get_edit_post_link( $draftId, 'raw' ) : '';

        wp_send_json_success( [
            'status'  => $item['asyncStatus'],
            'error'   => $item['asyncError'],
            'draftId' => $draftId,
            'editUrl' => $editUrl,
        ] );
    }

    /**
     * Gemini model listesini getirir.
     */
    public function ajaxFetchGeminiModels(): void {
        check_ajax_referer( 'borsatek_ajax', 'nonce' );

        if ( ! $this->permissions->currentUserIsAdmin() ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }

        $apiKey = sanitize_text_field( $_POST['api_key'] ?? '' );
        if ( empty( $apiKey ) ) {
            wp_send_json_error( [ 'message' => 'API anahtarı gerekli.' ] );
        }

        $models = $this->ai->fetchGeminiModels( $apiKey );
        wp_send_json_success( [ 'models' => $models ] );
    }

    /**
     * AI bağlantısını test eder.
     */
    public function ajaxTestAiConnection(): void {
        check_ajax_referer( 'borsatek_ajax', 'nonce' );

        if ( ! $this->permissions->currentUserIsAdmin() ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }

        $provider = sanitize_key( $_POST['provider'] ?? '' );
        $apiKey   = sanitize_text_field( $_POST['api_key'] ?? '' );
        $model    = sanitize_text_field( $_POST['model']   ?? '' );

        if ( $provider === 'gemini' ) {
            $result = $this->ai->testGeminiConnection( $apiKey, $model );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            }
            wp_send_json_success( [ 'ok' => true ] );
        } elseif ( $provider === 'anthropic' ) {
            $testPrompt = '{"ok":true}';
            $result     = $this->ai->callAnthropic( $testPrompt, $apiKey, $model, 50, 10 );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            }
            wp_send_json_success( [ 'ok' => true ] );
        } elseif ( $provider === 'jina' ) {
            $jinaUrlParam = sanitize_text_field( $_POST['jina_url'] ?? '' );
            $result       = $this->fetcher->testJinaReader( $apiKey, $jinaUrlParam !== '' ? $jinaUrlParam : null );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            }
            wp_send_json_success( [ 'ok' => true ] );
        } else {
            wp_send_json_error( [ 'message' => 'Desteklenmeyen sağlayıcı.' ] );
        }
    }

    /**
     * Hata metnini AI ile açıklar.
     */
    public function ajaxTroubleshootExplain(): void {
        check_ajax_referer( 'borsatek_ajax', 'nonce' );

        if ( ! $this->permissions->canAccessStream() ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }

        $errorText = sanitize_textarea_field( $_POST['error_text'] ?? '' );
        if ( empty( $errorText ) ) {
            wp_send_json_error( [ 'message' => 'Hata metni boş.' ] );
        }

        $prompt = "Aşağıdaki WordPress/PHP hata mesajını Türkçe olarak açıkla. Kısa, net ve teknik olmayan bir dille. Olası çözümleri de öner:\n\n" . mb_substr( $errorText, 0, 2000 );

        $preferred = (string) get_option( 'borsatek_ai_provider', 'anthropic' );
        $chain     = $this->ai->buildProviderChain( $preferred );
        $result    = $this->ai->callWithFallback( $prompt, $chain, 500, 30 );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // JSON olmayan düz metin bekleniyor — JSON açma denemesi
        $parsed = json_decode( $result, true );
        $explanation = is_array( $parsed ) ? ( $parsed['explanation'] ?? $result ) : $result;

        wp_send_json_success( [ 'explanation' => wp_kses_post( nl2br( $explanation ) ) ] );
    }

    // ─── Yardımcı ────────────────────────────────────────────────────────────

    /**
     * RSS listesi değişmeden önce verilen (boş olmayan) diziyi yedekler — yanlış kayıtta geri dönüş için.
     *
     * @param array $feeds Kayıttan hemen önceki liste (çoğunlukla önceki GET ile okunan değer).
     */
    private function snapshotRssFeedsBackupFromArray( array $feeds ): void {
        if ( empty( $feeds ) ) {
            return;
        }
        update_option( 'borsatek_ai_rss_feeds_backup', array_values( array_unique( $feeds ) ) );
        update_option( 'borsatek_ai_rss_feeds_backup_saved_at', time() );
    }

    /**
     * RSS textarea satırlarından URL dizisi üretir.
     * Şema yoksa https:// eklenir (www.site.com/feed yapıştıranların listesi silinmez).
     *
     * @param string $raw Çok satırlı ham metin.
     * @return string[] Benzersiz URL'ler.
     */
    private function parseRssFeedsFromRaw( string $raw ): array {
        $lines = preg_split( '/\R/u', $raw ) ?: [];
        $out   = [];

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' ) {
                continue;
            }

            if ( ! preg_match( '#^[a-z][a-z0-9+.-]*:#iu', $line ) ) {
                $line = 'https://' . ltrim( $line, '/' );
            }

            $url = esc_url_raw( $line );
            if ( $url !== '' ) {
                $out[] = $url;
            }
        }

        return array_values( array_unique( $out ) );
    }

    /**
     * Kuyruk öğesi için kaynak metni mümkünse URL'den günceller (paywall teaser / RSS özeti sonrası tam makale).
     *
     * @param array $item getItem çıktısı
     * @param int   $queueId Meta güncellemesi için (0 ise yazılmaz)
     * @return array{content:string,title:string}
     */
    private function maybeRefreshQueueItemSource( array $item, int $queueId ): array {
        $sourceContent = $item['sourceContent'] ?? '';
        $sourceTitle   = $item['sourceTitle'] ?? $item['title'] ?? '';
        $link          = $item['sourceLink'] ?? '';

        if ( $link === '' ) {
            return [ 'content' => $sourceContent, 'title' => $sourceTitle ];
        }

        $richEnoughThreshold = 2200;
        $storedLen           = mb_strlen( $sourceContent );

        if ( $storedLen >= $richEnoughThreshold ) {
            return [ 'content' => $sourceContent, 'title' => $sourceTitle ];
        }

        $fetched = $this->fetcher->fetch( $link );
        if ( ! is_wp_error( $fetched ) && mb_strlen( $fetched['content'] ?? '' ) > mb_strlen( $sourceContent ) ) {
            $sourceContent = $fetched['content'];
            if ( empty( $sourceTitle ) || $sourceTitle === $link ) {
                $sourceTitle = $fetched['title'] ?? $sourceTitle;
            }
            if ( $queueId > 0 ) {
                update_post_meta( $queueId, BorsatekQueue::META_SOURCE_CONTENT, $sourceContent );
                update_post_meta( $queueId, BorsatekQueue::META_SOURCE_TITLE, $sourceTitle );
            }
        }

        return [ 'content' => $sourceContent, 'title' => $sourceTitle ];
    }

    /**
     * Yönlendirme ve mesaj gösterir.
     */
    private function redirectWithMessage( string $message, string $type = 'success', string $tab = 'stream' ): void {
        $redirect = add_query_arg(
            [
                'page'          => 'borsatek-ai-news-app',
                'tab'           => $tab,
                'borsatek_msg'  => rawurlencode( $message ),
                'borsatek_type' => $type,
            ],
            admin_url( 'admin.php' )
        );
        wp_redirect( $redirect );
        exit;
    }
}
