<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Başlıkları Türkçeye çeviren servis.
 */
class BorsatekTranslator {

    /** @var BorsatekAiProvider */
    private BorsatekAiProvider $ai;

    /**
     * Constructor.
     */
    public function __construct( BorsatekAiProvider $ai ) {
        $this->ai = $ai;
    }

    /**
     * Başlığı Türkçeye çevirir. Gerekli değilse orijinali döndürür.
     */
    public function translateToTurkish( string $title ): string {
        if ( ! $this->shouldTranslate( $title ) ) {
            return $title;
        }

        // Önbellek kontrolü
        $cacheKey = 'borsatek_title_tr_' . md5( $title );
        $cached   = get_transient( $cacheKey );
        if ( $cached !== false ) {
            return (string) $cached;
        }

        // Önce DeepL dene
        $translated = $this->translateViaDeepL( $title );

        // DeepL başarısız veya boşsa AI dene
        if ( empty( $translated ) ) {
            $translated = $this->translateWithAi( $title );
        }

        // Her ikisi de başarısızsa orijinali döndür
        if ( empty( $translated ) ) {
            return $title;
        }

        // Başarılıysa önbelleğe kaydet
        set_transient( $cacheKey, $translated, 7 * DAY_IN_SECONDS );

        return $translated;
    }

    /**
     * Başlığın çevrilmesi gerekip gerekmediğini belirler.
     */
    public function shouldTranslate( string $title ): bool {
        if ( empty( $title ) ) {
            return false;
        }

        // Türkçe özel karakter varsa zaten Türkçe
        if ( preg_match( '/[ğşıöüçĞŞİÖÜÇ]/u', $title ) ) {
            return false;
        }

        // ASCII olmayan karakter oranını hesapla
        $totalLen   = mb_strlen( $title, 'UTF-8' );
        $asciiLen   = strlen( preg_replace( '/[^\x00-\x7F]/', '', $title ) );
        $asciiRatio = $totalLen > 0 ? $asciiLen / $totalLen : 1;

        // %80'den fazla ASCII ise muhtemelen İngilizce
        return $asciiRatio > 0.80;
    }

    /**
     * DeepL API ile çeviri yapar.
     */
    public function translateViaDeepL( string $title ): string {
        if ( ! (bool) get_option( 'borsatek_ai_deepl_enabled', false ) ) {
            return '';
        }

        $deepLKey = (string) get_option( 'borsatek_ai_deepl_key', '' );
        if ( empty( $deepLKey ) ) {
            return '';
        }

        $response = wp_remote_post( 'https://api-free.deepl.com/v2/translate', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key ' . $deepLKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'text'        => [ $title ],
                'target_lang' => 'TR',
                'source_lang' => 'EN',
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return $data['translations'][0]['text'] ?? '';
    }

    /**
     * AI ile çeviri yapar.
     */
    public function translateWithAi( string $title ): string {
        $preferred = (string) get_option( 'borsatek_ai_provider', 'anthropic' );

        // Sadece tercih edilen ilk sağlayıcıyı kullan
        $fullChain = $this->ai->buildProviderChain( $preferred );
        $chain     = ! empty( $fullChain ) ? [ $fullChain[0] ] : [];

        if ( empty( $chain ) ) {
            return '';
        }

        $prompt = "Şu İngilizce başlığı Türkçeye çevir. SADECE çeviriyi yaz, açıklama ekleme:\n{$title}";
        $result = $this->ai->callWithFallback( $prompt, $chain, 200, 15 );

        if ( is_wp_error( $result ) ) {
            return '';
        }

        return $this->normalizeTranslatedTitle( $result, $title );
    }

    /**
     * AI'dan gelen ham çeviriyi normalleştirir.
     */
    public function normalizeTranslatedTitle( string $raw, string $original ): string {
        $text = trim( $raw );

        // Tırnak ve backtick temizle
        $text = trim( $text, '"\'`' );

        // "Çeviri:", "Translation:" gibi önekleri kaldır
        $text = preg_replace( '/^(?:Çeviri|Ceviri|Translation|Türkçe|Turkce)\s*:\s*/iu', '', $text );

        // 150 karakteri aşarsa kes
        if ( mb_strlen( $text ) > 150 ) {
            $text = mb_substr( $text, 0, 150 );
            $lastSpace = mb_strrpos( $text, ' ' );
            if ( $lastSpace !== false ) {
                $text = mb_substr( $text, 0, $lastSpace );
            }
        }

        return ! empty( trim( $text ) ) ? $text : $original;
    }
}
