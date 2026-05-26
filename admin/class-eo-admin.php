<?php
/**
 * EO Admin v3 — Modern minimal UI, Meta Box Form Builder
 */
class EO_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'add_meta_boxes',        [ __CLASS__, 'add_form_meta_box' ] );
        add_action( 'save_post_eo_product',  [ __CLASS__, 'save_form_meta_box' ] );
        add_action( 'admin_head',            [ __CLASS__, 'admin_styles' ] );
    }

    /* ── Modern CSS untuk semua halaman EO ── */
    public static function admin_styles() {
        $screen = get_current_screen();
        if ( ! $screen ) return;
        $is_eo = strpos($screen->id, 'eo-') !== false
               || strpos($screen->id, 'eo_product') !== false
               || strpos($screen->post_type ?? '', 'eo_') !== false;
        if ( ! $is_eo ) return;
        ?>
        <style>
        :root {
            --eo-green:    #16a34a;
            --eo-green-dk: #15803d;
            --eo-green-lt: #dcfce7;
            --eo-slate:    #0f172a;
            --eo-muted:    #64748b;
            --eo-border:   #e2e8f0;
            --eo-bg:       #f8fafc;
            --eo-white:    #ffffff;
            --eo-radius:   10px;
            --eo-shadow:   0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.05);
        }

        /* ── Layout ── */
        .eo-wrap { max-width: 960px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .eo-wrap * { box-sizing: border-box; }

        /* ── Page header ── */
        .eo-page-header {
            display: flex; align-items: center; gap: 16px;
            margin: 0 0 28px; padding-bottom: 20px;
            border-bottom: 1px solid var(--eo-border);
        }
        .eo-page-icon { font-size: 36px; line-height: 1; }
        .eo-page-header h1 { margin: 0; font-size: 22px; font-weight: 800; color: var(--eo-slate); }
        .eo-page-header p  { margin: 4px 0 0; color: var(--eo-muted); font-size: 14px; }
        .eo-badge { display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px; background:var(--eo-green-lt); color:var(--eo-green-dk); }

        /* ── Cards ── */
        .eo-card {
            background: var(--eo-white); border: 1px solid var(--eo-border);
            border-radius: var(--eo-radius); padding: 22px 24px;
            box-shadow: var(--eo-shadow); margin-bottom: 20px;
        }
        .eo-card-title {
            font-size: 15px; font-weight: 700; color: var(--eo-slate);
            margin: 0 0 18px; padding-bottom: 12px; border-bottom: 1px solid var(--eo-border);
            display: flex; align-items: center; gap: 8px;
        }

        /* ── Stats Grid ── */
        .eo-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .eo-stat-item {
            background: var(--eo-white); border: 1px solid var(--eo-border);
            border-radius: var(--eo-radius); padding: 18px 20px;
            border-left: 4px solid var(--eo-green); box-shadow: var(--eo-shadow);
        }
        .eo-stat-item .num { font-size: 30px; font-weight: 800; color: var(--eo-slate); line-height: 1.1; }
        .eo-stat-item .lbl { font-size: 12px; color: var(--eo-muted); margin-top: 4px; font-weight: 500; }

        /* ── Buttons ── */
        .eo-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 18px; border-radius: 7px; font-size: 13px; font-weight: 600;
            cursor: pointer; text-decoration: none; border: 1.5px solid transparent;
            transition: all .15s; line-height: 1.4;
        }
        .eo-btn-primary   { background: var(--eo-green-dk); color: #fff !important; }
        .eo-btn-primary:hover { background: #166534; color: #fff; }
        .eo-btn-outline   { background: var(--eo-white); color: var(--eo-slate); border-color: var(--eo-border); }
        .eo-btn-outline:hover { border-color: #94a3b8; background: var(--eo-bg); color: var(--eo-slate); }
        .eo-btn-danger    { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }
        .eo-btn-danger:hover { background: #fecaca; }
        .eo-btn-blue      { background: #0284c7; color: #fff !important; }
        .eo-btn-blue:hover { background: #0369a1; }
        .eo-btn-sm        { padding: 5px 12px; font-size: 12px; }
        .eo-btn-lg        { padding: 12px 28px; font-size: 14px; }
        .eo-actions       { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }

        /* ── Tabs ── */
        .eo-tabs { display: flex; gap: 4px; margin-bottom: 0; border-bottom: 2px solid var(--eo-border); flex-wrap: wrap; }
        .eo-tab {
            background: none; border: none; padding: 10px 16px; font-size: 13px; font-weight: 600;
            color: var(--eo-muted); cursor: pointer; border-bottom: 2px solid transparent;
            margin-bottom: -2px; transition: all .15s;
        }
        .eo-tab:hover { color: var(--eo-green-dk); }
        .eo-tab.active { color: var(--eo-green-dk); border-bottom-color: var(--eo-green-dk); }
        .eo-tab-content { padding-top: 20px; }

        /* ── Form Fields ── */
        .eo-field-group { display: flex; flex-direction: column; gap: 16px; }
        .eo-field { display: flex; flex-direction: column; gap: 6px; }
        .eo-field label { font-size: 13px; font-weight: 600; color: var(--eo-slate); }
        .eo-field input[type=text],
        .eo-field input[type=email],
        .eo-field input[type=password],
        .eo-field input[type=number],
        .eo-field select,
        .eo-field textarea {
            padding: 9px 12px; border: 1.5px solid var(--eo-border); border-radius: 8px;
            font-size: 14px; color: var(--eo-slate); background: #fff; width: 100%;
            transition: border-color .15s;
        }
        .eo-field input:focus, .eo-field select:focus, .eo-field textarea:focus {
            outline: none; border-color: var(--eo-green);
        }
        .eo-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .eo-field-toggle { flex-direction: row; align-items: center; justify-content: space-between; }
        .eo-hint { font-size: 12px; color: var(--eo-muted); margin: 0; }
        .eo-hint-warn { color: #b45309 !important; }
        .eo-required { color: #dc2626; }
        .eo-input-large { width: 100%; }

        /* Toggle Switch */
        .eo-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .eo-switch input { opacity: 0; width: 0; height: 0; }
        .eo-slider {
            position: absolute; inset: 0; background: #cbd5e1; border-radius: 999px;
            cursor: pointer; transition: .2s;
        }
        .eo-slider:before {
            content: ''; position: absolute; width: 18px; height: 18px;
            left: 3px; top: 3px; background: white; border-radius: 50%; transition: .2s;
        }
        .eo-switch input:checked + .eo-slider { background: var(--eo-green); }
        .eo-switch input:checked + .eo-slider:before { transform: translateX(20px); }

        /* ── Notices ── */
        .eo-notice { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .eo-notice-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .eo-notice-warn    { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .eo-info-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 14px 18px; font-size: 13px; }
        .eo-badge-green { background: var(--eo-green-lt); color: var(--eo-green-dk); padding: 8px 14px; border-radius: 7px; font-size: 13px; }

        /* Test row */
        .eo-test-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--eo-border); }
        .eo-test-row input { padding: 9px 12px; border: 1.5px solid var(--eo-border); border-radius: 8px; font-size: 13px; width: 240px; }
        .eo-test-result { font-size: 13px; }

        /* ── Table CRM ── */
        .eo-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .eo-table th { background: var(--eo-bg); padding: 10px 12px; text-align: left; font-weight: 700; color: var(--eo-muted); font-size: 11px; text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid var(--eo-border); }
        .eo-table td { padding: 10px 12px; border-bottom: 1px solid var(--eo-border); color: var(--eo-slate); vertical-align: middle; }
        .eo-table tr:last-child td { border-bottom: none; }
        .eo-table tr:hover td { background: var(--eo-bg); }

        /* Status badges */
        .eo-status { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
        .eo-status-new      { background:#dbeafe; color:#1d4ed8; }
        .eo-status-contacted{ background:#fef3c7; color:#92400e; }
        .eo-status-qualified{ background:#dcfce7; color:#15803d; }
        .eo-status-closed   { background:#f1f5f9; color:#475569; }
        .eo-status-lost     { background:#fee2e2; color:#dc2626; }

        /* ── Meta Box ── */
        .eo-meta-section { margin-bottom: 24px; }
        .eo-meta-section-title { font-size: 13px; font-weight: 700; color: var(--eo-muted); text-transform: uppercase; letter-spacing: .5px; margin: 0 0 12px; padding-bottom: 8px; border-bottom: 1px solid var(--eo-border); }
        .eo-meta-field { margin-bottom: 14px; }
        .eo-meta-field label { display: block; font-size: 13px; font-weight: 600; color: var(--eo-slate); margin-bottom: 6px; }
        .eo-meta-field input[type=text],
        .eo-meta-field input[type=email],
        .eo-meta-field select,
        .eo-meta-field textarea {
            width: 100%; padding: 8px 10px; border: 1.5px solid var(--eo-border); border-radius: 7px;
            font-size: 13px; color: var(--eo-slate);
        }
        .eo-meta-field textarea { min-height: 90px; resize: vertical; }
        .eo-meta-field input:focus, .eo-meta-field select:focus, .eo-meta-field textarea:focus {
            outline: none; border-color: var(--eo-green);
        }
        .eo-meta-hint { font-size: 12px; color: var(--eo-muted); margin: 4px 0 0; }

        /* Products list in meta box */
        .eo-product-item {
            display: grid; grid-template-columns: 1fr 1fr auto;
            gap: 8px; align-items: center; margin-bottom: 8px;
            background: var(--eo-bg); padding: 10px; border-radius: 8px;
            border: 1px solid var(--eo-border);
        }
        .eo-product-item input { margin: 0; }

        /* Pagination */
        .eo-pagination { display: flex; gap: 4px; align-items: center; }
        .eo-page-btn { padding: 6px 12px; border: 1px solid var(--eo-border); border-radius: 6px; background: white; font-size: 13px; cursor: pointer; }
        .eo-page-btn.active { background: var(--eo-green-dk); color: white; border-color: var(--eo-green-dk); }
        .eo-page-btn:hover:not(.active) { background: var(--eo-bg); }

        /* Search/Filter bar */
        .eo-filter-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
        .eo-filter-bar input, .eo-filter-bar select { padding: 8px 12px; border: 1.5px solid var(--eo-border); border-radius: 7px; font-size: 13px; }

        /* ── Responsive ── */
        @media (max-width: 782px) {
            .eo-field-row { grid-template-columns: 1fr; }
            .eo-stat-grid { grid-template-columns: repeat(2, 1fr); }
        }
        </style>
        <?php
    }

    public static function register_menus() {
        /* ── Menu utama ── */
        add_menu_page(
            'EO Manager',
            '🌿 EO Manager',
            'manage_options',
            'eo-manager',
            [ __CLASS__, 'render_dashboard' ],
            'dashicons-chart-area',
            4
        );

        add_submenu_page( 'eo-manager', 'Dashboard EO', '📊 Dashboard',      'manage_options', 'eo-manager',        [ __CLASS__, 'render_dashboard' ] );
        add_submenu_page( 'eo-manager', 'Tambah Produk', '➕ Tambah Produk',  'manage_options', 'post-new.php?post_type=eo_product' );
    }

    /* ── Dashboard utama ── */
    public static function render_dashboard() {
        $stats        = EO_Leads::get_stats();
        $posts        = wp_count_posts('eo_product');
        $total_prods  = ( $posts->publish ?? 0 ) + ( $posts->draft ?? 0 );
        $recent_leads = EO_Leads::get_leads(['per_page' => 5, 'page' => 1]);

        $stat_items = [
            [ 'num' => $stats['total'],     'lbl' => 'Total Leads',    'color' => '#16a34a' ],
            [ 'num' => $stats['new'],        'lbl' => 'Belum Ditindak', 'color' => '#0284c7' ],
            [ 'num' => $stats['today'],      'lbl' => 'Hari Ini',       'color' => '#d97706' ],
            [ 'num' => $stats['this_week'],  'lbl' => '7 Hari Terakhir','color' => '#7c3aed' ],
            [ 'num' => $total_prods,         'lbl' => 'Produk',         'color' => '#0f172a' ],
        ];
        ?>
        <div class="eo-wrap">
            <div class="eo-page-header">
                <div class="eo-page-icon">🌿</div>
                <div>
                    <h1>EO Manager <span class="eo-badge">v<?php echo EO_PLUGIN_VERSION; ?></span></h1>
                    <p>Dashboard ringkasan leads & aktivitas terbaru.</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="eo-stat-grid">
                <?php foreach ( $stat_items as $s ) : ?>
                <div class="eo-stat-item" style="border-left-color:<?php echo $s['color']; ?>">
                    <div class="num"><?php echo $s['num']; ?></div>
                    <div class="lbl"><?php echo $s['lbl']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Quick Actions -->
            <div class="eo-actions">
                <a href="<?php echo admin_url('admin.php?page=eo-crm'); ?>" class="eo-btn eo-btn-primary">👥 Kelola Leads CRM</a>
                <a href="<?php echo admin_url('admin.php?page=eo-products'); ?>" class="eo-btn eo-btn-outline">📦 Kelola Produk</a>
                <a href="<?php echo admin_url('post-new.php?post_type=eo_product'); ?>" class="eo-btn eo-btn-outline">➕ Tambah Produk</a>
                <a href="<?php echo admin_url('admin.php?page=eo-settings'); ?>" class="eo-btn eo-btn-outline">⚙️ Pengaturan</a>
            </div>

            <!-- Recent Leads -->
            <div class="eo-card">
                <h3 class="eo-card-title">⏱ Lead Terbaru</h3>
                <?php if ( empty($recent_leads['items']) ) : ?>
                    <p style="color:var(--eo-muted);text-align:center;padding:20px 0;">Belum ada lead. Buat produk dan share link form-nya!</p>
                <?php else : ?>
                <table class="eo-table">
                    <thead>
                        <tr>
                            <th>Nama</th><th>WA</th><th>Email</th><th>Produk</th><th>Pilihan</th><th>Status</th><th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $recent_leads['items'] as $lead ) :
                        $st = $lead->status;
                        $st_map = [
                            'new'       => ['new',       'Baru'],
                            'contacted' => ['contacted', 'Dihubungi'],
                            'qualified' => ['qualified', 'Qualified'],
                            'closed'    => ['closed',    'Closed'],
                            'lost'      => ['lost',      'Lost'],
                        ];
                        $st_cls = $st_map[$st][0] ?? 'new';
                        $st_lbl = $st_map[$st][1] ?? $st;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($lead->nama); ?></strong></td>
                        <td><a href="https://wa.me/<?php echo preg_replace('/\D/', '', $lead->wa); ?>" target="_blank"><?php echo esc_html($lead->wa); ?></a></td>
                        <td><?php echo esc_html($lead->email ?: '—'); ?></td>
                        <td><?php echo esc_html($lead->post_title); ?></td>
                        <td><?php echo esc_html($lead->pilihan_label); ?></td>
                        <td><span class="eo-status eo-status-<?php echo $st_cls; ?>"><?php echo $st_lbl; ?></span></td>
                        <td><?php echo esc_html( mysql2date('d/m H:i', $lead->created_at) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:14px;">
                    <a href="<?php echo admin_url('admin.php?page=eo-crm'); ?>" class="eo-btn eo-btn-outline eo-btn-sm">Lihat Semua Leads →</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Integration Status -->
            <div class="eo-card">
                <h3 class="eo-card-title">🔌 Status Integrasi</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php
                    $fonnte_ok = ! empty(get_option('eo_fonnte_token'));
                    $mk_ok     = ! empty(get_option('eo_mailketing_api_key'));
                    $mk_from   = ! empty(get_option('eo_mailketing_from_email'));
                    $smtp_ok   = ! empty(get_option('eo_smtp_host'));
                    ?>
                    <div style="padding:14px;border:1px solid var(--eo-border);border-radius:8px;display:flex;align-items:center;gap:12px;">
                        <span style="font-size:24px;"><?php echo $fonnte_ok ? '✅' : '❌'; ?></span>
                        <div>
                            <div style="font-weight:700;font-size:14px;">Fonnte WA</div>
                            <div style="font-size:12px;color:var(--eo-muted);"><?php echo $fonnte_ok ? 'Token terkonfigurasi' : 'Token belum diisi'; ?></div>
                        </div>
                    </div>
                    <div style="padding:14px;border:1px solid var(--eo-border);border-radius:8px;display:flex;align-items:center;gap:12px;">
                        <span style="font-size:24px;"><?php echo ($mk_ok && $mk_from) ? '✅' : ($mk_ok ? '⚠️' : '❌'); ?></span>
                        <div>
                            <div style="font-weight:700;font-size:14px;">Mailketing Email API</div>
                            <div style="font-size:12px;color:var(--eo-muted);"><?php echo $mk_ok ? ($mk_from ? 'API + From Email OK' : 'API OK, From Email belum diisi') : 'API Token belum diisi'; ?></div>
                        </div>
                    </div>
                    <div style="padding:14px;border:1px solid var(--eo-border);border-radius:8px;display:flex;align-items:center;gap:12px;">
                        <span style="font-size:24px;"><?php echo $smtp_ok ? '✅' : '⚠️'; ?></span>
                        <div>
                            <div style="font-weight:700;font-size:14px;">SMTP Fallback</div>
                            <div style="font-size:12px;color:var(--eo-muted);"><?php echo $smtp_ok ? get_option('eo_smtp_host') : 'Belum dikonfigurasi (opsional)'; ?></div>
                        </div>
                    </div>
                    <div style="padding:14px;border:1px solid var(--eo-border);border-radius:8px;display:flex;align-items:center;gap:12px;">
                        <span style="font-size:24px;">✅</span>
                        <div>
                            <div style="font-weight:700;font-size:14px;">CRM Database</div>
                            <div style="font-size:12px;color:var(--eo-muted);"><?php echo $stats['total']; ?> leads tersimpan</div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <a href="<?php echo admin_url('admin.php?page=eo-settings'); ?>" class="eo-btn eo-btn-outline eo-btn-sm">⚙️ Konfigurasi Integrasi →</a>
                </div>
            </div>
        </div>
        <?php
    }

    public static function enqueue_assets( $hook ) {
        // Tambahkan script/style jika diperlukan di masa depan
    }

    /* ── Meta Box Form Builder ── */
    public static function add_form_meta_box() {
        add_meta_box(
            'eo-form-builder',
            '🛠 Form Builder & Autoresponder',
            [ __CLASS__, 'render_form_meta_box' ],
            'eo_product',
            'normal',
            'high'
        );
    }

    public static function render_form_meta_box( $post ) {
        $config   = EO_Form_Builder::get_form_config( $post->ID );
        $fields   = $config['fields']   ?? EO_Form_Builder::default_fields();
        $products = $config['products'] ?? [];
        wp_nonce_field( 'eo_save_form_meta', 'eo_form_nonce' );

        $shortcode = '[eo_form id="' . $post->ID . '"]';
        ?>
        <div style="background:var(--eo-green-lt);border:1px solid #86efac;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
            <span style="font-size:18px;">📋</span>
            <div>
                <strong>Shortcode:</strong>
                <code style="background:#fff;padding:4px 10px;border-radius:5px;font-size:13px;border:1px solid #bbf7d0;user-select:all;"><?php echo esc_html($shortcode); ?></code>
                <span style="font-size:12px;color:var(--eo-muted);margin-left:8px;">Paste di halaman/post manapun</span>
            </div>
        </div>

        <!-- SECTION: Pilihan Produk/Paket -->
        <div class="eo-meta-section">
            <div class="eo-meta-section-title">📦 Pilihan Produk / Paket</div>
            <div id="eo-products-list">
                <?php foreach ( $products as $i => $prod ) : ?>
                <div class="eo-product-item">
                    <input type="text" name="eo_products[<?php echo $i; ?>][label]"
                           value="<?php echo esc_attr($prod['label'] ?? ''); ?>"
                           placeholder="Nama produk/paket">
                    <input type="text" name="eo_products[<?php echo $i; ?>][price]"
                           value="<?php echo esc_attr($prod['price'] ?? ''); ?>"
                           placeholder="Harga (opsional)">
                    <button type="button" class="eo-btn eo-btn-danger eo-btn-sm" onclick="eoRemoveProduct(this)">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="eo-btn eo-btn-outline eo-btn-sm" onclick="eoAddProduct()" style="margin-top:8px;">➕ Tambah Pilihan</button>
        </div>

        <!-- SECTION: Form Fields -->
        <div class="eo-meta-section">
            <div class="eo-meta-section-title">📝 Form Fields</div>
            <div id="eo-fields-list">
                <?php foreach ( $fields as $i => $f ) : ?>
                <div class="eo-product-item" style="grid-template-columns:1fr 100px 100px auto;">
                    <input type="text" name="eo_fields[<?php echo $i; ?>][label]"
                           value="<?php echo esc_attr($f['label'] ?? ''); ?>"
                           placeholder="Label field">
                    <select name="eo_fields[<?php echo $i; ?>][type]">
                        <?php foreach ( ['text','email','tel','textarea','select'] as $t ) : ?>
                        <option value="<?php echo $t; ?>" <?php selected($f['type'] ?? 'text', $t); ?>><?php echo strtoupper($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;cursor:pointer;">
                        <input type="checkbox" name="eo_fields[<?php echo $i; ?>][required]" value="1" <?php checked(!empty($f['required'])); ?>> Wajib
                    </label>
                    <button type="button" class="eo-btn eo-btn-danger eo-btn-sm" onclick="eoRemoveProduct(this)">✕</button>
                    <input type="hidden" name="eo_fields[<?php echo $i; ?>][id]" value="<?php echo esc_attr($f['id'] ?? sanitize_key($f['label'] ?? 'field'.$i)); ?>">
                    <input type="hidden" name="eo_fields[<?php echo $i; ?>][placeholder]" value="<?php echo esc_attr($f['placeholder'] ?? ''); ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="eo-btn eo-btn-outline eo-btn-sm" onclick="eoAddField()" style="margin-top:8px;">➕ Tambah Field</button>
        </div>

        <!-- SECTION: WA Autoresponder -->
        <div class="eo-meta-section">
            <div class="eo-meta-section-title">📱 WA Autoresponder (ke customer)</div>
            <div class="eo-meta-field">
                <label>Template Pesan WA</label>
                <textarea name="eo_wa_template" rows="5"><?php echo esc_textarea($config['wa_template'] ?? EO_Form_Builder::default_wa_template()); ?></textarea>
                <p class="eo-meta-hint">Variabel: <code>{nama}</code> <code>{pilihan}</code> <code>{harga}</code> <code>{site_name}</code> <code>{wa}</code> <code>{email}</code> <code>{coa_link}</code></p>
            </div>
        </div>

        <!-- SECTION: Email Autoresponder -->
        <div class="eo-meta-section">
            <div class="eo-meta-section-title">📧 Email Autoresponder (ke customer)</div>
            <div class="eo-meta-field">
                <label>Subject Email</label>
                <input type="text" name="eo_email_subject"
                       value="<?php echo esc_attr($config['email_subject'] ?? 'Terima kasih telah menghubungi kami — {site_name}'); ?>">
            </div>
            <div class="eo-meta-field">
                <label>Body Email (HTML diperbolehkan)</label>
                <textarea name="eo_email_template" rows="6"><?php echo esc_textarea($config['email_template'] ?? EO_Form_Builder::default_email_template()); ?></textarea>
                <p class="eo-meta-hint">Variabel: <code>{nama}</code> <code>{pilihan}</code> <code>{harga}</code> <code>{site_name}</code></p>
            </div>
        </div>

        <!-- SECTION: Notifikasi & Lainnya -->
        <div class="eo-meta-section">
            <div class="eo-meta-section-title">🔔 Notifikasi Internal & Opsi Lain</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="eo-meta-field">
                    <label>Override WA Notifikasi (opsional)</label>
                    <input type="text" name="eo_notify_wa" value="<?php echo esc_attr($config['notify_wa'] ?? ''); ?>" placeholder="Kosong = pakai global setting">
                </div>
                <div class="eo-meta-field">
                    <label>Override Email Notifikasi (opsional)</label>
                    <input type="email" name="eo_notify_email" value="<?php echo esc_attr($config['notify_email'] ?? ''); ?>" placeholder="Kosong = pakai global setting">
                </div>
                <div class="eo-meta-field">
                    <label>Link COA / Katalog (opsional)</label>
                    <input type="text" name="eo_coa_link" value="<?php echo esc_attr($config['coa_link'] ?? ''); ?>" placeholder="https://...">
                </div>
                <div class="eo-meta-field">
                    <label>Judul Pesan Sukses</label>
                    <input type="text" name="eo_success_title" value="<?php echo esc_attr($config['success_title'] ?? 'Terima Kasih!'); ?>">
                </div>
            </div>
            <div class="eo-meta-field">
                <label>Pesan Sukses</label>
                <textarea name="eo_success_message" rows="2"><?php echo esc_textarea($config['success_message'] ?? 'Tim kami akan segera menghubungi Anda.'); ?></textarea>
            </div>
        </div>

        <script>
        var _eoFieldIdx = <?php echo count($fields); ?>;
        var _eoProdIdx  = <?php echo count($products); ?>;

        function eoAddProduct() {
            var i = _eoProdIdx++;
            var div = document.createElement('div');
            div.className = 'eo-product-item';
            div.innerHTML = '<input type="text" name="eo_products['+i+'][label]" placeholder="Nama produk/paket">'
                + '<input type="text" name="eo_products['+i+'][price]" placeholder="Harga (opsional)">'
                + '<button type="button" class="eo-btn eo-btn-danger eo-btn-sm" onclick="eoRemoveProduct(this)">✕</button>';
            document.getElementById('eo-products-list').appendChild(div);
        }

        function eoAddField() {
            var i = _eoFieldIdx++;
            var label = 'field_' + i;
            var div = document.createElement('div');
            div.className = 'eo-product-item';
            div.style = 'grid-template-columns:1fr 100px 100px auto;';
            div.innerHTML = '<input type="text" name="eo_fields['+i+'][label]" placeholder="Label field">'
                + '<select name="eo_fields['+i+'][type]"><option>text</option><option>email</option><option>tel</option><option>textarea</option><option>select</option></select>'
                + '<label style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;cursor:pointer;"><input type="checkbox" name="eo_fields['+i+'][required]" value="1"> Wajib</label>'
                + '<button type="button" class="eo-btn eo-btn-danger eo-btn-sm" onclick="eoRemoveProduct(this)">✕</button>'
                + '<input type="hidden" name="eo_fields['+i+'][id]" value="'+label+'">'
                + '<input type="hidden" name="eo_fields['+i+'][placeholder]" value="">';
            document.getElementById('eo-fields-list').appendChild(div);
        }

        function eoRemoveProduct(btn) {
            btn.closest('.eo-product-item').remove();
        }
        </script>
        <?php
    }

    public static function save_form_meta_box( $post_id ) {
        if ( ! isset($_POST['eo_form_nonce']) ) return;
        if ( ! wp_verify_nonce($_POST['eo_form_nonce'], 'eo_save_form_meta') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        /* Products */
        $products = [];
        if ( ! empty($_POST['eo_products']) && is_array($_POST['eo_products']) ) {
            foreach ( $_POST['eo_products'] as $p ) {
                $label = sanitize_text_field($p['label'] ?? '');
                if ( $label ) {
                    $products[] = [
                        'label' => $label,
                        'price' => sanitize_text_field($p['price'] ?? ''),
                        'value' => sanitize_key($label),
                    ];
                }
            }
        }

        /* Fields */
        $fields = [];
        if ( ! empty($_POST['eo_fields']) && is_array($_POST['eo_fields']) ) {
            foreach ( $_POST['eo_fields'] as $f ) {
                $label = sanitize_text_field($f['label'] ?? '');
                if ( $label ) {
                    $fid = sanitize_key($f['id'] ?? $label);
                    if ( ! $fid ) $fid = 'field_' . count($fields);
                    $fields[] = [
                        'id'          => $fid,
                        'label'       => $label,
                        'type'        => sanitize_key($f['type'] ?? 'text'),
                        'required'    => ! empty($f['required']),
                        'placeholder' => sanitize_text_field($f['placeholder'] ?? ''),
                    ];
                }
            }
        }
        if ( empty($fields) ) $fields = EO_Form_Builder::default_fields();

        $config = [
            'products'        => $products,
            'fields'          => $fields,
            'wa_template'     => sanitize_textarea_field($_POST['eo_wa_template']      ?? ''),
            'email_subject'   => sanitize_text_field($_POST['eo_email_subject']        ?? ''),
            'email_template'  => wp_kses_post($_POST['eo_email_template']              ?? ''),
            'notify_wa'       => sanitize_text_field($_POST['eo_notify_wa']            ?? ''),
            'notify_email'    => sanitize_email($_POST['eo_notify_email']              ?? ''),
            'coa_link'        => esc_url_raw($_POST['eo_coa_link']                     ?? ''),
            'success_title'   => sanitize_text_field($_POST['eo_success_title']        ?? 'Terima Kasih!'),
            'success_message' => sanitize_textarea_field($_POST['eo_success_message']  ?? ''),
        ];

        EO_Form_Builder::save_form_config( $post_id, $config );
    }
}
