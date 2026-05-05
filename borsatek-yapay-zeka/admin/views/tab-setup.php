<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Kurulum tamamlanmışsa stream'e yönlendir
if ( get_option( 'borsatek_ai_setup_complete' ) ) {
    wp_redirect( admin_url( 'admin.php?page=borsatek-ai-news-app&tab=stream' ) );
    exit;
}

// Mevcut değerleri al (aktivasyonda set edilmiş olacak)
$curProvider    = get_option( 'borsatek_ai_provider', 'gemini' );
$curGeminiKey   = get_option( 'borsatek_ai_gemini_key', '' );
$curGeminiModel = get_option( 'borsatek_ai_gemini_model', 'gemini-2.5-flash' );
$curAnthroKey   = get_option( 'borsatek_ai_anthropic_key', '' );
$curAnthroModel = get_option( 'borsatek_ai_anthropic_model', 'claude-sonnet-4-20250514' );
$curOpenaiKey   = get_option( 'borsatek_ai_openai_key', '' );
$curTogetherKey = get_option( 'borsatek_ai_together_key', '' );
$curJinaKey     = get_option( 'borsatek_ai_jina_key', '' );
$curDeeplKey    = get_option( 'borsatek_ai_deepl_key', '' );
$curDeeplOn     = get_option( 'borsatek_ai_deepl_enabled', true );
$curLanguage    = get_option( 'borsatek_ai_rewrite_language', 'Turkce' );
$curMaxItems    = get_option( 'borsatek_ai_max_items_per_run', 10 );
$curInterval    = get_option( 'borsatek_ai_scan_interval', 'borsatek_30min' );
$curWebhook     = get_option( 'borsatek_ai_webhook_token', '' );
$curFeeds       = (array) get_option( 'borsatek_ai_rss_feeds', [] );
$feedsRaw       = implode( "\n", $curFeeds );
?>

