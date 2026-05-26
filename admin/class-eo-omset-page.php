<?php
/**
 * EO Omset Page — Dashboard Peluang Omset Penjualan
 * Menghitung potensi omset dari leads berdasarkan harga pilihan (integer)
 */
class EO_Omset_Page {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'eo-manager',
            'Dashboard Omset',
            '💰 Omset',
            'manage_options',
            'eo-omset',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) return;

        /* ── Filter params ── */
        $post_id   = (int) ($_GET['product']   ?? 0);
        $status    = sanitize_text_field($_GET['status']    ?? '');
        $date_from = sanitize_text_field($_GET['date_from'] ?? '');
        $date_to   = sanitize_text_field($_GET['date_to']   ?? '');
        $group_by  = sanitize_key($_GET['group_by'] ?? 'day');
        if ( ! in_array($group_by, ['day','month','product']) ) $group_by = 'day';

        /* Default range: 30 hari terakhir jika tidak diset */
        if ( ! $date_from && ! $date_to ) {
            $date_from = date('Y-m-d', strtotime('-29 days'));
            $date_to   = date('Y-m-d');
        }

        $filter_args = [
            'post_id'   => $post_id,
            'status'    => $status,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'group_by'  => $group_by,
        ];

        $stats   = EO_Leads::get_omset_stats( $filter_args );
        $summary = [
            'total_leads' => $stats['total_leads'],
            'total_omset' => $stats['total_omset'],
            'avg_omset'   => $stats['total_leads'] > 0
                ? (int) round($stats['total_omset'] / $stats['total_leads'])
                : 0,
        ];

        /* Semua produk untuk dropdown */
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

        /* ── Chart data untuk JS ── */
        $chart_labels = [];
        $chart_omset  = [];
        $chart_leads  = [];
        foreach ( $stats['rows'] as $row ) {
            $chart_labels[] = $row->label;
            $chart_omset[]  = (int) $row->omset;
            $chart_leads[]  = (int) $row->leads;
        }

        $grand_total_stats = EO_Leads::get_stats();
        ?>
        <style>
        #wpcontent { padding-left: 20px !important; }
        .eo-omset-wrap {
            max-width: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            padding-right: 20px;
        }
        .eo-omset-wrap * { box-sizing: border-box; }
        .eo-omset-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 22px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,.07);
            margin-bottom: 20px;
        }
        .eo-omset-card-title {
            font-size: 15px; font-weight: 700; color: #0f172a;
            margin: 0 0 18px; padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .eo-omset-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .eo-omset-stat {
            background: #fff; border: 1px solid #e2e8f0;
            border-radius: 10px; padding: 20px;
            border-left: 4px solid #16a34a;
            box-shadow: 0 1px 3px rgba(0,0,0,.07);
        }
        .eo-omset-stat .num {
            font-size: 26px; font-weight: 800;
            color: #0f172a; line-height: 1.1;
            white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis;
        }
        .eo-omset-stat .lbl {
            font-size: 12px; color: #64748b;
            margin-top: 4px; font-weight: 500;
        }
        .eo-filter-form {
            display: flex; gap: 10px; flex-wrap: wrap;
            align-items: center; margin-bottom: 20px;
        }
        .eo-filter-form input,
        .eo-filter-form select {
            padding: 8px 12px; border: 1.5px solid #e2e8f0;
            border-radius: 7px; font-size: 13px;
            background: #fff; color: #0f172a;
        }
        .eo-filter-form input:focus,
        .eo-filter-form select:focus {
            outline: none; border-color: #15803d;
        }
        .eo-omset-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .eo-omset-table th {
            background: #f8fafc; padding: 10px 14px; text-align: left;
            font-weight: 700; color: #64748b; font-size: 11px;
            text-transform: uppercase; letter-spacing: .5px;
            border-bottom: 2px solid #e2e8f0;
        }
        .eo-omset-table td {
            padding: 10px 14px; border-bottom: 1px solid #e2e8f0;
            color: #0f172a; vertical-align: middle;
        }
        .eo-omset-table tr:last-child td { border-bottom: none; }
        .eo-omset-table tr:hover td { background: #f8fafc; }
        .eo-omset-bar-wrap {
            background: #e2e8f0; border-radius: 999px;
            height: 8px; min-width: 60px; overflow: hidden;
        }
        .eo-omset-bar {
            height: 8px; border-radius: 999px;
            background: linear-gradient(90deg, #15803d, #4ade80);
            transition: width .4s ease;
        }
        .eo-chart-wrap {
            position: relative; width: 100%; height: 280px;
        }
        </style>

        <div class="eo-omset-wrap">

            <div class="eo-page-header">
                <div class="eo-page-icon">💰</div>
                <div>
                    <h1>Dashboard Omset</h1>
                    <p>Estimasi potensi omset dari leads yang masuk. Dihitung dari harga pilihan x jumlah leads.</p>
                </div>
            </div>

            <!-- ── Summary All-time (tidak terpengaruh filter) ── -->
            <div class="eo-omset-stat-grid">
                <?php
                $alltime = EO_Leads::get_omset_stats([]);
                $alltime_items = [
                    [ 'num' => 'Rp ' . number_format($grand_total_stats['omset_total'] ?? 0, 0, ',', '.'),
                      'lbl' => 'Total Omset (All Time)', 'color' => '#15803d' ],
                    [ 'num' => 'Rp ' . number_format($grand_total_stats['omset_month'] ?? 0, 0, ',', '.'),
                      'lbl' => 'Omset Bulan Ini', 'color' => '#0284c7' ],
                    [ 'num' => number_format($alltime['total_leads']),
                      'lbl' => 'Total Leads Berbayar', 'color' => '#7c3aed' ],
                    [ 'num' => $alltime['total_leads'] > 0
                               ? 'Rp ' . number_format((int)round($alltime['total_omset']/$alltime['total_leads']), 0, ',', '.')
                               : 'Rp 0',
                      'lbl' => 'Rata-rata per Lead', 'color' => '#d97706' ],
                ];
                foreach ( $alltime_items as $s ) : ?>
                <div class="eo-omset-stat" style="border-left-color:<?php echo $s['color']; ?>">
                    <div class="num" title="<?php echo esc_attr($s['num']); ?>"><?php echo esc_html($s['num']); ?></div>
                    <div class="lbl"><?php echo esc_html($s['lbl']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Filter ── -->
            <div class="eo-omset-card">
                <div class="eo-omset-card-title">🔍 Filter Periode & Produk</div>
                <form method="get" action="" class="eo-filter-form">
                    <input type="hidden" name="page" value="eo-omset">
                    <input type="date" name="date_from"
                           value="<?php echo esc_attr($date_from); ?>"
                           title="Dari tanggal">
                    <span style="color:#94a3b8;font-size:13px;">s/d</span>
                    <input type="date" name="date_to"
                           value="<?php echo esc_attr($date_to); ?>"
                           title="Sampai tanggal">
                    <select name="product">
                        <option value="">Semua Produk</option>
                        <?php foreach ( $products as $p ) : ?>
                        <option value="<?php echo $p->ID; ?>"
                                <?php selected($post_id, $p->ID); ?>>
                            <?php echo esc_html($p->post_title); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <?php foreach ( $status_options as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr($val); ?>"
                                <?php selected($status, $val); ?>>
                            <?php echo esc_html($lbl); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="group_by">
                        <option value="day"     <?php selected($group_by,'day'); ?>>Harian</option>
                        <option value="month"   <?php selected($group_by,'month'); ?>>Bulanan</option>
                        <option value="product" <?php selected($group_by,'product'); ?>>Per Produk</option>
                    </select>
                    <button type="submit" class="eo-btn eo-btn-primary" style="padding:8px 18px;font-size:13px;">
                        Terapkan Filter
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=eo-omset'); ?>"
                       class="eo-btn eo-btn-outline" style="padding:8px 18px;font-size:13px;">
                        Reset
                    </a>
                </form>

                <!-- ── Stat hasil filter ── -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;
                            padding:16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                    <?php
                    $filtered_items = [
                        [ 'num' => 'Rp ' . number_format($summary['total_omset'], 0, ',', '.'),
                          'lbl' => 'Omset Periode Ini',   'color' => '#15803d' ],
                        [ 'num' => number_format($summary['total_leads']),
                          'lbl' => 'Leads Berbayar',      'color' => '#0284c7' ],
                        [ 'num' => 'Rp ' . number_format($summary['avg_omset'], 0, ',', '.'),
                          'lbl' => 'Rata-rata per Lead',  'color' => '#d97706' ],
                    ];
                    foreach ( $filtered_items as $s ) : ?>
                    <div style="padding:14px 16px;background:#fff;border-radius:8px;
                                border-left:3px solid <?php echo $s['color']; ?>;
                                border:1px solid #e2e8f0;border-left-width:3px;">
                        <div style="font-size:20px;font-weight:800;color:#0f172a;">
                            <?php echo esc_html($s['num']); ?>
                        </div>
                        <div style="font-size:12px;color:#64748b;margin-top:3px;">
                            <?php echo esc_html($s['lbl']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="padding:14px 16px;background:#fffbeb;border-radius:8px;
                                border:1px solid #fde68a;font-size:12px;color:#92400e;
                                display:flex;align-items:center;gap:8px;">
                        <span style="font-size:20px;">⚠️</span>
                        <span>Hanya lead dengan harga &gt; 0 yang dihitung. Lead FREE tidak masuk kalkulasi omset.</span>
                    </div>
                </div>
            </div>

            <!-- ── Grafik ── -->
            <?php if ( ! empty($stats['rows']) ) : ?>
            <div class="eo-omset-card">
                <div class="eo-omset-card-title">
                    📈 Grafik Omset —
                    <?php
                    $group_label = ['day'=>'Harian','month'=>'Bulanan','product'=>'Per Produk'];
                    echo esc_html($group_label[$group_by] ?? 'Harian');
                    ?>
                    <span style="font-size:12px;font-weight:400;color:#64748b;margin-left:8px;">
                        <?php echo esc_html($date_from); ?> s/d <?php echo esc_html($date_to); ?>
                    </span>
                </div>
                <div class="eo-chart-wrap">
                    <canvas id="eo-omset-chart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Tabel Detail ── -->
            <?php if ( ! empty($stats['rows']) ) : ?>
            <div class="eo-omset-card" style="padding:0;overflow:hidden;">
                <div style="padding:18px 20px;border-bottom:1px solid #e2e8f0;">
                    <h3 class="eo-omset-card-title" style="margin:0;border:none;padding:0;">
                        📊 Detail
                        <?php echo esc_html($group_label[$group_by] ?? ''); ?>
                    </h3>
                </div>
                <?php
                // Hitung max omset untuk bar
                $max_omset = max( array_map(function($r){ return (int)$r->omset; }, $stats['rows']) );
                $max_omset = $max_omset > 0 ? $max_omset : 1;
                ?>
                <div style="overflow-x:auto;">
                <table class="eo-omset-table">
                    <thead>
                        <tr>
                            <th><?php echo $group_by === 'product' ? 'Produk' : 'Periode'; ?></th>
                            <th>Leads</th>
                            <th>Omset</th>
                            <th style="width:200px;">Proporsi</th>
                            <th>Rata-rata/Lead</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $stats['rows'] as $row ) :
                        $row_omset   = (int) $row->omset;
                        $row_leads   = (int) $row->leads;
                        $row_avg     = $row_leads > 0 ? (int) round($row_omset / $row_leads) : 0;
                        $bar_pct     = $max_omset > 0 ? round(($row_omset / $max_omset) * 100) : 0;
                    ?>
                    <tr>
                        <td style="font-weight:600;">
                            <?php echo esc_html($row->label); ?>
                        </td>
                        <td>
                            <span style="font-weight:700;color:#0284c7;">
                                <?php echo number_format($row_leads); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight:700;color:#15803d;">
                                Rp <?php echo number_format($row_omset, 0, ',', '.'); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="eo-omset-bar-wrap" style="flex:1;">
                                    <div class="eo-omset-bar"
                                         style="width:<?php echo $bar_pct; ?>%"></div>
                                </div>
                                <span style="font-size:12px;color:#64748b;width:36px;text-align:right;">
                                    <?php echo $bar_pct; ?>%
                                </span>
                            </div>
                        </td>
                        <td style="color:#64748b;font-size:13px;">
                            Rp <?php echo number_format($row_avg, 0, ',', '.'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f0fdf4;">
                            <td style="font-weight:700;padding:12px 14px;">TOTAL</td>
                            <td style="font-weight:700;color:#0284c7;padding:12px 14px;">
                                <?php echo number_format($summary['total_leads']); ?>
                            </td>
                            <td style="font-weight:700;color:#15803d;padding:12px 14px;">
                                Rp <?php echo number_format($summary['total_omset'], 0, ',', '.'); ?>
                            </td>
                            <td style="padding:12px 14px;">—</td>
                            <td style="font-weight:700;padding:12px 14px;">
                                Rp <?php echo number_format($summary['avg_omset'], 0, ',', '.'); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </div>
            <?php else : ?>
            <div class="eo-omset-card" style="text-align:center;padding:40px;">
                <div style="font-size:48px;margin-bottom:12px;">📭</div>
                <p style="color:#64748b;">Tidak ada data omset untuk filter ini.<br>
                Pastikan produk sudah diisi harga (angka &gt; 0) dan ada leads yang masuk.</p>
            </div>
            <?php endif; ?>

        </div><!-- /.eo-omset-wrap -->

        <?php if ( ! empty($stats['rows']) ) : ?>
        <!-- Chart.js via CDN -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"
                integrity="sha512-ZwR1/gSZM3ai6vCdI+LVF1zSq/5HznD3oD+sCoJrzXJ+yKen9RtFkiUDe/KhakxdT2tmdI5YSIjSsmfnMRXg=="
                crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script>
        (function() {
            var labels  = <?php echo wp_json_encode($chart_labels); ?>;
            var omset   = <?php echo wp_json_encode($chart_omset); ?>;
            var leads   = <?php echo wp_json_encode($chart_leads); ?>;
            var groupBy = <?php echo wp_json_encode($group_by); ?>;

            var ctx = document.getElementById('eo-omset-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Omset (Rp)',
                            data: omset,
                            backgroundColor: 'rgba(21,128,61,0.15)',
                            borderColor: '#15803d',
                            borderWidth: 2,
                            borderRadius: 6,
                            yAxisID: 'yOmset',
                            order: 2,
                        },
                        {
                            label: 'Jumlah Leads',
                            data: leads,
                            type: 'line',
                            borderColor: '#0284c7',
                            backgroundColor: 'rgba(2,132,199,0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: '#0284c7',
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'yLeads',
                            order: 1,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    if (ctx.datasetIndex === 0) {
                                        return ' Omset: Rp ' + ctx.raw.toLocaleString('id-ID');
                                    }
                                    return ' Leads: ' + ctx.raw;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: '#f1f5f9' },
                            ticks: { maxRotation: 45, font: { size: 11 } }
                        },
                        yOmset: {
                            type: 'linear',
                            position: 'left',
                            grid: { color: '#f1f5f9' },
                            ticks: {
                                font: { size: 11 },
                                callback: function(v) {
                                    if (v >= 1000000) return 'Rp ' + (v/1000000).toFixed(1) + 'jt';
                                    if (v >= 1000)    return 'Rp ' + (v/1000).toFixed(0) + 'rb';
                                    return 'Rp ' + v;
                                }
                            }
                        },
                        yLeads: {
                            type: 'linear',
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: { font: { size: 11 }, stepSize: 1 }
                        }
                    }
                }
            });
        })();
        </script>
        <?php endif; ?>
        <?php
    }
}
