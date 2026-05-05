/* global borsatekData, borsatekDailyData, Chart */
jQuery( document ).ready( function ( $ ) {

    // ── 1. Şimdi Tara ────────────────────────────────────────────────────────
    $( '#borsatek-force-scan-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        var $btn = $( this );
        $btn.prop( 'disabled', true );
        $( '#borsatek-scan-spinner' ).show();

        $.post( borsatekData.ajaxUrl, {
            action: 'borsatek_force_scan',
            nonce:  borsatekData.nonce
        }, function ( res ) {
            $btn.prop( 'disabled', false );
            $( '#borsatek-scan-spinner' ).hide();
            if ( res.success ) {
                location.reload();
            } else {
                alert( 'Tarama hatası: ' + ( res.data && res.data.message ? res.data.message : 'Bilinmeyen hata' ) );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false );
            $( '#borsatek-scan-spinner' ).hide();
            alert( 'Sunucu hatası oluştu. Lütfen tekrar deneyin.' );
        } );
    } );

    // ── 2. Önizleme ───────────────────────────────────────────────────────────
    $( document ).on( 'click', '.borsatek-preview-btn', function ( e ) {
        e.preventDefault();
        var $btn         = $( this );
        var queueId      = $btn.data( 'queue-id' );
        var focusKeyword = $btn.data( 'focus-keyword' ) || '';

        $btn.prop( 'disabled', true ).text( 'İşleniyor...' );

        $.post( borsatekData.ajaxUrl, {
            action:        'borsatek_preview',
            nonce:         borsatekData.nonce,
            queue_id:      queueId,
            focus_keyword: focusKeyword
        }, function ( res ) {
            $btn.prop( 'disabled', false ).text( 'Önizle' );

            if ( res.success ) {
                var d          = res.data;
                var score      = d.seoScore ? d.seoScore.score : 0;
                var scoreColor = score >= 80 ? '#1B5E20' : ( score >= 60 ? '#E65100' : '#B71C1C' );

                $( '#borsatek-preview-title' ).text( d.title || '' );
                $( '#borsatek-preview-score' ).html(
                    '<span style="color:' + scoreColor + ';font-weight:bold;font-size:20px;">' +
                    score + '/100</span>'
                );
                $( '#borsatek-preview-meta' ).text( d.metaDescription || '' );

                var bodyPreview = d.body ? d.body.replace( /<[^>]+>/g, '' ).substring( 0, 500 ) + '...' : '';
                $( '#borsatek-preview-body' ).text( bodyPreview );

                var passedHtml = '';
                var failedHtml = '';
                if ( d.seoScore ) {
                    $.each( d.seoScore.passed || [], function ( i, p ) {
                        passedHtml += '<li style="color:#1B5E20">✓ ' + $( '<div>' ).text( p ).html() + '</li>';
                    } );
                    $.each( d.seoScore.failed || [], function ( i, f ) {
                        failedHtml += '<li style="color:#B71C1C">✗ ' + $( '<div>' ).text( f ).html() + '</li>';
                    } );
                }
                $( '#borsatek-preview-rules' ).html( '<ul>' + passedHtml + failedHtml + '</ul>' );
                $( '#borsatek-preview-modal' ).fadeIn( 200 );

            } else {
                var errMsg = res.data && res.data.message ? res.data.message : 'Bilinmeyen hata';
                alert( 'Önizleme hatası: ' + errMsg );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false ).text( 'Önizle' );
            alert( 'Sunucu hatası oluştu.' );
        } );
    } );

    // Modal kapat
    $( '#borsatek-preview-modal-close, #borsatek-preview-modal' ).on( 'click', function ( e ) {
        if ( $( e.target ).is( '#borsatek-preview-modal' ) || $( e.target ).is( '#borsatek-preview-modal-close' ) ) {
            $( '#borsatek-preview-modal' ).fadeOut( 200 );
        }
    } );

    // ESC tuşuyla modal kapat
    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) {
            $( '#borsatek-preview-modal' ).fadeOut( 200 );
        }
    } );

    // ── 3. Toplu Dönüştürme ───────────────────────────────────────────────────
    $( 'input[name="borsatek_select_all"]' ).on( 'change', function () {
        $( 'input.borsatek-queue-checkbox' ).prop( 'checked', $( this ).is( ':checked' ) );
        updateBulkBtn();
    } );

    $( document ).on( 'change', 'input.borsatek-queue-checkbox', updateBulkBtn );

    function updateBulkBtn() {
        var checked = $( 'input.borsatek-queue-checkbox:checked' ).length;
        $( '#borsatek-bulk-convert-btn' )
            .prop( 'disabled', checked === 0 )
            .text( checked > 0 ? checked + ' Haberi Dönüştür' : 'Seçilenleri Dönüştür' );
        $( '#borsatek-bulk-delete-btn' )
            .prop( 'disabled', checked === 0 )
            .text( checked > 0 ? checked + ' Haberi Sil' : 'Seçilenleri Sil' );
    }

    $( '#borsatek-bulk-convert-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        var ids = $( 'input.borsatek-queue-checkbox:checked' ).map( function () {
            return $( this ).val();
        } ).get();

        if ( ! ids.length ) return;
        if ( ! confirm( ids.length + ' haber dönüştürülecek. Bu işlem biraz zaman alabilir. Emin misiniz?' ) ) return;

        var $btn      = $( this ).prop( 'disabled', true );
        var $progress = $( '#borsatek-bulk-progress' ).show();
        var done      = 0;
        var errors    = 0;

        function processNext() {
            if ( done >= ids.length ) {
                $progress.text( 'Tamamlandı! ' + ( ids.length - errors ) + ' başarılı, ' + errors + ' hatalı.' );
                setTimeout( function () {
                    location.reload();
                }, 2000 );
                return;
            }

            $progress.text( 'İşleniyor: ' + ( done + 1 ) + ' / ' + ids.length );

            $.post( borsatekData.ajaxUrl, {
                action:    'borsatek_bulk_convert',
                nonce:     borsatekData.nonce,
                queue_ids: [ ids[ done ] ]
            }, function ( res ) {
                if ( res.success && res.data && res.data.results ) {
                    $.each( res.data.results, function ( i, r ) {
                        if ( ! r.success ) errors++;
                    } );
                }
                done++;
                processNext();
            } ).fail( function () {
                errors++;
                done++;
                processNext();
            } );
        }

        processNext();
    } );

    // ── 3b. Toplu Silme ───────────────────────────────────────────────────────
    $( '#borsatek-bulk-delete-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        var ids = $( 'input.borsatek-queue-checkbox:checked' ).map( function () {
            return $( this ).val();
        } ).get();

        if ( ! ids.length ) return;
        if ( ! confirm( ids.length + ' haber kalıcı olarak silinecek. Emin misiniz?' ) ) return;

        var $btn      = $( this ).prop( 'disabled', true );
        var $progress = $( '#borsatek-bulk-progress' ).show().text( 'Siliniyor...' );

        $.post( borsatekData.ajaxUrl, {
            action:    'borsatek_bulk_delete',
            nonce:     borsatekData.nonce,
            queue_ids: ids
        }, function ( res ) {
            $btn.prop( 'disabled', false );
            if ( res.success ) {
                $progress.text( ( res.data && res.data.deleted ? res.data.deleted : ids.length ) + ' öğe silindi.' );
                setTimeout( function () { location.reload(); }, 1200 );
            } else {
                $progress.text( 'Silme hatası: ' + ( res.data && res.data.message ? res.data.message : 'Bilinmeyen' ) );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false );
            $progress.text( 'Sunucu hatası oluştu.' );
        } );
    } );

    // ── 4. Async Durum Polling ────────────────────────────────────────────────
    var asyncJobIdEl = document.getElementById( 'borsatek-async-job-id' );
    var asyncJobId   = asyncJobIdEl ? parseInt( asyncJobIdEl.value || '0', 10 ) : 0;

    if ( asyncJobId > 0 ) {
        $( '#borsatek-async-status' ).show();

        var pollInterval = setInterval( function () {
            $.post( borsatekData.ajaxUrl, {
                action:  'borsatek_async_status',
                nonce:   borsatekData.nonce,
                job_id:  asyncJobId
            }, function ( res ) {
                if ( ! res.success ) return;

                var status = res.data.status;

                if ( status === 'done' ) {
                    clearInterval( pollInterval );
                    var editLink = res.data.editUrl
                        ? ' <a href="' + res.data.editUrl + '">Taslağı Düzenle →</a>'
                        : '';
                    $( '#borsatek-async-status' ).html(
                        '<span style="color:#1B5E20;font-weight:bold;">✓ Tamamlandı!</span>' + editLink
                    ).removeClass( 'borsatek-async-notice' ).addClass( 'borsatek-async-success' );
                } else if ( status === 'error' ) {
                    clearInterval( pollInterval );
                    $( '#borsatek-async-status' ).html(
                        '<span style="color:#B71C1C;">✗ Hata: ' +
                        $( '<div>' ).text( res.data.error || 'Bilinmeyen' ).html() + '</span>'
                    ).addClass( 'borsatek-async-error' );
                } else {
                    $( '#borsatek-async-status-text' ).text( 'İşleniyor... (durum: ' + status + ')' );
                }
            } );
        }, 3000 );
    }

    // ── 5. Gemini Model Listesi ───────────────────────────────────────────────
    $( '#borsatek-fetch-models-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        var apiKey = $( 'input[name="borsatek_gemini_key"]' ).val();
        if ( ! apiKey ) {
            alert( 'Önce Gemini API anahtarı girin.' );
            return;
        }

        var $btn = $( this ).prop( 'disabled', true ).text( 'Alınıyor...' );

        $.post( borsatekData.ajaxUrl, {
            action:  'borsatek_fetch_gemini_models',
            nonce:   borsatekData.nonce,
            api_key: apiKey
        }, function ( res ) {
            $btn.prop( 'disabled', false ).text( 'Modelleri Getir' );

            if ( res.success && res.data && res.data.models && res.data.models.length ) {
                var $select    = $( '#borsatek_gemini_model_select' );
                var currentVal = $select.val();
                $select.empty();

                $.each( res.data.models, function ( i, m ) {
                    $select.append( '<option value="' + m + '">' + m + '</option>' );
                } );

                // Önceki değeri koru
                if ( currentVal ) {
                    $select.val( currentVal );
                }
                alert( res.data.models.length + ' model listelendi.' );
            } else {
                alert( 'Model listesi alınamadı. API anahtarını kontrol edin.' );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false ).text( 'Modelleri Getir' );
            alert( 'Sunucu hatası.' );
        } );
    } );

    // Gemini bağlantı testi
    $( '#borsatek-test-gemini-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        var $result = $( '#borsatek-gemini-test-result' ).text( 'Test ediliyor...' ).css( 'color', '#555' );
        var $btn    = $( this ).prop( 'disabled', true );

        $.post( borsatekData.ajaxUrl, {
            action:   'borsatek_test_ai_connection',
            nonce:    borsatekData.nonce,
            provider: 'gemini',
            api_key:  $( 'input[name="borsatek_gemini_key"]' ).val(),
            model:    $( '#borsatek_gemini_model_select' ).val()
        }, function ( res ) {
            $btn.prop( 'disabled', false );
            if ( res.success ) {
                $result.text( '✓ Bağlantı başarılı' ).css( 'color', '#1B5E20' );
            } else {
                var errMsg = res.data && res.data.message ? res.data.message : 'Hata';
                $result.text( '✗ Hata: ' + errMsg ).css( 'color', '#B71C1C' );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false );
            $result.text( '✗ Sunucu hatası' ).css( 'color', '#B71C1C' );
        } );
    } );

    // Jina Reader bağlantı testi
    $( '#borsatek-test-jina-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        var $result = $( '#borsatek-jina-test-result' ).text( 'Test ediliyor...' ).css( 'color', '#555' );
        var $btn    = $( this ).prop( 'disabled', true );

        $.post( borsatekData.ajaxUrl, {
            action:   'borsatek_test_ai_connection',
            nonce:    borsatekData.nonce,
            provider: 'jina',
            api_key:  $( '#borsatek_jina_key' ).val()
        }, function ( res ) {
            $btn.prop( 'disabled', false );
            if ( res.success ) {
                $result.text( '✓ Jina bağlantısı başarılı' ).css( 'color', '#1B5E20' );
            } else {
                var errMsg = res.data && res.data.message ? res.data.message : 'Hata';
                $result.text( '✗ Hata: ' + errMsg ).css( 'color', '#B71C1C' );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false );
            $result.text( '✗ Sunucu hatası' ).css( 'color', '#B71C1C' );
        } );
    } );

    // Anthropic bağlantı testi
    $( '#borsatek-test-anthropic-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        var $result = $( '#borsatek-anthropic-test-result' ).text( 'Test ediliyor...' ).css( 'color', '#555' );
        var $btn    = $( this ).prop( 'disabled', true );

        $.post( borsatekData.ajaxUrl, {
            action:   'borsatek_test_ai_connection',
            nonce:    borsatekData.nonce,
            provider: 'anthropic',
            api_key:  $( 'input[name="borsatek_anthropic_key"]' ).val(),
            model:    $( 'input[name="borsatek_anthropic_model"]' ).val()
        }, function ( res ) {
            $btn.prop( 'disabled', false );
            if ( res.success ) {
                $result.text( '✓ Bağlantı başarılı' ).css( 'color', '#1B5E20' );
            } else {
                var errMsg = res.data && res.data.message ? res.data.message : 'Hata';
                $result.text( '✗ Hata: ' + errMsg ).css( 'color', '#B71C1C' );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false );
            $result.text( '✗ Sunucu hatası' ).css( 'color', '#B71C1C' );
        } );
    } );

    // ── 6. Troubleshoot Açıkla ────────────────────────────────────────────────
    $( '#borsatek-explain-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        var errorText = $( '#borsatek-error-text' ).val().trim();
        if ( ! errorText ) {
            alert( 'Açıklanacak hata metnini girin.' );
            return;
        }

        var $btn = $( this ).prop( 'disabled', true ).text( 'Açıklanıyor...' );
        $( '#borsatek-explain-spinner' ).show();

        $.post( borsatekData.ajaxUrl, {
            action:     'borsatek_troubleshoot_explain',
            nonce:      borsatekData.nonce,
            error_text: errorText
        }, function ( res ) {
            $btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-search"></span> AI ile Açıkla' );
            $( '#borsatek-explain-spinner' ).hide();

            if ( res.success ) {
                $( '#borsatek-explain-result' ).html(
                    '<div style="background:#f0f8ff;padding:14px 16px;border-left:4px solid #1565C0;border-radius:3px;line-height:1.7;">' +
                    res.data.explanation +
                    '</div>'
                );
            } else {
                var errMsg = res.data && res.data.message ? res.data.message : 'Bilinmeyen hata';
                $( '#borsatek-explain-result' ).html(
                    '<div style="color:#B71C1C;">Hata: ' + $( '<div>' ).text( errMsg ).html() + '</div>'
                );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-search"></span> AI ile Açıkla' );
            $( '#borsatek-explain-spinner' ).hide();
            $( '#borsatek-explain-result' ).html( '<div style="color:#B71C1C;">Sunucu hatası oluştu.</div>' );
        } );
    } );

    // ── 7. Stats Grafik ───────────────────────────────────────────────────────
    if ( typeof borsatekDailyData !== 'undefined' && document.getElementById( 'borsatek-daily-chart' ) ) {
        var ctx = document.getElementById( 'borsatek-daily-chart' ).getContext( '2d' );
        new Chart( ctx, {
            type: 'line',
            data: {
                labels: borsatekDailyData.map( function ( d ) { return d.date; } ),
                datasets: [ {
                    label:           'İşlenen Haber',
                    data:            borsatekDailyData.map( function ( d ) { return d.count; } ),
                    borderColor:     '#1565C0',
                    backgroundColor: 'rgba(21,101,192,0.08)',
                    borderWidth:     2,
                    pointRadius:     3,
                    tension:         0.3,
                    fill:            true
                } ]
            },
            options: {
                responsive:  true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function ( ctx ) {
                                return ' ' + ctx.parsed.y + ' haber';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        } );
    }

    // ── 8. Webhook Token Oluştur ──────────────────────────────────────────────
    $( '#borsatek-gen-webhook-token' ).on( 'click', function ( e ) {
        e.preventDefault();
        var token = '';
        var chars = '0123456789abcdef';
        for ( var i = 0; i < 32; i++ ) {
            token += chars[ Math.floor( Math.random() * chars.length ) ];
        }
        $( 'input[name="borsatek_webhook_token"]' ).val( token );
    } );

    // ── 9. Filtre Formu: Enter Tuşu ───────────────────────────────────────────
    $( '.borsatek-filter-input' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) {
            $( this ).closest( 'form' ).submit();
        }
    } );

    // ── 10. Satır Renk Güncellemesi (running durumu) ──────────────────────────
    $( '.borsatek-row-running' ).each( function () {
        $( this ).css( 'background-color', '#fffde7' );
    } );

} );