<div class="borsatek-setup-wizard">

  <div class="borsatek-setup-header">
    <h1>🚀 Borsatek Yapay Zeka — İlk Kurulum</h1>
    <p>Eklenti çalışmaya başlamadan önce API anahtarlarınızı ve tercihlerinizi ayarlayın.<br>
       Bu bilgiler WordPress veritabanında saklanır.</p>
  </div>

  <?php if ( ! empty( $_GET['error'] ) ) : ?>
    <div class="notice notice-error"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <form method="post" action="" class="borsatek-setup-form">
    <?php wp_nonce_field( 'borsatek_settings' ); ?>
    <input type="hidden" name="borsatek_save_action" value="save_setup">

    <!-- ══ BÖLÜM 1: AI SAĞLAYICILAR ══════════════════════════════════ -->
    <div class="borsatek-setup-section">
      <h2><span class="borsatek-step-badge">1</span> Yapay Zeka Sağlayıcısı</h2>
      <p class="description">Haberler hangi yapay zeka ile yeniden yazılsın? İkisi de tanımlıysa birincil başarısız olunca otomatik diğerine geçilir.</p>

      <table class="form-table">
        <tr>
          <th>Birincil Sağlayıcı</th>
          <td>
            <label>
              <input type="radio" name="borsatek_provider" value="gemini" <?php checked( $curProvider, 'gemini' ); ?>>
              <strong>Google Gemini</strong> — Hızlı, ekonomik
            </label><br><br>
            <label>
              <input type="radio" name="borsatek_provider" value="anthropic" <?php checked( $curProvider, 'anthropic' ); ?>>
              <strong>Anthropic Claude</strong> — Yüksek kalite Türkçe
            </label>
          </td>
        </tr>

        <tr>
          <th>Gemini API Anahtarı</th>
          <td>
            <input type="password" name="borsatek_gemini_key" value="<?php echo esc_attr( $curGeminiKey ); ?>" class="regular-text" placeholder="AIzaSy...">
            <p class="description"><a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a>'dan ücretsiz alabilirsiniz.</p>
          </td>
        </tr>

        <tr>
          <th>Gemini Model</th>
          <td>
            <select name="borsatek_gemini_model">
              <option value="gemini-2.5-flash" <?php selected( $curGeminiModel, 'gemini-2.5-flash' ); ?>>gemini-2.5-flash — Hızlı ve ekonomik (Önerilen)</option>
              <option value="gemini-2.5-pro"   <?php selected( $curGeminiModel, 'gemini-2.5-pro' );   ?>>gemini-2.5-pro — Yüksek kalite (pahalı)</option>
              <option value="gemini-2.0-flash" <?php selected( $curGeminiModel, 'gemini-2.0-flash' ); ?>>gemini-2.0-flash — Stabil eski sürüm</option>
            </select>
            <button type="button" id="borsatek-setup-test-gemini" class="button button-secondary" style="margin-left:8px;">Bağlantıyı Test Et</button>
            <span id="borsatek-setup-gemini-result" style="margin-left:8px;font-weight:bold;"></span>
          </td>
        </tr>

        <tr>
          <th>Anthropic API Anahtarı</th>
          <td>
            <input type="password" name="borsatek_anthropic_key" value="<?php echo esc_attr( $curAnthroKey ); ?>" class="regular-text" placeholder="sk-ant-...">
            <p class="description"><a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a>'dan alabilirsiniz. Birincil Gemini ise yedek olarak kullanılır.</p>
          </td>
        </tr>
        <input type="hidden" name="borsatek_anthropic_model" value="<?php echo esc_attr( $curAnthroModel ); ?>">

        <tr>
          <th>OpenAI API Anahtarı <span style="font-weight:normal;color:#888">(Yedek)</span></th>
          <td>
            <input type="password" name="borsatek_openai_key" value="<?php echo esc_attr( $curOpenaiKey ); ?>" class="regular-text" placeholder="sk-proj-...">
          </td>
        </tr>

        <tr>
          <th>TogetherAI API Anahtarı <span style="font-weight:normal;color:#888">(Yedek)</span></th>
          <td>
            <input type="password" name="borsatek_together_key" value="<?php echo esc_attr( $curTogetherKey ); ?>" class="regular-text" placeholder="tgp_v1_...">
            <input type="hidden" name="borsatek_together_model" value="meta-llama/Llama-3.3-70B-Instruct-Turbo">
          </td>
        </tr>
      </table>
    </div>

    <!-- ══ BÖLÜM 2: İÇERİK ÇEKME & ÇEVİRİ ══════════════════════════ -->
    <div class="borsatek-setup-section">
      <h2><span class="borsatek-step-badge">2</span> İçerik Çekme ve Çeviri</h2>

      <table class="form-table">
        <tr>
          <th>Jina Reader API Anahtarı</th>
          <td>
            <input type="password" name="borsatek_jina_key" id="borsatek-setup-jina-key" value="<?php echo esc_attr( $curJinaKey ); ?>" class="regular-text" placeholder="jina_...">
            <button type="button" id="borsatek-setup-test-jina" class="button button-secondary" style="margin-left:8px;">Bağlantıyı Test Et</button>
            <span id="borsatek-setup-jina-result" style="margin-left:8px;font-weight:bold;"></span>
            <p class="description"><a href="https://jina.ai/" target="_blank">jina.ai</a>'dan alabilirsiniz. Investing.com ve engelli sitelerden içerik çekmek için gerekli.</p>
          </td>
        </tr>

        <tr>
          <th>DeepL API Anahtarı</th>
          <td>
            <input type="password" name="borsatek_deepl_key" value="<?php echo esc_attr( $curDeeplKey ); ?>" class="regular-text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx:fx">
            <p class="description"><a href="https://www.deepl.com/pro-api" target="_blank">deepl.com</a> Free hesabından alabilirsiniz. İngilizce başlıkları Türkçeye çevirmek için kullanılır.</p>
          </td>
        </tr>

        <tr>
          <th>DeepL Çeviriyi Etkinleştir</th>
          <td>
            <label>
              <input type="checkbox" name="borsatek_deepl_enabled" value="1" <?php checked( $curDeeplOn ); ?>>
              İngilizce başlıkları otomatik Türkçeye çevir
            </label>
          </td>
        </tr>

        <tr>
          <th>Yazım Dili</th>
          <td>
            <select name="borsatek_rewrite_language">
              <option value="Turkce"    <?php selected( $curLanguage, 'Turkce' ); ?>>Türkçe</option>
              <option value="Ingilizce" <?php selected( $curLanguage, 'Ingilizce' ); ?>>İngilizce</option>
            </select>
            <p class="description">AI içerikleri hangi dilde üretsin?</p>
          </td>
        </tr>

        <tr>
          <th>Tarama Başına Maksimum Haber</th>
          <td>
            <input type="number" name="borsatek_max_items" value="<?php echo esc_attr( $curMaxItems ); ?>" min="1" max="50" style="width:80px;">
            <p class="description">Her otomatik RSS taramasında en fazla kaç haber işlensin. (Önerilen: 10)</p>
          </td>
        </tr>
      </table>
    </div>

    <!-- ══ BÖLÜM 3: RSS & ZAMANLAMA ══════════════════════════════════ -->
    <div class="borsatek-setup-section">
      <h2><span class="borsatek-step-badge">3</span> Haber Kaynakları ve Zamanlama</h2>

      <table class="form-table">
        <tr>
          <th>RSS Feed Listesi</th>
          <td>
            <textarea name="borsatek_rss_feeds_raw" rows="5" class="large-text"
                      placeholder="https://paratic.com/feed/&#10;https://borsatek.com/feed/"><?php echo esc_textarea( $feedsRaw ); ?></textarea>
            <p class="description">Her satıra bir RSS feed URL'si girin.</p>
          </td>
        </tr>

        <tr>
          <th>Tarama Sıklığı</th>
          <td>
            <label>
              <input type="radio" name="borsatek_scan_interval" value="borsatek_15min" <?php checked( $curInterval, 'borsatek_15min' ); ?>>
              Her 15 dakika <span style="color:#888">(Yüksek trafik)</span>
            </label><br><br>
            <label>
              <input type="radio" name="borsatek_scan_interval" value="borsatek_30min" <?php checked( $curInterval, 'borsatek_30min' ); ?>>
              <strong>Her 30 dakika</strong> <span style="color:#888">(Önerilen)</span>
            </label><br><br>
            <label>
              <input type="radio" name="borsatek_scan_interval" value="hourly" <?php checked( $curInterval, 'hourly' ); ?>>
              Her saat <span style="color:#888">(Düşük trafik)</span>
            </label>
          </td>
        </tr>

        <tr>
          <th>Webhook Güvenlik Anahtarı</th>
          <td>
            <input type="text" name="borsatek_webhook_token" id="borsatek-setup-webhook-token"
                   value="<?php echo esc_attr( $curWebhook ); ?>" class="regular-text">
            <button type="button" id="borsatek-setup-gen-token" class="button button-secondary">Yeni Oluştur</button>
            <p class="description">Dışarıdan haber göndermek için kullanılan güvenlik anahtarı.</p>
          </td>
        </tr>
      </table>
    </div>

    <!-- ══ TAMAMLA ════════════════════════════════════════════════════ -->
    <div class="borsatek-setup-footer">
      <p>Ayarları daha sonra <strong>AI &amp; SEO Ayarları</strong> sekmesinden değiştirebilirsiniz.</p>
      <button type="submit" class="button button-primary button-hero">
        ✓ Kurulumu Tamamla ve Başla
      </button>
    </div>

  </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Gemini bağlantı testi
    $('#borsatek-setup-test-gemini').on('click', function() {
        var $r = $('#borsatek-setup-gemini-result').text('Test ediliyor...');
        $.post(ajaxurl, {
            action: 'borsatek_test_ai_connection',
            nonce: '<?php echo wp_create_nonce( 'borsatek_ajax' ); ?>',
            provider: 'gemini',
            api_key: $('input[name="borsatek_gemini_key"]').val(),
            model: $('select[name="borsatek_gemini_model"]').val()
        }, function(res) {
            $r.text(res.success ? '✓ Bağlantı başarılı' : '✗ ' + ((res.data && res.data.message) ? res.data.message : 'Hata'))
              .css('color', res.success ? '#1B5E20' : '#B71C1C');
        });
    });

    $('#borsatek-setup-test-jina').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $r = $('#borsatek-setup-jina-result').text('Test ediliyor...').css('color', '#555');
        $.post(ajaxurl, {
            action: 'borsatek_test_ai_connection',
            nonce: '<?php echo wp_create_nonce( 'borsatek_ajax' ); ?>',
            provider: 'jina',
            api_key: $('#borsatek-setup-jina-key').val()
        }, function(res) {
            $btn.prop('disabled', false);
            $r.text(res.success ? '✓ Jina bağlantısı başarılı' : '✗ ' + ((res.data && res.data.message) ? res.data.message : 'Hata'))
              .css('color', res.success ? '#1B5E20' : '#B71C1C');
        }).fail(function() {
            $btn.prop('disabled', false);
            $r.text('✗ Sunucu hatası').css('color', '#B71C1C');
        });
    });

    // Webhook token oluştur
    $('#borsatek-setup-gen-token').on('click', function() {
        var t = '';
        var c = '0123456789abcdef';
        for (var i = 0; i < 32; i++) t += c[Math.floor(Math.random() * c.length)];
        $('#borsatek-setup-webhook-token').val(t);
    });
});
</script>

<style>
.borsatek-setup-wizard { max-width: 900px; }
.borsatek-setup-header { background: #0D2B4E; color: #fff; padding: 24px 28px; border-radius: 6px; margin-bottom: 24px; }
.borsatek-setup-header h1 { color: #fff; margin: 0 0 8px; font-size: 22px; }
.borsatek-setup-header p  { color: #bdd4ee; margin: 0; line-height: 1.6; }
.borsatek-setup-section   { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 20px 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.borsatek-setup-section h2 { display: flex; align-items: center; gap: 10px; margin-top: 0; font-size: 16px; border-bottom: 1px solid #f0f0f1; padding-bottom: 12px; margin-bottom: 15px; }
.borsatek-step-badge { background: #1565C0; color: #fff; border-radius: 50%; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; flex-shrink: 0; }
.borsatek-setup-footer { background: #f0f6ff; border: 1px solid #c5d9f7; border-radius: 6px; padding: 24px 28px; text-align: center; }
.borsatek-setup-footer p  { color: #555; margin-bottom: 14px; font-size: 14px; }
.borsatek-setup-footer .button-hero { font-size: 16px; padding: 10px 28px; height: auto; line-height: 1.5; }
.borsatek-setup-form .form-table th { width: 220px; }
</style>
