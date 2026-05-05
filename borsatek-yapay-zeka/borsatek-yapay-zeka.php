<?php
/**
 * Plugin Name: Borsatek Yapay Zeka
 * Plugin URI:  https://borsatek.com
 * Description: RSS kaynaklarından finans haberleri çeker, Anthropic/Gemini ile SEO'lu Türkçe içerik üretir, WordPress'e taslak kaydeder.
 * Version:     2.0.0
 * Author:      Borsatek
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: borsatek-yapay-zeka
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sabitler
define( 'BORSATEK_YZ_VERSION',  '2.0.0' );
define( 'BORSATEK_YZ_FILE',     __FILE__ );
define( 'BORSATEK_YZ_DIR',      plugin_dir_path( __FILE__ ) );
define( 'BORSATEK_YZ_URL',      plugin_dir_url( __FILE__ ) );

// Sınıfları yükle
$borsatek_includes = [
    'class-permissions',
    'class-queue',
    'class-content-fetcher',
    'class-ai-provider',
    'class-seo-engine',
    'class-translator',
    'class-rewriter',
    'class-rss-scanner',
    'class-stats',
    'class-webhook',
    'class-admin',
    'class-plugin',
];
foreach ( $borsatek_includes as $file ) {
    require_once BORSATEK_YZ_DIR . 'includes/' . $file . '.php';
}

// Aktivasyon / deaktivasyon
register_activation_hook( __FILE__, 'borsatek_yz_activate' );
register_deactivation_hook( __FILE__, 'borsatek_yz_deactivate' );

/**
 * Eklenti aktive edildiğinde çalışır.
 */
function borsatek_yz_activate() {
    BorsatekQueue::registerPostTypeStatic();
    flush_rewrite_rules();
    BorsatekRssScanner::scheduleStaticCron();

    $defaults = [
        // AI Sağlayıcı
        'borsatek_ai_provider'              => 'gemini',
        'borsatek_ai_gemini_key'            => '',
        'borsatek_ai_gemini_model'          => 'gemini-2.5-flash',
        'borsatek_ai_gemini_active'         => true,
        'borsatek_ai_anthropic_key'         => '',
        'borsatek_ai_anthropic_model'       => 'claude-sonnet-4-20250514',
        'borsatek_ai_anthropic_active'      => true,
        'borsatek_ai_openai_key'            => '',
        'borsatek_ai_openai_model'          => 'gpt-4o-mini',
        'borsatek_ai_together_key'          => '',
        'borsatek_ai_together_model'        => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
        // İçerik çekimi
        'borsatek_ai_jina_key'              => '',
        'borsatek_ai_jina_url'              => 'https://r.jina.ai',
        // DeepL
        'borsatek_ai_deepl_key'             => '',
        'borsatek_ai_deepl_enabled'         => true,
        'borsatek_ai_deepl_url'             => 'https://api-free.deepl.com/v2/translate',
        // RSS ve zamanlama
        // Boş başlatılır; tek satır paratic görünümü çoğu zaman "ilk aktivasyon / sıfırlanmış seçenek" demektir.
        'borsatek_ai_rss_feeds'             => [],
        'borsatek_ai_scan_interval'         => 'borsatek_30min',
        'borsatek_ai_max_items_per_run'     => 10,
        'borsatek_ai_rewrite_language'      => 'Turkce',
        // Güvenlik
        'borsatek_ai_webhook_token'         => '',
        // AI davranış
        'borsatek_ai_timeout'               => 90,
        'borsatek_ai_cost_mode'             => 'balanced',
        'borsatek_ai_min_source_chars'      => 300,
        'borsatek_ai_repair_pass_enabled'   => false,
        'borsatek_ai_repair_provider'       => 'auto',
        'borsatek_ai_repair_min_violations' => 2,
        'borsatek_ai_enforce_seo_rules'     => true,
        'borsatek_ai_auto_repair_seo'       => true,
        'borsatek_ai_include_source_line'   => true,
    ];

    foreach ( $defaults as $key => $value ) {
        if ( get_option( $key ) === false ) {
            update_option( $key, $value );
        }
    }

    // İlk kurulumda sihirbazı tetikle
    if ( ! get_option( 'borsatek_ai_setup_complete' ) ) {
        set_transient( 'borsatek_ai_show_setup_wizard', true, 120 );
    }
}

/**
 * Eklenti deaktive edildiğinde çalışır.
 */
function borsatek_yz_deactivate() {
    BorsatekRssScanner::clearStaticCron();
    flush_rewrite_rules();
}

// Eklentiyi başlat
add_action( 'plugins_loaded', [ 'BorsatekPlugin', 'getInstance' ] );
