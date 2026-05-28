<?php
/**
 * EO CRM Page — Hotfix A
 * Fix: Export CSV headers, full width, 2 kolom AR status, resize kolom
 */
class EO_CRM_Page {

    public static function init() {
        add_action( 'admin_menu',                    [ __CLASS__, 'add_menu' ] );
        add_action( 'wp_ajax_eo_update_lead_status', [ __CLASS__, 'ajax_update_status' ] );
        add_action( 'wp_ajax_eo_delete_lead',        [ __CLASS__, 'ajax_delete_lead' ] );
        add_action( 'admin_init',                    [ __CLASS__, 'maybe_export_csv' ] );
    }

    public static function add_menu() {
        add_submenu_page( 'eo-manager', 'CRM Leads', '👥 CRM Leads', 'manage_options', 'eo-crm', [ __CLASS__, 'render_page' ] );
    }

    /**
     * FIX UTAMA: Export dipindah ke admin_init
     * agar bisa ob_end_clean() sebelum header CSV dikirim
     */
    public static function maybe_export_csv() {
        if ( ! is_admin() ) return;
        if ( ( $_GET['page'] ?? '' ) !== 'eo-crm' ) return;
        if ( ! isset($_GET['eo_export']) ) return;
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'eo_export' ) ) wp_die('Nonce gagal');

        $args = [
            'search'    => sanitize_text_field( $_GET['s']         ?? '' ),
            'status'    => sanitize_text_field( $_GET['status']    ?? '' ),
            'post_id'   => (int)( $_GET['product']  ?? 0 ),
            'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_GET['date_to']   ?? '' ),
            'per_page'  => 99999,
            'page'      => 1,
        ];

        // Bersihkan semua output buffer
        while ( ob_get_level() > 0 ) ob_end_clean();

        $filename = 'leads-' . date('Y-m-d-His') . '.csv';
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $rows = EO_Leads::get_leads($args)['items'];
        $out  = fopen('php://output', 'w');
        fputs( $out, "\xEF\xBB\xBF" ); // BOM UTF-8 untuk Excel

        fputcsv($out, ['ID','Produk','Nama','WA','Email','Perusahaan','Pilihan','Harga','Harga (Angka)','AR WA','AR Email','Status','Catatan','Waktu']);
        foreach ( $rows as $row ) {
            fputcsv($out, [
                $row->id, $row->post_title, $row->nama, $row->wa, $row->email,
                $row->perusahaan, $row->pilihan_label, $row->pilihan_price,
                $row->pilihan_price_num ?? 0,
                $row->ar_wa_status    ?? 'pending',
                $row->ar_email_status ?? 'pending',
                $row->status, $row->catatan, $row->created_at,
            ]);
        }
        fclose($out);
        exit;
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) return;

        $page      = max(1, (int)($_GET['paged']     ?? 1));
        $search    = sanitize_text_field($_GET['s']         ?? '');
        $status    = sanitize_text_field($_GET['status']    ?? '');
        $post_id   = (int)($_GET['product']  ?? 0);
        $date_from = sanitize_text_field($_GET['date_from'] ?? '');
        $date_to   = sanitize_text_field($_GET['date_to']   ?? '');

        $result      = EO_Leads::get_leads(['page'=>$page,'search'=>$search,'status'=>$status,'post_id'=>$post_id,'date_from'=>$date_from,'date_to'=>$date_to]);
        $leads       = $result['items'];
        $total       = $result['total'];
        $total_pages = $result['pages'];

        $products = get_posts(['post_type'=>'eo_product','numberposts'=>-1,'post_status'=>['publish','draft']]);

        $status_options = [
            ''=>'Semua Status','new'=>'🔵 Baru','contacted'=>'🟡 Dihubungi',
            'qualified'=>'🟢 Qualified','closed'=>'⚫ Closed','lost'=>'🔴 Lost',
        ];

        $nonce = wp_create_nonce('wp_rest');
        $export_url = wp_nonce_url(
            add_query_arg(['page'=>'eo-crm','eo_export'=>'1','s'=>$search,'status'=>$status,'product'=>$post_id?:'','date_from'=>$date_from,'date_to'=>$date_to], admin_url('admin.php')),
            'eo_export'
        );
        ?>
        <style>
        #wpcontent { padding-left:20px !important; }
        .eo-crm-wrap { max-width:100%; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; padding-right:20px; box-sizing:border-box; }
        .eo-crm-wrap * { box-sizing:border-box; }
        .eo-ar-badge { display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;font-size:12px;cursor:default; }
        .eo-ar-sent    { background:#dcfce7; }
        .eo-ar-pending { background:#f1f5f9; }
        .eo-ar-failed  { background:#fee2e2; }
        </style>

        <div class="eo-crm-wrap">
            <div class="eo-page-header">
                <div class="eo-page-icon">👥</div>
                <div><h1>CRM Leads</h1><p><?php echo number_format($total); ?> lead ditemukan</p></div>
            </div>

            <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                <input type="hidden" name="page" value="eo-crm">
                <div class="eo-filter-bar">
                    <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="🔍 Cari nama, WA, email...">
                    <select name="status">
                        <?php foreach ($status_options as $v=>$l): ?>
                        <option value="<?php echo esc_attr($v); ?>" <?php selected($status,$v); ?>><?php echo esc_html($l); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="product">
                        <option value="">Semua Produk</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p->ID; ?>" <?php selected($post_id,$p->ID); ?>><?php echo esc_html($p->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                    <input type="date" name="date_to"   value="<?php echo esc_attr($date_to); ?>">
                    <button type="submit" class="eo-btn eo-btn-outline">Filter</button>
                    <a href="<?php echo admin_url('admin.php?page=eo-crm'); ?>" class="eo-btn eo-btn-outline">Reset</a>
                    <a href="<?php echo esc_url($export_url); ?>" class="eo-btn eo-btn-outline" style="margin-left:auto;">⬇️ Export CSV</a>
                </div>
            </form>

            <div class="eo-card" style="padding:0;overflow:hidden;">
            <?php if (empty($leads)): ?>
                <div style="padding:40px;text-align:center;color:var(--eo-muted);"><div style="font-size:40px;margin-bottom:8px;">📭</div><p>Tidak ada lead ditemukan.</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;width:100%;">
                <table class="eo-table" style="width:100%;table-layout:fixed;min-width:980px;">
                    <colgroup>
                        <col style="width:36px">   <!-- # -->
                        <col style="width:100px">  <!-- Nama -->
                        <col style="width:115px">  <!-- WA -->
                        <col style="width:120px">  <!-- Email -->
                        <col style="min-width:150px"> <!-- Produk -->
                        <col style="width:95px">   <!-- Pilihan -->
                        <col style="width:95px">   <!-- Harga -->
                        <col style="width:44px">   <!-- AR WA -->
                        <col style="width:44px">   <!-- AR Mail -->
                        <col style="width:125px">  <!-- Status -->
                        <col style="width:90px">   <!-- Waktu -->
                        <col style="width:66px">   <!-- Aksi -->
                    </colgroup>
                    <thead><tr>
                        <th>#</th><th>Nama</th><th>WhatsApp</th><th>Email</th>
                        <th>Produk</th><th>Pilihan</th><th>Harga</th>
                        <th title="WA Autoresponder" style="text-align:center;">📱</th>
                        <th title="Email Autoresponder" style="text-align:center;">📧</th>
                        <th>Status</th><th>Waktu</th><th>Aksi</th>
                    </tr></thead>
                    <tbody id="eo-leads-tbody">
                    <?php
                    $st_map = ['new'=>['new','🔵 Baru'],'contacted'=>['contacted','🟡 Dihubungi'],'qualified'=>['qualified','🟢 Qualified'],'closed'=>['closed','⚫ Closed'],'lost'=>['lost','🔴 Lost']];
                    foreach ($leads as $lead):
                        $pn = (int)($lead->pilihan_price_num??0);
                        $pd = $lead->pilihan_label !== '' ? ($pn===0 ? 'FREE' : 'Rp '.number_format($pn,0,',','.')) : '';
                        $ar_wa    = $lead->ar_wa_status    ?? 'pending';
                        $ar_email = $lead->ar_email_status ?? 'pending';
                        $ar_icon  = fn($s,$t) => match($s) {
                            'sent'   => '<span class="eo-ar-badge eo-ar-sent" title="'.$t.' Terkirim">✅</span>',
                            'failed' => '<span class="eo-ar-badge eo-ar-failed" title="'.$t.' Gagal">❌</span>',
                            default  => '<span class="eo-ar-badge eo-ar-pending" title="'.$t.' Pending">⏳</span>',
                        };
                        $wa_href = defined('EO_BROADCASTER_ACTIVE')
                            ? admin_url('admin.php?page=eob-wa&lead_id='.$lead->id)
                            : 'https://wa.me/'.preg_replace('/\D/','',$lead->wa);
                        $em_href = defined('EO_BROADCASTER_ACTIVE')
                            ? admin_url('admin.php?page=eob-email&lead_id='.$lead->id)
                            : 'mailto:'.$lead->email;
                    ?>
                    <tr id="lead-row-<?php echo $lead->id; ?>">
                        <td style="font-size:11px;color:var(--eo-muted);">#<?php echo $lead->id; ?></td>
                        <td>
                            <strong style="font-size:12px;"><?php echo esc_html($lead->nama); ?></strong>
                            <?php if ($lead->perusahaan): ?><br><span style="font-size:10px;color:var(--eo-muted);"><?php echo esc_html($lead->perusahaan); ?></span><?php endif; ?>
                            <?php if ($lead->catatan): ?><br><span style="font-size:10px;color:#d97706;" title="<?php echo esc_attr($lead->catatan); ?>">📝</span><?php endif; ?>
                        </td>
                        <td><a href="<?php echo esc_url($wa_href); ?>" <?php echo !defined('EO_BROADCASTER_ACTIVE') ? 'target="_blank"' : ''; ?> style="color:var(--eo-green-dk);font-weight:600;font-size:12px;"><?php echo esc_html($lead->wa); ?></a></td>
                        <td><?php if ($lead->email): ?><a href="<?php echo esc_attr($em_href); ?>" style="color:var(--eo-muted);font-size:11px;word-break:break-all;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($lead->email); ?>"><?php echo esc_html($lead->email); ?></a><?php else: ?><span style="color:var(--eo-border);">—</span><?php endif; ?></td>
                        <td style="font-size:12px;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($lead->post_title); ?></td>
                        <td style="font-size:11px;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($lead->pilihan_label?:'—'); ?></td>
                        <td><?php if ($pd==='FREE'): ?><span style="font-size:11px;font-weight:700;color:#0284c7;background:#eff6ff;padding:2px 6px;border-radius:999px;">FREE</span><?php elseif ($pd): ?><span style="font-size:11px;font-weight:700;color:var(--eo-green-dk);"><?php echo esc_html($pd); ?></span><?php else: ?><span style="color:var(--eo-border);">—</span><?php endif; ?></td>
                        <td style="text-align:center;"><?php echo $ar_icon($ar_wa,'WA'); ?></td>
                        <td style="text-align:center;"><?php echo $ar_icon($ar_email,'Email'); ?></td>
                        <td>
                            <select onchange="eoUpdateStatus(<?php echo $lead->id; ?>,this.value)" style="padding:3px 5px;border:1.5px solid var(--eo-border);border-radius:5px;font-size:11px;cursor:pointer;font-weight:600;width:100%;">
                                <?php foreach ($st_map as $v=>$inf): ?><option value="<?php echo $v; ?>" <?php selected($lead->status,$v); ?>><?php echo $inf[1]; ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td style="font-size:11px;color:var(--eo-muted);white-space:nowrap;"><?php echo esc_html(mysql2date('d/m/y H:i',$lead->created_at)); ?></td>
                        <td><div style="display:flex;gap:2px;">
                            <button class="eo-btn eo-btn-outline eo-btn-sm" onclick="eoViewLead(<?php echo $lead->id; ?>)" title="Detail" style="padding:3px 6px;font-size:12px;">👁</button>
                            <button class="eo-btn eo-btn-danger eo-btn-sm" onclick="eoDeleteLead(<?php echo $lead->id; ?>)" title="Hapus" style="padding:3px 6px;font-size:12px;">🗑</button>
                        </div></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php if ($total_pages>1): ?>
                <div style="padding:14px 20px;display:flex;align-items:center;gap:8px;border-top:1px solid var(--eo-border);">
                    <span style="font-size:13px;color:var(--eo-muted);">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> · <?php echo number_format($total); ?> leads</span>
                    <div style="margin-left:auto;display:flex;gap:4px;">
                    <?php
                    $base=['page'=>'eo-crm','s'=>$search,'status'=>$status,'product'=>$post_id?:'','date_from'=>$date_from,'date_to'=>$date_to];
                    $start=max(1,$page-5); $end=min($total_pages,$page+5);
                    if($start>1){ echo '<a href="'.esc_url(add_query_arg(array_merge($base,['paged'=>1]),admin_url('admin.php'))).'" class="eo-page-btn">1</a>'; if($start>2) echo '<span class="eo-page-btn" style="cursor:default">…</span>'; }
                    for($p=$start;$p<=$end;$p++): ?>
                    <a href="<?php echo esc_url(add_query_arg(array_merge($base,['paged'=>$p]),admin_url('admin.php'))); ?>" class="eo-page-btn <?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
                    <?php endfor;
                    if($end<$total_pages){ if($end<$total_pages-1) echo '<span class="eo-page-btn" style="cursor:default">…</span>'; echo '<a href="'.esc_url(add_query_arg(array_merge($base,['paged'=>$total_pages]),admin_url('admin.php'))).'" class="eo-page-btn">'.$total_pages.'</a>'; }
                    ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        </div>

        <!-- Modal -->
        <div id="eo-lead-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:14px;padding:28px;max-width:540px;width:90%;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3 style="margin:0;font-size:16px;font-weight:700;">Detail Lead</h3>
                    <button onclick="document.getElementById('eo-lead-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">✕</button>
                </div>
                <div id="eo-lead-modal-content"></div>
                <div style="margin-top:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Catatan</label>
                    <textarea id="eo-lead-notes" rows="3" style="width:100%;padding:8px;border:1.5px solid var(--eo-border);border-radius:7px;font-size:13px;" placeholder="Tambah catatan..."></textarea>
                    <button class="eo-btn eo-btn-primary" style="margin-top:8px;" onclick="eoSaveNotes()">💾 Simpan Catatan</button>
                </div>
            </div>
        </div>

        <script>
        var _eoNonce='<?php echo esc_js($nonce); ?>';
        var _eoAjax='<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var _eoLeadId=0;
        var _eoLeads=<?php echo wp_json_encode(array_map(function($l){
            $pn=(int)($l->pilihan_price_num??0);
            $pd=$l->pilihan_label!==''?($pn===0?'FREE':'Rp '.number_format($pn,0,',','.')):'';
            return['id'=>$l->id,'nama'=>$l->nama,'wa'=>$l->wa,'email'=>$l->email,'perusahaan'=>$l->perusahaan,'post_title'=>$l->post_title,'pilihan_label'=>$l->pilihan_label,'price_disp'=>$pd,'price_num'=>$pn,'status'=>$l->status,'catatan'=>$l->catatan,'sumber_url'=>$l->sumber_url,'created_at'=>$l->created_at,'ar_wa'=>$l->ar_wa_status??'pending','ar_email'=>$l->ar_email_status??'pending','extra_fields'=>json_decode($l->extra_fields,true)?:[]];
        },$leads)); ?>;

        function eoUpdateStatus(id,status){fetch(_eoAjax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'eo_update_lead_status',nonce:_eoNonce,id:id,status:status})}).then(r=>r.json()).then(res=>{if(!res.success)alert('Gagal: '+res.data);});}
        function eoDeleteLead(id){if(!confirm('Hapus lead #'+id+'?'))return;fetch(_eoAjax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'eo_delete_lead',nonce:_eoNonce,id:id})}).then(r=>r.json()).then(res=>{if(res.success){var row=document.getElementById('lead-row-'+id);if(row)row.remove();}else alert('Gagal: '+res.data);});}
        function eoViewLead(id){
            var lead=_eoLeads.find(l=>l.id==id); if(!lead)return; _eoLeadId=id;
            var arLabel=function(s,t){return s==='sent'?'✅ Terkirim':(s==='failed'?'❌ Gagal':'⏳ Pending');};
            var rows=[['Nama',lead.nama],['WhatsApp','<a href="https://wa.me/'+lead.wa.replace(/\D/g,'')+'" target="_blank">'+lead.wa+'</a>'],['Email',lead.email||'—'],['Perusahaan',lead.perusahaan||'—'],['Produk',lead.post_title||'—'],['Pilihan',lead.pilihan_label||'—'],['Harga',lead.price_disp?'<strong style="color:'+(lead.price_num===0?'#0284c7':'#15803d')+'">'+lead.price_disp+'</strong>':'—'],['WA Autoresponder',arLabel(lead.ar_wa)],['Email Autoresponder',arLabel(lead.ar_email)],['Status',lead.status],['Waktu',lead.created_at],['Sumber',lead.sumber_url?'<a href="'+lead.sumber_url+'" target="_blank" style="word-break:break-all">'+lead.sumber_url+'</a>':'—']];
            if(lead.extra_fields)Object.entries(lead.extra_fields).forEach(function(e){if(e[0]&&!e[0].startsWith('_'))rows.push([e[0],e[1]]);});
            var html='<table style="width:100%;border-collapse:collapse;font-size:13px;">';
            rows.forEach(function(r,i){html+='<tr style="background:'+(i%2?'#f8fafc':'#fff')+'"><td style="padding:7px 10px;font-weight:700;width:140px;color:#64748b;">'+r[0]+'</td><td style="padding:7px 10px;">'+r[1]+'</td></tr>';});
            html+='</table>';
            document.getElementById('eo-lead-modal-content').innerHTML=html;
            document.getElementById('eo-lead-notes').value=lead.catatan||'';
            document.getElementById('eo-lead-modal').style.display='flex';
        }
        function eoSaveNotes(){var notes=document.getElementById('eo-lead-notes').value;fetch(_eoAjax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'eo_update_lead_status',nonce:_eoNonce,id:_eoLeadId,catatan:notes})}).then(r=>r.json()).then(res=>{if(res.success){var lead=_eoLeads.find(l=>l.id==_eoLeadId);if(lead)lead.catatan=notes;document.getElementById('eo-lead-modal').style.display='none';}});}
        document.getElementById('eo-lead-modal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});
        </script>
        <?php
    }

    public static function ajax_update_status(){
        if(!current_user_can('manage_options'))wp_die();
        if(!wp_verify_nonce($_POST['nonce']??'','wp_rest'))wp_send_json_error('Nonce gagal.');
        EO_Leads::update_status((int)($_POST['id']??0),sanitize_text_field($_POST['status']??''),isset($_POST['catatan'])?sanitize_textarea_field($_POST['catatan']):null);
        wp_send_json_success('Updated');
    }
    public static function ajax_delete_lead(){
        if(!current_user_can('manage_options'))wp_die();
        if(!wp_verify_nonce($_POST['nonce']??'','wp_rest'))wp_send_json_error('Nonce gagal.');
        EO_Leads::delete((int)($_POST['id']??0));
        wp_send_json_success('Deleted');
    }
}
