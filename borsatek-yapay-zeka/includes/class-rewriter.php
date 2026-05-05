<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI dönüşüm orkestratörü. Tüm içerik yeniden yazma işlemlerinin tek giriş noktası.
 */
class BorsatekRewriter {

    /** @var BorsatekAiProvider */
    private BorsatekAiProvider $ai;

    /** @var BorsatekSeoEngine */
    private BorsatekSeoEngine $seo;

    /** @var BorsatekTranslator */
    private BorsatekTranslator $translator;

    /** @var BorsatekContentFetcher */
    private BorsatekContentFetcher $fetcher;

    /** @var BorsatekStats */
    private BorsatekStats $stats;

    /**
     * Constructor.
     */
    public function __construct(
        BorsatekAiProvider    $ai,
        BorsatekSeoEngine     $seo,
        BorsatekTranslator    $translator,
        BorsatekContentFetcher $fetcher,
        BorsatekStats         $stats
    ) {
        $this->ai         = $ai;
        $this->seo        = $seo;
        $this->translator = $translator;
        $this->fetcher    = $fetcher;
        $this->stats      = $stats;
    }

    /**
     * Kaynak içeriği AI ile yeniden yazar ve SEO kurallarını uygular.
     *
     * @return array|WP_Error İşlenmiş içerik dizisi veya hata
     */
    public function rewrite( string $sourceTitle, string $sourceContent, string $sourceLink, string $focusKeyword = '', int $authorId = 0 ): array|\WP_Error {
        // SEO kurallarını al ve ayrıştır
        $seoOptions  = get_option( 'borsatek_ai_seo_rules', [ 'seo' => '' ] );
        $seoRulesText = is_array( $seoOptions ) ? ( $seoOptions['seo'] ?? '' ) : (string) $seoOptions;
        $ruleSet     = $this->seo->parseRules( $seoRulesText, $focusKeyword );

        // Ayarları oku
        $costMode  = (string) get_option( 'borsatek_ai_cost_mode', 'balanced' );
        $preferred = (string) get_option( 'borsatek_ai_provider', 'anthropic' );
        $chain     = $this->ai->buildProviderChain( $preferred );
        $maxTokens = $this->ai->getMaxTokens( $costMode, $preferred );
        $timeout   = (int) get_option( 'borsatek_ai_timeout', 90 );

        // Prompt oluştur ve AI çağrısı yap
        $prompt  = $this->buildPrompt( $sourceTitle, $sourceContent, $sourceLink, $focusKeyword, $seoRulesText, $ruleSet, $costMode );
        $rawText = $this->ai->callWithFallback( $prompt, $chain, $maxTokens, $timeout );

        if ( is_wp_error( $rawText ) ) {
            $this->stats->incrementFailed( $rawText->get_error_code() );
            return $rawText;
        }

        // JSON ayrıştır
        $parsed = $this->ai->parseJsonResponse( $rawText );
        if ( $parsed === null ) {
            $this->stats->incrementFailed( 'parse_failed' );
            return new WP_Error( 'parse_failed', 'AI yanıtı JSON olarak ayrıştırılamadı.' );
        }

        // SEO kurallarını uygula (sunucu tarafı — panelden kapatılabilir)
        if ( (bool) get_option( 'borsatek_ai_enforce_seo_rules', true ) ) {
            $result = $this->seo->enforce( $parsed, $focusKeyword, $ruleSet, $sourceContent );
        } else {
            $result = $parsed;
        }

        // Kaynak bilgilerini ekle
        $result['sourceLink'] = $sourceLink;
        $result['sourceFeed'] = $parsed['sourceFeed'] ?? '';

        // İhlal varsa otomatik veya manuel repair pass
        $violations    = $this->seo->collectViolations( $result, $focusKeyword, $ruleSet, $sourceContent );
        $manualRepair  = (bool) get_option( 'borsatek_ai_repair_pass_enabled', false );
        $autoRepair    = (bool) get_option( 'borsatek_ai_auto_repair_seo', true );
        $minViol       = (int) get_option( 'borsatek_ai_repair_min_violations', 2 );

        $runRepair = false;
        if ( count( $violations ) > 0 ) {
            if ( $autoRepair && trim( $seoRulesText ) !== '' ) {
                $runRepair = true;
            } elseif ( $manualRepair && count( $violations ) >= $minViol ) {
                $runRepair = true;
            }
        }

        if ( $runRepair ) {
            $result = $this->runRepairPass( $result, $sourceTitle, $sourceContent, $sourceLink, $focusKeyword, $seoRulesText, $ruleSet, $violations );
        }

        // SEO skorunu hesapla
        $result['seoScore'] = $this->seo->calculateSeoScore( $result, $focusKeyword, $ruleSet );

        // İstatistik güncelle
        $this->stats->incrementProcessed( $result['sourceFeed'] ?? '', $preferred );

        return $result;
    }

