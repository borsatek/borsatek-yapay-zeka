<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tüm AI API çağrılarını yöneten sınıf.
 */
class BorsatekAiProvider {

    /**
     * Anthropic Claude API'sini çağırır.
     */
    public function callAnthropic( string $prompt, string $apiKey, string $model, int $maxTokens, int $timeout ): string|\WP_Error {
        $body = wp_json_encode( [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => 'Aşağıdaki kullanıcı mesajındaki tüm talimatları uygula. Yanıt YALNIZCA tek bir geçerli JSON nesnesi olmalı; markdown kod bloğu, ön ya da son açıklama yazma.',
            'messages'   => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ] );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            'body'    => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $bodyText = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return new WP_Error( 'anthropic_http', "HTTP {$code}: {$bodyText}" );
        }

        $data = json_decode( $bodyText, true );
        if ( empty( $data['content'] ) ) {
            return new WP_Error( 'anthropic_empty', 'Anthropic boş yanıt döndürdü.' );
        }

        $text = '';
        foreach ( $data['content'] as $block ) {
            if ( isset( $block['text'] ) ) {
                $text .= $block['text'];
            }
        }

        return $text;
    }

    /**
     * Google Gemini API'sini çağırır.
     */
    public function callGemini( string $prompt, string $apiKey, string $model, int $maxTokens, int $timeout, bool $isThinking = false ): string|\WP_Error {
        $generationConfig = [
            'maxOutputTokens' => $maxTokens,
            'temperature'     => 0.7,
        ];

        if ( $isThinking ) {
            $generationConfig['thinkingConfig'] = [ 'thinkingBudget' => 8000 ];
        }

        $body = wp_json_encode( [
            'system_instruction' => [
                'parts' => [ [ 'text' => 'Yanıt YALNIZCA geçerli JSON nesnesi olmalı. Markdown veya açıklama yazma.' ] ],
            ],
            'contents'           => [
                [ 'parts' => [ [ 'text' => $prompt ] ] ],
            ],
            'generationConfig'   => $generationConfig,
        ] );

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = wp_remote_post( $endpoint, [
            'timeout' => $timeout,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $bodyText = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return new WP_Error( 'gemini_http', "HTTP {$code}: {$bodyText}" );
        }

        $data = json_decode( $bodyText, true );
        if ( empty( $data['candidates'][0]['content']['parts'] ) ) {
            return new WP_Error( 'gemini_empty', 'Gemini boş yanıt döndürdü.' );
        }

        $text = '';
        foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
            if ( isset( $part['text'] ) ) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    /**
     * OpenAI API'sini çağırır.
     */
    public function callOpenAI( string $prompt, string $apiKey, string $model, int $maxTokens, int $timeout ): string|\WP_Error {
        $body = wp_json_encode( [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => [
                [ 'role' => 'system', 'content' => 'Yanıt YALNIZCA geçerli JSON nesnesi olmalı.' ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
        ] );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'body'    => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $bodyText = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return new WP_Error( 'openai_http', "HTTP {$code}: {$bodyText}" );
        }

        $data = json_decode( $bodyText, true );
        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'openai_empty', 'OpenAI boş yanıt döndürdü.' );
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Together AI API'sini çağırır (OpenAI uyumlu format).
     */
    public function callTogetherAI( string $prompt, string $apiKey, string $model, int $maxTokens, int $timeout ): string|\WP_Error {
        $body = wp_json_encode( [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => [
                [ 'role' => 'system', 'content' => 'Yanıt YALNIZCA geçerli JSON nesnesi olmalı.' ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
        ] );

        $response = wp_remote_post( 'https://api.together.xyz/v1/chat/completions', [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'body'    => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $bodyText = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return new WP_Error( 'together_http', "HTTP {$code}: {$bodyText}" );
        }

        $data = json_decode( $bodyText, true );
        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'together_empty', 'Together AI boş yanıt döndürdü.' );
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Fallback zinciriyle API çağrısı yapar; sıradaki sağlayıcıya geçer.
     *
     * @param array $chain [['provider'=>'anthropic','key'=>'...','model'=>'...'], ...]
     */
    public function callWithFallback( string $prompt, array $chain, int $maxTokens, int $timeout ): string|\WP_Error {
        $lastError = new WP_Error( 'no_providers', 'Kullanılabilir AI sağlayıcı bulunamadı.' );

        foreach ( $chain as $item ) {
            $provider = $item['provider'] ?? '';
            $key      = $item['key']      ?? '';
            $model    = $item['model']    ?? '';

            if ( empty( $key ) || empty( $model ) ) {
                continue;
            }

            $result = null;
            switch ( $provider ) {
                case 'anthropic':
                    $result = $this->callAnthropic( $prompt, $key, $model, $maxTokens, $timeout );
                    break;
                case 'gemini':
                    $result = $this->callGemini( $prompt, $key, $model, $maxTokens, $timeout );
                    break;
                case 'openai':
                    $result = $this->callOpenAI( $prompt, $key, $model, $maxTokens, $timeout );
                    break;
                case 'together':
                    $result = $this->callTogetherAI( $prompt, $key, $model, $maxTokens, $timeout );
                    break;
                default:
                    continue 2;
            }

            if ( ! is_wp_error( $result ) ) {
                return $result;
            }

            error_log( "Borsatek AI fallback: {$provider} başarısız → " . $result->get_error_message() );
            $lastError = $result;
        }

        return $lastError;
    }

    /**
     * Aktif ve yapılandırılmış sağlayıcılardan oluşan bir zincir oluşturur.
     */
    public function buildProviderChain( string $preferred ): array {
        $chain = [];

        $providers = [
            'anthropic' => [
                'key'    => (string) get_option( 'borsatek_ai_anthropic_key', '' ),
                'model'  => (string) get_option( 'borsatek_ai_anthropic_model', 'claude-sonnet-4-20250514' ),
                'active' => (bool) get_option( 'borsatek_ai_anthropic_active', true ),
            ],
            'gemini'    => [
                'key'    => (string) get_option( 'borsatek_ai_gemini_key', '' ),
                'model'  => (string) get_option( 'borsatek_ai_gemini_model', 'gemini-2.5-flash' ),
                'active' => (bool) get_option( 'borsatek_ai_gemini_active', true ),
            ],
            'openai'    => [
                'key'    => (string) get_option( 'borsatek_ai_openai_key', '' ),
                'model'  => (string) get_option( 'borsatek_ai_openai_model', 'gpt-4o-mini' ),
                'active' => true,
            ],
            'together'  => [
                'key'    => (string) get_option( 'borsatek_ai_together_key', '' ),
                'model'  => (string) get_option( 'borsatek_ai_together_model', 'meta-llama/Llama-3.3-70B-Instruct-Turbo' ),
                'active' => true,
            ],
        ];

        // Sıralamayı tercih edilen sağlayıcıya göre ayarla
        $order = [ 'anthropic', 'gemini', 'openai', 'together' ];
        if ( $preferred === 'gemini' ) {
            $order = [ 'gemini', 'anthropic', 'openai', 'together' ];
        } elseif ( $preferred === 'openai' ) {
            $order = [ 'openai', 'anthropic', 'gemini', 'together' ];
        } elseif ( $preferred === 'together' ) {
            $order = [ 'together', 'anthropic', 'gemini', 'openai' ];
        }

        foreach ( $order as $name ) {
            $cfg = $providers[ $name ];
            if ( $cfg['active'] && ! empty( $cfg['key'] ) ) {
                $chain[] = [
                    'provider' => $name,
                    'key'      => $cfg['key'],
                    'model'    => $cfg['model'],
                ];
            }
        }

        return $chain;
    }

    /**
     * AI yanıtını JSON olarak ayrıştırır.
     */
    public function parseJsonResponse( string $raw ): ?array {
        // Markdown kod bloğunu temizle
        $clean = preg_replace( '/^```(?:json)?\s*/m', '', $raw );
        $clean = preg_replace( '/\s*```\s*$/m', '', $clean );
        $clean = trim( $clean );

        // JSON decode dene
        $decoded = json_decode( $clean, true );
        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        // Truncated JSON onarımı
        $repaired = $this->repairTruncatedJson( $clean );
        $decoded  = json_decode( $repaired, true );
        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        return null;
    }

    /**
     * Kesilmiş JSON'u onarır.
     */
    public function repairTruncatedJson( string $json ): string {
        $json = trim( $json );

        // Açık string varsa kapat
        $quoteCount = substr_count( $json, '"' ) - substr_count( $json, '\\"' );
        if ( $quoteCount % 2 !== 0 ) {
            $json .= '"';
        }

        // Açık array ve obje kapanışlarını ekle
        $opens  = 0;
        $arrays = 0;
        $inStr  = false;
        $escape = false;

        for ( $i = 0; $i < strlen( $json ); $i++ ) {
            $c = $json[ $i ];
            if ( $escape ) {
                $escape = false;
                continue;
            }
            if ( $c === '\\' ) {
                $escape = true;
                continue;
            }
            if ( $c === '"' ) {
                $inStr = ! $inStr;
                continue;
            }
            if ( $inStr ) {
                continue;
            }
            if ( $c === '{' ) {
                $opens++;
            } elseif ( $c === '}' ) {
                $opens--;
            } elseif ( $c === '[' ) {
                $arrays++;
            } elseif ( $c === ']' ) {
                $arrays--;
            }
        }

        // Son karakterin virgül olması durumunu düzelt
        $json = rtrim( $json, ',' );

        for ( $i = 0; $i < $arrays; $i++ ) {
            $json .= ']';
        }
        for ( $i = 0; $i < $opens; $i++ ) {
            $json .= '}';
        }

        return $json;
    }

    /**
     * Gemini API'de mevcut modelleri getirir.
     */
    public function fetchGeminiModels( string $apiKey ): array {
        $response = wp_remote_get( "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}", [
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return [];
        }

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $models = [];

        if ( ! empty( $data['models'] ) ) {
            foreach ( $data['models'] as $model ) {
                if (
                    ! empty( $model['name'] ) &&
                    ! empty( $model['supportedGenerationMethods'] ) &&
                    in_array( 'generateContent', $model['supportedGenerationMethods'], true )
                ) {
                    $name = str_replace( 'models/', '', $model['name'] );
                    if ( strpos( $name, 'gemini' ) !== false ) {
                        $models[] = $name;
                    }
                }
            }
        }

        sort( $models );
        return $models;
    }

    /**
     * Gemini bağlantısını test eder.
     */
    public function testGeminiConnection( string $apiKey, string $model ): bool|\WP_Error {
        $result = $this->callGemini(
            'Merhaba, JSON ile yanıt ver: {"ok":true}',
            $apiKey,
            $model,
            100,
            15
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return stripos( $result, 'ok' ) !== false || stripos( $result, 'true' ) !== false;
    }

    /**
     * Maliyet moduna ve sağlayıcıya göre maksimum token sayısını döndürür.
     */
    public function getMaxTokens( string $costMode, string $provider ): int {
        $map = [
            'anthropic' => [ 'low' => 8000,  'balanced' => 20000, 'high' => 32000 ],
            'gemini'    => [ 'low' => 16384, 'balanced' => 32768, 'high' => 65536 ],
            'openai'    => [ 'low' => 4000,  'balanced' => 12000, 'high' => 20000 ],
            'together'  => [ 'low' => 4000,  'balanced' => 12000, 'high' => 20000 ],
        ];

        return $map[ $provider ][ $costMode ] ?? $map['anthropic']['balanced'];
    }

    /**
     * Repair pass için token limitini döndürür.
     */
    public function getRepairTokens( string $costMode, string $provider ): int {
        $map = [
            'anthropic' => [ 'low' => 1800, 'balanced' => 3200, 'high' => 5000 ],
            'gemini'    => [ 'low' => 2000, 'balanced' => 4000, 'high' => 8000 ],
            'openai'    => [ 'low' => 2200, 'balanced' => 4200, 'high' => 6500 ],
            'together'  => [ 'low' => 2200, 'balanced' => 4200, 'high' => 6500 ],
        ];

        return $map[ $provider ][ $costMode ] ?? $map['anthropic']['balanced'];
    }
}
