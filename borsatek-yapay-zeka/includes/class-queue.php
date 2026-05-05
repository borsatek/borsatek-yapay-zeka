<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Haber kuyruğunu custom post type üzerinden yönetir.
 */
class BorsatekQueue {

    const POST_TYPE            = 'borsatek_ai_queue';
    const META_SOURCE_LINK     = '_borsatek_source_link';
    const META_SOURCE_TITLE    = '_borsatek_source_title';
    const META_SOURCE_CONTENT  = '_borsatek_source_content';
    const META_SOURCE_FEED     = '_borsatek_source_feed';
    const META_SOURCE_DATE     = '_borsatek_source_date';
    const META_PRIORITY        = '_borsatek_priority';
    const META_FOCUS_KEYWORD   = '_borsatek_focus_keyword';
    const META_ASYNC_STATUS    = '_borsatek_async_status';
    const META_ASYNC_ERROR     = '_borsatek_async_error';
    const META_ASYNC_DRAFT_ID  = '_borsatek_async_draft_id';
    const TRANSIENT_ITEMS      = 'borsatek_queue_items_v6';
    const TRANSIENT_ITEMS_LITE = 'borsatek_queue_items_lite_v6';
    const FETCH_LIMIT          = 500;
    const MAX_QUEUE_SIZE       = 1000;

    /**
     * Custom post type'ı kaydeder (init hook'u için).
     */
    public function registerPostType(): void {
        self::registerPostTypeStatic();
    }

    /**
     * Custom post type'ı statik olarak kaydeder (aktivasyon hook'u için).
     */
    public static function registerPostTypeStatic(): void {
        register_post_type( self::POST_TYPE, [
            'labels'       => [
                'name'          => 'Borsatek Kuyruk',
                'singular_name' => 'Kuyruk Öğesi',
            ],
            'public'          => false,
            'show_ui'         => false,
            'capability_type' => 'post',
            'supports'        => [ 'title', 'custom-fields' ],
            'rewrite'         => false,
            'query_var'       => false,
        ] );
    }

    /**
     * Kaynak URL'sini mükerrer kontrolü için tek biçime indirger (RSS izleme parametreleri vb.).
     */
    public static function normalizeSourceLinkForDedup( string $url ): string {
        $url = trim( $url );
        if ( $url === '' ) {
            return '';
        }

        $url = esc_url_raw( $url );
        if ( $url === '' ) {
            return '';
        }

        $parts = wp_parse_url( $url );
        if ( empty( $parts['host'] ) ) {
            return $url;
        }

        $scheme = strtolower( $parts['scheme'] ?? 'https' );
        if ( $scheme !== 'http' && $scheme !== 'https' ) {
            $scheme = 'https';
        }

        $host = strtolower( $parts['host'] );
        $path = $parts['path'] ?? '';

        // Yahoo haber bağlantıları: sorgu dizesi neredeyse her zaman izleme; aynı makale ?tsrc=rss ile yeniden eklenmesin
        if ( preg_match( '/(^|\.)yahoo\.com$/', $host ) ) {
            $host  = preg_replace( '/^www\./', '', $host );
            $query = '';
        } elseif ( ! empty( $parts['query'] ) ) {
            parse_str( $parts['query'], $params );
            $stripKeys = [
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'utm_reader',
                'fbclid', 'gclid', 'mc_cid', 'mc_eid', 'ref', 'referrer', 'src', 'source', 'partner',
                'feature', 'tsrc', 'icid', 'ncid', 'guccounter', 'gucref', 'guccidx', 'cmp', 'vid',
                'hootPostID', 'soc_src', 'soc_trk', 'yptr', 'fr', 'rh', 'tpcc',
            ];
            foreach ( $stripKeys as $k ) {
                unset( $params[ $k ] );
            }
            $query = ! empty( $params ) ? http_build_query( $params ) : '';
        } else {
            $query = '';
        }

        if ( strlen( $path ) > 1 ) {
            $path = untrailingslashit( $path );
        }

        $qs = $query !== '' ? '?' . $query : '';

        return $scheme . '://' . $host . $path . $qs;
    }