    /**
     * Önizleme amaçlı yeniden yazar (taslak oluşturmaz).
     */
    public function preview( string $sourceTitle, string $sourceContent, string $focusKeyword = '' ): array|\WP_Error {
        return $this->rewrite( $sourceTitle, $sourceContent, '', $focusKeyword, 0 );
    }

    /**
     * Yeniden yazılmış içerikten WordPress taslağı oluşturur.
     *
     * @return int|WP_Error Oluşturulan post ID veya hata
     */
    public function createDraft( array $result, int $queueId, int $authorId ): int|\WP_Error {
        $authorId = $authorId > 0 ? $authorId : get_current_user_id();

        $postId = wp_insert_post( [
            'post_title'   => $result['title']   ?? '',
            'post_content' => $result['body']    ?? '',
            'post_excerpt' => $result['excerpt'] ?? '',
            'post_status'  => 'draft',
            'post_name'    => $result['slug']    ?? '',
            'post_author'  => $authorId,
            'post_type'    => 'post',
        ], true );

        if ( is_wp_error( $postId ) ) {
            return $postId;
        }

        // Yoast SEO meta
        update_post_meta( $postId, '_yoast_wpseo_focuskw',   $result['focusKeyword']    ?? '' );
        update_post_meta( $postId, '_yoast_wpseo_title',     $result['seoTitle']        ?? '' );
        update_post_meta( $postId, '_yoast_wpseo_metadesc',  $result['metaDescription'] ?? '' );

        // RankMath SEO meta
        update_post_meta( $postId, 'rank_math_focus_keyword', $result['focusKeyword']    ?? '' );
        update_post_meta( $postId, 'rank_math_title',         $result['seoTitle']        ?? '' );
        update_post_meta( $postId, 'rank_math_description',   $result['metaDescription'] ?? '' );

        // Eklentiye özgü meta
        $seoScore = $result['seoScore'] ?? [];
        update_post_meta( $postId, '_borsatek_seo_score',    $seoScore['score'] ?? 0 );
        update_post_meta( $postId, '_borsatek_image_prompt', $result['imagePrompt']  ?? '' );
        update_post_meta( $postId, '_borsatek_source_link',  $result['sourceLink']   ?? '' );

        // Etiketler
        if ( ! empty( $result['tags'] ) && is_array( $result['tags'] ) ) {
            wp_set_post_tags( $postId, $result['tags'], false );
        }

        // Kategori önerisi
        if ( ! empty( $result['suggestedCategory'] ) ) {
            $cat = get_term_by( 'name', $result['suggestedCategory'], 'category' );
            if ( $cat && ! is_wp_error( $cat ) ) {
                wp_set_post_categories( $postId, [ $cat->term_id ] );
            }
        }

        // Kuyruk öğesini güncelle
        if ( $queueId > 0 ) {
            update_post_meta( $queueId, BorsatekQueue::META_ASYNC_STATUS,   'done' );
            update_post_meta( $queueId, BorsatekQueue::META_ASYNC_DRAFT_ID, $postId );
        }

        return $postId;
    }

