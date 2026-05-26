<?php
/**
 * EO Display Settings
 * Merge dari: plugin "GeneratePress Global Layout Settings" (disable-element.php)
 * Kontrol: Sidebar Layout, Footer Widgets, Container, Disable Elements (global & per-post),
 *          serta hide title, meta, tag, category, komentar, breadcrumb.
 */
class EO_Display_Settings {

    public static function init() {
        add_action( 'add_meta_boxes',  [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post',       [ __CLASS__, 'save_meta_box' ] );
        add_action( 'admin_menu',      [ __CLASS__, 'add_display_menu' ] );
        add_action( 'admin_init',      [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_post_eo_save_display_settings', [ __CLASS__, 'save_global_settings' ] );
        // Apply ke frontend
        add_action( 'wp', [ __CLASS__, 'apply_frontend' ] );
    }

    /* =========================================================
       REGISTER SETTINGS (agar options.php bisa menyimpan)
       ========================================================= */
    public static function register_settings() {
        $fields = [
            'eo_gp_sidebar_layout', 'eo_gp_footer_widgets', 'eo_gp_content_container',
            'eo_gp_disable_top_bar', 'eo_gp_disable_header', 'eo_gp_disable_mobile_header',
            'eo_gp_disable_primary_nav', 'eo_gp_disable_featured_image',
            'eo_gp_disable_content_title', 'eo_gp_disable_footer',
        ];
        foreach ( $fields as $f ) {
            register_setting( 'eo_display_group', $f );
        }
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

        // ── Disable Elements (per-post) ──
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

        // ── Layout overrides (per-post) ──
        $sb_val  = $saved['sidebar_layout']   ?? 'default';
        $fw_val  = $saved['footer_widgets']   ?? 'default';
        $cc_val  = $saved['content_container']?? 'default';
        ?>
        <style>
        .eo-mb-label { font-size:11px; font-weight:700; color:#64748b; display:block; margin:8px 0 4px; text-transform:uppercase; letter-spacing:.04em; }
        .eo-mb-select { width:100%; padding:5px 8px; border:1px solid #ddd; border-radius:4px; font-size:12px; }
        </style>

        <p style="font-size:12px;color:#64748b;margin:0 0 10px;">Override setting khusus untuk halaman ini saja.</p>

        <!-- Sidebar Layout -->
        <span class="eo-mb-label">Sidebar Layout</span>
        <select name="eo_display[sidebar_layout]" class="eo-mb-select">
            <?php foreach (['default'=>'Default (Global)','no-sidebar'=>'No Sidebar','right-sidebar'=>'Content / Sidebar','left-sidebar'=>'Sidebar / Content','both-sidebars'=>'Both Sidebars'] as $v=>$l): ?>
            <option value="<?php echo $v; ?>" <?php selected($sb_val,$v); ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Footer Widgets -->
        <span class="eo-mb-label">Footer Widgets</span>
        <select name="eo_display[footer_widgets]" class="eo-mb-select">
            <?php foreach (['default'=>'Default','0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5'] as $v=>$l): ?>
            <option value="<?php echo $v; ?>" <?php selected($fw_val,$v); ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Content Container -->
        <span class="eo-mb-label">Content Container</span>
        <select name="eo_display[content_container]" class="eo-mb-select">
            <?php foreach (['default'=>'Default','full-width'=>'Full Width','contained'=>'Contained'] as $v=>$l): ?>
            <option value="<?php echo $v; ?>" <?php selected($cc_val,$v); ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Disable Elements -->
        <span class="eo-mb-label" style="margin-top:12px;">Disable / Sembunyikan</span>
        <div style="font-size:12px;">
        <?php foreach ( $disable_opts as $key => $label ) :
            $checked = ! empty( $saved[$key] ) ? 'checked' : '';
        ?>
            <label style="display:block;margin-bottom:5px;">
                <input type="checkbox" name="eo_display[<?php echo esc_attr($key); ?>]" value="1" <?php echo $checked; ?>>
                <?php echo esc_html($label); ?>
            </label>
        <?php endforeach; ?>
        </div>
        <p style="font-size:10px;color:#999;margin-top:8px;">Setting global ada di <a href="<?php echo admin_url('admin.php?page=eo-display'); ?>">Menu Tampilan</a>.</p>
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
                // selectbox: simpan string; checkbox: simpan bool
                $data[$key] = in_array($key, ['sidebar_layout','footer_widgets','content_container'])
                    ? sanitize_text_field($v)
                    : (bool)$v;
            }
        }
        // Pastikan selectbox selalu tersimpan meski tidak dikirim (default)
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
        ?>
        <div class="wrap">
        <h1>👁️ Pengaturan Tampilan & Layout Global</h1>
        <p style="color:#555;max-width:600px;">Berlaku untuk <strong>semua halaman/post</strong> secara global. Per-halaman dapat di-override via meta box di editor masing-masing.</p>

        <?php if ( isset($_GET['saved']) ) : ?>
            <div class="notice notice-success is-dismissible"><p>✅ Pengaturan berhasil disimpan.</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="max-width:640px;background:#fff;padding:24px;border:1px solid #e2e8f0;border-radius:10px;margin-top:16px;">
            <input type="hidden" name="action" value="eo_save_display_settings">
            <?php wp_nonce_field('eo_save_display_settings'); ?>

            <?php
            $sb  = get_option('eo_gp_sidebar_layout','default');
            $fw  = get_option('eo_gp_footer_widgets','default');
            $cc  = get_option('eo_gp_content_container','default');
            $cm  = get_option('eo_comment_mode','default');
            $dsp = get_option('eo_display_settings', []);
            ?>

            <!-- ── GeneratePress Layout ── -->
            <h2 style="font-size:15px;border-bottom:1px solid #e2e8f0;padding-bottom:8px;">🗂️ GeneratePress Layout (Global)</h2>
            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th style="width:200px;">Sidebar Layout</th>
                    <td>
                        <select name="eo_gp_sidebar_layout" style="width:250px;">
                            <?php foreach ([
                                'default'       => 'Default (sesuai tema)',
                                'no-sidebar'    => 'No Sidebars',
                                'right-sidebar' => 'Content / Sidebar',
                                'left-sidebar'  => 'Sidebar / Content',
                                'both-sidebars' => 'Sidebar / Content / Sidebar',
                                'both-left'     => 'Sidebar / Sidebar / Content',
                                'both-right'    => 'Content / Sidebar / Sidebar',
                            ] as $v => $l): ?>
                            <option value="<?php echo $v; ?>" <?php selected($sb,$v); ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Footer Widgets</th>
                    <td>
                        <select name="eo_gp_footer_widgets" style="width:250px;">
                            <?php foreach (['default'=>'Default','0'=>'0 Widgets','1'=>'1 Widget','2'=>'2 Widgets','3'=>'3 Widgets','4'=>'4 Widgets','5'=>'5 Widgets'] as $v=>$l): ?>
                            <option value="<?php echo $v; ?>" <?php selected($fw,$v); ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Content Container</th>
                    <td>
                        <select name="eo_gp_content_container" style="width:250px;">
                            <?php foreach (['default'=>'Default','full-width'=>'Full Width','contained'=>'Contained'] as $v=>$l): ?>
                            <option value="<?php echo $v; ?>" <?php selected($cc,$v); ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <!-- ── Disable Elements ── -->
            <h2 style="font-size:15px;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin-top:24px;">🚫 Disable Elements (Global)</h2>
            <table class="form-table" style="margin-top:0;">
                <?php
                $gp_disables = [
                    'eo_gp_disable_top_bar'          => 'Top Bar',
                    'eo_gp_disable_header'            => 'Header',
                    'eo_gp_disable_mobile_header'     => 'Mobile Header',
                    'eo_gp_disable_primary_nav'       => 'Primary Navigation',
                    'eo_gp_disable_featured_image'    => 'Featured Image',
                    'eo_gp_disable_content_title'     => 'Content Title (H1)',
                    'eo_gp_disable_footer'            => 'Footer',
                ];
                foreach ($gp_disables as $opt => $label): ?>
                <tr>
                    <th><?php echo esc_html($label); ?></th>
                    <td><input type="checkbox" name="<?php echo $opt; ?>" value="1" <?php checked(get_option($opt), 1); ?>></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <!-- ── EO Custom Elements ── -->
            <h2 style="font-size:15px;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin-top:24px;">✏️ Sembunyikan Elemen Konten (Global)</h2>
            <table class="form-table" style="margin-top:0;">
                <?php
                $eo_disables = [
                    'hide_title_globally'       => 'Sembunyikan Entry Title di semua post',
                    'hide_meta_globally'        => 'Sembunyikan Entry Meta (date, author)',
                    'hide_comments_globally'    => 'Sembunyikan area komentar',
                    'hide_breadcrumb_globally'  => 'Sembunyikan Breadcrumb',
                    'hide_categories_globally'  => 'Sembunyikan Kategori di post',
                    'hide_tags_globally'        => 'Sembunyikan Tag di post',
                ];
                foreach ($eo_disables as $key => $label): ?>
                <tr>
                    <th><?php echo esc_html($label); ?></th>
                    <td><input type="checkbox" name="eo_display_global[<?php echo $key; ?>]" value="1" <?php checked(!empty($dsp[$key]), true); ?>></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <!-- ── Komentar ── -->
            <h2 style="font-size:15px;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin-top:24px;">🗨️ Komentar WordPress</h2>
            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th>Mode Komentar</th>
                    <td>
                        <select name="eo_comment_mode" style="width:250px;">
                            <option value="default"  <?php selected($cm,'default'); ?>>Default WordPress</option>
                            <option value="disabled" <?php selected($cm,'disabled'); ?>>Nonaktifkan Semua Komentar</option>
                            <option value="hidden"   <?php selected($cm,'hidden'); ?>>Sembunyikan Tampilan Komentar</option>
                        </select>
                        <p class="description">Pilih "Nonaktifkan" = tutup kolom komentar di seluruh situs.</p>
                    </td>
                </tr>
            </table>

            <hr style="margin:24px 0;border:0;border-top:1px solid #e2e8f0;">
            <?php submit_button('💾 Simpan Semua Pengaturan Tampilan'); ?>
        </form>
        </div>
        <?php
    }

    public static function save_global_settings() {
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        check_admin_referer('eo_save_display_settings');

        // Simpan GeneratePress layout options
        $gp_opts = [
            'eo_gp_sidebar_layout', 'eo_gp_footer_widgets', 'eo_gp_content_container',
            'eo_gp_disable_top_bar', 'eo_gp_disable_header', 'eo_gp_disable_mobile_header',
            'eo_gp_disable_primary_nav', 'eo_gp_disable_featured_image',
            'eo_gp_disable_content_title', 'eo_gp_disable_footer',
        ];
        foreach ( $gp_opts as $opt ) {
            // checkbox: simpan 1 atau hapus; select: simpan string
            if ( in_array($opt, ['eo_gp_sidebar_layout','eo_gp_footer_widgets','eo_gp_content_container']) ) {
                update_option( $opt, sanitize_text_field($_POST[$opt] ?? 'default') );
            } else {
                update_option( $opt, isset($_POST[$opt]) ? 1 : 0 );
            }
        }

        // Simpan EO display settings
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

        wp_redirect( admin_url('admin.php?page=eo-display&saved=1') );
        exit;
    }

    /* =========================================================
       APPLY KE FRONTEND
       ========================================================= */
    public static function apply_frontend() {
        if ( is_admin() ) return;

        // ── Baca setting global ──
        $sb_global = get_option('eo_gp_sidebar_layout', 'default');
        $fw_global = get_option('eo_gp_footer_widgets', 'default');
        $cc_global = get_option('eo_gp_content_container', 'default');

        // ── Baca setting per-post (override global jika ada) ──
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

        // Apply GeneratePress filters
        if ( $sb !== 'default' ) {
            add_filter( 'generate_sidebar_layout', function() use ($sb) { return $sb; }, 999 );
        }
        if ( $fw !== 'default' ) {
            add_filter( 'generate_footer_widgets', function() use ($fw) { return $fw; }, 999 );
        }
        if ( $cc !== 'default' ) {
            add_filter( 'generate_page_builder_display', function() use ($cc) { return $cc; }, 999 );
        }

        // ── GeneratePress Disable Elements (Global) ──
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
            add_filter( 'generate_show_post_image',          '__return_false', 999 );
            add_filter( 'generate_show_page_builder_image',  '__return_false', 999 );
        }
        if ( get_option('eo_gp_disable_content_title') ) {
            add_filter( 'generate_show_title', '__return_false', 999 );
        }
        if ( get_option('eo_gp_disable_footer') ) {
            remove_action( 'generate_footer', 'generate_construct_footer' );
        }

        // ── Per-post Disable Elements (override) ──
        if ( is_singular() && ! empty($per_post) ) {
            if ( ! empty($per_post['hide-title'])     ) add_filter( 'generate_show_title', '__return_false', 999 );
            if ( ! empty($per_post['hide-breadcrumb'])) add_filter( 'generate_show_breadcrumbs', '__return_false', 999 );
            if ( ! empty($per_post['hide-featured-image']) ) {
                add_filter( 'generate_show_post_image',         '__return_false', 999 );
                add_filter( 'generate_show_page_builder_image', '__return_false', 999 );
            }
        }

        // ── EO body classes (CSS-based hiding) ──
        add_filter( 'body_class', [ __CLASS__, 'add_body_classes' ] );

        // ── Komentar ──
        $cm = get_option('eo_comment_mode', 'default');
        if ( $cm === 'hidden' ) {
            add_filter( 'comments_template', function() {
                return EO_PLUGIN_DIR . 'includes/empty.php';
            }, 999 );
        }
    }

    /**
     * Body classes dari setting global + per-post
     */
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
        // Per-post
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
