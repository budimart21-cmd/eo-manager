<?php
/**
 * EO Products Page v3
 */
class EO_Products_Page {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'eo-manager',
            'Kelola Produk',
            '📦 Produk',
            'manage_options',
            'eo-products',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) return;

        $products = get_posts([
            'post_type'      => 'eo_product',
            'numberposts'    => -1,
            'post_status'    => ['publish', 'draft'],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        ?>
        <div class="eo-wrap">
            <div class="eo-page-header">
                <div class="eo-page-icon">📦</div>
                <div>
                    <h1>Produk / Landing Form</h1>
                    <p><?php echo count($products); ?> produk tersedia</p>
                </div>
            </div>

            <div class="eo-actions">
                <a href="<?php echo admin_url('post-new.php?post_type=eo_product'); ?>" class="eo-btn eo-btn-primary">➕ Tambah Produk Baru</a>
            </div>

            <?php if ( empty($products) ) : ?>
            <div class="eo-card" style="text-align:center;padding:40px;">
                <div style="font-size:48px;margin-bottom:12px;">📦</div>
                <h3>Belum ada produk</h3>
                <p style="color:var(--eo-muted);">Buat produk pertama Anda untuk mulai mengumpulkan leads.</p>
                <a href="<?php echo admin_url('post-new.php?post_type=eo_product'); ?>" class="eo-btn eo-btn-primary" style="margin-top:8px;">➕ Buat Produk Pertama</a>
            </div>
            <?php else : ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                <?php foreach ( $products as $p ) :
                    $config    = EO_Form_Builder::get_form_config($p->ID);
                    $products_list = $config['products'] ?? [];
                    $shortcode = '[eo_form id="' . $p->ID . '"]';
                    $lead_count = EO_Leads::count_by_post($p->ID);
                    $status = $p->post_status;
                ?>
                <div class="eo-card" style="padding:20px;position:relative;">
                    <div style="position:absolute;top:16px;right:16px;">
                        <span style="font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;background:<?php echo $status==='publish' ? 'var(--eo-green-lt)' : '#f1f5f9'; ?>;color:<?php echo $status==='publish' ? 'var(--eo-green-dk)' : 'var(--eo-muted)'; ?>;">
                            <?php echo $status === 'publish' ? '● Aktif' : '○ Draft'; ?>
                        </span>
                    </div>
                    <h3 style="margin:0 0 8px;font-size:15px;font-weight:700;color:var(--eo-slate);padding-right:60px;"><?php echo esc_html($p->post_title); ?></h3>
                    <div style="font-size:12px;color:var(--eo-muted);margin-bottom:12px;">
                        <?php echo count($products_list); ?> pilihan · <strong style="color:var(--eo-green-dk);"><?php echo $lead_count; ?> leads</strong>
                    </div>
                    <div style="background:var(--eo-bg);border-radius:6px;padding:8px 10px;margin-bottom:14px;">
                        <code style="font-size:12px;color:var(--eo-slate);user-select:all;"><?php echo esc_html($shortcode); ?></code>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a href="<?php echo get_edit_post_link($p->ID); ?>" class="eo-btn eo-btn-outline eo-btn-sm">✏️ Edit</a>
                        <a href="<?php echo get_permalink($p->ID); ?>" target="_blank" class="eo-btn eo-btn-outline eo-btn-sm">👁 Preview</a>
                        <a href="<?php echo admin_url('admin.php?page=eo-crm&product='.$p->ID); ?>" class="eo-btn eo-btn-outline eo-btn-sm">👥 Leads</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