    /**
     * AI prompt'unu oluşturur.
     */
    public function buildPrompt( string $sourceTitle, string $sourceContent, string $sourceLink, string $focusKeyword, string $seoRulesText, array $ruleSet, string $costMode ): string {
        $contentLimits = [ 'low' => 10000, 'balanced' => 20000, 'high' => 40000 ];
        $contentLimit  = $contentLimits[ $costMode ] ?? 20000;
        $truncatedContent = mb_substr( $sourceContent, 0, $contentLimit );

        $rulesSection    = ! empty( $seoRulesText ) ? $seoRulesText : 'Standart SEO kuralları geçerlidir.';
        $structuralRules = $this->seo->formatRulesForPrompt( $ruleSet );
        $keywordLine     = ! empty( $focusKeyword ) ? $focusKeyword : '(belirtilmedi)';
        $language        = (string) get_option( 'borsatek_ai_rewrite_language', 'Turkce' );

        $linkLine        = ! empty( $sourceLink ) ? $sourceLink : '(URL yok)';
        $plainSource     = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $sourceContent ) ) );
        $trimmedLen      = mb_strlen( $plainSource );
        $wordApprox      = $plainSource === '' ? 0 : count( preg_split( '/\s+/u', $plainSource ) );

        $thinSourceNote = '';
        if ( $trimmedLen < 400 ) {
            $thinSourceNote = "\n=== Kaynak uyarısı (kısa metin) ===\nKaynak sınırlı; yeni rakam, hedef fiyat, yüzde veya kaynakta olmayan senaryo EKLEME. Buna rağmen gövdeyi en az 3–4 anlamlı <p> paragrafına böl; mümkünse 1–2 <h2> ile bölümle. Cümleleri sadece yeniden sıralayıp akıcı Türkçe ile ifade et, içerik olarak genişletme.\n";
        }

        // Uzun kaynakta modelin "iki paragraflık özet"e kaymasını engelle
        $bodyDepthGuidance = '';
        if ( $wordApprox >= 500 ) {
            $bodyDepthGuidance = <<<DEPTH

=== Gövde derinliği (önemli) ===
Kaynak metin uzun veya orta uzunlukta (~{$wordApprox} sözcük). Gövdeyi aşırı kısaltma: okuyucuya haber sitesi makalesi hissi verecek kadar geliştir.
- "body" alanında geçerli HTML kullan; paragraflar <p>...</p>, bölümler <h2>...</h2> ile açılsın.
- Toplam gövde uzunluğu (düz metin) kabaca kaynağın %45–75'i kadar olmalı; kaynaktan belirgin şekilde daha kısa bir "özet broşür" yazma.
- Her önemli gelişme, bağlam ve somut bilgi (rakam, hedef, değerlendirme, risk) korunmalı; aynı fikri tekrar etme ama içeriği inceltme.
- "subheadings" dizisindeki başlıklar gövdede gerçek <h2> olarak yer almalı.
DEPTH;
        } elseif ( $wordApprox >= 120 ) {
            $bodyDepthGuidance = <<<DEPTH

=== Gövde derinliği ===
Kaynak orta uzunlukta. Gövde en az 5–6 paragraf ve en az 2 <h2> bölüm içersin; yalnızca kaynakta geçen bilgileri kullan ama haber dilinde açık ve akıcı genişlet (abartı veya uydurma yok).
DEPTH;
        } else {
            $bodyDepthGuidance = <<<DEPTH

=== Gövde derinliği ===
Kaynak kısa. Yine de gövde en az 3 <p> paragrafı ve okunabilir akış içersin; ekstra varsayım veya rakam üretme.
DEPTH;
        }

        return <<<PROMPT
=== SEO Dönüştürme Komutları ===
{$rulesSection}

=== Yapısal Parametreler ===
{$structuralRules}

=== Öncelik (panel SEO) ===
Panelde yazdığınız SEO kuralları ile bu bloktaki yapısal parametreler çelişirse önce panel metnindeki kuralları uygula; sonra yapısal limitlemeye uy.
{$bodyDepthGuidance}

=== Yanıt dili (katı) ===
Tüm çıktılar (title, excerpt, body, tags, subheadings, metaDescription dahil) {$language} dilinde olmalı.
- Almanca, İngilizce veya başka dilde tek kelime bile yazma (ör. "nächsten", "AI-driven", "next phase" YASAK).
- İngilizce kaynak metinden çeviri yapıyorsan her şeyi doğal Türkçe ile ifade et: "AI ile güçlendirilmiş talep", "yarı iletken rallisi", "bir sonraki aşama" gibi.
- Hisse kodları yalnızca ilk anılışta bir kez parantez içinde verilebilir (örn. Qualcomm (NASDAQ: QCOM)); metnin geri kalanında kod tekrarı yapma.

