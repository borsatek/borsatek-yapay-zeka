<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO kural ayrıştırma, uygulama ve skor hesaplama motorudur.
 */
class BorsatekSeoEngine {

    /** Korunan kısaltmalar — büyük harf normalleştirmede dokunulmaz. */
    private const PROTECTED_ABBR = [
        'TL', 'BIST', 'ABD', 'AB', 'IMF', 'FED', 'GSYH', 'TCMB',
        'ECB', 'NYSE', 'SPK', 'BDDK', 'AAPL', 'MSFT', 'NVDA',
        'TSLA', 'EU', 'UK', 'US', 'USD', 'EUR', 'GBP', 'JPY',
        'NATO', 'OPEC', 'BTC', 'ETH',
    ];

    /**
     * Varsayılan SEO kural setini döndürür.
     */
    public function getDefaults( string $keyword = '' ): array {
        return [
            'keyword'                          => trim( $keyword ),
            'titleMaxChars'                    => 100,
            'seoTitleMaxChars'                 => 60,
            'metaDescriptionMaxChars'          => 160,
            'slugMaxChars'                     => 90,
            'excerptMaxChars'                  => 200,
            'bodyMinParagraphs'                => 4,
            'bodyMinSourceRatio'               => 0.52,
            'subheadingCount'                  => 2,
            'requireKeywordInTitle'            => true,
            'requireKeywordInSeoTitle'         => true,
            'requireKeywordInFirstParagraph'   => true,
            'requireKeywordInMetaDescription'  => true,
            'requireKeywordInExcerpt'          => true,
            'requireKeywordInSubheading'       => false,
            'requireKeywordInSlug'             => true,
            'keywordDensityMin'                => 0,
            'keywordDensityMax'                => 0,
            'forbiddenPhrases'                 => [
                'bu kapsamda', 'söz konusu', 'soz konusu',
                'dikkat çekti', 'dikkat cekti',
                'öne çıktı', 'one cikti', 'öne cıktı',
                'sonuç olarak', 'sonuc olarak',
                'özetle', 'ozetle',
                'netice itibariyle',
                'kısacası', 'kisacasi',
                'genel olarak değerlendirildiğinde',
                'genel olarak degerlendirildiginde',
                'yahoo finance tarafından', 'yahoo finance tarafindan',
                'tarafından paylaşılan veriler',
                'tarafindan paylasilan veriler',
                'kaynaklardan alınan bilgilere göre',
            ],
            'forbiddenSentenceStarts'          => [
                'Bu ', 'Bu nedenle', 'Bu durum', 'Bu gelişme',
                'Bu tablo', 'Bu karar', 'Bu adım', 'Bu süreç',
                'Bu eğilim', 'Bu görünüm', 'Bu veri',
                'Bu artış', 'Bu düşüş', 'Bu açıklama',
                'Bu beklenti', 'Bu sonuç', 'Bu hamle',
                'Bu çerçevede', 'Sonuç olarak',
            ],
            'requiredPhrases'                  => [],
        ];
    }

    /**
     * Serbest metin SEO kurallarını ayrıştırır ve kural seti döndürür.
     */
    public function parseRules( string $rulesText, string $keyword = '' ): array {
        $ruleSet = $this->getDefaults( $keyword );

        if ( empty( $rulesText ) ) {
            return $ruleSet;
        }

        // Türkçe karakterleri normalleştir (yalnızca regex eşleştirme için)
        $normalized = mb_strtolower( $rulesText, 'UTF-8' );
        $normalized = strtr( $normalized, [
            'ş' => 's', 'ğ' => 'g', 'ı' => 'i',
            'ö' => 'o', 'ü' => 'u', 'ç' => 'c',
        ] );

        // SEO başlık karakter limiti
        if ( preg_match( '/seo baslik[^0-9]{0,30}(\d{2,3})\s*karakter/u', $normalized, $m ) ) {
            $ruleSet['seoTitleMaxChars'] = (int) $m[1];
        }

        // Meta açıklama karakter limiti
        if ( preg_match( '/(?:meta aciklama|meta description)[^0-9]{0,30}(\d{2,3})\s*karakter/u', $normalized, $m ) ) {
            $ruleSet['metaDescriptionMaxChars'] = (int) $m[1];
        }

        // Başlık karakter limiti
        if ( preg_match( '/(?:^|[^a-z])(?:baslik|title)[^0-9]{0,30}(\d{2,3})\s*karakter/mu', $normalized, $m ) ) {
            $ruleSet['titleMaxChars'] = (int) $m[1];
        }

        // Slug karakter limiti
        if ( preg_match( '/(?:slug|url|permalink)[^0-9]{0,40}(\d{2,3})\s*karakter/u', $normalized, $m ) ) {
            $ruleSet['slugMaxChars'] = (int) $m[1];
        }

        // Minimum paragraf
        if ( preg_match( '/(?:en az|minimum|min\.)[^0-9]{0,12}(\d{1,2})\s*paragraf/u', $normalized, $m ) ) {
            $ruleSet['bodyMinParagraphs'] = (int) $m[1];
        }

        // Ara başlık sayısı — "olmasın" durumu
        if ( preg_match( '/ara baslik.{0,20}(?:olmasin|kullanma|kullanilmasin)/u', $normalized ) ) {
            $ruleSet['subheadingCount'] = 0;
        } elseif ( preg_match( '/(?:ara baslik|h2)[^0-9]{0,12}(\d{1,2})\s*(?:adet|tane)?/u', $normalized, $m ) ) {
            $ruleSet['subheadingCount'] = (int) $m[1];
        }

        // Keyword yoğunluğu
        if ( preg_match( '/keyword yog[^0-9]{0,10}%?(\d{1,2})-?%?(\d{1,2})?/u', $normalized, $m ) ) {
            $ruleSet['keywordDensityMin'] = (int) $m[1];
            $ruleSet['keywordDensityMax'] = isset( $m[2] ) ? (int) $m[2] : (int) $m[1];
        }

        // Özet / excerpt karakter limiti
        if ( preg_match( '/(?:ozet|özet|excerpt)[^0-9]{0,25}(\d{2,3})\s*karakter/u', $normalized, $m ) ) {
            $ruleSet['excerptMaxChars'] = (int) $m[1];
        }

        // İngilizce kalıplar (ör. title max 90 chars)
        if ( preg_match( '/title[^\d]{0,25}(\d{2,3})\s*(?:chars|characters)/u', $normalized, $m ) ) {
            $ruleSet['titleMaxChars'] = (int) $m[1];
        }

        // Odak kelime zorunluluğu iptalleri ve ara başlıkta keyword talebi (panel metnine göre)
        $this->applyKeywordRequirementOverrides( $rulesText, $ruleSet );

        // Başlık + liste bloklarından yasaklı / zorunlu ifadeler
        $lists = $this->extractSectionListsFromPanel( $rulesText );
        $ruleSet['forbiddenPhrases']          = array_values( array_unique( array_merge( $ruleSet['forbiddenPhrases'], $lists['forbiddenPhrases'] ) ) );
        $ruleSet['forbiddenSentenceStarts']    = array_values( array_unique( array_merge( $ruleSet['forbiddenSentenceStarts'], $lists['forbiddenSentenceStarts'] ) ) );
        $ruleSet['requiredPhrases']            = array_values( array_unique( array_merge( $ruleSet['requiredPhrases'], $lists['requiredPhrases'] ) ) );

        return $ruleSet;
    }

