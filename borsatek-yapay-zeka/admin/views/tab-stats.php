<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var BorsatekStats $stats */

$monthlyReport = $stats->getMonthlyReport();
$topFeeds      = $stats->getTopFeeds( 5 );
$dailyChart    = $stats->getDailyChart( 30 );

// En aktif sağlayıcıyı bul
$byProvider    = $monthlyReport['byProvider'] ?? [];
arsort( $byProvider );
$topProvider   = key( $byProvider ) ?: '—';

$currentMonth  = wp_date( 'F Y' );
?>

<div class="borsatek-stats-wrap">

    <!-- Özet Kartları -->
    <div class="borsatek-stats-cards">

        <div class="borsatek-stat-card">
            <div class="borsatek-stat-icon">
                <span class="dashicons dashicons-yes-alt" style="color:#1B5E20;"></span>
            </div>
            <div class="borsatek-stat-content">
                <div class="borsatek-stat-number"><?php echo esc_html( $monthlyReport['total'] ); ?></div>
                <div class="borsatek-stat-label">Bu Ay İşlenen</div>
                <div class="borsatek-stat-sub"><?php echo esc_html( $currentMonth ); ?></div>
            </div>
        </div>

        <div class="borsatek-stat-card">
            <div class="borsatek-stat-icon">
                <span class="dashicons dashicons-dismiss" style="color:#B71C1C;"></span>
            </div>
            <div class="borsatek-stat-content">
                <div class="borsatek-stat-number"><?php echo esc_html( $monthlyReport['totalFailed'] ); ?></div>
                <div class="borsatek-stat-label">Başarısız</div>
                <div class="borsatek-stat-sub"><?php echo esc_html( $currentMonth ); ?></div>
            </div>
        </div>

        <div class="borsatek-stat-card">
            <div class="borsatek-stat-icon">
                <span class="dashicons dashicons-superhero" style="color:#1565C0;"></span>
            </div>
            <div class="borsatek-stat-content">
                <div class="borsatek-stat-number"><?php echo esc_html( ucfirst( $topProvider ) ); ?></div>
                <div class="borsatek-stat-label">En Aktif Sağlayıcı</div>
                <div class="borsatek-stat-sub">
                    <?php
                    if ( ! empty( $byProvider[ $topProvider ] ) ) {
                        echo esc_html( $byProvider[ $topProvider ] . ' işlem' );
                    }
                    ?>
                </div>
            </div>
        </div>

    </div>

    <div class="borsatek-stats-row">

        <!-- Sağlayıcı Dağılımı -->
        <div class="borsatek-card borsatek-stats-half">
            <h2 class="borsatek-card-title">
                <span class="dashicons dashicons-chart-pie"></span>
                Sağlayıcı Dağılımı
            </h2>

            <table class="borsatek-queue-table wp-list-table widefat">
                <thead>
                    <tr>
                        <th>Sağlayıcı</th>
                        <th>İşlem Sayısı</th>
                        <th>Oran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $providerNames = [
                        'anthropic' => 'Anthropic Claude',
                        'gemini'    => 'Google Gemini',
                        'openai'    => 'OpenAI',
                        'together'  => 'Together AI',
                        'rss'       => 'RSS (indeks)',
                    ];
                    $totalProcessed = max( 1, $monthlyReport['total'] );
                    $allProviders   = array_merge(
                        array_fill_keys( array_keys( $providerNames ), 0 ),
                        $byProvider
                    );
                    arsort( $allProviders );
                    foreach ( $allProviders as $pKey => $pCount ) :
                        if ( $pCount === 0 && ! isset( $providerNames[ $pKey ] ) ) continue;
                        $pName  = $providerNames[ $pKey ] ?? ucfirst( $pKey );
                        $pRatio = $pCount > 0 ? round( $pCount / $totalProcessed * 100 ) : 0;
                    ?>
                        <tr>
                            <td><?php echo esc_html( $pName ); ?></td>
                            <td><?php echo esc_html( $pCount ); ?></td>
                            <td>
                                <div class="borsatek-ratio-bar">
                                    <div class="borsatek-ratio-fill" style="width:<?php echo esc_attr( $pRatio ); ?>%"></div>
                                </div>
                                <small><?php echo esc_html( $pRatio ); ?>%</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- En Aktif 5 Kaynak -->
        <div class="borsatek-card borsatek-stats-half">
            <h2 class="borsatek-card-title">
                <span class="dashicons dashicons-rss"></span>
                En Aktif 5 Kaynak
            </h2>

            <?php if ( empty( $topFeeds ) ) : ?>
                <p class="description">Henüz veri yok.</p>
            <?php else : ?>
                <table class="borsatek-queue-table wp-list-table widefat">
                    <thead>
                        <tr>
                            <th>Kaynak</th>
                            <th>Haber Sayısı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $topFeeds as $feedUrl => $feedCount ) :
                            $feedHost = parse_url( $feedUrl, PHP_URL_HOST ) ?: $feedUrl;
                        ?>
                            <tr>
                                <td title="<?php echo esc_attr( $feedUrl ); ?>">
                                    <?php echo esc_html( $feedHost ); ?>
                                </td>
                                <td><strong><?php echo esc_html( $feedCount ); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

    <!-- 30 Günlük Grafik -->
    <div class="borsatek-card">
        <h2 class="borsatek-card-title">
            <span class="dashicons dashicons-chart-line"></span>
            Son 30 Günlük Aktivite
        </h2>
        <canvas id="borsatek-daily-chart" height="80"></canvas>
    </div>

    <!-- İstatistikleri Sıfırla -->
    <div class="borsatek-card borsatek-danger-zone">
        <h2 class="borsatek-card-title" style="color:#B71C1C;">
            <span class="dashicons dashicons-trash"></span>
            Tehlikeli Bölge
        </h2>
        <p>Tüm istatistik verilerini sıfırlar. Bu işlem geri alınamaz.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
              onsubmit="return confirm('Tüm istatistikler silinecek. Emin misiniz?')">
            <?php wp_nonce_field( 'borsatek_reset_stats' ); ?>
            <input type="hidden" name="action" value="borsatek_reset_stats">
            <button type="submit" class="button borsatek-btn-danger">
                İstatistikleri Sıfırla
            </button>
        </form>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var borsatekDailyData = <?php echo wp_json_encode( $dailyChart ); ?>;
</script>