    /**
     * Kuyruğa yeni bir haber öğesi ekler.
     *
     * @param array $data title, content, link, feed, date, priority, focusKeyword
     * @return int|WP_Error Oluşturulan post ID veya hata
     */
    public function addItem( array $data ): int|\WP_Error {
        $title        = isset( $data['title'] )        ? sanitize_text_field( $data['title'] )   : 'Başlıksız';
        $content      = isset( $data['content'] )      ? $data['content']                        : '';
        $link         = isset( $data['link'] ) ? esc_url_raw( $data['link'] ) : '';
        $link         = self::normalizeSourceLinkForDedup( $link );
        $feed         = isset( $data['feed'] )         ? esc_url_raw( $data['feed'] )            : '';
        $date         = isset( $data['date'] ) ? trim( sanitize_text_field( $data['date'] ) ) : '';
        if ( $date !== '' ) {
            $ts = strtotime( $date );
            $date = $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
        }
        if ( $date === '' ) {
            $date = current_time( 'mysql' );
        }
        $priority     = isset( $data['priority'] )     ? sanitize_key( $data['priority'] )       : 'normal';
        $focusKeyword = isset( $data['focusKeyword'] ) ? sanitize_text_field( $data['focusKeyword'] ) : '';

        if ( ! in_array( $priority, [ 'high', 'normal', 'low' ], true ) ) {
            $priority = 'normal';
        }

        $postId = wp_insert_post( [
            'post_title'  => $title,
            'post_status' => 'draft',
            'post_type'   => self::POST_TYPE,
        ], true );

        if ( is_wp_error( $postId ) ) {
            return $postId;
        }

        // Kuyruk max boyutunu aşıyorsa en eski bekleyen öğeyi sil
        $this->enforceMaxSize();

        update_post_meta( $postId, self::META_SOURCE_LINK,    $link );
        update_post_meta( $postId, self::META_SOURCE_TITLE,   $title );
        update_post_meta( $postId, self::META_SOURCE_CONTENT, $content );
        update_post_meta( $postId, self::META_SOURCE_FEED,    $feed );
        update_post_meta( $postId, self::META_SOURCE_DATE,    $date );
        update_post_meta( $postId, self::META_PRIORITY,       $priority );
        update_post_meta( $postId, self::META_FOCUS_KEYWORD,  $focusKeyword );
        update_post_meta( $postId, self::META_ASYNC_STATUS,   'queued' );
        update_post_meta( $postId, self::META_ASYNC_ERROR,    '' );
        update_post_meta( $postId, self::META_ASYNC_DRAFT_ID, 0 );

        $this->clearCache();

        return $postId;
    }