    /**
     * Panel metninden odak kelime zorunluluğu kapama/açma ve ara başlık kuralı.
     */
    private function applyKeywordRequirementOverrides( string $rulesText, array &$ruleSet ): void {
        $neg = '(?:olmasın|olmamalı|zorunlu\s*değil|zorunlu\s*degil|opsiyonel|gerekmez|istemiyorum|bulunmasın|bulunmamalı)';

        if ( preg_match( '/(?:başlık|baslik|title)[^\n]{0,100}(?:keyword|odak\s*kelime|odak\s*kelimesi)[^\n]{0,80}' . $neg . '/iu', $rulesText ) ) {
            $ruleSet['requireKeywordInTitle'] = false;
        }
        if ( preg_match( '/seo[^\n]{0,50}(?:başlık|baslik)[^\n]{0,100}(?:keyword|odak\s*kelime)[^\n]{0,80}' . $neg . '/iu', $rulesText ) ) {
            $ruleSet['requireKeywordInSeoTitle'] = false;
        }
        if ( preg_match( '/(?:meta\s*açıklama|meta\s*aciklama)[^\n]{0,120}(?:keyword|odak\s*kelime)[^\n]{0,80}' . $neg . '/iu', $rulesText ) ) {
            $ruleSet['requireKeywordInMetaDescription'] = false;
        }
        if ( preg_match( '/(?:özet|ozet|excerpt)[^\n]{0,80}(?:keyword|odak\s*kelime)[^\n]{0,80}' . $neg . '/iu', $rulesText ) ) {
            $ruleSet['requireKeywordInExcerpt'] = false;
        }
        if ( preg_match( '/(?:ilk\s*paragraf|birinci\s*paragraf)[^\n]{0,100}(?:keyword|odak\s*kelime)[^\n]{0,80}' . $neg . '/iu', $rulesText ) ) {
            $ruleSet['requireKeywordInFirstParagraph'] = false;
        }
        if ( preg_match( '/slug[^\n]{0,80}(?:keyword|odak\s*kelime)[^\n]{0,80}' . $neg . '/iu', $rulesText ) ) {
            $ruleSet['requireKeywordInSlug'] = false;
        }

        if ( preg_match( '/ara\s*(?:başlık|baslik)|h2[^\n]{0,80}(?:keyword|odak\s*kelime)[^\n]{0,80}(?:geç|geçmeli|bulunmalı|zorunlu|olmali)/iu', $rulesText ) ) {
            $ruleSet['requireKeywordInSubheading'] = true;
        }
    }

    /**
     * Panel SEO metninde başlık satırı + madde işaretli listelerden yapı çıkarır.
     */
    private function extractSectionListsFromPanel( string $rulesText ): array {
        $fp = [];
        $fs = [];
        $rq = [];

        $lines = preg_split( '/\r\n|\r|\n/', $rulesText );
        $mode  = null;

        foreach ( $lines as $line ) {
            $trim = trim( $line );
            if ( $trim === '' ) {
                $mode = null;
                continue;
            }

            $isBullet = preg_match( '/^[-*•]\s+/u', $trim );

            if ( ! $isBullet ) {
                if ( preg_match( '/^(?:#{1,6}\s*)?(?:[*_]{0,2})?\s*(?:yasaklı|yasak)\s+(?:ifade|ifadeler|kelime|kelimeler)|kullanıl(?:mayacak|maması)|forbidden\s+phrases?/iu', $trim ) ) {
                    $mode = 'fp';
                } elseif ( preg_match( '/^(?:#{1,6}\s*)?(?:[*_]{0,2})?\s*(?:yasaklı\s+cümle|cümle\s+başında\s+kullanılmayacak|cümle\s+basinda|forbidden\s+sentence)/iu', $trim ) ) {
                    $mode = 'fs';
                } elseif ( preg_match( '/^(?:#{1,6}\s*)?(?:[*_]{0,2})?\s*(?:zorunlu\s+(?:ifade|ifadeler)|mutlaka\s+geç|required\s+phrases?)/iu', $trim ) ) {
                    $mode = 'rq';
                } else {
                    $mode = null;
                }

                if ( $mode !== null && preg_match( '/[:：]\s*(.+)$/u', $trim, $im ) ) {
                    foreach ( preg_split( '/[,;|]/u', $im[1] ) as $chunk ) {
                        $chunk = trim( $chunk );
                        if ( $chunk !== '' && mb_strlen( $chunk ) > 1 ) {
                            if ( $mode === 'fp' ) {
                                $fp[] = $chunk;
                            } elseif ( $mode === 'fs' ) {
                                $fs[] = $chunk;
                            } else {
                                $rq[] = $chunk;
                            }
                        }
                    }
                }
                continue;
            }

            if ( preg_match( '/^[-*•]\s+(.+)$/u', $trim, $m ) ) {
                $item = trim( $m[1] );
                if ( $mode === 'fp' ) {
                    $fp[] = $item;
                } elseif ( $mode === 'fs' ) {
                    $fs[] = $item;
                } elseif ( $mode === 'rq' ) {
                    $rq[] = $item;
                }
            }
        }

        return [
            'forbiddenPhrases'       => $fp,
            'forbiddenSentenceStarts'=> $fs,
            'requiredPhrases'       => $rq,
        ];
    }

