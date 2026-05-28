<?php
/**
 * EO CRM Page v3 — Leads management dengan UI modern
 * - Tambah kolom Harga di tabel
 * - Full width layout
 */
class EO_CRM_Page {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'wp_ajax_eo_update_lead_status', [ __CLASS__, 'ajax_update_status' ] );
        add_action( 'wp_ajax_eo_delete_lead',        [ __CLASS__, 'ajax_delete_lead' ] );
        add_action( 'wp_ajax_eo_export_leads',       [ __CLASS__, 'ajax_export' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'eo-manager',
            'CRM Leads',
            '👥 CRM Leads',
            'manage_options',
            'eo-crm',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) return;

        if ( isset($_GET['eo_export']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'eo_export') ) {
            EO_Leads::export_csv();
        }

        $page      = max(1, (int)($_GET['paged']     ?? 1));
        $search    = sanitize_text_field($_GET['s']         ?? '');
        $status    = sanitize_text_field($_GET['status']    ?? '');
        $post_id   = (int)($_GET['product']  ?? 0);
        $date_from = sanitize_text_field($_GET['date_from'] ?? '');
        $date_to   = sanitize_text_field($_GET['date_to']   ?? '');

        $result = EO_Leads::get_leads([
            'page'      => $page,
            'search'    => $search,
            'status'    => $status,
            'post_id'   => $post_id,
            'date_from' => $date_from,
            'date_to'   => $date_to,
        ]);

        $leads       = $result['items'];
        $total       = $result['total'];
        $total_pages = $result['pages'];

        $products = get_posts([
            'post_type'   => 'eo_product',
            'numberposts' => -1,
            'post_status' => ['publish','draft'],
        ]);

        $status_options = [
            ''          => 'Semua Status',
            'new'       => '🔵 Baru',
            'contacted' => '🟡 Dihubungi',
            'qualified' => '🟢 Qualified',
            'closed'    => '⚫ Closed',
            'lost'      => '🔴 Lost',
        ];

        $nonce      = wp_create_nonce('wp_rest');
        $export_url = wp_nonce_url(
            admin_url('admin.php?page=eo-crm&eo_export=1'
                . ($search ? '&s=' . urlencode($search) : '')
                . ($status ? '&status=' . urlencode($status) : '')
                . ($post_id ? '&product=' . $post_id : '')
            ),
            'eo_export'
        );
        ?>
        <style>
        /* ── Full-width override untuk halaman CRM ── */
        #wpcontent { padding-left: 20px !important; }
        .eo-crm-wrap {
            max-width: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            padding-right: 20px;
        }
        .eo-crm-wrap * { box-sizing: border-box; }
        </style>

        <div class="eo-crm-wrap">
            <div class="eo-page-header">
                <div class="eo-page-icon">👥</div>
                <div>
                    <h1>CRM Leads</h1>
                    <p><?php echo number_format($total); ?> lead ditemukan</p>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="get" action="">
                <input type="hidden" name="page" value="eo-crm">
                <div class="eo-filter-bar">
                    <input type="text" name="s"
                           value="<?php echo esc_attr($search); ?>"
                           placeholder="🔍 Cari nama, WA, email...">
                    <select name="status">
                        <?php foreach ( $status_options as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($status, $val); ?>>
                            <?php echo esc_html($lbl); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="product">
                        <option value="">Semua Produk</option>
                        <?php foreach ( $products as $p ) : ?>
                        <option value="<?php echo $p->ID; ?>" <?php selected($post_id, $p->ID); ?>>
                            <?php echo esc_html($p->post_title); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_from"
                           value="<?php echo esc_attr($date_from); ?>"
                           title="Dari tanggal">
                    <input type="date" name="date_to"
                           value="<?php echo esc_attr($date_to); ?>"
                           title="Sampai tanggal">
                    <button type="submit" class="eo-btn eo-btn-outline">Filter</button>
                    <a href="<?php echo admin_url('admin.php?page=eo-crm'); ?>"
                       class="eo-btn eo-btn-outline">Reset</a>
                    <a href="<?php echo esc_url($export_url); ?>"
                       class="eo-btn eo-btn-outline"
                       style="margin-left:auto;">⬇️ Export CSV</a>
                </div>
            </form>

            <div class="eo-card" style="padding:0;overflow:hidden;">
                <?php if ( empty($leads) ) : ?>
                    <div style="padding:40px;text-align:center;color:var(--eo-muted);">
                        <div style="font-size:40px;margin-bottom:8px;">📭</div>
                        <p>Tidak ada lead ditemukan.</p>
                    </div>
                <?php else : ?>
                <div style="overflow-x:auto;width:100%;">
                <table class="eo-table" style="min-width:900px;">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Nama</th>
                            <th>WhatsApp</th>
                            <th>Email</th>
                            <th>Produk</th>
                            <th>Pilihan</th>
                            <th style="width:120px;">Harga</th>
                            <th style="width:130px;">Status</th>
                            <th style="width:110px;">Waktu</th>
                            <th style="width:80px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="eo-leads-tbody">
                    <?php
                    $st_map = [
                        'new'       => ['new',       '🔵 Baru'],
                        'contacted' => ['contacted', '🟡 Dihubungi'],
                        'qualified' => ['qualified', '🟢 Qualified'],
                        'closed'    => ['closed',    '⚫ Closed'],
                        'lost'      => ['lost',      '🔴 Lost'],
                    ];
                    foreach ( $leads as $lead ) :
                        $st_cls      = $st_map[$lead->status][0] ?? 'new';
                        $price_num   = (int) ($lead->pilihan_price_num ?? 0);
                        $price_disp  = '';
                        if ( $lead->pilihan_label !== '' ) {
                            $price_disp = $price_num === 0
                                ? 'FREE'
                                : 'Rp ' . number_format($price_num, 0, ',', '.');
                        }
                    ?>
                    <tr id="lead-row-<?php echo $lead->id; ?>">
                        <td style="color:var(--eo-muted);font-size:12px;">#<?php echo $lead->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($lead->nama); ?></strong>
                            <?php if ( $lead->perusahaan ) : ?>
                            <br><span style="font-size:11px;color:var(--eo-muted);">
                                <?php echo esc_html($lead->perusahaan); ?>
                            </span>
                            <?php endif; ?>
                            <?php if ( $lead->catatan ) : ?>
                            <br><span style="font-size:11px;color:#d97706;font-style:italic;"
                                      title="<?php echo esc_attr($lead->catatan); ?>">📝 Ada catatan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="https://wa.me/<?php echo preg_replace('/\D/','',$lead->wa); ?>"
                               target="_blank"
                               style="color:var(--eo-green-dk);font-weight:600;">
                                <?php echo esc_html($lead->wa); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ( $lead->email ) : ?>
                            <a href="mailto:<?php echo esc_attr($lead->email); ?>"
                               style="color:var(--eo-muted);">
                                <?php echo esc_html($lead->email); ?>
                            </a>
                            <?php else : ?>
                            <span style="color:var(--eo-border);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;max-width:140px;">
                            <?php echo esc_html($lead->post_title); ?>
                        </td>
                        <td style="font-size:13px;max-width:120px;">
                            <?php echo esc_html($lead->pilihan_label ?: '—'); ?>
                        </td>
                        <td>
                            <?php if ( $price_disp === 'FREE' ) : ?>
                                <span style="font-size:12px;font-weight:700;color:#0284c7;
                                             background:#eff6ff;padding:2px 8px;border-radius:999px;">
                                    FREE
                                </span>
                            <?php elseif ( $price_disp ) : ?>
                                <span style="font-size:12px;font-weight:700;color:var(--eo-green-dk);">
                                    <?php echo esc_html($price_disp); ?>
                                </span>
                            <?php else : ?>
                                <span style="color:var(--eo-border);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select class="eo-status-select"
                                    data-id="<?php echo $lead->id; ?>"
                                    onchange="eoUpdateStatus(<?php echo $lead->id; ?>, this.value)"
                                    style="padding:4px 8px;border:1.5px solid var(--eo-border);
                                           border-radius:6px;font-size:12px;cursor:pointer;
                                           font-weight:600;width:100%;">
                                <?php foreach ( $st_map as $val => $info ) : ?>
                                <option value="<?php echo $val; ?>"
                                        <?php selected($lead->status, $val); ?>>
                                    <?php echo $info[1]; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="font-size:12px;color:var(--eo-muted);white-space:nowrap;">
                            <?php echo esc_html( mysql2date('d/m/Y H:i', $lead->created_at) ); ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <button class="eo-btn eo-btn-outline eo-btn-sm"
                                        onclick="eoViewLead(<?php echo $lead->id; ?>)"
                                        title="Detail">👁</button>
                                <button class="eo-btn eo-btn-danger eo-btn-sm"
                                        onclick="eoDeleteLead(<?php echo $lead->id; ?>)"
                                        title="Hapus">🗑</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <!-- Pagination -->
                <?php if ( $total_pages > 1 ) : ?>
                <div style="padding:14px 20px;display:flex;align-items:center;gap:8px;
                            border-top:1px solid var(--eo-border);">
                    <span style="font-size:13px;color:var(--eo-muted);">
                        Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>
                        &nbsp;·&nbsp; <?php echo number_format($total); ?> leads
                    </span>
                    <div style="margin-left:auto;" class="eo-pagination">
                        <?php
                        // Tampilkan max 10 halaman, dengan ellipsis jika perlu
                        $range = 5;
                        $start = max(1, $page - $range);
                        $end   = min($total_pages, $page + $range);
                        if ( $start > 1 ) {
                            $url = add_query_arg(['paged'=>1,'s'=>$search,'status'=>$status,'product'=>$post_id?:''], admin_url('admin.php?page=eo-crm'));
                            echo '<a href="' . esc_url($url) . '" class="eo-page-btn">1</a>';
                            if ( $start > 2 ) echo '<span class="eo-page-btn" style="cursor:default;">…</span>';
                        }
                        for ( $p = $start; $p <= $end; $p++ ) :
                            $url = add_query_arg(['paged'=>$p,'s'=>$search,'status'=>$status,'product'=>$post_id?:''], admin_url('admin.php?page=eo-crm'));
                        ?>
                        <a href="<?php echo esc_url($url); ?>"
                           class="eo-page-btn <?php echo $p === $page ? 'active' : ''; ?>">
                           <?php echo $p; ?>
                        </a>
                        <?php endfor;
                        if ( $end < $total_pages ) {
                            if ( $end < $total_pages - 1 ) echo '<span class="eo-page-btn" style="cursor:default;">…</span>';
                            $url = add_query_arg(['paged'=>$total_pages,'s'=>$search,'status'=>$status,'product'=>$post_id?:''], admin_url('admin.php?page=eo-crm'));
                            echo '<a href="' . esc_url($url) . '" class="eo-page-btn">' . $total_pages . '</a>';
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div><!-- /.eo-card -->
        </div><!-- /.eo-crm-wrap -->

        <!-- Modal Detail Lead -->
        <div id="eo-lead-modal"
             style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
                    z-index:9999;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:14px;padding:28px;max-width:540px;
                        width:90%;max-height:85vh;overflow-y:auto;
                        box-shadow:0 20px 60px rgba(0,0,0,.2);">
                <div style="display:flex;justify-content:space-between;align-items:center;
                            margin-bottom:20px;">
                    <h3 style="margin:0;font-size:16px;font-weight:700;color:var(--eo-slate);">
                        Detail Lead
                    </h3>
                    <button onclick="document.getElementById('eo-lead-modal').style.display='none'"
                            style="background:none;border:none;font-size:20px;cursor:pointer;
                                   color:var(--eo-muted);">✕</button>
                </div>
                <div id="eo-lead-modal-content"></div>
                <div style="margin-top:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">
                        Catatan
                    </label>
                    <textarea id="eo-lead-notes" rows="3"
                              style="width:100%;padding:8px;border:1.5px solid var(--eo-border);
                                     border-radius:7px;font-size:13px;"
                              placeholder="Tambah catatan..."></textarea>
                    <button class="eo-btn eo-btn-primary"
                            style="margin-top:8px;"
                            onclick="eoSaveNotes()">💾 Simpan Catatan</button>
                </div>
            </div>
        </div>

        <script>
        var _eoNonce  = '<?php echo $nonce; ?>';
        var _eoAjax   = '<?php echo admin_url('admin-ajax.php'); ?>';
        var _eoLeadId = 0;
        var _eoLeads  = <?php echo wp_json_encode( array_map(function($l) {
            $price_num  = (int) ($l->pilihan_price_num ?? 0);
            $price_disp = '';
            if ( $l->pilihan_label !== '' ) {
                $price_disp = $price_num === 0
                    ? 'FREE'
                    : 'Rp ' . number_format($price_num, 0, ',', '.');
            }
            return [
                'id'            => $l->id,
                'nama'          => $l->nama,
                'wa'            => $l->wa,
                'email'         => $l->email,
                'perusahaan'    => $l->perusahaan,
                'post_title'    => $l->post_title,
                'pilihan_label' => $l->pilihan_label,
                'pilihan_price' => $l->pilihan_price,
                'price_num'     => $price_num,
                'price_disp'    => $price_disp,
                'status'        => $l->status,
                'catatan'       => $l->catatan,
                'sumber_url'    => $l->sumber_url,
                'created_at'    => $l->created_at,
                'extra_fields'  => json_decode($l->extra_fields, true) ?: [],
            ];
        }, $leads) ); ?>;

        function eoUpdateStatus(id, status) {
            fetch(_eoAjax, {
                method  : 'POST',
                headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
                body    : new URLSearchParams({
                    action : 'eo_update_lead_status',
                    nonce  : _eoNonce,
                    id     : id,
                    status : status
                })
            }).then(r => r.json()).then(res => {
                if (!res.success) alert('Gagal update status: ' + res.data);
            });
        }

        function eoDeleteLead(id) {
            if (!confirm('Hapus lead #' + id + '? Tindakan ini tidak dapat dibatalkan.')) return;
            fetch(_eoAjax, {
                method  : 'POST',
                headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
                body    : new URLSearchParams({
                    action : 'eo_delete_lead',
                    nonce  : _eoNonce,
                    id     : id
                })
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    var row = document.getElementById('lead-row-' + id);
                    if (row) row.remove();
                } else {
                    alert('Gagal: ' + res.data);
                }
            });
        }

        function eoViewLead(id) {
            var lead = _eoLeads.find(function(l) { return l.id == id; });
            if (!lead) return;
            _eoLeadId = id;

            var rows = [
                ['Nama',       lead.nama],
                ['WhatsApp',   '<a href="https://wa.me/'+lead.wa.replace(/\D/g,'')+'" target="_blank">'+lead.wa+'</a>'],
                ['Email',      lead.email || '—'],
                ['Perusahaan', lead.perusahaan || '—'],
                ['Produk',     lead.post_title || '—'],
                ['Pilihan',    lead.pilihan_label || '—'],
                ['Harga',      lead.price_disp
                                ? '<strong style="color:'+(lead.price_num===0?'#0284c7':'#15803d')+'">'+lead.price_disp+'</strong>'
                                : '—'],
                ['Status',     lead.status],
                ['Waktu',      lead.created_at],
                ['Sumber',     lead.sumber_url
                                ? '<a href="'+lead.sumber_url+'" target="_blank" style="word-break:break-all;">'+lead.sumber_url+'</a>'
                                : '—'],
            ];

            if (lead.extra_fields) {
                Object.entries(lead.extra_fields).forEach(function(e) {
                    if (e[0] && !e[0].startsWith('_')) rows.push([e[0], e[1]]);
                });
            }

            var html = '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
            rows.forEach(function(r, i) {
                html += '<tr style="background:'+(i%2?'#f8fafc':'#fff')+'">'
                    + '<td style="padding:8px 10px;font-weight:700;width:110px;color:#64748b;">'+r[0]+'</td>'
                    + '<td style="padding:8px 10px;">'+r[1]+'</td></tr>';
            });
            html += '</table>';

            document.getElementById('eo-lead-modal-content').innerHTML = html;
            document.getElementById('eo-lead-notes').value = lead.catatan || '';
            document.getElementById('eo-lead-modal').style.display = 'flex';
        }

        function eoSaveNotes() {
            var notes = document.getElementById('eo-lead-notes').value;
            fetch(_eoAjax, {
                method  : 'POST',
                headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
                body    : new URLSearchParams({
                    action  : 'eo_update_lead_status',
                    nonce   : _eoNonce,
                    id      : _eoLeadId,
                    catatan : notes
                })
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    var lead = _eoLeads.find(function(l) { return l.id == _eoLeadId; });
                    if (lead) lead.catatan = notes;
                    document.getElementById('eo-lead-modal').style.display = 'none';
                }
            });
        }

        /* Tutup modal klik di luar */
        document.getElementById('eo-lead-modal').addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
        </script>
        <?php
    }

    public static function ajax_update_status() {
        if ( ! current_user_can('manage_options') ) wp_die();
        if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest') ) wp_send_json_error('Nonce gagal.');
        $id      = (int)($_POST['id']      ?? 0);
        $status  = sanitize_text_field($_POST['status']   ?? '');
        $catatan = isset($_POST['catatan']) ? sanitize_textarea_field($_POST['catatan']) : null;
        EO_Leads::update_status($id, $status, $catatan);
        wp_send_json_success('Updated');
    }

    public static function ajax_delete_lead() {
        if ( ! current_user_can('manage_options') ) wp_die();
        if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest') ) wp_send_json_error('Nonce gagal.');
        EO_Leads::delete( (int)($_POST['id'] ?? 0) );
        wp_send_json_success('Deleted');
    }

    public static function ajax_export() {
        if ( ! current_user_can('manage_options') ) wp_die();
        EO_Leads::export_csv();
    }
}
