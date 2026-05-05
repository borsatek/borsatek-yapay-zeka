<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RSS feed'lerini WP-Cron ile tarar ve haberleri kuyruğa ekler.
 */
class BorsatekRssScanner {

    /** @var BorsatekQueue */
    private BorsatekQueue $queue;

    /** @var BorsatekContentFetcher */
    private BorsatekContentFetcher $fetcher;

    /** @var BorsatekStats */
    private BorsatekStats $stats;

    /** @var BorsatekTranslator */
    private BorsatekTranslator $translator;

    /**
     * Constructor.
     */
    public function __construct( BorsatekQueue $queue, BorsatekContentFetcher $fetcher, BorsatekStats $stats, BorsatekTranslator $translator ) {
        $this->queue      = $queue;
        $this->fetcher    = $fetcher;
        $this->stats      = $stats;
        $this->translator = $translator;
    }

    /**
     * Özel cron periyotlarını kaydeder ve zamanlanmış görevleri planlar.
     */
    public static function scheduleStaticCron(): void {
        // Özel periyotları kaydet (wp_get_schedules filtresi)
        add_filter( 'cron_schedules', function ( $schedules ) {
            $schedules['borsatek_15min'] = [
                'interval' => 900,
                'display'  => 'Her 15 Dakika',
            ];
            $schedules['borsatek_30min'] = [
                'interval' => 1800,
                'display'  => 'Her 30 Dakika',
            ];
            return $schedules;
        } );

        // RSS tarama cron'u
        if ( ! wp_next_scheduled( 'borsatek_cron_scan_rss' ) ) {
            $interval = get_option( 'borsatek_ai_scan_interval', 'borsatek_30min' );
            wp_schedule_event( time(), $interval, 'borsatek_cron_scan_rss' );
        }

        // Kuyruk temizleme cron'u (günlük)
        if ( ! wp_next_scheduled( 'borsatek_cron_cleanup_queue' ) ) {
            wp_schedule_event( time(), 'daily', 'borsatek_cron_cleanup_queue' );
        }
    }

    /**
     * Zamanlanmış cron görevlerini temizler.
     */
    public static function clearStaticCron(): void {
        wp_clear_scheduled_hook( 'borsatek_cron_scan_rss' );
        wp_clear_scheduled_hook( 'borsatek_cron_cleanup_queue' );
    }

    /**
     * Tüm RSS feed'lerini tarar. WP-Cron callback'i.
     */
    public function runScan(): void {
        $feeds = (array) get_option( 'borsatek_ai_rss_feeds', [] );

        if ( empty( $feeds ) ) {
            return;
        }

        foreach ( $feeds as $feedUrl ) {
            $feedUrl = esc_url_raw( trim( $feedUrl ) );
            if ( ! empty( $feedUrl ) ) {
                $this->processOneFeed( $feedUrl );
            }
        }

        update_option( 'borsatek_ai_last_scan_time', time() );
    }

    /**
     * Taramayı hemen çalıştırır. Admin "Şimdi Tara" butonu callback'i.
     */
    public function forceScanNow(): void {
        $this->queue->clearCache();
        $this->runScan();
    }

    /**
     * Tek bir feed'i işler.
     * İçerik çekme (HTTP) burada YAPILMAZ — sadece RSS metadata kuyruğa eklenir.
     * Tam içerik, dönüştürme sırasında runAsyncDraftJob() tarafından çekilir.
     */
    public function processOneFeed( string $feedUrl ): void {
        $feed = fetch_feed( $feedUrl );

        if ( is_wp_error( $feed ) ) {
            $this->saveFeedHealth( $feedUrl, false, $feed->get_error_message() );
            return;
        }

        if ( ! $feed instanceof SimplePie ) {
            $this->saveFeedHealth( $feedUrl, false, 'Feed nesnesi geçersiz.' );
            return;
        }

        $maxItems = (int) get_option( 'borsatek_ai_max_items_per_run', 10 );
        // 3 katı çek; zaten kuyruktakileri atlayınca hedef sayıya ulaşılır
        $items = $feed->get_items( 0, $maxItems * 3 );

        if ( empty( $items ) ) {
            $this->saveFeedHealth( $feedUrl, true, '' );
            return;
        }

        $processed = 0;

        foreach ( $items as $item ) {
            if ( $processed >= $maxItems ) {
                break;
            }

            $itemUrl = $item->get_permalink();
            if ( empty( $itemUrl ) ) {
                continue;
            }

            // Zaten kuyruğa eklenmişse atla
            if ( $this->queue->itemExistsBySourceLink( $itemUrl ) ) {
                continue;
            }

            // Yayın tarihini al
            $pubDate = '';
            if ( $item->get_date( 'U' ) ) {
                $pubDate = date( 'Y-m-d H:i:s', $item->get_date( 'U' ) );
            }

            // RSS başlığını al (tam içerik dönüştürme sırasında çekilecek)
            $title      = wp_strip_all_tags( $item->get_title() ?? '' );
            $rssExcerpt = wp_strip_all_tags( $item->get_description() ?? '' );

            // İngilizce başlığı DeepL / AI ile Türkçeye çevir
            if ( $this->translator->shouldTranslate( $title ) ) {
                $translated = $this->translator->translateToTurkish( $title );
                if ( ! empty( $translated ) ) {
                    $title = $translated;
                }
            }

            // RSS özeti varsa başlangıç içeriği olarak sakla; yoksa boş bırak
            $content = mb_substr( $rssExcerpt, 0, 1000 );

            $this->queue->addItem( [
                'title'   => $title ?: $itemUrl,
                'content' => $content,
                'link'    => $itemUrl,
                'feed'    => $feedUrl,
                'date'    => $pubDate,
            ] );

            $this->stats->incrementProcessed( $feedUrl, 'rss' );
            $processed++;
        }

        $this->saveFeedHealth( $feedUrl, true, '' );
    }

    /**
     * Feed sağlık durumunu transient'e kaydeder.
     */
    public function saveFeedHealth( string $feedUrl, bool $ok, string $message ): void {
        $report = get_transient( 'borsatek_feed_health' );
        if ( ! is_array( $report ) ) {
            $report = [];
        }

        $report[ $feedUrl ] = [
            'ok'        => $ok,
            'message'   => $message,
            'lastCheck' => time(),
        ];

        set_transient( 'borsatek_feed_health', $report, 30 * MINUTE_IN_SECONDS );
    }

    /**
     * Feed sağlık raporunu döndürür.
     */
    public function getFeedHealthReport(): array {
        return get_transient( 'borsatek_feed_health' ) ?: [];
    }
}