    /**
     * Kural setini AI prompt'u için okunabilir bir formata dönüştürür.
     */
    public function formatRulesForPrompt( array $ruleSet ): string {
        $lines = [];

        $lines[] = "- Başlık maksimum {$ruleSet['titleMaxChars']} karakter olmalı";
        $lines[] = "- SEO başlık maksimum {$ruleSet['seoTitleMaxChars']} karakter olmalı";
        $lines[] = "- Meta açıklama maksimum {$ruleSet['metaDescriptionMaxChars']} karakter olmalı";
        $lines[] = "- Slug maksimum {$ruleSet['slugMaxChars']} karakter olmalı";
        $lines[] = "- Gövde en az {$ruleSet['bodyMinParagraphs']} paragraf içermeli";

        if ( $ruleSet['subheadingCount'] > 0 ) {
            $lines[] = "- {$ruleSet['subheadingCount']} adet H2 ara başlık kullanılmalı";
        } else {
            $lines[] = "- Ara başlık (H2) kullanılmamalı";
        }

        if ( ! empty( $ruleSet['keyword'] ) ) {
            if ( $ruleSet['requireKeywordInTitle'] ) {
                $lines[] = "- Odak kelime başlıkta geçmeli";
            }
            if ( ! empty( $ruleSet['requireKeywordInSeoTitle'] ) ) {
                $lines[] = "- Odak kelime SEO başlıkta geçmeli";
            }
            if ( $ruleSet['requireKeywordInFirstParagraph'] ) {
                $lines[] = "- Odak kelime ilk paragrafta geçmeli";
            }
            if ( $ruleSet['requireKeywordInMetaDescription'] ) {
                $lines[] = "- Odak kelime meta açıklamada geçmeli";
            }
            if ( $ruleSet['requireKeywordInExcerpt'] ) {
                $lines[] = "- Odak kelime özetin içinde geçmeli";
            }
            if ( ! empty( $ruleSet['requireKeywordInSubheading'] ) ) {
                $lines[] = "- Odak kelime en az bir H2 ara başlıkta geçmeli";
            }
            if ( ! empty( $ruleSet['requireKeywordInSlug'] ) ) {
                $lines[] = "- Odak kelime slug içinde geçmeli";
            }
        }

        $lines[] = "- Özet (excerpt) maksimum {$ruleSet['excerptMaxChars']} karakter olmalı";

        if ( ! empty( $ruleSet['requiredPhrases'] ) ) {
            $reqList = implode( '; ', array_slice( $ruleSet['requiredPhrases'], 0, 25 ) );
            $lines[] = "- Şu ifadeler başlık veya gövdede mutlaka yer almalı: {$reqList}";
        }

        if ( $ruleSet['keywordDensityMin'] > 0 ) {
            $lines[] = "- Keyword yoğunluğu %{$ruleSet['keywordDensityMin']}-{$ruleSet['keywordDensityMax']} aralığında olmalı";
        }

        if ( ! empty( $ruleSet['forbiddenPhrases'] ) ) {
            $phraseList = implode( ', ', array_slice( $ruleSet['forbiddenPhrases'], 0, 40 ) );
            $lines[]    = "- Şu ifadeler kullanılmamalı (tam liste — gövde/başlıkta yok): {$phraseList}";
        }

        if ( ! empty( $ruleSet['forbiddenSentenceStarts'] ) ) {
            $startList = implode( ', ', array_slice( $ruleSet['forbiddenSentenceStarts'], 0, 20 ) );
            $lines[]   = "- Paragraflar şu kalıplarla başlamamalı: {$startList}";
        }

        return implode( "\n", $lines );
    }

    /**
     * AI çıktısını SEO kurallarına uygun şekilde düzenler.
     */
    public function enforce( array $result, string $keyword, array $ruleSet, string $sourceContent = '' ): array {
        $forbidden = $ruleSet['forbiddenPhrases'] ?? [];
        $starts    = $ruleSet['forbiddenSentenceStarts'] ?? [];
        $excerptMax = (int) ( $ruleSet['excerptMaxChars'] ?? 200 );

        // Başlık
        $title = $result['title'] ?? '';
        $title = $this->normalizeEditorialText( $title );
        $title = $this->removeForbiddenPhrases( $title, $forbidden );
        $title = $this->stripForbiddenSentenceStartsPlain( $title, $starts );
        if ( ! empty( $ruleSet['requireKeywordInTitle'] ) && ! empty( $keyword ) ) {
            $title = $this->ensureKeywordInText( $title, $keyword, ' - ' );
        }
        $title             = $this->fitTextToLimit( $title, $ruleSet['titleMaxChars'], $keyword );
        $result['title'] = $title;

        // Excerpt
        $excerpt = $result['excerpt'] ?? '';
        $excerpt = $this->normalizeEditorialText( $excerpt );
        $excerpt = $this->removeForbiddenPhrases( $excerpt, $forbidden );
        $excerpt = $this->stripForbiddenSentenceStartsPlain( $excerpt, $starts );
        if ( ! empty( $ruleSet['requireKeywordInExcerpt'] ) && ! empty( $keyword ) ) {
            $excerpt = $this->ensureKeywordInText( $excerpt, $keyword, ': ' );
        }
        if ( mb_strlen( $excerpt ) > $excerptMax ) {
            $excerpt = $this->snapToSentence( $excerpt, $excerptMax );
        }
        $result['excerpt'] = $excerpt;

        // Body
        $body = $result['body'] ?? '';
        $body = $this->cleanBodyParagraphText( $body );
        $body = $this->normalizeEditorialText( $body );
        $body = $this->removeForbiddenPhrases( $body, $forbidden );
        $body = $this->stripForbiddenStartsFromSimpleParagraphs( $body, $starts );

        $blocks = $this->splitBodyBlocks( $body );
        $blocks = $this->ensureSubheadings( $blocks, $ruleSet['subheadingCount'] );
        if ( ! empty( $ruleSet['requireKeywordInSubheading'] ) && ! empty( $keyword ) ) {
            $blocks = $this->ensureKeywordInSubheadings( $blocks, $keyword );
        }
        $blocks = $this->ensureKeywordInFirstParagraph( $blocks, $keyword, ! empty( $ruleSet['requireKeywordInFirstParagraph'] ) );
        $blocks = $this->ensureMinParagraphs( $blocks, $ruleSet['bodyMinParagraphs'] );
        $blocks = $this->dedupeBodyBlocks( $blocks );
        $body   = $this->joinBodyBlocks( $blocks );

        if ( $ruleSet['keywordDensityMin'] > 0 && ! empty( $keyword ) ) {
            $body = $this->ensureKeywordDensity( $body, $keyword, $ruleSet );
        }
        if ( ! empty( $keyword ) && (int) ( $ruleSet['keywordDensityMax'] ?? 0 ) > 0 ) {
            $body = $this->trimKeywordDensityMax( $body, $keyword, (int) $ruleSet['keywordDensityMax'] );
        }

        $body          = $this->stripBodyFooterArtifacts( $body );
        $result['body'] = $body;

        // SEO Başlık
        $seoTitle = $result['seoTitle'] ?? $title;
        $seoTitle = $this->normalizeEditorialText( $seoTitle );
        $seoTitle = $this->removeForbiddenPhrases( $seoTitle, $forbidden );
        $seoTitle = $this->stripForbiddenSentenceStartsPlain( $seoTitle, $starts );
        if ( ! empty( $ruleSet['requireKeywordInSeoTitle'] ) && ! empty( $keyword ) ) {
            $seoTitle = $this->ensureKeywordInText( $seoTitle, $keyword, ' - ' );
        }
        $seoTitle           = $this->fitTextToLimit( $seoTitle, $ruleSet['seoTitleMaxChars'], $keyword );
        $result['seoTitle'] = $seoTitle;

        // Slug
        $slug = $result['slug'] ?? sanitize_title( $title );
        $slug = sanitize_title( $slug );
        if ( ! empty( $ruleSet['requireKeywordInSlug'] ) && ! empty( $keyword ) ) {
            $slug = $this->ensureKeywordInSlug( $slug, $keyword );
        }
        if ( mb_strlen( $slug ) > $ruleSet['slugMaxChars'] ) {
            $slug = mb_substr( $slug, 0, $ruleSet['slugMaxChars'] );
            $slug = rtrim( $slug, '-' );
        }
        $slug           = preg_replace( '/^-+/', '', $slug );
        $result['slug'] = $slug;

        // Meta açıklama
        $meta = $result['metaDescription'] ?? $excerpt;
        $meta = $this->normalizeEditorialText( $meta );
        $meta = $this->removeForbiddenPhrases( $meta, $forbidden );
        $meta = $this->stripForbiddenSentenceStartsPlain( $meta, $starts );
        if ( ! empty( $ruleSet['requireKeywordInMetaDescription'] ) && ! empty( $keyword ) ) {
            $meta = $this->ensureKeywordInText( $meta, $keyword, ': ' );
        }
        $meta = $this->fitTextToLimit( $meta, $ruleSet['metaDescriptionMaxChars'], $keyword );
        $meta = $this->snapToSentence( $meta, $ruleSet['metaDescriptionMaxChars'] );
        $result['metaDescription'] = $meta;

        // Focus keyword alanını güncelle
        if ( ! empty( $keyword ) ) {
            $result['focusKeyword'] = $keyword;
        }

        // Kaynak sadakat kontrolü
        if ( ! empty( $sourceContent ) ) {
            $result = $this->validateSourceFidelity( $result, $sourceContent );
        }

        return $result;
    }

