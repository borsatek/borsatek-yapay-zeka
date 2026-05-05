<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Kurulum tamamlanmamışsa sihirbaza yönlendir
if ( ! get_option( 'borsatek_ai_setup_complete' ) ) {
    wp_redirect( admin_url( 'admin.php?page=borsatek-ai-news-app&tab=setup' ) );
    exit;
}

$seoOptions    = get_option( 'borsatek_ai_seo_rules', [ 'seo' => '' ] );
$seoRulesText  = is_array( $seoOptions ) ? ( $seoOptions['seo'] ?? '' ) : (string) $seoOptions;
$provider      = (string) get_option( 'borsatek_ai_provider', 'anthropic' );
$costMode      = (string) get_option( 'borsatek_ai_cost_mode', 'balanced' );
$aiTimeout     = (int) get_option( 'borsatek_ai_timeout', 90 );

$anthropicKey    = (string) get_option( 'borsatek_ai_anthropic_key', '' );
$anthropicModel  = (string) get_option( 'borsatek_ai_anthropic_model', 'claude-sonnet-4-20250514' );
$anthropicActive = (bool) get_option( 'borsatek_ai_anthropic_active', true );

$geminiKey    = (string) get_option( 'borsatek_ai_gemini_key', '' );
$geminiModel  = (string) get_option( 'borsatek_ai_gemini_model', 'gemini-2.5-flash' );
$geminiActive = (bool) get_option( 'borsatek_ai_gemini_active', true );

$openaiKey   = (string) get_option( 'borsatek_ai_openai_key', '' );
$openaiModel = (string) get_option( 'borsatek_ai_openai_model', 'gpt-4o-mini' );

$togetherKey   = (string) get_option( 'borsatek_ai_together_key', '' );
$togetherModel = (string) get_option( 'borsatek_ai_together_model', 'meta-llama/Llama-3.3-70B-Instruct-Turbo' );

$repairEnabled    = (bool) get_option( 'borsatek_ai_repair_pass_enabled', false );
$repairProvider   = (string) get_option( 'borsatek_ai_repair_provider', 'auto' );
$repairMinViol    = (int) get_option( 'borsatek_ai_repair_min_violations', 2 );

$enforceSeoRules  = (bool) get_option( 'borsatek_ai_enforce_seo_rules', true );
$autoRepairSeo    = (bool) get_option( 'borsatek_ai_auto_repair_seo', true );

$jinaKey          = (string) get_option( 'borsatek_ai_jina_key', '' );
$deeplKey         = (string) get_option( 'borsatek_ai_deepl_key', '' );
$deeplEnabled     = (bool) get_option( 'borsatek_ai_deepl_enabled', false );
$minSourceChars   = (int) get_option( 'borsatek_ai_min_source_chars', 300 );
$includeSource    = (bool) get_option( 'borsatek_ai_include_source_line', true );
$webhookToken     = (string) get_option( 'borsatek_ai_webhook_token', '' );
?>

<div class="borsatek-seo-wrap">

