<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * İşlem istatistiklerini yönetir.
 */
class BorsatekStats {

    /**
     * İşlenen haber sayısını artırır.
     */
    public function incrementProcessed( string $feed, string $provider ): void {
        $key   = $this->getCurrentMonthKey();
        $stats = get_option( $key, $this->emptyStats() );

        $stats['total']++;

        if ( ! empty( $feed ) ) {
            $stats['byFeed'][ $feed ] = ( $stats['byFeed'][ $feed ] ?? 0 ) + 1;
        }

        if ( ! empty( $provider ) ) {
            $stats['byProvider'][ $provider ] = ( $stats['byProvider'][ $provider ] ?? 0 ) + 1;
        }

        update_option( $key, $stats, false );

        // Günlük sayacı da güncelle
        $dailyKey = 'borsatek_daily_' . date( 'Y_m_d' );
        $daily    = (int) get_option( $dailyKey, 0 );
        update_option( $dailyKey, $daily + 1, false );
    }

    /**
     * Başarısız işlem sayısını artırır.
     */
    public function incrementFailed( string $reason ): void {
        $key   = $this->getCurrentMonthKey();
        $stats = get_option( $key, $this->emptyStats() );

        $stats['totalFailed']++;
        $cleanReason = sanitize_key( $reason );
        $stats['failed'][ $cleanReason ] = ( $stats['failed'][ $cleanReason ] ?? 0 ) + 1;

        update_option( $key, $stats, false );
    }

    /**
     * Token kullanımını kaydeder.
     */
    public function logCost( string $provider, int $inputTokens, int $outputTokens ): void {
        $key   = $this->getCurrentMonthKey();
        $stats = get_option( $key, $this->emptyStats() );

        if ( ! isset( $stats['tokens'][ $provider ] ) ) {
            $stats['tokens'][ $provider ] = [ 'in' => 0, 'out' => 0 ];
        }

        $stats['tokens'][ $provider ]['in']  += $inputTokens;
        $stats['tokens'][ $provider ]['out'] += $outputTokens;

        update_option( $key, $stats, false );
    }

    /**
     * Belirtilen aya ait aylık raporu döndürür.
     */
    public function getMonthlyReport( string $month = '' ): array {
        if ( empty( $month ) ) {
            $month = date( 'Y_m' );
        }
        return get_option( 'borsatek_stats_' . $month, $this->emptyStats() );
    }

    /**
     * Son $days günlük işlem verilerini döndürür.
     *
     * @return array [['date'=>'2026-05-01', 'count'=>5], ...]
     */
    public function getDailyChart( int $days = 30 ): array {
        $chart = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $ts      = strtotime( "-{$i} days" );
            $dayKey  = 'borsatek_daily_' . date( 'Y_m_d', $ts );
            $chart[] = [
                'date'  => date( 'Y-m-d', $ts ),
                'count' => (int) get_option( $dayKey, 0 ),
            ];
        }
        return $chart;
    }

    /**
     * En aktif $n feed'i döndürür.
     */
    public function getTopFeeds( int $n = 5 ): array {
        $stats  = $this->getMonthlyReport();
        $feeds  = $stats['byFeed'] ?? [];
        arsort( $feeds );
        return array_slice( $feeds, 0, $n, true );
    }

    /**
     * Tüm istatistik verilerini siler.
     */
    public function reset(): void {
        global $wpdb;

        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'borsatek_stats_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'borsatek_daily_%'" );

        wp_cache_flush();
    }

    /**
     * Boş istatistik dizisi döndürür.
     */
    public function emptyStats(): array {
        return [
            'total'       => 0,
            'totalFailed' => 0,
            'byFeed'      => [],
            'byProvider'  => [],
            'failed'      => [],
            'tokens'      => [],
        ];
    }

    /**
     * Mevcut ay için option anahtarını döndürür.
     */
    private function getCurrentMonthKey(): string {
        return 'borsatek_stats_' . date( 'Y_m' );
    }
}