    /**
     * SEO skorunu hesaplar.
     *
     * @return array ['score'=>int, 'passed'=>[], 'failed'=>[]]
     */
    public function calculateSeoScore( array $result, string $keyword, array $ruleSet ): array {
        $passed = [];
        $failed = [];

        $title           = $result['title']           ?? '';
        $seoTitle        = $result['seoTitle']        ?? '';
        $meta            = $result['metaDescription'] ?? '';
        $body            = $result['body']            ?? '';
        $slug            = $result['slug']            ?? '';
        $excerpt         = $result['excerpt']         ?? '';
        $forbiddenPhrases = $ruleSet['forbiddenPhrases'] ?? [];

        // 1. Keyword başlıkta
        if ( ! empty( $keyword ) && ! empty( $ruleSet['requireKeywordInTitle'] ) ) {
            if ( mb_stripos( $title, $keyword ) !== false ) {
                $passed[] = 'Keyword başlıkta mevcut';
            } else {
                $failed[] = 'Keyword başlıkta bulunamadı';
            }
        }

        // 2. Keyword SEO başlıkta
        if ( ! empty( $keyword ) && ! empty( $ruleSet['requireKeywordInSeoTitle'] ) ) {
            if ( mb_stripos( $seoTitle, $keyword ) !== false ) {
                $passed[] = 'Keyword SEO başlıkta mevcut';
            } else {
                $failed[] = 'Keyword SEO başlıkta bulunamadı';
            }
        }

        // 3. Keyword meta açıklamada
        if ( ! empty( $keyword ) && ! empty( $ruleSet['requireKeywordInMetaDescription'] ) ) {
            if ( mb_stripos( $meta, $keyword ) !== false ) {
                $passed[] = 'Keyword meta açıklamada mevcut';
            } else {
                $failed[] = 'Keyword meta açıklamada bulunamadı';
            }
        }

        // 4. Keyword ilk paragrafta
        if ( ! empty( $keyword ) && ! empty( $ruleSet['requireKeywordInFirstParagraph'] ) ) {
            $firstP = '';
            if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $body, $pm ) ) {
                $firstP = strip_tags( $pm[1] );
            }
            if ( mb_stripos( $firstP, $keyword ) !== false ) {
                $passed[] = 'Keyword ilk paragrafta mevcut';
            } else {
                $failed[] = 'Keyword ilk paragrafta bulunamadı';
            }
        }

        // 4b. Keyword özette
        if ( ! empty( $keyword ) && ! empty( $ruleSet['requireKeywordInExcerpt'] ) ) {
            if ( mb_stripos( $excerpt, $keyword ) !== false ) {
                $passed[] = 'Keyword özette mevcut';
            } else {
                $failed[] = 'Keyword özette bulunamadı';
            }
        }

        // 4c. Keyword en az bir H2 içinde
        if ( ! empty( $keyword ) && ! empty( $ruleSet['requireKeywordInSubheading'] ) ) {
            preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $body, $hm );
            $kwInH2 = false;
            foreach ( $hm[1] ?? [] as $h2Inner ) {
                if ( mb_stripos( strip_tags( $h2Inner ), $keyword ) !== false ) {
                    $kwInH2 = true;
                    break;
                }
            }
            if ( $kwInH2 ) {
                $passed[] = 'Keyword ara başlıkta mevcut';
            } else {
                $failed[] = 'Keyword ara başlıkta bulunamadı';
            }
        }

        // 5. Başlık karakter limiti
        if ( mb_strlen( $title ) <= $ruleSet['titleMaxChars'] ) {
            $passed[] = "Başlık {$ruleSet['titleMaxChars']} karakter limitinde";
        } else {
            $failed[] = "Başlık {$ruleSet['titleMaxChars']} karakter limitini aşıyor";
        }

        // 6. SEO başlık karakter limiti
        if ( mb_strlen( $seoTitle ) <= $ruleSet['seoTitleMaxChars'] ) {
            $passed[] = "SEO başlık {$ruleSet['seoTitleMaxChars']} karakter limitinde";
        } else {
            $failed[] = "SEO başlık {$ruleSet['seoTitleMaxChars']} karakter limitini aşıyor";
        }

        // 7. Meta açıklama karakter limiti
        if ( mb_strlen( $meta ) <= $ruleSet['metaDescriptionMaxChars'] ) {
            $passed[] = "Meta açıklama {$ruleSet['metaDescriptionMaxChars']} karakter limitinde";
        } else {
            $failed[] = "Meta açıklama {$ruleSet['metaDescriptionMaxChars']} karakter limitini aşıyor";
        }

        // 7b. Özet karakter limiti
        $excerptMax = (int) ( $ruleSet['excerptMaxChars'] ?? 200 );
        if ( mb_strlen( $excerpt ) <= $excerptMax ) {
            $passed[] = "Özet {$excerptMax} karakter limitinde";
        } else {
            $failed[] = "Özet {$excerptMax} karakter limitini aşıyor";
        }

        // 8. Minimum paragraf sayısı
        $pCount = preg_match_all( '/<p[^>]*>/', $body );
        if ( $pCount >= $ruleSet['bodyMinParagraphs'] ) {
            $passed[] = "Minimum {$ruleSet['bodyMinParagraphs']} paragraf sağlandı ({$pCount} adet)";
        } else {
            $failed[] = "Minimum {$ruleSet['bodyMinParagraphs']} paragraf gerekiyor ({$pCount} adet var)";
        }

        // 9. H2 sayısı
        $h2Count = preg_match_all( '/<h2[^>]*>/', $body );
        if ( $ruleSet['subheadingCount'] === 0 || $h2Count >= $ruleSet['subheadingCount'] ) {
            $passed[] = "H2 sayısı uygun ({$h2Count} adet)";
        } else {
            $failed[] = "H2 sayısı yetersiz ({$h2Count}/{$ruleSet['subheadingCount']})";
        }

        // 10. Yasak ifade yok (başlık + SEO + meta + özet + gövde)
        $hasForbidden = false;
        $scanLower    = mb_strtolower( $title . ' ' . $seoTitle . ' ' . $meta . ' ' . $excerpt . ' ' . $body );
        foreach ( $forbiddenPhrases as $phrase ) {
            $ph = mb_strtolower( trim( $phrase ) );
            if ( $ph !== '' && mb_stripos( $scanLower, $ph ) !== false ) {
                $hasForbidden = true;
                break;
            }
        }
        if ( ! $hasForbidden ) {
            $passed[] = 'Yasak ifade içermiyor';
        } else {
            $failed[] = 'Yasak ifade içeriyor';
        }

        // 10b. Zorunlu ifadeler
        foreach ( $ruleSet['requiredPhrases'] ?? [] as $req ) {
            $req = trim( $req );
            if ( $req === '' ) {
                continue;
            }
            $blob = $title . ' ' . $excerpt . ' ' . strip_tags( $body );
            if ( mb_stripos( $blob, $req ) !== false ) {
                $passed[] = 'Zorunlu ifade mevcut: ' . $req;
            } else {
                $failed[] = 'Zorunlu ifade eksik: ' . $req;
            }
        }

        // 10c. Keyword yoğunluğu üst sınır
        if ( ! empty( $keyword ) && (int) ( $ruleSet['keywordDensityMax'] ?? 0 ) > 0 ) {
            $plain = strip_tags( $body );
            $words = preg_split( '/\s+/u', trim( $plain ), -1, PREG_SPLIT_NO_EMPTY );
            $wc    = count( $words );
            if ( $wc > 0 ) {
                $occ = substr_count( mb_strtolower( $plain ), mb_strtolower( $keyword ) );
                $pct = ( $occ / $wc ) * 100;
                if ( $pct <= (float) $ruleSet['keywordDensityMax'] ) {
                    $passed[] = 'Keyword yoğunluğu üst sınır içinde (%' . round( $pct, 2 ) . ')';
                } else {
                    $failed[] = 'Keyword yoğunluğu çok yüksek (%' . round( $pct, 2 ) . ', üst sınır %' . $ruleSet['keywordDensityMax'] . ')';
                }
            }
        }

        // 11. Slug keyword içeriyor
        if ( ! empty( $keyword ) && ! empty( $ruleSet['requireKeywordInSlug'] ) ) {
            $keywordSlug = sanitize_title( $keyword );
            if ( strpos( $slug, $keywordSlug ) !== false ) {
                $passed[] = 'Slug keyword içeriyor';
            } else {
                $failed[] = 'Slug keyword içermiyor';
            }
        }

        // 12. Meta açıklama boş değil
        if ( ! empty( trim( $meta ) ) ) {
            $passed[] = 'Meta açıklama dolu';
        } else {
            $failed[] = 'Meta açıklama boş';
        }

        $total = count( $passed ) + count( $failed );
        $score = $total > 0 ? (int) round( count( $passed ) / $total * 100 ) : 0;

        return [
            'score'  => $score,
            'passed' => $passed,
            'failed' => $failed,
        ];
    }

    /**
     * SEO ihlallerini toplar.
     */
    public function collectViolations( array $result, string $keyword, array $ruleSet, string $sourceContent = '' ): array {
        $scoreData  = $this->calculateSeoScore( $result, $keyword, $ruleSet );
        $violations = $scoreData['failed'];

        // Ek kontroller
        $title = $result['title'] ?? '';
        $body  = $result['body']  ?? '';

        // Başlıkta : karakteri
        if ( strpos( $title, ':' ) !== false ) {
            $violations[] = 'Başlıkta ":" karakteri var';
        }

        // Başlıkta | karakteri
        if ( strpos( $title, '|' ) !== false ) {
            $violations[] = 'Başlıkta "|" karakteri var';
        }

        // Tekrarlayan alt başlıklar
        preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $body, $h2Matches );
        if ( ! empty( $h2Matches[1] ) ) {
            $h2Texts = array_map( 'strip_tags', $h2Matches[1] );
            if ( count( $h2Texts ) !== count( array_unique( $h2Texts ) ) ) {
                $violations[] = 'Tekrarlayan H2 başlık var';
            }
        }

        return $violations;
    }

    /**
     * Repair pass için düzeltme prompt'u oluşturur.
     */
    public function buildRepairPrompt( array $result, string $sourceTitle, string $sourceContent, string $keyword, string $seoRulesText, array $ruleSet, array $violations ): string {
        $violationList = implode( "\n- ", $violations );
        $currentJson   = wp_json_encode( $result, JSON_UNESCAPED_UNICODE );
        $sourceSnippet = mb_substr( $sourceContent, 0, 5000 );
        $panelBlock    = '';
        $trimPanel     = trim( $seoRulesText );
        if ( $trimPanel !== '' ) {
            $panelBlock = "\n=== Panel SEO kuralları (tam metin — öncelikli, eksiksiz uygula) ===\n{$trimPanel}\n";
        }

        return <<<PROMPT
Aşağıdaki JSON içeriğinde SEO ihlalleri tespit edildi. Yalnızca ihlalleri gider; kaynaktan çıkarılmaması gereken bilgileri silme.

=== Mevcut JSON ===
{$currentJson}

=== SEO İhlalleri (düzelt) ===
- {$violationList}
{$panelBlock}
=== Orijinal Kaynak ===
Başlık: {$sourceTitle}
Metin: {$sourceSnippet}

=== Dil ===
Çıktı tamamen Türkçe olmalı; Almanca/İngilizce kelime bırakma.

=== Odak Kelime ===
{$keyword}

Yanıt YALNIZCA düzeltilmiş JSON nesnesi olmalı. Açıklama ekleme.
PROMPT;
    }

    // ─── Yardımcı metodlar ───────────────────────────────────────────────────

    /**
     * Metni editoryal standartlara göre normalleştirir.
     */
    public function normalizeEditorialText( string $text ): string {
        // Windows satır sonlarını normalleştir
        $text = str_replace( "\r\n", "\n", $text );
        $text = str_replace( "\r", "\n", $text );

        // Çoklu boşluk → tek boşluk (satır içi)
        $text = preg_replace( '/[^\S\n]+/', ' ', $text );

        // TÜMÜ BÜYÜK sözcükleri normalleştir
        $words  = preg_split( '/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
        $result = [];
        foreach ( $words as $word ) {
            if ( preg_match( '/^\s+$/', $word ) ) {
                $result[] = $word;
                continue;
            }
            $clean = strip_tags( $word );
            // Korunan kısaltmalarsa dokunma
            if ( in_array( $clean, self::PROTECTED_ABBR, true ) ) {
                $result[] = $word;
                continue;
            }
            // Tüm harfleri büyük ve 3+ karakter ise küçülT
            if ( mb_strlen( $clean ) >= 3 && $clean === mb_strtoupper( $clean, 'UTF-8' ) && preg_match( '/[a-zA-ZğşıöüçĞŞİÖÜÇ]{3,}/', $clean ) ) {
                $result[] = $this->deScreamTurkish( $word );
            } else {
                $result[] = $word;
            }
        }
        $text = implode( '', $result );

        // Cümle başı büyük harf
        $text = $this->normalizeSentenceCase( $text );

        return trim( $text );
    }

    /**
     * Tümü büyük harf sözcüğü küçük harfe çevirir (Türkçe uyumlu).
     */
    public function deScreamTurkish( string $word ): string {
        $clean = strip_tags( $word );
        if ( in_array( $clean, self::PROTECTED_ABBR, true ) ) {
            return $word;
        }
        $lower = mb_strtolower( $clean, 'UTF-8' );
        // İlk harfi büyük yap
        return mb_strtoupper( mb_substr( $lower, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $lower, 1, null, 'UTF-8' );
    }

    /**
     * Her cümlenin ilk harfini büyük yapar.
     */
    public function normalizeSentenceCase( string $text ): string {
        return preg_replace_callback(
            '/([.!?]\s+)([a-zğşıöüç])/u',
            function ( $m ) {
                return $m[1] . mb_strtoupper( $m[2], 'UTF-8' );
            },
            $text
        );
    }

    /**
     * Yasaklı cümle başı kalıplarını düz metinden tek katman soyar (örn. "Bu durum..." ile başlıyorsa kes).
     */
    public function stripForbiddenSentenceStartsPlain( string $text, array $starts ): string {
        $text = trim( $text );
        if ( $text === '' || empty( $starts ) ) {
            return $text;
        }

        $sorted = array_values( array_filter( array_map( 'trim', $starts ) ) );
        usort( $sorted, fn( $a, $b ) => mb_strlen( $b ) <=> mb_strlen( $a ) );

        foreach ( $sorted as $start ) {
            if ( $start === '' ) {
                continue;
            }
            if ( mb_stripos( $text, $start ) === 0 ) {
                $text = trim( mb_substr( $text, mb_strlen( $start ) ) );
                if ( $text !== '' ) {
                    $text = mb_strtoupper( mb_substr( $text, 0, 1 ), 'UTF-8' ) . mb_substr( $text, 1, null, 'UTF-8' );
                }
                break;
            }
        }

        return $text;
    }

    /**
     * Basit &lt;p&gt; düz metin paragraflarında yasak cümle başlarını düzeltir (iç içe HTML yoksa).
     */
    public function stripForbiddenStartsFromSimpleParagraphs( string $html, array $starts ): string {
        if ( empty( $starts ) ) {
            return $html;
        }

        return preg_replace_callback(
            '/<p([^>]*)>\s*([^<]*)\s*<\/p>/iu',
            function ( array $m ) use ( $starts ) {
                $inner = $this->stripForbiddenSentenceStartsPlain( trim( $m[2] ), $starts );
                return '<p' . $m[1] . '>' . $inner . '</p>';
            },
            $html
        );
    }

    /**
     * Hisse kodları yoksa ilk H2 içinde odak kelimeyi garanti eder.
     */
    public function ensureKeywordInSubheadings( array $blocks, string $keyword ): array {
        if ( empty( $keyword ) ) {
            return $blocks;
        }

        foreach ( $blocks as $block ) {
            if ( $block['type'] === 'h2' && mb_stripos( strip_tags( $block['content'] ), $keyword ) !== false ) {
                return $blocks;
            }
        }

        foreach ( $blocks as $i => $block ) {
            if ( $block['type'] === 'h2' ) {
                $inner = strip_tags( $block['content'] );
                $blocks[ $i ]['content'] = '<h2>' . esc_html( trim( $keyword . ' — ' . $inner ) ) . '</h2>';
                break;
            }
        }

        return $blocks;
    }

    /**
     * Keyword yoğunluğu üst sınırın üzerindeyse fazla geçişleri metnin sonundan kısıtlar (HTML içinde son eşleşmelerden siler).
     */
    public function trimKeywordDensityMax( string $body, string $keyword, float $maxPercent ): string {
        if ( empty( $keyword ) || $maxPercent <= 0 ) {
            return $body;
        }

        $plain = strip_tags( $body );
        $words = preg_split( '/\s+/u', trim( $plain ), -1, PREG_SPLIT_NO_EMPTY );
        $wc    = count( $words );
        if ( $wc < 5 ) {
            return $body;
        }

        $plainLower = mb_strtolower( $plain );
        $kwLower    = mb_strtolower( $keyword );
        $occ        = substr_count( $plainLower, $kwLower );
        $maxKw      = max( 1, (int) floor( $wc * ( $maxPercent / 100 ) ) );

        if ( $occ <= $maxKw ) {
            return $body;
        }

        $remove = $occ - $maxKw;
        $len    = mb_strlen( $keyword );

        while ( $remove > 0 ) {
            $lowerHay = mb_strtolower( $body );
            $pos      = mb_strrpos( $lowerHay, $kwLower );
            if ( $pos === false ) {
                break;
            }
            $body = mb_substr( $body, 0, $pos ) . mb_substr( $body, $pos + $len );
            $remove--;
        }

        return $body;
    }

    /**
     * Yasak ifadeleri metinden kaldırır.
     */
    public function removeForbiddenPhrases( string $text, array $phrases ): string {
        foreach ( $phrases as $phrase ) {
            $text = preg_replace( '/' . preg_quote( $phrase, '/' ) . '/iu', '', $text );
        }
        // Oluşan çift boşlukları temizle
        $text = preg_replace( '/[^\S\n]{2,}/', ' ', $text );
        return trim( $text );
    }

    /**
     * Keyword metinde yoksa ekler.
     */
    public function ensureKeywordInText( string $text, string $keyword, string $separator ): string {
        if ( empty( $keyword ) || mb_stripos( $text, $keyword ) !== false ) {
            return $text;
        }
        return $text . $separator . $keyword;
    }

    /**
     * Metni karakter limitine sığdırır, keyword'ü korur.
     */
    public function fitTextToLimit( string $text, int $limit, string $keyword ): string {
        if ( mb_strlen( $text ) <= $limit ) {
            return $text;
        }

        // Tam sözcük sınırında kes
        $trimmed = mb_substr( $text, 0, $limit );
        $lastSpace = mb_strrpos( $trimmed, ' ' );
        if ( $lastSpace !== false ) {
            $trimmed = mb_substr( $trimmed, 0, $lastSpace );
        }

        // Keyword kaybolacaksa yeniden düzenle
        if ( ! empty( $keyword ) && mb_stripos( $trimmed, $keyword ) === false ) {
            $kwLen       = mb_strlen( $keyword );
            $available   = $limit - $kwLen - 3; // " - " için
            $shortText   = mb_substr( $text, 0, $available );
            $lastSpace2  = mb_strrpos( $shortText, ' ' );
            if ( $lastSpace2 !== false ) {
                $shortText = mb_substr( $shortText, 0, $lastSpace2 );
            }
            return $keyword . ' - ' . $shortText;
        }

        return $trimmed;
    }

    /**
     * Body HTML'ini bloklara ayırır.
     */
    public function splitBodyBlocks( string $body ): array {
        $blocks  = [];
        $pattern = '/(<h2[^>]*>.*?<\/h2>|<p[^>]*>.*?<\/p>|<ul[^>]*>.*?<\/ul>|<ol[^>]*>.*?<\/ol>)/is';

        preg_match_all( $pattern, $body, $matches );

        foreach ( $matches[1] as $match ) {
            if ( preg_match( '/^<h2/i', $match ) ) {
                $blocks[] = [ 'type' => 'h2',   'content' => $match ];
            } elseif ( preg_match( '/^<(ul|ol)/i', $match ) ) {
                $blocks[] = [ 'type' => 'list',  'content' => $match ];
            } else {
                $blocks[] = [ 'type' => 'p',     'content' => $match ];
            }
        }

        // Hiç blok bulunamazsa düz metni p'ye sar
        if ( empty( $blocks ) && ! empty( trim( strip_tags( $body ) ) ) ) {
            $paragraphs = preg_split( '/\n{2,}/', trim( $body ) );
            foreach ( $paragraphs as $para ) {
                $para = trim( $para );
                if ( ! empty( $para ) ) {
                    $blocks[] = [ 'type' => 'p', 'content' => '<p>' . esc_html( $para ) . '</p>' ];
                }
            }
        }

        return $blocks;
    }

    /**
     * Ardışık veya neredeyse aynı paragrafları kaldırır (AI tekrarı için).
     */
    public function dedupeBodyBlocks( array $blocks ): array {
        $out       = [];
        $prevPlain = '';

        foreach ( $blocks as $block ) {
            if ( $block['type'] !== 'p' ) {
                $out[] = $block;
                $prevPlain = '';
                continue;
            }

            $plain = strtolower( preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( $block['content'] ?? '' ) ) ) );
            if ( $plain === '' ) {
                continue;
            }

            $duplicate = false;
            if ( $prevPlain !== '' ) {
                if ( $plain === $prevPlain ) {
                    $duplicate = true;
                } else {
                    similar_text( $plain, $prevPlain, $pct );
                    if ( $pct > 88.0 ) {
                        $duplicate = true;
                    }
                }
            }

            if ( $duplicate ) {
                continue;
            }

            $out[]       = $block;
            $prevPlain = $plain;
        }

        return $out;
    }

    /**
     * Blokları HTML string'e birleştirir.
     */
    public function joinBodyBlocks( array $blocks ): string {
        return implode( "\n", array_column( $blocks, 'content' ) );
    }

    /**
     * H2 sayısını hedef sayıya göre ayarlar.
     */
    public function ensureSubheadings( array $blocks, int $target ): array {
        $h2Count = count( array_filter( $blocks, fn( $b ) => $b['type'] === 'h2' ) );

        if ( $target === 0 ) {
            // H2 olmamalı: tüm H2'leri P'ye çevir
            foreach ( $blocks as &$block ) {
                if ( $block['type'] === 'h2' ) {
                    $inner         = strip_tags( $block['content'] );
                    $block['type'] = 'p';
                    $block['content'] = '<p>' . esc_html( $inner ) . '</p>';
                }
            }
            return $blocks;
        }

        // Eksik H2 ekle: uzun paragraflardan üret
        if ( $h2Count < $target ) {
            $needed = $target - $h2Count;
            $newBlocks = [];
            $added = 0;

            foreach ( $blocks as $block ) {
                if ( $added < $needed && $block['type'] === 'p' ) {
                    $text      = strip_tags( $block['content'] );
                    $firstSent = preg_split( '/(?<=[.!?])\s+/', $text, 2 )[0] ?? '';
                    if ( mb_strlen( $firstSent ) > 10 && mb_strlen( $firstSent ) < 80 ) {
                        $newBlocks[] = [ 'type' => 'h2', 'content' => '<h2>' . esc_html( $firstSent ) . '</h2>' ];
                        $added++;
                    }
                }
                $newBlocks[] = $block;
            }
            return $newBlocks;
        }

        // Fazla H2 kaldır
        if ( $h2Count > $target ) {
            $toRemove = $h2Count - $target;
            $removed  = 0;
            $result   = [];

            foreach ( array_reverse( $blocks ) as $block ) {
                if ( $block['type'] === 'h2' && $removed < $toRemove ) {
                    $removed++;
                    continue;
                }
                $result[] = $block;
            }
            return array_reverse( $result );
        }

        return $blocks;
    }

    /**
     * Minimum paragraf sayısını sağlar; uzun paragrafları cümle sınırlarından böler.
     */
    public function ensureMinParagraphs( array $blocks, int $min ): array {
        $maxPasses = 12;

        for ( $pass = 0; $pass < $maxPasses; $pass++ ) {
            $pCount = count( array_filter( $blocks, fn( $b ) => $b['type'] === 'p' ) );
            if ( $pCount >= $min ) {
                return $blocks;
            }

            $needed = $min - $pCount;
            $newBlocks = [];
            $split     = 0;

            foreach ( $blocks as $block ) {
                if ( $block['type'] !== 'p' || $split >= $needed ) {
                    $newBlocks[] = $block;
                    continue;
                }

                $text       = strip_tags( $block['content'] );
                $sentences  = preg_split( '/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

                if ( is_array( $sentences ) && count( $sentences ) >= 3 ) {
                    $half    = (int) ceil( count( $sentences ) / 2 );
                    $first   = implode( ' ', array_slice( $sentences, 0, $half ) );
                    $second  = implode( ' ', array_slice( $sentences, $half ) );
                    $newBlocks[] = [ 'type' => 'p', 'content' => '<p>' . esc_html( $first ) . '</p>' ];
                    $newBlocks[] = [ 'type' => 'p', 'content' => '<p>' . esc_html( $second ) . '</p>' ];
                    $split++;
                } elseif ( is_array( $sentences ) && count( $sentences ) >= 2 ) {
                    $first  = $sentences[0];
                    $second = implode( ' ', array_slice( $sentences, 1 ) );
                    $newBlocks[] = [ 'type' => 'p', 'content' => '<p>' . esc_html( $first ) . '</p>' ];
                    $newBlocks[] = [ 'type' => 'p', 'content' => '<p>' . esc_html( $second ) . '</p>' ];
                    $split++;
                } else {
                    $newBlocks[] = $block;
                }
            }

            $blocks = $newBlocks;
            if ( $split === 0 ) {
                break;
            }
        }

        return $blocks;
    }

    /**
     * İlk paragrafta keyword yoksa ekler (isteğe bağlı).
     */
    public function ensureKeywordInFirstParagraph( array $blocks, string $keyword, bool $required = true ): array {
        if ( empty( $keyword ) || ! $required ) {
            return $blocks;
        }

        foreach ( $blocks as &$block ) {
            if ( $block['type'] !== 'p' ) {
                continue;
            }
            $text = strip_tags( $block['content'] );
            if ( mb_stripos( $text, $keyword ) === false ) {
                $text            = $keyword . ': ' . $text;
                $block['content'] = '<p>' . esc_html( $text ) . '</p>';
            }
            break;
        }

        return $blocks;
    }

    /**
     * Body HTML'ini temizler, <br> zincirleri paragraflaştırır.
     */
    public function cleanBodyParagraphText( string $text ): string {
        // Ardışık <br> taglarını paragraf yap
        $text = preg_replace( '/(<br\s*\/?>\s*){2,}/i', '</p><p>', $text );

        // Boş paragrafları kaldır
        $text = preg_replace( '/<p[^>]*>\s*<\/p>/i', '', $text );

        return $text;
    }

    /**
     * Body sonundaki yapay/tekrar blokları siler.
     */
    public function stripBodyFooterArtifacts( string $text ): string {
        $stopPatterns = [
            '/<p[^>]*>\s*Bu haber[^<]*<\/p>/i',
            '/<p[^>]*>\s*Kaynak:[^<]*<\/p>/i',
            '/<p[^>]*>\s*Yasal Uyarı[^<]*<\/p>/i',
        ];

        foreach ( $stopPatterns as $pattern ) {
            $text = preg_replace( $pattern, '', $text );
        }

        return trim( $text );
    }

    /**
     * Keyword yoğunluğunu ayarlar.
     */
    public function ensureKeywordDensity( string $body, string $keyword, array $ruleSet ): string {
        if ( empty( $keyword ) || $ruleSet['keywordDensityMin'] <= 0 ) {
            return $body;
        }

        $plainText  = strip_tags( $body );
        $wordCount  = str_word_count( $plainText );
        $kwCount    = substr_count( mb_strtolower( $plainText ), mb_strtolower( $keyword ) );
        $density    = $wordCount > 0 ? ( $kwCount / $wordCount ) * 100 : 0;

        // Hedef yoğunluğun altındaysa son paragrafta keyword ekle
        if ( $density < $ruleSet['keywordDensityMin'] ) {
            $body .= '<p>' . esc_html( $keyword ) . ' konusunda en güncel gelişmeleri takip etmeye devam ediyoruz.</p>';
        }

        return $body;
    }

    /**
     * Slug'a keyword ekler.
     */
    public function ensureKeywordInSlug( string $slug, string $keyword ): string {
        if ( empty( $keyword ) ) {
            return $slug;
        }

        $keywordSlug = sanitize_title( $keyword );
        if ( strpos( $slug, $keywordSlug ) === false ) {
            $slug = $keywordSlug . '-' . $slug;
        }

        return $slug;
    }

    /**
     * Metni cümle sınırında keser.
     */
    public function snapToSentence( string $text, int $limit ): string {
        if ( mb_strlen( $text ) <= $limit ) {
            return $text;
        }

        $trimmed = mb_substr( $text, 0, $limit );

        // Son cümle sonunu bul
        $lastDot = max(
            mb_strrpos( $trimmed, '.' ),
            mb_strrpos( $trimmed, '!' ),
            mb_strrpos( $trimmed, '?' )
        );

        if ( $lastDot !== false && $lastDot > $limit * 0.5 ) {
            return mb_substr( $trimmed, 0, $lastDot + 1 );
        }

        // Cümle bulunamazsa sözcük sınırında kes
        $lastSpace = mb_strrpos( $trimmed, ' ' );
        return $lastSpace !== false ? mb_substr( $trimmed, 0, $lastSpace ) : $trimmed;
    }

    /**
     * Kaynak sadakat kontrolü yapar - hallüsinasyon tespiti
     */
    private function validateSourceFidelity( array $result, string $sourceContent ): array {
        $body = $result['body'] ?? '';
        $title = $result['title'] ?? '';
        
        if ( empty( $body ) && empty( $title ) ) {
            return $result;
        }

        $sourceText = wp_strip_all_tags( $sourceContent );
        $generatedText = wp_strip_all_tags( $body . ' ' . $title );

        // Sayı hallüsinasyon kontrolü
        $sourceNumbers = $this->extractNumbers( $sourceText );
        $generatedNumbers = $this->extractNumbers( $generatedText );
        
        // Kaynak metinde olmayan sayılar varsa uyarı ekle
        $hallucinations = [];
        foreach ( $generatedNumbers as $num ) {
            if ( ! in_array( $num, $sourceNumbers ) && $num > 100 ) { // Küçük sayılar göz ardı
                $hallucinations[] = "Kaynakta olmayan rakam: $num";
            }
        }

        // Büyük farklılık tespiti (kaynak metin çok kısaltılmışsa)
        $sourceWordCount = str_word_count( $sourceText );
        $bodyWordCount = str_word_count( wp_strip_all_tags( $body ) );
        
        if ( $sourceWordCount > 100 && $bodyWordCount < ( $sourceWordCount * 0.3 ) ) {
            $hallucinations[] = "İçerik aşırı kısaltılmış - kaynak bilgileri eksik olabilir";
        }

        // Hallüsinasyon tespit edilirse meta alanına not ekle
        if ( ! empty( $hallucinations ) ) {
            $result['_borsatek_fidelity_warnings'] = $hallucinations;
        }

        return $result;
    }

    /**
     * Metin içindeki sayıları çıkarır
     */
    private function extractNumbers( string $text ): array {
        preg_match_all( '/\b\d+(?:[.,]\d+)*\b/', $text, $matches );
        return array_map( function( $num ) {
            return (float) str_replace( ',', '.', $num );
        }, $matches[0] ?? [] );
    }
}