<form method="post" action="" class="borsatek-form">
    <?php wp_nonce_field( 'borsatek_settings' ); ?>
    <input type="hidden" name="borsatek_save_action" value="save_seo">

    <!-- Bölüm 1: AI Sağlayıcı -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-superhero"></span>
            AI Sağlayıcı & Genel Ayarlar
        </h2>

        <table class="form-table">
            <tr>
                <th scope="row"><label>Aktif Sağlayıcı</label></th>
                <td>
                    <label class="borsatek-radio-label">
                        <input type="radio" name="borsatek_ai_provider" value="anthropic" <?php checked( $provider, 'anthropic' ); ?>>
                        Anthropic Claude
                    </label>
                    &nbsp;&nbsp;
                    <label class="borsatek-radio-label">
                        <input type="radio" name="borsatek_ai_provider" value="gemini" <?php checked( $provider, 'gemini' ); ?>>
                        Google Gemini
                    </label>
                    &nbsp;&nbsp;
                    <label class="borsatek-radio-label">
                        <input type="radio" name="borsatek_ai_provider" value="openai" <?php checked( $provider, 'openai' ); ?>>
                        OpenAI
                    </label>
                    &nbsp;&nbsp;
                    <label class="borsatek-radio-label">
                        <input type="radio" name="borsatek_ai_provider" value="together" <?php checked( $provider, 'together' ); ?>>
                        Together AI
                    </label>
                    <p class="description">Birincil sağlayıcı başarısız olursa diğerleri otomatik devreye girer.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="borsatek_cost_mode">Maliyet Modu</label></th>
                <td>
                    <select name="borsatek_cost_mode" id="borsatek_cost_mode">
                        <option value="low"      <?php selected( $costMode, 'low' );      ?>>Düşük Maliyet (Az token)</option>
                        <option value="balanced" <?php selected( $costMode, 'balanced' ); ?>>Dengeli (Önerilen)</option>
                        <option value="high"     <?php selected( $costMode, 'high' );     ?>>Yüksek Kalite (Çok token)</option>
                    </select>
                    <p class="description">Daha yüksek mod daha uzun ve kaliteli içerik üretir, ancak API maliyetini artırır.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="borsatek_ai_timeout">AI Zaman Aşımı (sn)</label></th>
                <td>
                    <input type="number" name="borsatek_ai_timeout" id="borsatek_ai_timeout"
                           value="<?php echo esc_attr( $aiTimeout ); ?>" min="30" max="180" class="small-text">
                    <p class="description">Varsayılan: 90 saniye. Yavaş bağlantılarda artırın.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Bölüm 2: Anthropic -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-format-chat"></span>
            Anthropic Claude
        </h2>

        <table class="form-table">
            <tr>
                <th><label for="borsatek_anthropic_key">API Anahtarı</label></th>
                <td>
                    <input type="password" name="borsatek_anthropic_key" id="borsatek_anthropic_key"
                           value="<?php echo esc_attr( $anthropicKey ); ?>" class="regular-text"
                           placeholder="sk-ant-...">
                    <p class="description"><a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a> üzerinden alın.</p>
                </td>
            </tr>
            <tr>
                <th><label for="borsatek_anthropic_model">Model</label></th>
                <td>
                    <input type="text" name="borsatek_anthropic_model" id="borsatek_anthropic_model"
                           value="<?php echo esc_attr( $anthropicModel ); ?>" class="regular-text"
                           placeholder="claude-sonnet-4-20250514">
                </td>
            </tr>
            <tr>
                <th>Aktif</th>
                <td>
                    <label>
                        <input type="checkbox" name="borsatek_anthropic_active" value="1" <?php checked( $anthropicActive ); ?>>
                        Anthropic kullanılabilir durumdaysa aktif
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <!-- Bölüm 3: Gemini -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-star-filled"></span>
            Google Gemini
        </h2>

        <table class="form-table">
            <tr>
                <th><label for="borsatek_gemini_key">API Anahtarı</label></th>
                <td>
                    <input type="password" name="borsatek_gemini_key" id="borsatek_gemini_key"
                           value="<?php echo esc_attr( $geminiKey ); ?>" class="regular-text"
                           placeholder="AIza...">
                    <p class="description"><a href="https://aistudio.google.com/" target="_blank">aistudio.google.com</a> üzerinden alın.</p>
                </td>
            </tr>
            <tr>
                <th><label for="borsatek_gemini_model_select">Model</label></th>
                <td>
                    <select name="borsatek_gemini_model" id="borsatek_gemini_model_select">
                        <?php
                        $geminiModels = [
                            'gemini-2.5-flash'      => 'Gemini 2.5 Flash (Önerilen)',
                            'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
                            'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite',
                            'gemini-2.0-flash'      => 'Gemini 2.0 Flash',
                        ];
                        foreach ( $geminiModels as $value => $label ) :
                        ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $geminiModel, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    &nbsp;
                    <button type="button" id="borsatek-fetch-models-btn" class="button">
                        Modelleri Getir
                    </button>
                    <p class="description">API anahtarı girildikten sonra "Modelleri Getir" ile güncel listeyi çekin.</p>
                </td>
            </tr>
            <tr>
                <th>Test & Aktif</th>
                <td>
                    <button type="button" id="borsatek-test-gemini-btn" class="button">
                        Bağlantıyı Test Et
                    </button>
                    <span id="borsatek-gemini-test-result" style="margin-left:10px;font-weight:bold;"></span>
                    <br><br>
                    <label>
                        <input type="checkbox" name="borsatek_gemini_active" value="1" <?php checked( $geminiActive ); ?>>
                        Gemini kullanılabilir durumdaysa aktif
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <!-- Bölüm 4: OpenAI -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-admin-network"></span>
            OpenAI
        </h2>

        <table class="form-table">
            <tr>
                <th><label for="borsatek_openai_key">API Anahtarı</label></th>
                <td>
                    <input type="password" name="borsatek_openai_key" id="borsatek_openai_key"
                           value="<?php echo esc_attr( $openaiKey ); ?>" class="regular-text"
                           placeholder="sk-...">
                </td>
            </tr>
            <tr>
                <th><label for="borsatek_openai_model">Model</label></th>
                <td>
                    <input type="text" name="borsatek_openai_model" id="borsatek_openai_model"
                           value="<?php echo esc_attr( $openaiModel ); ?>" class="regular-text"
                           placeholder="gpt-4o-mini">
                </td>
            </tr>
        </table>
    </div>

    <!-- Bölüm 5: Together AI -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-groups"></span>
            Together AI
        </h2>

        <table class="form-table">
            <tr>
                <th><label for="borsatek_together_key">API Anahtarı</label></th>
                <td>
                    <input type="password" name="borsatek_together_key" id="borsatek_together_key"
                           value="<?php echo esc_attr( $togetherKey ); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="borsatek_together_model">Model</label></th>
                <td>
                    <input type="text" name="borsatek_together_model" id="borsatek_together_model"
                           value="<?php echo esc_attr( $togetherModel ); ?>" class="regular-text"
                           placeholder="meta-llama/Llama-3.3-70B-Instruct-Turbo">
                </td>
            </tr>
        </table>
    </div>

    <!-- Bölüm 6: Repair Pass -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-update-alt"></span>
            SEO Onarım Geçişi (Repair Pass)
        </h2>
        <p class="description">
            Belirtilen sayıda SEO ihlali tespit edildiğinde AI'ı ikinci kez çalıştırarak ihlalleri giderir.
            Maliyet artışına yol açar; dengeli ve yüksek modda önerilir.
        </p>

        <table class="form-table">
            <tr>
                <th>Aktif</th>
                <td>
                    <label>
                        <input type="checkbox" name="borsatek_repair_pass_enabled" value="1" <?php checked( $repairEnabled ); ?>>
                        Repair pass etkin
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="borsatek_repair_provider">Repair Sağlayıcı</label></th>
                <td>
                    <select name="borsatek_repair_provider" id="borsatek_repair_provider">
                        <option value="auto"      <?php selected( $repairProvider, 'auto' );      ?>>Otomatik (birincil sağlayıcı)</option>
                        <option value="anthropic" <?php selected( $repairProvider, 'anthropic' ); ?>>Sadece Anthropic</option>
                        <option value="gemini"    <?php selected( $repairProvider, 'gemini' );    ?>>Sadece Gemini</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="borsatek_repair_min_violations">Minimum İhlal Sayısı</label></th>
                <td>
                    <input type="number" name="borsatek_repair_min_violations" id="borsatek_repair_min_violations"
                           value="<?php echo esc_attr( $repairMinViol ); ?>" min="1" max="5" class="small-text">
                    <p class="description">Bu sayıya ulaşıldığında repair çalışır. Önerilen: 2.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Bölüm 7: SEO Kuralları -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-chart-line"></span>
            SEO Kuralları
        </h2>
        <p class="description">
            Serbest metin + yapılandırılmış bloklar. Satır içi kurallar (ör. “Başlık maksimum 90 karakter”) otomatik okunur.
            <strong>Yasaklı / zorunlu listeler</strong> için başlık satırı yazıp altına madde işaretli liste ekleyin:
        </p>
        <pre class="description" style="background:#f6f7f7;padding:10px;border-radius:4px;overflow:auto;">Yasaklı ifadeler:
