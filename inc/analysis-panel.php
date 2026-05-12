<?php
/**
 * Analysis Slide-in Panel
 * Kitap analiz yazıları için ortalanmış modal okuma paneli
 *
 * @package Mediumish / TheTelos
 */

// -----------------------------------------------------
// CSS Enqueue
// -----------------------------------------------------
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular( 'post' ) ) return;

    wp_enqueue_style(
        'thetelos-analysis-panel',
        get_template_directory_uri() . '/inc/analysis-panel.css',
        [],
        '1.1'
    );
});

// -----------------------------------------------------
// Modal HTML (footer'a ekle)
// -----------------------------------------------------
add_action( 'wp_footer', function() {
    if ( ! is_singular( 'post' ) ) return;

    global $post;
    $analysis = thetelos_get_analysis_for_post( $post->ID );
    if ( ! $analysis ) return;

    $content = apply_filters( 'the_content', $analysis->post_content );
    ?>

    <!-- Analysis Overlay -->
    <div id="analysis-overlay"></div>

    <!-- Analysis Modal -->
    <div id="analysis-panel" role="dialog" aria-modal="true" aria-label="Book Analysis">
        <div class="panel-header">
            <div class="panel-header-top">
                <div class="panel-label">Deep Analysis</div>
                <button class="panel-close" id="analysis-close" aria-label="Close">&#x2715;</button>
            </div>
            <div class="panel-title"><?php echo esc_html( $analysis->post_title ); ?></div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div class="panel-header-actions">
                <a href="#" class="thetelos-action-btn" onclick="thelosPrint(); return false;" title="Print">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/>
                    </svg>
                </a>
                <a href="#" class="thetelos-action-btn" id="thetelos-pdf-btn" title="Save as PDF">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/>
                    </svg>
                </a>
                </div>
                <?php if ( function_exists('thetelos_analysis_reading_time') ) : ?>
                <span style="font-size:12px;color:#aaa;font-family:sans-serif;white-space:nowrap;">
                    <?php echo esc_html( thetelos_analysis_reading_time( $analysis ) ); ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel-content">
            <?php echo $content; ?>
        </div>

    </div>

    <script>
    (function() {
        var panel   = document.getElementById('analysis-panel');
        var overlay = document.getElementById('analysis-overlay');
        var trigger = document.getElementById('analysis-trigger');
        var close1  = document.getElementById('analysis-close');
        var close2  = document.getElementById('analysis-close-footer');
        var pdfBtn  = document.getElementById('thetelos-pdf-btn');

        function openPanel() {
            overlay.style.display = 'block';
            panel.style.display = 'flex';
            setTimeout(function() {
                panel.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }, 10);
        }

        function closePanel() {
            panel.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(function() {
                overlay.style.display = 'none';
                panel.style.display = 'none';
            }, 380);
        }

        // Print fonksiyonu
        window.thelosPrint = function() {
            var titleEl   = panel.querySelector('.panel-title');
            var contentEl = panel.querySelector('.panel-content');
            var title     = titleEl ? titleEl.innerText : 'Analysis';
            thelosOpenPrintWindow(title, contentEl.innerHTML);
        };

        // PDF / Print — temiz yeni pencere
        function thelosOpenPrintWindow(title, contentHTML) {
            var printWindow = window.open('', '_blank');
            printWindow.document.write('<!DOCTYPE html><html><head>' +
                '<meta charset="UTF-8">' +
                '<title>' + title + ' \u2014 thetelos.org</title>' +
                '<style>' +
                    '@page { margin: 2cm; }' +
                    'body { font-family: Georgia, serif; font-size: 12pt; line-height: 1.8; color: #2a2a2a; max-width: 680px; margin: 0 auto; padding: 0; }' +
                    '.thetelos-label { font-size: 9pt; letter-spacing: 0.2em; text-transform: uppercase; color: #aaa; font-family: sans-serif; margin-bottom: 10px; }' +
                    'h1 { font-size: 20pt; font-weight: 700; color: #1a1a1a; margin: 0 0 28px 0; line-height: 1.3; }' +
                    'h2, h3, h4 { font-family: Georgia, serif; margin-top: 1.6em; margin-bottom: 0.5em; color: #1a1a1a; }' +
                    'p { margin-bottom: 1.2em; }' +
                    'blockquote { border-left: 3px solid #ddd; margin-left: 0; padding-left: 20px; color: #555; font-style: italic; }' +
                    '.thetelos-footer { margin-top: 48px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 10pt; color: #888; font-family: sans-serif; text-align: center; }' +
                '</style>' +
                '</head><body>' +
                '<div class="thetelos-label">Deep Analysis \u2014 thetelos.org</div>' +
                '<h1>' + title + '</h1>' +
                contentHTML +
                '<div class="thetelos-footer">This is an excerpt. Full summary and deep analysis available at thetelos.org</div>' +
                '</body></html>'
            );
            printWindow.document.close();
            printWindow.focus();
            setTimeout(function() { printWindow.print(); }, 600);
        }

        // PDF butonu
        if (pdfBtn) {
            pdfBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var titleEl   = panel.querySelector('.panel-title');
                var contentEl = panel.querySelector('.panel-content');
                var title     = titleEl ? titleEl.innerText : 'Analysis';
                thelosOpenPrintWindow(title, contentEl.innerHTML);
            });
        }

        if (trigger) trigger.addEventListener('click', function(e) { e.preventDefault(); openPanel(); });
        if (close1)  close1.addEventListener('click', closePanel);
        if (close2)  close2.addEventListener('click', function(e) { e.preventDefault(); closePanel(); });
        if (overlay) overlay.addEventListener('click', closePanel);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closePanel();
        });

        if (window.location.hash === '#deep-analysis') {
            setTimeout(openPanel, 300);
            history.replaceState(null, '', window.location.pathname);
        }
    })();
    </script>

    <?php
});

// -----------------------------------------------------
// Trigger Butonu: Yazar adının yanına ekle (single.php'deki yazar satırına)
// -----------------------------------------------------
add_action( 'wp_head', function() {
    if ( ! is_singular( 'post' ) ) return;

    global $post;
    $analysis = thetelos_get_analysis_for_post( $post->ID );
    if ( ! $analysis ) return;
    ?>
    <style>
        .post-top-meta .analysis-trigger-btn,
        .mainheading h5 + .analysis-trigger-btn {
            margin-left: 10px;
        }
    </style>
    <?php
});

// İçeriğin başına değil — yazar adının hemen ardından buton eklemek için
// single.php'de yazar adı h5 tagı içinde, ona hook atalım
