<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST API webhook endpoint'lerini yönetir.
 */
class BorsatekWebhook {

    /** @var BorsatekQueue */
    private BorsatekQueue $queue;

    /**
     * Constructor.
     */
    public function __construct( BorsatekQueue $queue ) {
        $this->queue = $queue;
    }

    /**
     * REST API rotalarını kaydeder.
     */
    public function registerRoutes(): void {
        register_rest_route( 'borsatek-ai/v1', '/queue', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'receiveWebhook' ],
            'permission_callback' => [ $this, 'verifyWebhookToken' ],
        ] );

        register_rest_route( 'borsatek-ai/v1', '/settings', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'getRuntimeSettings' ],
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
        ] );
    }

    /**
     * Webhook token'ını doğrular.
     */
    public function verifyWebhookToken( WP_REST_Request $request ): bool {
        $token = (string) get_option( 'borsatek_ai_webhook_token', '' );
        if ( empty( $token ) ) {
            return false;
        }

        $header = $request->get_header( 'authorization' );
        if ( empty( $header ) ) {
            return false;
        }

        return hash_equals( 'Bearer ' . $token, $header );
    }

    /**
     * Webhook üzerinden gelen haberi kuyruğa ekler.
     */
    public function receiveWebhook( WP_REST_Request $request ): WP_REST_Response {
        $params  = $request->get_json_params();
        $title   = sanitize_text_field( $params['title']   ?? '' );
        $content = wp_kses_post( $params['content']        ?? '' );
        $url     = esc_url_raw( $params['url']             ?? '' );
        $feed    = esc_url_raw( $params['feed']            ?? '' );

        if ( empty( $title ) || empty( $content ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'message' => 'title ve content zorunludur.' ],
                400
            );
        }

        // URL zaten kuyruktaysa çakışma
        if ( ! empty( $url ) && $this->queue->itemExistsBySourceLink( $url ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'message' => 'Bu URL zaten kuyruğa eklenmiş.' ],
                409
            );
        }

        $queueId = $this->queue->addItem( [
            'title'   => $title,
            'content' => $content,
            'link'    => $url,
            'feed'    => $feed,
            'date'    => sanitize_text_field( $params['date'] ?? '' ),
        ] );

        if ( is_wp_error( $queueId ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'message' => $queueId->get_error_message() ],
                500
            );
        }

        return new WP_REST_Response(
            [ 'success' => true, 'id' => $queueId ],
            201
        );
    }

    /**
     * Çalışma zamanı ayarlarını döndürür.
     */
    public function getRuntimeSettings( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( [
            'rssFeeds'     => get_option( 'borsatek_ai_rss_feeds', [] ),
            'provider'     => get_option( 'borsatek_ai_provider', 'anthropic' ),
            'scanInterval' => get_option( 'borsatek_ai_scan_interval', 'borsatek_30min' ),
            'lastScan'     => get_option( 'borsatek_ai_last_scan_time', 0 ),
        ], 200 );
    }
}