    /**
     * Kuyruk öğelerini döndürür. Transient önbellek kullanır.
     *
     * @param array $args feed, dateFrom, dateTo, search, page, perPage, includeLite
     */
    public function getItems( array $args = [] ): array {
        $feed        = isset( $args['feed'] )        ? esc_url_raw( $args['feed'] )        : '';
        $dateFrom    = isset( $args['dateFrom'] )    ? sanitize_text_field( $args['dateFrom'] ) : '';
        $dateTo      = isset( $args['dateTo'] )      ? sanitize_text_field( $args['dateTo'] )   : '';
        $search      = isset( $args['search'] )      ? sanitize_text_field( $args['search'] )   : '';
        $page        = isset( $args['page'] )        ? max( 1, (int) $args['page'] )            : 1;
        $perPage     = isset( $args['perPage'] )     ? min( 200, max( 1, (int) $args['perPage'] ) ) : 50;
        $includeLite = ! empty( $args['includeLite'] );

        // Filtre yoksa önbellek kullan
        $useCache = empty( $feed ) && empty( $dateFrom ) && empty( $dateTo ) && empty( $search ) && $page === 1 && $perPage === 50;
        $cacheKey = $includeLite ? self::TRANSIENT_ITEMS_LITE : self::TRANSIENT_ITEMS;

        if ( $useCache ) {
            $cached = get_transient( $cacheKey );
            if ( $cached !== false ) {
                return $cached;
            }
        }

        $queryArgs = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'draft',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            // ORDER BY posts_clauses filtresinde kaynak tarihi + DESC (en yeni üstte).
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $feed ) ) {
            $queryArgs['meta_query'][] = [
                'key'     => self::META_SOURCE_FEED,
                'value'   => $feed,
                'compare' => '=',
            ];
        }

        if ( ! empty( $search ) ) {
            $queryArgs['s'] = $search;
        }

        global $wpdb;
        $sourceDateMeta = self::META_SOURCE_DATE;
        $sort_by_source_date = static function ( array $clauses ) use ( $wpdb, $sourceDateMeta ): array {
            // JOIN yerine alt sorgu: aynı postta çoklu _borsatek_source_date meta satırı olsa bile satır çoğalmaz.
            $clauses['orderby'] = $wpdb->prepare(
                "COALESCE(NULLIF(TRIM((SELECT pm.meta_value FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = %s ORDER BY pm.meta_id DESC LIMIT 1)), ''), {$wpdb->posts}.post_date) DESC, {$wpdb->posts}.ID DESC",
                $sourceDateMeta
            );

            return $clauses;
        };

        add_filter( 'posts_clauses', $sort_by_source_date, 10, 1 );
        $query = new WP_Query( $queryArgs );
        remove_filter( 'posts_clauses', $sort_by_source_date, 10 );
        $items = [];

        $seenIds   = [];
        $seenCanon = [];
        foreach ( $query->posts as $post ) {
            if ( isset( $seenIds[ $post->ID ] ) ) {
                continue;
            }
            $seenIds[ $post->ID ] = true;

            $storedLink = (string) get_post_meta( $post->ID, self::META_SOURCE_LINK, true );
            $canon      = self::normalizeSourceLinkForDedup( $storedLink );
            if ( $canon !== '' && isset( $seenCanon[ $canon ] ) ) {
                continue;
            }
            if ( $canon !== '' ) {
                $seenCanon[ $canon ] = true;
            }
            $sourceDate  = get_post_meta( $post->ID, self::META_SOURCE_DATE, true );
            $asyncStatus = get_post_meta( $post->ID, self::META_ASYNC_STATUS, true ) ?: 'queued';

            $item = [
                'id'           => $post->ID,
                'title'        => $post->post_title,
                'sourceLink'   => get_post_meta( $post->ID, self::META_SOURCE_LINK, true ),
                'sourceFeed'   => get_post_meta( $post->ID, self::META_SOURCE_FEED, true ),
                'sourceDate'   => $sourceDate,
                'sourceContent'=> $includeLite ? '' : get_post_meta( $post->ID, self::META_SOURCE_CONTENT, true ),
                'priority'     => get_post_meta( $post->ID, self::META_PRIORITY, true ) ?: 'normal',
                'asyncStatus'  => $asyncStatus,
                'asyncError'   => get_post_meta( $post->ID, self::META_ASYNC_ERROR, true ),
                'asyncDraftId' => (int) get_post_meta( $post->ID, self::META_ASYNC_DRAFT_ID, true ),
                'focusKeyword' => get_post_meta( $post->ID, self::META_FOCUS_KEYWORD, true ),
                'displayDate'  => ! empty( $sourceDate ) ? $sourceDate : get_the_date( 'Y-m-d H:i', $post->ID ),
            ];

            // Tarih-saat filtresi (sourceDate üzerinden — saat dahil)
            $itemTs = $item['displayDate'] ? strtotime( $item['displayDate'] ) : 0;
            if ( ! empty( $dateFrom ) && $itemTs < strtotime( $dateFrom ) ) {
                continue;
            }
            if ( ! empty( $dateTo ) && $itemTs > strtotime( $dateTo ) ) {
                continue;
            }

            $items[] = $item;
        }

        $result = [
            'items'      => $items,
            'total'      => $query->found_posts,
            'totalPages' => $query->max_num_pages,
            'page'       => $page,
        ];

        if ( $useCache ) {
            set_transient( $cacheKey, $result, 5 * MINUTE_IN_SECONDS );
        }

        return $result;
    }

    /**
     * Tek bir kuyruk öğesini ID ile döndürür.
     */
    public function getItem( int $id ): ?array {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return null;
        }

        return [
            'id'            => $post->ID,
            'title'         => $post->post_title,
            'sourceLink'    => get_post_meta( $post->ID, self::META_SOURCE_LINK, true ),
            'sourceFeed'    => get_post_meta( $post->ID, self::META_SOURCE_FEED, true ),
            'sourceDate'    => get_post_meta( $post->ID, self::META_SOURCE_DATE, true ),
            'sourceContent' => get_post_meta( $post->ID, self::META_SOURCE_CONTENT, true ),
            'sourceTitle'   => get_post_meta( $post->ID, self::META_SOURCE_TITLE, true ),
            'priority'      => get_post_meta( $post->ID, self::META_PRIORITY, true ) ?: 'normal',
            'asyncStatus'   => get_post_meta( $post->ID, self::META_ASYNC_STATUS, true ) ?: 'queued',
            'asyncError'    => get_post_meta( $post->ID, self::META_ASYNC_ERROR, true ),
            'asyncDraftId'  => (int) get_post_meta( $post->ID, self::META_ASYNC_DRAFT_ID, true ),
            'focusKeyword'  => get_post_meta( $post->ID, self::META_FOCUS_KEYWORD, true ),
        ];
    }

    /**
     * Kuyruk öğesini kalıcı olarak siler.
     */
    public function deleteItem( int $id ): bool {
        $result = wp_delete_post( $id, true );
        $this->clearCache();
        return $result !== false;
    }

    /**
     * Belirli günden eski süresi dolmuş öğeleri temizler.
     */
    public function cleanupExpired( int $days = 3 ): void {
        $cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $query = new WP_Query( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'draft',
            'nopaging'       => true,
            'date_query'     => [
                [
                    'before'    => $cutoff,
                    'inclusive' => true,
                ],
            ],
            'fields'         => 'ids',
        ] );

        foreach ( $query->posts as $postId ) {
            wp_delete_post( (int) $postId, true );
        }

        $this->clearCache();
    }

    /**
     * Kuyruk MAX_QUEUE_SIZE'ı aşarsa en eski "queued" öğeleri siler.
     */
    private function enforceMaxSize(): void {
        $total = wp_count_posts( self::POST_TYPE )->draft ?? 0;

        if ( (int) $total <= self::MAX_QUEUE_SIZE ) {
            return;
        }

        // Fazla öğe kadar en eski bekleyenleri sil
        $overflow = (int) $total - self::MAX_QUEUE_SIZE;

        $old = new WP_Query( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'draft',
            'posts_per_page' => $overflow,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::META_ASYNC_STATUS,
                    'value'   => 'queued',
                    'compare' => '=',
                ],
            ],
        ] );

        foreach ( $old->posts as $postId ) {
            wp_delete_post( (int) $postId, true );
        }
    }

    /**
     * Eski kayıtlarda kaynak link RSS izleme parametreleriyle saklanmış olabilir; tek seferlik olarak kanonik forma çeker.
     * Böylece mükerrer kontrolü veritabanındaki değerle eşleşir (cron / REST / admin ilk isteklerinde partiler halinde çalışır).
     */
    public function maybeMigrateNormalizedSourceLinks(): void {
        if ( get_option( 'borsatek_queue_src_norm_done', '' ) === '1' ) {
            return;
        }

        if ( ! is_admin() && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }

        $batch = (int) apply_filters( 'borsatek_queue_src_norm_batch', 75 );
        if ( $batch < 1 ) {
            $batch = 75;
        }

        $offset = (int) get_option( 'borsatek_queue_src_norm_offset', 0 );

        $posts = get_posts( [
            'post_type'              => self::POST_TYPE,
            'post_status'            => 'any',
            'posts_per_page'         => $batch,
            'offset'                 => $offset,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'suppress_filters'       => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ] );

        if ( empty( $posts ) ) {
            update_option( 'borsatek_queue_src_norm_done', '1', false );
            delete_option( 'borsatek_queue_src_norm_offset' );
            $this->clearCache();

            return;
        }

        foreach ( $posts as $postId ) {
            $postId = (int) $postId;
            $link   = (string) get_post_meta( $postId, self::META_SOURCE_LINK, true );
            if ( $link === '' ) {
                continue;
            }
            $norm = self::normalizeSourceLinkForDedup( $link );
            if ( $norm !== '' && $norm !== $link ) {
                update_post_meta( $postId, self::META_SOURCE_LINK, $norm );
            }
        }

        $offset += count( $posts );
        update_option( 'borsatek_queue_src_norm_offset', $offset, false );

        if ( count( $posts ) < $batch ) {
            update_option( 'borsatek_queue_src_norm_done', '1', false );
            delete_option( 'borsatek_queue_src_norm_offset' );
            $this->clearCache();
        }
    }

    /**
     * Verilen URL'nin kuyruğa daha önce eklenip eklenmediğini kontrol eder.
     */
    public function itemExistsBySourceLink( string $url ): bool {
        $url = self::normalizeSourceLinkForDedup( esc_url_raw( $url ) );
        if ( $url === '' ) {
            return false;
        }

        $transientKey = 'borsatek_link_exists_' . md5( $url );
        $cached       = get_transient( $transientKey );

        if ( $cached !== false ) {
            return (bool) $cached;
        }

        $query = new WP_Query( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::META_SOURCE_LINK,
                    'value'   => $url,
                    'compare' => '=',
                ],
            ],
        ] );

        $exists = $query->found_posts > 0;
        set_transient( $transientKey, $exists ? 1 : 0, 10 * MINUTE_IN_SECONDS );

        return $exists;
    }

    /**
     * Verilen URL'ye sahip kuyruk öğesinin ID'sini döndürür (bulunamazsa 0).
     */
    public function getItemIdBySourceLink( string $url ): int {
        $url = self::normalizeSourceLinkForDedup( esc_url_raw( $url ) );
        if ( $url === '' ) {
            return 0;
        }

        $query = new WP_Query( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::META_SOURCE_LINK,
                    'value'   => $url,
                    'compare' => '=',
                ],
            ],
        ] );

        if ( $query->found_posts > 0 && ! empty( $query->posts ) ) {
            return (int) $query->posts[0];
        }

        return 0;
    }

    /**
     * Kuyruk öğesinin async işlem durumunu günceller.
     */
    public function setAsyncStatus( int $id, string $status, string $error = '' ): void {
        update_post_meta( $id, self::META_ASYNC_STATUS, sanitize_key( $status ) );
        if ( ! empty( $error ) ) {
            update_post_meta( $id, self::META_ASYNC_ERROR, sanitize_text_field( $error ) );
        }
        $this->clearCache();
    }

    /**
     * Kuyruk önbelleğini temizler.
     */
    public function clearCache(): void {
        delete_transient( self::TRANSIENT_ITEMS );
        delete_transient( self::TRANSIENT_ITEMS_LITE );
    }
}
