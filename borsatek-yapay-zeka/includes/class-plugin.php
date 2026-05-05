<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Eklentinin ana singleton sınıfı. Tüm bağımlılıkları oluşturur ve hook'ları kaydeder.
 */
class BorsatekPlugin {

    /** @var BorsatekPlugin|null */
    private static ?BorsatekPlugin $instance = null;

    /** @var BorsatekQueue */
    private BorsatekQueue $queue;

    /** @var BorsatekRssScanner */
    private BorsatekRssScanner $scanner;

    /** @var BorsatekContentFetcher */
    private BorsatekContentFetcher $fetcher;

    /** @var BorsatekAiProvider */
    private BorsatekAiProvider $aiProvider;

    /** @var BorsatekSeoEngine */
    private BorsatekSeoEngine $seoEngine;

    /** @var BorsatekTranslator */
    private BorsatekTranslator $translator;

    /** @var BorsatekRewriter */
    private BorsatekRewriter $rewriter;

    /** @var BorsatekStats */
    private BorsatekStats $stats;

    /** @var BorsatekAdmin */
    private BorsatekAdmin $admin;

    /** @var BorsatekPermissions */
    private BorsatekPermissions $permissions;

    /** @var BorsatekWebhook */
    private BorsatekWebhook $webhook;

    /**
     * Singleton örneğini döndürür veya oluşturur.
     */
    public static function getInstance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — bağımlılıkları oluşturur ve hook'ları kaydeder.
     */
    private function __construct() {
        $this->permissions = new BorsatekPermissions();
        $this->queue       = new BorsatekQueue();
        $this->fetcher     = new BorsatekContentFetcher();
        $this->aiProvider  = new BorsatekAiProvider();
        $this->seoEngine   = new BorsatekSeoEngine();
        $this->translator  = new BorsatekTranslator( $this->aiProvider );
        $this->stats       = new BorsatekStats();
        $this->rewriter    = new BorsatekRewriter(
            $this->aiProvider,
            $this->seoEngine,
            $this->translator,
            $this->fetcher,
            $this->stats
        );
        $this->scanner = new BorsatekRssScanner( $this->queue, $this->fetcher, $this->stats, $this->translator );
        $this->webhook = new BorsatekWebhook( $this->queue );
        $this->admin   = new BorsatekAdmin(
            $this->queue,
            $this->scanner,
            $this->rewriter,
            $this->aiProvider,
            $this->stats,
            $this->permissions,
            $this->fetcher
        );

        $this->registerHooks();
    }

    /**
     * WordPress hook'larını kaydeder.
     */
    private function registerHooks(): void {
        // Post type ve REST API
        add_action( 'init',          [ $this->queue,   'registerPostType' ] );
        add_action( 'init',          [ $this->queue,   'maybeMigrateNormalizedSourceLinks' ], 25 );
        add_action( 'rest_api_init', [ $this->webhook, 'registerRoutes' ] );

        // Özel cron periyotları
        add_filter( 'cron_schedules', [ $this, 'addCronSchedules' ] );

        // Admin
        add_action( 'admin_menu',            [ $this->admin, 'registerAdminPage' ] );
        add_action( 'admin_enqueue_scripts', [ $this->admin, 'enqueueAdminAssets' ] );
        add_action( 'admin_init',            [ $this->admin, 'handleSettingsSave' ] );

        // Admin POST form işlemleri
        add_action( 'admin_post_borsatek_convert_queue',       [ $this->admin, 'handleConvertQueue' ] );
        add_action( 'admin_post_borsatek_create_manual_draft', [ $this->admin, 'handleCreateManualDraft' ] );
        add_action( 'admin_post_borsatek_create_manual_paste', [ $this->admin, 'handleCreateManualPaste' ] );
        add_action( 'admin_post_borsatek_delete_queue_item',   [ $this->admin, 'handleDeleteQueueItem' ] );

        // AJAX
        add_action( 'wp_ajax_borsatek_stream_snapshot',     [ $this->admin, 'ajaxStreamSnapshot' ] );
        add_action( 'wp_ajax_borsatek_force_scan',          [ $this->admin, 'ajaxForceScan' ] );
        add_action( 'wp_ajax_borsatek_preview',             [ $this->admin, 'ajaxPreview' ] );
        add_action( 'wp_ajax_borsatek_bulk_convert',        [ $this->admin, 'ajaxBulkConvert' ] );
        add_action( 'wp_ajax_borsatek_bulk_delete',         [ $this->admin, 'ajaxBulkDelete' ] );
        add_action( 'wp_ajax_borsatek_async_status',        [ $this->admin, 'ajaxAsyncStatus' ] );
        add_action( 'wp_ajax_borsatek_fetch_gemini_models', [ $this->admin, 'ajaxFetchGeminiModels' ] );
        add_action( 'wp_ajax_borsatek_test_ai_connection',  [ $this->admin, 'ajaxTestAiConnection' ] );
        add_action( 'wp_ajax_borsatek_troubleshoot_explain',[ $this->admin, 'ajaxTroubleshootExplain' ] );
        add_action( 'wp_ajax_borsatek_update_focus_keyword', [ $this->admin, 'handleUpdateFocusKeyword' ] );
        add_action( 'wp_ajax_borsatek_generate_convert_nonce', [ $this->admin, 'ajaxGenerateConvertNonce' ] );

        // Cron callback'leri
        add_action( 'borsatek_cron_scan_rss',      [ $this->scanner, 'runScan' ] );
        add_action( 'borsatek_cron_cleanup_queue', [ $this->queue,   'cleanupExpired' ] );

        // Arka plan async job
        add_action( 'borsatek_async_draft_job', [ $this->admin, 'runAsyncDraftJob' ] );
    }

    /**
     * Özel WP-Cron periyotlarını ekler.
     */
    public function addCronSchedules( array $schedules ): array {
        $schedules['borsatek_15min'] = [
            'interval' => 900,
            'display'  => 'Her 15 Dakika',
        ];
        $schedules['borsatek_30min'] = [
            'interval' => 1800,
            'display'  => 'Her 30 Dakika',
        ];
        return $schedules;
    }
}
