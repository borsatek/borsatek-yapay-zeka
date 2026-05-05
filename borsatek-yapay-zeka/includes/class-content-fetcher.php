<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RSS haberlerinin tam içeriğini çeşitli yöntemlerle çeker.
 */
class BorsatekContentFetcher {

    /**
     * Verilen URL'den haber içeriğini çeker.
     * Zincir: Jina → Direkt (Chrome UA) → Direkt (Mobile UA) → En iyi sonuç
     *
     * @return array|WP_Error ['title'=>'', 'content'=>'', 'date'=>'']
     */
    public function fetch( string $url ): array|\WP_Error {
        // Jina URL'si yanlışlıkla RSS listesine eklenmiş ise atla
        if ( strpos( $url, 'r.jina.ai' ) !== false ) {
            return new WP_Error( 'invalid_feed_url', 'Jina URL RSS listesine eklenmemeli, sadece haber URL\'si girin.' );
        }

        if ( strpos( $url, 'borsatr.com' ) !== false ) {
            $result = $this->fetchDirect( $url );
            if ( ! is_wp_error( $result ) ) {
                $result['content'] = $this->cleanBorsaTrContent( $result['content'] );
            }
            return $result;
        }

        if ( strpos( $url, 'finance.yahoo.com' ) !== false ) {
            // Yahoo doğrudan erişimi kısıtladı → Jina dene
            $result = $this->fetchViaJina( $url );
            if ( ! is_wp_error( $result ) && mb_strlen( $result['content'] ) >= $this->getMinSourceChars() ) {
                $result['content'] = $this->cleanYahooContent( $result['content'] );
                return $result;
            }
            // Jina da başarısız → direkt çek
            $direct = $this->fetchDirect( $url );
            if ( ! is_wp_error( $direct ) ) {
                $direct['content'] = $this->cleanYahooContent( $direct['content'] );
            }
            return $direct;
        }

        if ( strpos( $url, 'investing.com' ) !== false ) {
            return $this->fetchInvesting( $url );
        }

        // Trefis: çoğunlukla JS ile render; Reader ile tam metin daha güvenilir
        if ( strpos( $url, 'trefis.com' ) !== false ) {
            return $this->fetchTrefis( $url );
        }

        // Paywall / JS ağırlıklı finans siteleri: önce Jina (tam makale), sonra direkt
        if ( preg_match( '/thestreet\.com|marketwatch\.com|barrons\.com|fool\.com/i', $url ) ) {
            return $this->fetchJinaThenDirect( $url );
        }

        // Varsayılan: direkt çek; çok kısa veya şüpheli özet ise Jina ile karşılaştır
        // (TheStreet vb. doğrudan çekimde bazen yalnızca paywall öncesi teaser gelir > min karakter)
        $result    = $this->fetchDirect( $url );
        $resultLen = is_wp_error( $result ) ? 0 : mb_strlen( $result['content'] ?? '' );
        $min       = $this->getMinSourceChars();

        $tryJinaCompare = $resultLen < $min;
        // Makale beklenirken doğrudan metin çok kısaysa Jina ile zenginleştirmeyi dene
        if ( ! $tryJinaCompare && $resultLen < 2200 ) {
            $tryJinaCompare = true;
        }

        if ( $tryJinaCompare ) {
            $jinaResult = $this->fetchViaJina( $url );
            if ( ! is_wp_error( $jinaResult ) ) {
                $jLen = mb_strlen( $jinaResult['content'] ?? '' );
                $gain = max( 250, (int) round( $resultLen * 0.35 ) );
                if ( $jLen > $resultLen && ( $resultLen < $min || $jLen >= $resultLen + $gain ) ) {
                    return $jinaResult;
                }
            }
        }

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'fetch_failed', 'İçerik çekilemedi.' );
        }

        return $result;
    }

    /**
     * Önce Jina Reader (tam metin), gerekirse doğrudan HTTP — paywall teaser tuzakları için.
     *
     * @return array|WP_Error
     */
    private function fetchJinaThenDirect( string $url ): array|\WP_Error {
        $min      = $this->getMinSourceChars();
        $jina     = $this->fetchViaJina( $url );
        $jinaLen  = is_wp_error( $jina ) ? 0 : mb_strlen( $jina['content'] ?? '' );
        $direct   = $this->fetchDirect( $url );
        $directLen = is_wp_error( $direct ) ? 0 : mb_strlen( $direct['content'] ?? '' );

        if ( ! is_wp_error( $jina ) && $jinaLen >= $min ) {
            return $jina;
        }

        if ( ! is_wp_error( $jina ) && $jinaLen > $directLen ) {
            return $jina;
        }

        if ( ! is_wp_error( $direct ) ) {
            return $direct;
        }

        return is_wp_error( $jina ) ? $direct : $jina;
    }

    /**
     * investing.com için çoklu fallback zinciri:
     * Jina → Direkt (Mobile UA) → RSS özet
     */
    private function fetchInvesting( string $url ): array|\WP_Error {
        // 1. Jina ile dene
        $jina = $this->fetchViaJina( $url );
        if ( ! is_wp_error( $jina ) && mb_strlen( $jina['content'] ) >= $this->getMinSourceChars() ) {
            return $jina;
        }

        // 2. Jina 451/403 hatası → mobil User-Agent ile direkt dene
        $direct = $this->fetchDirect( $url, 'mobile' );
        if ( ! is_wp_error( $direct ) && mb_strlen( $direct['content'] ) >= $this->getMinSourceChars() ) {
            return $direct;
        }

        // 3. Google AMP cache üzerinden dene
        $ampResult = $this->fetchViaGoogleAmp( $url );
        if ( ! is_wp_error( $ampResult ) && mb_strlen( $ampResult['content'] ) >= $this->getMinSourceChars() ) {
            return $ampResult;
        }

        // 4. Hiçbiri çalışmadı → Jina sonucunu (kısa da olsa) döndür, AI özet için yeterli olabilir
        if ( ! is_wp_error( $jina ) && mb_strlen( $jina['content'] ) > 50 ) {
            return $jina;
        }

        return new WP_Error( 'investing_blocked', 'investing.com içeriği çekilemedi (451/403). RSS özeti kullanılacak.' );
    }

    /**
     * trefis.com analiz yazıları: önce Jina (tam makale), sonra direkt fallback.
     */
    private function fetchTrefis( string $url ): array|\WP_Error {
        $min     = $this->getMinSourceChars();
        $jina    = $this->fetchViaJina( $url );
        $jinaLen = is_wp_error( $jina ) ? 0 : mb_strlen( $jina['content'] ?? '' );

        if ( ! is_wp_error( $jina ) && $jinaLen >= $min ) {
            return $jina;
        }

        $direct    = $this->fetchDirect( $url );
        $directLen = is_wp_error( $direct ) ? 0 : mb_strlen( $direct['content'] ?? '' );

        if ( ! is_wp_error( $direct ) && $directLen >= $min ) {
            return $direct;
        }

        if ( ! is_wp_error( $jina ) && $jinaLen > max( $directLen, 80 ) ) {
            return $jina;
        }

        return is_wp_error( $direct ) ? $jina : $direct;
    }

    /**
     * Google AMP cache üzerinden içerik çeker (paywall bypass için).
     * Format: https://[domain-dashes].cdn.ampproject.org/v/s/[url]
     */
    private function fetchViaGoogleAmp( string $url ): array|\WP_Error {
        $parsed = parse_url( $url );
        if ( empty( $parsed['host'] ) ) {
            return new WP_Error( 'amp_invalid_url', 'Geçersiz URL' );
        }

        // Host'u AMP formatına çevir (noktalara çizgi koy)
        $ampHost = str_replace( '.', '-', $parsed['host'] );
        $path    = $parsed['path'] ?? '/';
        $query   = ! empty( $parsed['query'] ) ? '?' . $parsed['query'] : '';
        $ampUrl  = "https://{$ampHost}.cdn.ampproject.org/v/s/{$parsed['host']}{$path}{$query}";

        $response = wp_remote_get( $ampUrl, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'amp_http', "AMP HTTP {$code}" );
        }

        $body = wp_remote_retrieve_body( $response );
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( '<?xml encoding="UTF-8">' . $body );
        libxml_clear_errors();

        $xpath    = new DOMXPath( $dom );
        $nodes    = $xpath->query( '//article | //*[@class="article-body"] | //main' );
        $content  = '';

        if ( $nodes && $nodes->length > 0 ) {
            $content = $this->extractTextFromNode( $nodes->item( 0 ) );
        }

        if ( mb_strlen( $content ) < 100 ) {
            return new WP_Error( 'amp_empty', 'AMP içeriği boş' );
        }

        $titleNodes = $xpath->query( '//title' );
        $title = $titleNodes && $titleNodes->length > 0 ? trim( $titleNodes->item(0)->textContent ) : '';

        return [
            'title'   => $title,
            'content' => $this->normalizeSourceText( $content ),
            'date'    => '',
        ];
    }

    /**
     * Jina Reader API üzerinden içerik çeker.
     */
    public function fetchViaJina( string $url ): array|\WP_Error {
        $jinaKey = $this->getJinaKey();
        $jinaUrl = get_option( 'borsatek_ai_jina_url', 'https://r.jina.ai' );

        $headers = [
            'Accept'           => 'text/plain',
            'X-Respond-With'   => 'markdown',
            'X-Timeout'        => '25',
            'X-No-Cache'       => 'true',
        ];
        if ( ! empty( $jinaKey ) ) {
            $headers['Authorization'] = 'Bearer ' . $jinaKey;
        }

        $response = wp_remote_get( rtrim( $jinaUrl, '/' ) . '/' . ltrim( $url, '/' ), [
            'timeout' => 30,
            'headers' => $headers,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code === 451 ) {
            // 451 = Yasal nedenle engellendi (investing.com gibi). Farklı yöntem denenecek.
            return new WP_Error( 'jina_451', "Jina: İçerik yasal nedenle engellendi (451). Site erişimi kısıtlıyor." );
        }

        if ( $code === 429 ) {
            return new WP_Error( 'jina_429', 'Jina rate limit aşıldı. Bir süre bekleyin.' );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'jina_http', "Jina HTTP {$code}" );
        }

        $cleaned = $this->cleanJinaMarkdown( $body );
        $title   = '';

        // İlk # satırından başlığı al
        if ( preg_match( '/^#\s+(.+)$/m', $cleaned, $m ) ) {
            $title   = trim( $m[1] );
            $cleaned = trim( preg_replace( '/^#\s+.+$/m', '', $cleaned, 1 ) );
        }

        return [
            'title'   => $title,
            'content' => $this->normalizeSourceText( $cleaned ),
            'date'    => '',
        ];
    }

    /**
     * Jina Reader erişimini doğrular (sabit bir test URL'si ile).
     *
     * @param string      $apiKey        Boş bırakılırsa anonim kota kullanılır (sınırlı).
     * @param string|null $jinaBaseUrl   Örn. https://r.jina.ai — null ise ayarlardan okunur.
     */
    public function testJinaReader( string $apiKey = '', ?string $jinaBaseUrl = null ): bool|\WP_Error {
        $savedUrl = esc_url_raw( rtrim( (string) get_option( 'borsatek_ai_jina_url', 'https://r.jina.ai' ), '/' ) );
        $jinaUrl  = $jinaBaseUrl !== null && $jinaBaseUrl !== ''
            ? esc_url_raw( rtrim( $jinaBaseUrl, '/' ) )
            : $savedUrl;

        if ( $jinaUrl === '' || ! preg_match( '#^https?://#i', $jinaUrl ) ) {
            return new WP_Error( 'jina_bad_url', 'Jina Reader taban URL geçersiz.' );
        }

        $headers = [
            'Accept'         => 'text/plain',
            'X-Respond-With' => 'markdown',
            'X-Timeout'      => '15',
            'X-No-Cache'     => 'true',
        ];
        $keyTrim = trim( $apiKey );
        if ( $keyTrim !== '' ) {
            $headers['Authorization'] = 'Bearer ' . $keyTrim;
        }

        $probe    = 'https://example.com/';
        $endpoint = $jinaUrl . '/' . ltrim( $probe, '/' );

        $response = wp_remote_get( $endpoint, [
            'timeout' => 25,
            'headers' => $headers,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code === 401 ) {
            return new WP_Error( 'jina_unauthorized', 'Jina: Yetkisiz (401). API anahtarını kontrol edin.' );
        }

        if ( $code === 429 ) {
            return new WP_Error( 'jina_429', 'Jina rate limit (429). Bir süre sonra tekrar deneyin.' );
        }

        if ( $code !== 200 ) {
            $snippet = mb_substr( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $body ) ), 0, 140 );

            return new WP_Error(
                'jina_http',
                'Jina HTTP ' . $code . ( $snippet !== '' ? ': ' . $snippet : '' )
            );
        }

        $cleaned = trim( $this->cleanJinaMarkdown( $body ) );
        if ( mb_strlen( $cleaned ) < 15 ) {
            return new WP_Error( 'jina_empty', 'Jina yanıtı çok kısa veya boş.' );
        }

        return true;
    }

    /**
     * Doğrudan HTTP isteğiyle içerik çeker ve HTML'i ayrıştırır.
     *
     * @param string $uaMode 'desktop' (varsayılan) | 'mobile' | 'bot'
     */
    public function fetchDirect( string $url, string $uaMode = 'desktop' ): array|\WP_Error {
        $userAgents = [
            'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'mobile'  => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'bot'     => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ];

        $response = wp_remote_get( $url, [
            'timeout'    => 18,
            'user-agent' => $userAgents[ $uaMode ] ?? $userAgents['desktop'],
            'headers'    => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate',
                'Cache-Control'   => 'no-cache',
                'Referer'         => 'https://www.google.com/',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return new WP_Error( 'direct_http', "HTTP {$code}" );
        }

        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( '<?xml encoding="UTF-8">' . $body );
        libxml_clear_errors();

        $xpath   = new DOMXPath( $dom );
        $content = '';

        // İçerik bloğunu sırayla ara
        $selectors = [
            '//article',
            '//*[@itemprop="articleBody"]',
            '//main',
            '//div[contains(@class,"content")]',
            '//div[contains(@class,"post-content")]',
            '//div[contains(@class,"entry-content")]',
        ];

        foreach ( $selectors as $selector ) {
            $nodes = $xpath->query( $selector );
            if ( $nodes && $nodes->length > 0 ) {
                $text = $this->extractTextFromNode( $nodes->item( 0 ) );
                if ( mb_strlen( $text ) > 200 ) {
                    $content = $text;
                    break;
                }
            }
        }

        // Hâlâ boşsa: tüm p bloklarından en yüksek puanlıyı seç
        if ( empty( $content ) ) {
            $paragraphs = $xpath->query( '//p' );
            $best       = '';
            $bestScore  = 0;

            if ( $paragraphs ) {
                $current = '';
                foreach ( $paragraphs as $p ) {
                    $text  = trim( $p->textContent );
                    $score = $this->scoreTextBlock( $text, $url );
                    if ( $score > $bestScore && mb_strlen( $text ) > 100 ) {
                        $bestScore = $score;
                        $best      = $text;
                    }
                    $current .= ' ' . $text;
                }
                $content = mb_strlen( $current ) > mb_strlen( $best ) ? $current : $best;
            }
        }

        // Başlığı <title> tagından al
        $titleNodes = $xpath->query( '//title' );
        $title      = '';
        if ( $titleNodes && $titleNodes->length > 0 ) {
            $title = trim( $titleNodes->item( 0 )->textContent );
        }

        return [
            'title'   => $title,
            'content' => $this->normalizeSourceText( $content ),
            'date'    => '',
        ];
    }

    /**
     * DOM node'undan düz metin çıkarır.
     */
    private function extractTextFromNode( DOMNode $node ): string {
        $text = '';
        foreach ( $node->childNodes as $child ) {
            if ( $child->nodeType === XML_TEXT_NODE ) {
                $text .= $child->textContent . ' ';
            } elseif ( $child->nodeType === XML_ELEMENT_NODE ) {
                $tag = strtolower( $child->nodeName );
                if ( in_array( $tag, [ 'p', 'div', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote' ], true ) ) {
                    $text .= "\n" . $this->extractTextFromNode( $child ) . "\n";
                } else {
                    $text .= $this->extractTextFromNode( $child );
                }
            }
        }
        return $text;
    }

    /**
     * Metin bloğuna puan verir (içerik kalitesi değerlendirmesi).
     */
    public function scoreTextBlock( string $text, string $url ): int {
        $score = 0;

        if ( mb_strlen( $text ) > 200 ) {
            $score += 3;
        }

        // Türkçe karakter kontrolü
        if ( preg_match( '/[ğşıöüçĞŞİÖÜÇ]/', $text ) ) {
            $score += 2;
        }

        // URL domain eşleşmesi
        $host = parse_url( $url, PHP_URL_HOST );
        if ( $host && strpos( $text, $host ) !== false ) {
            $score += 1;
        }

        // Menü/nav sözcükleri → negatif
        $navWords = [ 'menü', 'menu', 'navigation', 'navbar', 'footer', 'header', 'sidebar', 'cookie', 'gizlilik' ];
        foreach ( $navWords as $word ) {
            if ( stripos( $text, $word ) !== false ) {
                $score -= 5;
                break;
            }
        }

        return $score;
    }

    /**
     * borsatr.com içeriğini temizler.
     */
    public function cleanBorsaTrContent( string $text ): string {
        $stopWords = [ 'Yorumlar', 'İlgili Haberler', 'Benzer Haberler', 'Bültene Kayıt' ];
        foreach ( $stopWords as $word ) {
            $pos = mb_strpos( $text, $word );
            if ( $pos !== false ) {
                $text = mb_substr( $text, 0, $pos );
            }
        }

        // Ardışık 3+ boş satırı tek satıra indir
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }

    /**
     * Yahoo Finance içeriğini temizler.
     */
    public function cleanYahooContent( string $text ): string {
        $removePatterns = [
            '/^Yahoo Finance.*$/mi',
            '/^This article was.*$/mi',
            '/^Read more at.*$/mi',
            '/^Disclaimer.*$/mi',
        ];

        foreach ( $removePatterns as $pattern ) {
            $text = preg_replace( $pattern, '', $text );
        }

        return trim( $text );
    }

    /**
     * Jina Reader'dan gelen Markdown metnini temizler.
     */
    public function cleanJinaMarkdown( string $text ): string {
        $lines    = explode( "\n", $text );
        $filtered = [];

        $skipPatterns = [
            '/^---+$/',
            '/^===+$/',
            '/^\[Read more\]/i',
            '/^Subscribe/i',
            '/^Cookie/i',
            '/^Accept all/i',
            '/^JavaScript/i',
            '/^×$/',
        ];

        foreach ( $lines as $line ) {
            $skip = false;
            foreach ( $skipPatterns as $pattern ) {
                if ( preg_match( $pattern, trim( $line ) ) ) {
                    $skip = true;
                    break;
                }
            }
            if ( ! $skip ) {
                $filtered[] = $line;
            }
        }

        $text = implode( "\n", $filtered );
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }

    /**
     * Kaynak metni normalleştirir (boşluk, satır sonu, baş/son).
     */
    public function normalizeSourceText( string $text ): string {
        // Windows satır sonlarını normalleştir
        $text = str_replace( "\r\n", "\n", $text );
        $text = str_replace( "\r", "\n", $text );

        // Birden fazla boşluğu tek boşluğa indir (satır sonları hariç)
        $text = preg_replace( '/[^\S\n]+/', ' ', $text );

        // Satır başı/sonundaki boşlukları temizle
        $lines = array_map( 'trim', explode( "\n", $text ) );
        $text  = implode( "\n", $lines );

        // Ardışık 3+ boş satırı tek satıra indir
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }

    /**
     * Minimum kaynak karakter sayısını döndürür.
     */
    public function getMinSourceChars(): int {
        return (int) get_option( 'borsatek_ai_min_source_chars', 300 );
    }

    /**
     * Jina Reader API anahtarını döndürür.
     */
    public function getJinaKey(): string {
        return (string) get_option( 'borsatek_ai_jina_key', '' );
    }
}