- özetle
- sonuç olarak

Yasaklı cümle başları:
- Bu durum

Zorunlu ifadeler:
- BIST</pre>

        <div class="borsatek-field">
            <label for="borsatek_seo_rules_text">SEO Kural Metni</label>
            <textarea id="borsatek_seo_rules_text" name="borsatek_seo_rules_text"
                      rows="15" class="large-text"
                      placeholder="- Başlık maksimum 90 karakter; Özet maksimum 200 karakter; SEO başlık 60 karakter"><?php echo esc_textarea( $seoRulesText ); ?></textarea>
        </div>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Sunucuda zorunlu SEO</th>
                <td>
                    <label>
                        <input type="checkbox" name="borsatek_enforce_seo_rules" value="1" <?php checked( $enforceSeoRules ); ?>>
                        PHP ile panel kurallarını uygula (limitler, yasaklı ifade silme, odak kelime yerleşimi, yoğunluk üst sınırı)
                    </label>
                    <p class="description">Kapatılırsa çıktı yalnızca AI ve ilk JSON’a kalır; uyumluluk düşebilir.</p>
                    <label>
                        <input type="checkbox" name="borsatek_auto_repair_seo" value="1" <?php checked( $autoRepairSeo ); ?>>
                        Panel SEO metni dolu ve skorda ihlal varsa otomatik AI repair çalıştır (ek API çağrısı)
                    </label>
                    <p class="description">İhlalleri düzeltmek için önerilir. İsterseniz “Repair pass etkin” ile manuel eşik de kullanılabilir.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Bölüm 8: Diğer Ayarlar -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-admin-settings"></span>
            Diğer Ayarlar
        </h2>

        <table class="form-table">
            <tr>
                <th><label for="borsatek_jina_key">Jina Reader API Anahtarı</label></th>
                <td>
                    <input type="password" name="borsatek_jina_key" id="borsatek_jina_key"
                           value="<?php echo esc_attr( $jinaKey ); ?>" class="regular-text"
                           placeholder="jina_...">
                    <button type="button" id="borsatek-test-jina-btn" class="button" style="margin-left:8px;vertical-align:middle;">
                        Bağlantıyı Test Et
                    </button>
                    <span id="borsatek-jina-test-result" style="margin-left:10px;font-weight:bold;vertical-align:middle;"></span>
                    <p class="description">Opsiyonel. <a href="https://jina.ai/reader/" target="_blank">jina.ai</a>'dan alın. Investing.com gibi siteler için gerekli. Anahtarı kaydetmeden önce test edebilirsiniz.</p>
                </td>
            </tr>
            <tr>
                <th><label for="borsatek_deepl_key">DeepL API Anahtarı</label></th>
                <td>
                    <input type="password" name="borsatek_deepl_key" id="borsatek_deepl_key"
                           value="<?php echo esc_attr( $deeplKey ); ?>" class="regular-text">
                    <label>
                        <input type="checkbox" name="borsatek_deepl_enabled" value="1" <?php checked( $deeplEnabled ); ?>>
                        DeepL çevirisi etkin
                    </label>
                    <p class="description">İngilizce başlıkları Türkçeye çevirmek için kullanılır.</p>
                </td>
            </tr>
            <tr>
                <th><label for="borsatek_min_source_chars">Minimum Kaynak Karakter</label></th>
                <td>
                    <input type="number" name="borsatek_min_source_chars" id="borsatek_min_source_chars"
                           value="<?php echo esc_attr( $minSourceChars ); ?>" min="100" max="2000" class="small-text">
                    <p class="description">Bu sayıdan kısa içerikler işlenmez. Varsayılan: 300.</p>
                </td>
            </tr>
            <tr>
                <th>Kaynak Bağlantısı</th>
                <td>
                    <label>
                        <input type="checkbox" name="borsatek_include_source_line" value="1" <?php checked( $includeSource ); ?>>
                        Taslağa orijinal kaynak bağlantısı meta verisi ekle
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="borsatek_webhook_token">Webhook Token</label></th>
                <td>
                    <input type="text" name="borsatek_webhook_token" id="borsatek_webhook_token"
                           value="<?php echo esc_attr( $webhookToken ); ?>" class="regular-text"
                           placeholder="Güvenli bir token oluşturun">
                    <button type="button" id="borsatek-gen-webhook-token" class="button">
                        Oluştur
                    </button>
                    <p class="description">
                        REST API endpoint: <code><?php echo esc_html( home_url( '/wp-json/borsatek-ai/v1/queue' ) ); ?></code><br>
                        Başlık: <code>Authorization: Bearer {token}</code>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <p class="borsatek-save-bar">
        <button type="submit" class="button button-primary button-large">
            <span class="dashicons dashicons-saved"></span>
            Tüm Ayarları Kaydet
        </button>
    </p>

</form>

</div>
