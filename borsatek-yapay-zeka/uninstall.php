<?php
/**
 * Eklenti kaldırıldığında tüm verileri temizler.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Tüm eklenti ayarlarını sil
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'borsatek_ai_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'borsatek_stats_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'borsatek_daily_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_borsatek_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_borsatek_%'" );

// Tüm kuyruk post'larını kalıcı olarak sil
$posts = get_posts( [
    'post_type'   => 'borsatek_ai_queue',
    'numberposts' => -1,
    'post_status' => 'any',
] );

foreach ( $posts as $post ) {
    wp_delete_post( $post->ID, true );
}

// Cron görevlerini temizle
wp_clear_scheduled_hook( 'borsatek_cron_scan_rss' );
wp_clear_scheduled_hook( 'borsatek_cron_cleanup_queue' );

// Önbelleği temizle
wp_cache_flush();