=== Kaynak bağlantısı (konu sabitleme) ===
Aşağıdaki makaleyi yeniden yazıyorsun; başka kaynak veya genel bilgi kullanma:
{$linkLine}{$thinSourceNote}

=== Kaynak sadakati — somut kurallar ===
- Gövdedeki her somut iddia (rakam, tarih, hedef fiyat, yüzde, şirket adımı, mahkeme/regülasyon) "Kaynak metin" içinde yer almalı; kaynakta yoksa yazma.
- Kaynak ana temayı anlatıyorsa gövde de aynı temayı işlemeli; genel piyasa özeti veya farklı bir hisse senedi hikâyesine sapma.
- Özgünlük cümle yapısı ve kelime seçimiyle sağlanır; kaynaktaki somut rakamlar ve kurum adları korunmalı.
- Kaynakta geçmeyen yurt dışı coğrafi veya askeri metafor kullanma.
- "title" tek ana başlık olsun; "/" ile iki başlık birleştirme.
- Aynı veya neredeyse aynı cümleyi gövdede iki kez yazma; tekrarlayan paragrafları birleştir.
- Kaynakta borç, zarar veya tahvil detayı ana konuysa gövdede korunmalı; uzun kaynaklarda özellikle iki–üç paragraflık aşırı kısa özete sıkıştırma.

=== Zorunlu JSON Şeması (başka metin ekleme) ===
{
  "title": "...",
  "excerpt": "...",
  "body": "... (geçerli HTML: çoklu <p>, bölüm başlıkları için <h2>; sadece düz metin verme)",
  "tags": ["..."],
  "focusKeyword": "...",
  "subheadings": ["... (body içindeki <h2> metinleriyle uyumlu)"],
  "suggestedCategory": "...",
  "imagePrompt": "...",
  "seoTitle": "...",
  "slug": "...",
  "metaDescription": "..."
}

Odak sözcük (focusKeyword alanına birebir yaz): {$keywordLine}

Kaynak başlık:
{$sourceTitle}

Kaynak metin (yalnızca bunu temel al):
{$truncatedContent}
PROMPT;
    }

    /**
     * SEO ihlallerini gidermek için ikinci AI geçişi çalıştırır.
     */
    private function runRepairPass( array $result, string $sourceTitle, string $sourceContent, string $sourceLink, string $focusKeyword, string $seoRulesText, array $ruleSet, array $violations ): array {
        $repairProvider = (string) get_option( 'borsatek_ai_repair_provider', 'auto' );
        $costMode       = (string) get_option( 'borsatek_ai_cost_mode', 'balanced' );
        $preferred      = (string) get_option( 'borsatek_ai_provider', 'anthropic' );
        $timeout        = (int) get_option( 'borsatek_ai_timeout', 90 );

        // Repair zinciri oluştur
        if ( $repairProvider === 'auto' ) {
            $chain = $this->ai->buildProviderChain( $preferred );
        } else {
            $fullChain = $this->ai->buildProviderChain( $repairProvider );
            $chain     = array_filter( $fullChain, fn( $c ) => $c['provider'] === $repairProvider );
            $chain     = array_values( $chain );
        }

        if ( empty( $chain ) ) {
            return $result;
        }

        $repairTokens = $this->ai->getRepairTokens( $costMode, $chain[0]['provider'] ?? $preferred );
        $repairPrompt = $this->seo->buildRepairPrompt( $result, $sourceTitle, $sourceContent, $focusKeyword, $seoRulesText, $ruleSet, $violations );

        $rawRepair = $this->ai->callWithFallback( $repairPrompt, $chain, $repairTokens, $timeout );

        if ( is_wp_error( $rawRepair ) ) {
            error_log( 'Borsatek repair pass başarısız: ' . $rawRepair->get_error_message() );
            return $result;
        }

        $parsedRepair = $this->ai->parseJsonResponse( $rawRepair );
        if ( $parsedRepair === null ) {
            return $result;
        }

        // Repair sonucuna da SEO kurallarını uygula
        $repairedResult = $this->seo->enforce( $parsedRepair, $focusKeyword, $ruleSet, $sourceContent );
        $repairedResult['sourceLink'] = $sourceLink;
        $repairedResult['sourceFeed'] = $result['sourceFeed'] ?? '';

        return $repairedResult;
    }
}
