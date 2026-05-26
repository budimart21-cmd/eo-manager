<?php
/**
 * EO Display Settings
 * - Sidebar Layout, Footer Widgets, Container, Disable Elements (global & per-post)
 * - Hide title, meta, tag, category, komentar, breadcrumb
 * - Featured Image Square untuk eo_product (Chapter 5)
 */
class EO_Display_Settings {

    public static function init() {
        add_action( 'add_meta_boxes',  [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post',       [ __CLASS__, 'save_meta_box' ] );
        add_action( 'admin_menu',      [ __CLASS__, 'add_display_menu' ] );
        add_action( 'admin_init',      [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_post_eo_save_display_settings', [ __CLASS__, 'save_global_settings' ] );
        add_action( 'wp',              [ __CLASS__, 'apply_frontend' ] );
        // Square image: filter thumbnail size untuk eo_product
        add_filter( 'post_thumbnail_size', [ __CLASS__, 'maybe_square_thumbnail' ] );
        // Square CSS di frontend
        add_action( 'wp_head',         [ __CLASS__, 'square_image_css' ] );
    }

    /* =========================================================
       REGISTER SETTINGS
       ========================================================= */
    public static function register_settings() {
        $fields = [
            'eo_gp_sidebar_layout', 'eo_gp_footer_widgets', 'eo_gp_content_container',
            'eo_gp_disable_top_bar', 'eo_gp_disable_header', 'eo_gp_disable_mobile_header',
            'eo_gp_disable_primary_nav', 'eo_gp_disable_featured_image',
            'eo_gp_disable_content_title', 'eo_gp_disable_footer',
            // Square image options (Chapter 5)
            'eo_square_image_enabled',
            'eo_square_image_size',
            'eo_square_image_scope',
        ];
        foreach ( $fields as $f ) {
            register_setting( 'eo_display_group', $f );
        }
    }

    /* =========================================================
       FILTER: Ganti thumbnail size ke square saat render eo_product
       Hanya aktif jika:
       - eo_square_image_enabled = 1
       - scope = 'eo_product' (default) atau 'all'
       ========================================================= */
    public static function maybe_square_thumbnail( $size ) {
        if ( ! get_option('eo_square_image_enabled', '0') ) return $size;

        $scope = get_option('eo_square_image_scope', 'eo_product');

        if ( $scope === 'all' ) {
            return 'eo-product-square';
        }

        // Scope = eo_product saja
        if ( is_singular('eo_product') || get_post_type() === 'eo_product' ) {
            return 'eo-product-square';
        }

        return $size;
    }

    /* =========================================================
       CSS FRONTEND: Pastikan gambar tampil square via CSS
       Ini safety net jika tema sudah render sebelum filter aktif,
       atau gambar belum di-regenerate
       ========================================================= */
    public static function square_image_css() {
        if ( ! get_option('eo_square_image_enabled', '0') ) return;

        $scope = get_option('eo_square_image_scope', 'eo_product');
        $size  = (int) get_option('eo_square_image_size', 600);
        if ( $size < 100 ) $size = 600;

        // Tentukan selector berdasarkan scope
        if ( $scope === 'eo_product' ) {
            // Hanya pada singular eo_product
            if ( ! is_singular('eo_product') ) return;
            $selector = '.post-thumbnail img, .wp-post-image, .generate-featured-image img, .post-image img';
        } else {
            $selector = '.post-thumbnail img, .wp-post-image, .generate-featured-image img, .post-image img';
        }
        ?>
        <style id="eo-square-image-css">
        <?php echo esc_html($selector); ?> {
            width: 100% !important;
            aspect-ratio: 1 / 1 !important;
            object-fit: cover !important;
            object-position: center !important;
            display: block;
            border-radius: <?php echo (int) get_option('eo_square_image_radius', 0); ?>px;
        }
        /* GeneratePress featured image container */
        .generate-featured-image,
        .post-thumbnail {
            overflow: hidden;
            line-height: 0;
        }
        </style>
        <?php
    }

    /* =========================================================
       META BOX — per post/page/produk
       ========================================================= */
    public static function add_meta_box() {
        add_meta_box(
            'eo_display_settings',
            '👁️ Pengaturan Tampilan Halaman Ini',
            [ __CLASS__, 'render_meta_box' ],
            [ 'post', 'page', 'eo_product' ],
            'side',
            'default'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'eo_display_settings_save', 'eo_display_nonce' );
        $saved = get_post_meta( $post->ID, '_eo_display_settings', true ) ?: [];

        $disable_opts = [
            'hide-title'          => 'Sembunyikan Judul (Title)',
            'hide-meta'           => 'Sembunyikan Meta (author, date)',
            'hide-footer-meta'    => 'Sembunyikan Footer Meta',
            'hide-categories'     => 'Sembunyikan Kategori',
            'hide-tags'           => 'Sembunyikan Tag',
            'hide-comments'       => 'Sembunyikan Komentar',
            'hide-breadcrumb'     => 'Sembunyikan Breadcrumb',
            'hide-featured-image' => 'Sembunyikan Featured Image',
        ];

        $sb_val = $saved['sidebar_layout']    ?? 'default';
        $fw_val = $saved['footer_widgets']    ?? 'default';
        $cc_val = $saved['content_container'] ?? 'default';
        ?>
        <style>
        .eo-mb-label { font-size:11px; font-weight:700; color:#64748b; display:block; margin:8px 0 4px; text-transform:uppercase; letter-spacing:.04em; }
        .eo-mb-select { width:100%; padding:5px 8px; border:1px solid #ddd; border-radius:4px; font-size:12px; }
        </style>

        <p style="font-size:12px;color:#64748b;margin:0 0 10px;">
            Override setting khusus untuk halaman ini saja.
        </p>

        <span class="eo-mb-label">Sidebar Layout</span>
        <select name="eo_display[sidebar_layout]" class="eo-mb-select">
            <?php foreach ([
                'default'      => 'Default (Global)',
                'no-sidebar'   => 'No Sidebar',
                'right-sidebar'=> 'Content / Sidebar',
                'left-sidebar' => 'Sidebar / Content',
                'both-sidebars'=> 'Both Sidebars',
            ] as $v => $l) : ?>
            <option value="<?php echo $v; ?>" <?php selected($sb_val,$v); ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
        </select>

        <span class="eo-mb-label">Footer Widgets</span>
        <select name="eo_display[footer_widgets]" class="eo-mb-select">
            <?php foreach (['default'=>'Default','0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5'] as $v=>$l) : ?>
            <option value="<?php echo $v; ?>" <?php selected($fw_val,$v); ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
        </select>

        <span class="eo-mb-label">Content Container</span>
        <select name="eo_display[content_container]" class="eo-mb-select">
            <?php foreach (['default'=>'Default','full-width'=>'Full Width','contained'=>'Contained'] as $v=>$l) : ?>
            <option value="<?php echo $v; ?>" <?php selected($cc_val,$v); ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
        </select>

        <span class="eo-mb-label" style="margin-top:12px;">Disable / Sembunyikan</span>
        <div style="font-size:12px;">
        <?php foreach ( $disable_opts as $key => $label ) :
            $checked = ! empty( $saved[$key] ) ? 'checked' : '';
        ?>
            <label style="display:block;margin-bottom:5px;">
                <input type="checkbox"
                       name="eo_display[<?php echo esc_attr($key); ?>]"
                       value="1" <?php echo $checked; ?>>
                <?php echo esc_html($label); ?>
            </label>
        <?php endforeach; ?>
        </div>
        <p style="font-size:10px;color:#999;margin-top:8px;">
            Setting global ada di
            <a href="<?php echo admin_url('admin.php?page=eo-display'); ?>">Menu Tampilan</a>.
        </p>
        <?php
    }

    public static function save_meta_box( $post_id ) {
        if ( ! isset($_POST['eo_display_nonce']) ) return;
        if ( ! wp_verify_nonce($_POST['eo_display_nonce'], 'eo_display_settings_save') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        $data = [];
        if ( isset($_POST['eo_display']) && is_array($_POST['eo_display']) ) {
            foreach ( $_POST['eo_display'] as $k => $v ) {
                $key = sanitize_key($k);
                $data[$key] = in_array($key, ['sidebar_layout','footer_widgets','content_container'])
                    ? sanitize_text_field($v)
                    : (bool)$v;
            }
        }
        foreach (['sidebar_layout','footer_widgets','content_container'] as $sel) {
            if ( ! isset($data[$sel]) ) $data[$sel] = 'default';
        }
        update_post_meta( $post_id, '_eo_display_settings', $data );
    }

    /* =========================================================
       HALAMAN PENGATURAN GLOBAL
       ========================================================= */
    public static function add_display_menu() {
        add_submenu_page(
            'eo-manager',
            'Tampilan & Layout',
            '👁️ Tampilan',
            'manage_options',
            'eo-display',
            [ __CLASS__, 'render_global_page' ]
        );
    }

    public static function render_global_page() {
        if ( ! current_user_can('manage_options') ) return;
        $sq_enabled = get_option('eo_square_image_enabled', '0');
        $sq_size    = (int) get_option('eo_square_image_size', 600);
        $sq_scope   = get_option('eo_square_image_scope', 'eo_product');
        $sq_radius  = (int) get_option('eo_square_image_radius', 0);
        ?>
        <div class="wrap">
        <h1>👁️ Pengaturan Tampilan & Layout Global</h1>
        <p style="color:#555;max-width:700px;">
            Berlaku untuk <strong>semua halaman/post</strong> secara global.
            Per-halaman dapat di-override via meta box di editor masing-masing.
        </p>

        <?php if ( isset($_GET['saved']) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>✅ Pengaturan berhasil disimpan.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>"
              style="max-width:700px;">
            <input type="hidden" name="action" value="eo_save_display_settings">
            <?php wp_nonce_field('eo_save_display_settings'); ?>

            <?php
            $sb  = get_option('eo_gp_sidebar_layout','default');
            $fw  = get_option('eo_gp_footer_widgets','default');
            $cc  = get_option('eo_gp_content_container','default');
            $cm  = get_option('eo_comment_mode','default');
            $dsp = get_option('eo_display_settings', []);

            $card_style = 'background:#fff;padding:22px 24px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:20px;';
            $h2_style   = 'font-size:15px;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin:0 0 16px;';
            ?>

            <!-- ── GeneratePress Layout ── -->
            <div style="<?php echo $card_style; ?>">
                <h2 style="<?php echo $h2_style; ?>">🗂️ GeneratePress Layout (Global)</h2>
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th style="width:220px;">Sidebar Layout</th>
                        <td>
                            <select name="eo_gp_sidebar_layout" style="width:260px;">
                                <?php foreach ([
                                    'default'       => 'Default (sesuai tema)',
                                    'no-sidebar'    => 'No Sidebars',
                                    'right-sidebar' => 'Content / Sidebar',
                                    'left-sidebar'  => 'Sidebar / Content',
                                    'both-sidebars' => 'Sidebar / Content / Sidebar',
                                    'both-left'     => 'Sidebar / Sidebar / Content',
                                    'both-right'    => 'Content / Sidebar / Sidebar',
                                ] as $v => $l) : ?>
                                <option value="<?php echo $v; ?>" <?php selected($sb,$v); ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Footer Widgets</th>
                        <td>
                            <select name="eo_gp_footer_widgets" style="width:260px;">
                                <?php foreach (['default'=>'Default','0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5'] as $v=>$l) : ?>
                                <option value="<?php echo $v; ?>" <?php selected($fw,$v); ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Content Container</th>
                        <td>
                            <select name="eo_gp_content_container" style="width:260px;">
                                <?php foreach (['default'=>'Default','full-width'=>'Full Width','contained'=>'Contained'] as $v=>$l) : ?>
                                <option value="<?php echo $v; ?>" <?php selected($cc,$v); ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── Disable Elements ── -->
            <div style="<?php echo $card_style; ?>">
                <h2 style="<?php echo $h2_style; ?>">🚫 Disable Elements (Global)</h2>
                <table class="form-table" style="margin-top:0;">
                    <?php
                    $gp_disables = [
                        'eo_gp_disable_top_bar'       => 'Top Bar',
                        'eo_gp_disable_header'         => 'Header',
                        'eo_gp_disable_mobile_header'  => 'Mobile Header',
                        'eo_gp_disable_primary_nav'    => 'Primary Navigation',
                        'eo_gp_disable_featured_image' => 'Featured Image',
                        'eo_gp_disable_content_title'  => 'Content Title (H1)',
                        'eo_gp_disable_footer'         => 'Footer',
                    ];
                    foreach ($gp_disables as $opt => $label) : ?>
                    <tr>
                        <th><?php echo esc_html($label); ?></th>
                        <td>
                            <input type="checkbox"
                                   name="<?php echo $opt; ?>"
                                   value="1"
                                   <?php checked(get_option($opt), 1); ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- ── EO Custom Elements ── -->
            <div style="<?php echo $card_style; ?>">
                <h2 style="<?php echo $h2_style; ?>">✏️ Sembunyikan Elemen Konten (Global)</h2>
                <table class="form-table" style="margin-top:0;">
                    <?php
                    $eo_disables = [
                        'hide_title_globally'      => 'Sembunyikan Entry Title di semua post',
                        'hide_meta_globally'       => 'Sembunyikan Entry Meta (date, author)',
                        'hide_comments_globally'   => 'Sembunyikan area komentar',
                        'hide_breadcrumb_globally' => 'Sembunyikan Breadcrumb',
                        'hide_categories_globally' => 'Sembunyikan Kategori di post',
                        'hide_tags_globally'       => 'Sembunyikan Tag di post',
                    ];
                    foreach ($eo_disables as $key => $label) : ?>
                    <tr>
                        <th><?php echo esc_html($label); ?></th>
                        <td>
                            <input type="checkbox"
                                   name="eo_display_global[<?php echo $key; ?>]"
                                   value="1"
                                   <?php checked(!empty($dsp[$key]), true); ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- ── Komentar ── -->
            <div style="<?php echo $card_style; ?>">
                <h2 style="<?php echo $h2_style; ?>">🗨️ Komentar WordPress</h2>
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th style="width:220px;">Mode Komentar</th>
                        <td>
                            <select name="eo_comment_mode" style="width:260px;">
                                <option value="default"  <?php selected($cm,'default'); ?>>Default WordPress</option>
                                <option value="disabled" <?php selected($cm,'disabled'); ?>>Nonaktifkan Semua Komentar</option>
                                <option value="hidden"   <?php selected($cm,'hidden'); ?>>Sembunyikan Tampilan Komentar</option>
                            </select>
                            <p class="description">
                                "Nonaktifkan" = tutup kolom komentar di seluruh situs.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ════════════════════════════════════════════════
                 ── Featured Image Square (Chapter 5 — BARU) ──
                 ════════════════════════════════════════════════ -->
            <div style="<?php echo $card_style; ?>border-left:4px solid #15803d;">
                <h2 style="<?php echo $h2_style; ?>">🖼️ Featured Image Square</h2>
                <p style="font-size:13px;color:#64748b;margin:0 0 16px;">
                    Paksa gambar produk tampil persegi (1:1) menggunakan crop otomatis.
                    Tidak mengubah file asli — hanya tampilan via CSS + WordPress image size.
                </p>

                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th style="width:220px;">Aktifkan Square Image</th>
                        <td>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox"
                                       name="eo_square_image_enabled"
                                       id="eo_square_image_enabled"
                                       value="1"
                                       <?php checked($sq_enabled, '1'); ?>
                                       onchange="document.getElementById('eo-sq-options').style.display=this.checked?'block':'none'">
                                <span style="font-weight:600;">Aktifkan</span>
                            </label>
                        </td>
                    </tr>
                </table>

                <div id="eo-sq-options" style="<?php echo $sq_enabled ? '' : 'display:none;'; ?>
                     background:#f8fafc;border-radius:8px;padding:16px;
                     border:1px solid #e2e8f0;margin-top:12px;">

                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:200px;padding:8px 0;">Berlaku Untuk</th>
                            <td style="padding:8px 0;">
                                <select name="eo_square_image_scope" style="width:260px;padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px;">
                                    <option value="eo_product" <?php selected($sq_scope,'eo_product'); ?>>
                                        Hanya Produk EO (eo_product)
                                    </option>
                                    <option value="all" <?php selected($sq_scope,'all'); ?>>
                                        Semua Post Type
                                    </option>
                                </select>
                                <p class="description" style="margin-top:4px;">
                                    Rekomendasi: pilih "Hanya Produk EO" agar tidak mempengaruhi post/page lain.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th style="padding:8px 0;">Ukuran Square (px)</th>
                            <td style="padding:8px 0;">
                                <input type="number"
                                       name="eo_square_image_size"
                                       value="<?php echo esc_attr($sq_size); ?>"
                                       min="200" max="2000" step="50"
                                       style="width:120px;padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px;">
                                <span style="font-size:13px;color:#64748b;margin-left:6px;">× <?php echo esc_attr($sq_size); ?> px</span>
                                <p class="description" style="margin-top:4px;">
                                    Default 600×600. Setelah mengubah ukuran,
                                    <strong>regenerate thumbnails</strong> via plugin
                                    <a href="https://wordpress.org/plugins/regenerate-thumbnails/" target="_blank">Regenerate Thumbnails</a>
                                    agar gambar lama ikut ter-crop.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th style="padding:8px 0;">Border Radius (px)</th>
                            <td style="padding:8px 0;">
                                <input type="number"
                                       name="eo_square_image_radius"
                                       value="<?php echo esc_attr($sq_radius); ?>"
                                       min="0" max="300" step="2"
                                       style="width:100px;padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px;">
                                <span style="font-size:13px;color:#64748b;margin-left:6px;">px</span>
                                <p class="description" style="margin-top:4px;">
                                    0 = persegi tajam. Isi 300 untuk tampilan lingkaran penuh.
                                    Masukkan angka sesuai selera, misal 12 untuk sudut membulat.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <!-- Preview live radius -->
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #e2e8f0;">
                        <p style="font-size:12px;font-weight:600;color:#64748b;margin:0 0 8px;">
                            Preview Radius:
                        </p>
                        <div id="eo-sq-preview"
                             style="width:80px;height:80px;background:linear-gradient(135deg,#15803d,#4ade80);
                                    border-radius:<?php echo $sq_radius; ?>px;
                                    display:inline-block;transition:border-radius .2s;">
                        </div>
                    </div>
                </div>

                <!-- Info box -->
                <div style="margin-top:14px;background:#eff6ff;border:1px solid #bfdbfe;
                            border-radius:8px;padding:12px 14px;font-size:13px;color:#1e40af;">
                    <strong>ℹ️ Cara Kerja:</strong>
                    <ol style="margin:6px 0 0 18px;line-height:1.8;">
                        <li>Plugin mendaftarkan image size <code>eo-product-square</code> (crop tengah).</li>
                        <li>Filter WordPress mengganti size thumbnail saat halaman produk di-load.</li>
                        <li>CSS tambahan memastikan tampilan 1:1 via <code>aspect-ratio</code> + <code>object-fit:cover</code>.</li>
                        <li>Gambar yang diupload <strong>setelah</strong> fitur ini diaktifkan otomatis ter-crop.
                            Gambar <strong>lama</strong> perlu di-regenerate manual.</li>
                    </ol>
                </div>
            </div>
            <!-- ── End Featured Image Square ── -->

            <hr style="margin:24px 0;border:0;border-top:1px solid #e2e8f0;">
            <?php submit_button('💾 Simpan Semua Pengaturan Tampilan', 'primary large'); ?>
        </form>
        </div>

        <script>
        // Live preview border radius
        (function() {
            var radInput = document.querySelector('input[name="eo_square_image_radius"]');
            var preview  = document.getElementById('eo-sq-preview');
            var sizeSpan = document.querySelector('input[name="eo_square_image_size"] + span');
            if (radInput && preview) {
                radInput.addEventListener('input', function() {
                    preview.style.borderRadius = this.value + 'px';
                });
            }
            var sizeInput = document.querySelector('input[name="eo_square_image_size"]');
            if (sizeInput && sizeSpan) {
                sizeInput.addEventListener('input', function() {
                    sizeSpan.textContent = '× ' + this.value + ' px';
                });
            }
        })();
        </script>
        <?php
    }

    public static function save_global_settings() {
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        check_admin_referer('eo_save_display_settings');

        $gp_opts = [
            'eo_gp_sidebar_layout', 'eo_gp_footer_widgets', 'eo_gp_content_container',
            'eo_gp_disable_top_bar', 'eo_gp_disable_header', 'eo_gp_disable_mobile_header',
            'eo_gp_disable_primary_nav', 'eo_gp_disable_featured_image',
            'eo_gp_disable_content_title', 'eo_gp_disable_footer',
        ];
        foreach ( $gp_opts as $opt ) {
            if ( in_array($opt, ['eo_gp_sidebar_layout','eo_gp_footer_widgets','eo_gp_content_container']) ) {
                update_option( $opt, sanitize_text_field($_POST[$opt] ?? 'default') );
            } else {
                update_option( $opt, isset($_POST[$opt]) ? 1 : 0 );
            }
        }

        // EO display settings
        $data = [];
        if ( isset($_POST['eo_display_global']) && is_array($_POST['eo_display_global']) ) {
            foreach ( $_POST['eo_display_global'] as $k => $v ) {
                $data[ sanitize_key($k) ] = (bool)$v;
            }
        }
        update_option( 'eo_display_settings', $data );

        // Komentar mode
        $cm = sanitize_text_field( $_POST['eo_comment_mode'] ?? 'default' );
        update_option( 'eo_comment_mode', $cm );
        if ( $cm === 'disabled' ) {
            update_option( 'default_comment_status', 'closed' );
            update_option( 'default_ping_status', 'closed' );
        } else {
            update_option( 'default_comment_status', 'open' );
        }

        // ── Square Image Settings (Chapter 5) ──
        update_option( 'eo_square_image_enabled',
            isset($_POST['eo_square_image_enabled']) ? '1' : '0' );
        update_option( 'eo_square_image_scope',
            sanitize_key($_POST['eo_square_image_scope'] ?? 'eo_product') );
        $sq_size = (int) ($_POST['eo_square_image_size'] ?? 600);
        update_option( 'eo_square_image_size', max(200, min(2000, $sq_size)) );
        $sq_radius = (int) ($_POST['eo_square_image_radius'] ?? 0);
        update_option( 'eo_square_image_radius', max(0, min(300, $sq_radius)) );

        wp_redirect( admin_url('admin.php?page=eo-display&saved=1') );
        exit;
    }

    /* =========================================================
       APPLY KE FRONTEND
       ========================================================= */
    public static function apply_frontend() {
        if ( is_admin() ) return;

        $sb_global = get_option('eo_gp_sidebar_layout', 'default');
        $fw_global = get_option('eo_gp_footer_widgets', 'default');
        $cc_global = get_option('eo_gp_content_container', 'default');

        $per_post = [];
        if ( is_singular() ) {
            $per_post = get_post_meta( get_the_ID(), '_eo_display_settings', true ) ?: [];
        }

        $sb = ( ! empty($per_post['sidebar_layout']) && $per_post['sidebar_layout'] !== 'default' )
            ? $per_post['sidebar_layout'] : $sb_global;
        $fw = ( ! empty($per_post['footer_widgets']) && $per_post['footer_widgets'] !== 'default' )
            ? $per_post['footer_widgets'] : $fw_global;
        $cc = ( ! empty($per_post['content_container']) && $per_post['content_container'] !== 'default' )
            ? $per_post['content_container'] : $cc_global;

        if ( $sb !== 'default' ) {
            add_filter( 'generate_sidebar_layout',      function() use ($sb) { return $sb; }, 999 );
        }
        if ( $fw !== 'default' ) {
            add_filter( 'generate_footer_widgets',      function() use ($fw) { return $fw; }, 999 );
        }
        if ( $cc !== 'default' ) {
            add_filter( 'generate_page_builder_display', function() use ($cc) { return $cc; }, 999 );
        }

        if ( get_option('eo_gp_disable_top_bar') ) {
            remove_action( 'generate_before_header', 'generate_top_bar', 5 );
        }
        if ( get_option('eo_gp_disable_header') ) {
            remove_action( 'generate_header', 'generate_construct_header' );
        }
        if ( get_option('eo_gp_disable_mobile_header') ) {
            add_filter( 'generate_has_mobile_header', '__return_false', 999 );
        }
        if ( get_option('eo_gp_disable_primary_nav') ) {
            add_filter( 'generate_navigation_location', '__return_false', 999 );
        }
        if ( get_option('eo_gp_disable_featured_image') ) {
            add_filter( 'generate_show_post_image',         '__return_false', 999 );
            add_filter( 'generate_show_page_builder_image', '__return_false', 999 );
        }
        if ( get_option('eo_gp_disable_content_title') ) {
            add_filter( 'generate_show_title', '__return_false', 999 );
        }
        if ( get_option('eo_gp_disable_footer') ) {
            remove_action( 'generate_footer', 'generate_construct_footer' );
        }

        if ( is_singular() && ! empty($per_post) ) {
            if ( ! empty($per_post['hide-title'])      ) add_filter( 'generate_show_title',       '__return_false', 999 );
            if ( ! empty($per_post['hide-breadcrumb']) ) add_filter( 'generate_show_breadcrumbs', '__return_false', 999 );
            if ( ! empty($per_post['hide-featured-image']) ) {
                add_filter( 'generate_show_post_image',         '__return_false', 999 );
                add_filter( 'generate_show_page_builder_image', '__return_false', 999 );
            }
        }

        add_filter( 'body_class', [ __CLASS__, 'add_body_classes' ] );

        $cm = get_option('eo_comment_mode', 'default');
        if ( $cm === 'hidden' ) {
            add_filter( 'comments_template', function() {
                return EO_PLUGIN_DIR . 'includes/empty.php';
            }, 999 );
        }
    }

    public static function add_body_classes( $classes ) {
        $global = get_option('eo_display_settings', []);
        $map = [
            'hide_title_globally'      => 'eo-hide-title',
            'hide_meta_globally'       => 'eo-hide-meta eo-hide-footer-meta',
            'hide_comments_globally'   => 'eo-hide-comments',
            'hide_breadcrumb_globally' => 'eo-hide-breadcrumb',
            'hide_categories_globally' => 'eo-hide-categories',
            'hide_tags_globally'       => 'eo-hide-tags',
        ];
        foreach ($map as $key => $cls) {
            if ( ! empty($global[$key]) ) {
                foreach (explode(' ', $cls) as $c) $classes[] = $c;
            }
        }
        if ( is_singular() ) {
            $per = get_post_meta( get_the_ID(), '_eo_display_settings', true ) ?: [];
            $per_map = [
                'hide-title'          => 'eo-hide-title',
                'hide-meta'           => 'eo-hide-meta',
                'hide-footer-meta'    => 'eo-hide-footer-meta',
                'hide-categories'     => 'eo-hide-categories',
                'hide-tags'           => 'eo-hide-tags',
                'hide-comments'       => 'eo-hide-comments',
                'hide-breadcrumb'     => 'eo-hide-breadcrumb',
                'hide-featured-image' => 'eo-hide-featured-image',
            ];
            foreach ($per_map as $key => $cls) {
                if ( ! empty($per[$key]) ) $classes[] = $cls;
            }
        }
        return $classes;
    }
}
