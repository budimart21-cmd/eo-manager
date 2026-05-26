<?php
/**
 * EO Settings v3 — Mailketing API (fixed endpoint) + SMTP Fallback
 */
class EO_Settings {

    public static function init() {
        add_action( 'admin_menu',                      [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init',                      [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_post_eo_save_site_options', [ __CLASS__, 'save_site_options' ] );
        add_action( 'wp_ajax_eo_test_mailketing',      [ __CLASS__, 'ajax_test_mailketing' ] );
        add_action( 'wp_ajax_eo_test_fonnte',          [ __CLASS__, 'ajax_test_fonnte' ] );
        add_action( 'wp_ajax_eo_fetch_mk_lists',       [ __CLASS__, 'ajax_fetch_mk_lists' ] );
        add_action( 'wp_ajax_eo_test_smtp',            [ __CLASS__, 'ajax_test_smtp' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'eo-manager',
            'Pengaturan EO Manager',
            '⚙️ Pengaturan',
            'manage_options',
            'eo-settings',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings() {
        $fields = [
            'eo_fonnte_token', 'eo_fonnte_device_wa',
            'eo_mailketing_api_key', 'eo_mailketing_from_name', 'eo_mailketing_from_email',
            'eo_mailketing_list_id',
            'eo_notify_wa', 'eo_notify_email',
            'eo_smtp_fallback_enabled',
            'eo_smtp_host', 'eo_smtp_port', 'eo_smtp_username', 'eo_smtp_password', 'eo_smtp_encryption',
        ];
        foreach ( $fields as $f ) {
            register_setting( 'eo_settings_group', $f );
        }
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) return;
        $nonce = wp_create_nonce('wp_rest');
        ?>
        <div class="eo-wrap">

            <div class="eo-page-header">
                <div class="eo-page-icon">⚙️</div>
                <div>
                    <h1>Pengaturan EO Manager</h1>
                    <p>Konfigurasi integrasi Fonnte, Mailketing API, dan SMTP fallback.</p>
                </div>
            </div>

            <?php if ( isset($_GET['saved']) ) : ?>
            <div class="eo-notice eo-notice-success">✅ Pengaturan berhasil disimpan.</div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="eo-info-box" style="margin-bottom:24px;">
                <strong>📋 Cara Setup Mailketing yang Benar:</strong>
                <ol style="margin:8px 0 0 20px;line-height:2;">
                    <li>Masuk ke <a href="https://be.mailketing.co.id" target="_blank">be.mailketing.co.id</a> → Integration → copy <strong>API Token</strong></li>
                    <li>Di Mailketing → Add Domain: daftarkan dan verifikasi <strong>From Email</strong> Anda</li>
                    <li>Isi API Token + From Email di form di bawah, lalu klik <strong>Test Kirim Email</strong></li>
                    <li>(Opsional) Buat List di Mailketing, klik <strong>Ambil List</strong> untuk memilih list auto-subscriber</li>
                </ol>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('eo_settings_group'); ?>

                <!-- TAB NAVIGATION -->
                <div class="eo-tabs">
                    <button type="button" class="eo-tab active" onclick="eoTab('fonnte', this)">📱 Fonnte WA</button>
                    <button type="button" class="eo-tab" onclick="eoTab('mailketing', this)">📧 Mailketing Email</button>
                    <button type="button" class="eo-tab" onclick="eoTab('smtp', this)">🔧 SMTP Fallback</button>
                    <button type="button" class="eo-tab" onclick="eoTab('notif', this)">🔔 Notifikasi</button>
                    <button type="button" class="eo-tab" onclick="eoTab('homepage', this)">🏠 Homepage</button>
                </div>

                <!-- TAB: FONNTE -->
                <div class="eo-tab-content" id="eo-tab-fonnte">
                    <div class="eo-card">
                        <h3 class="eo-card-title">📱 Fonnte WhatsApp Autoresponder</h3>
                        <div class="eo-field-group">
                            <div class="eo-field">
                                <label>Device Token Fonnte</label>
                                <input type="text" name="eo_fonnte_token"
                                       value="<?php echo esc_attr(get_option('eo_fonnte_token','')); ?>"
                                       placeholder="Token dari dashboard Fonnte (tanpa Bearer)">
                                <p class="eo-hint">Dapatkan di <a href="https://fonnte.com" target="_blank">fonnte.com</a> → Device → Token. <strong>Jangan tambah "Bearer".</strong></p>
                            </div>
                        </div>
                        <div class="eo-test-row">
                            <input type="text" id="eo-test-wa-num" placeholder="Nomor WA test (628xxx)">
                            <button type="button" class="eo-btn eo-btn-outline" onclick="eoTestFonnte()">🧪 Test Kirim WA</button>
                            <span id="eo-test-wa-result" class="eo-test-result"></span>
                        </div>
                    </div>
                </div>

                <!-- TAB: MAILKETING -->
                <div class="eo-tab-content" id="eo-tab-mailketing" style="display:none">
                    <div class="eo-card">
                        <h3 class="eo-card-title">📧 Mailketing Email API</h3>
                        <div class="eo-badge-green" style="margin-bottom:16px;">
                            ✅ Endpoint: <code>https://api.mailketing.co.id/api/v1/send</code> (form-data + api_token)
                        </div>
                        <div class="eo-field-group">
                            <div class="eo-field">
                                <label>API Token Mailketing <span class="eo-required">*</span></label>
                                <input type="text" name="eo_mailketing_api_key"
                                       value="<?php echo esc_attr(get_option('eo_mailketing_api_key','')); ?>"
                                       placeholder="API Token dari Mailketing → Integration">
                                <p class="eo-hint">Login Mailketing → Integration → API Token</p>
                            </div>
                            <div class="eo-field-row">
                                <div class="eo-field">
                                    <label>From Name</label>
                                    <input type="text" name="eo_mailketing_from_name"
                                           value="<?php echo esc_attr(get_option('eo_mailketing_from_name', get_bloginfo('name'))); ?>"
                                           placeholder="Nama pengirim">
                                </div>
                                <div class="eo-field">
                                    <label>From Email <span class="eo-required">*</span></label>
                                    <input type="email" name="eo_mailketing_from_email"
                                           value="<?php echo esc_attr(get_option('eo_mailketing_from_email', get_option('admin_email'))); ?>"
                                           placeholder="email@domain.com">
                                    <p class="eo-hint eo-hint-warn">⚠️ HARUS sudah diverifikasi di Mailketing → Add Domain</p>
                                </div>
                            </div>
                            <div class="eo-field">
                                <label>List Mailketing (Auto-Subscribe)</label>
                                <div style="display:flex;gap:10px;align-items:center;">
                                    <select name="eo_mailketing_list_id" id="eo-list-select" style="flex:1;padding:9px 12px;border:1.5px solid var(--eo-border);border-radius:8px;font-size:14px;">
                                        <option value="">— Pilih list (opsional) —</option>
                                        <?php
                                        $saved_list = get_option('eo_mailketing_list_id', '');
                                        $lists = EO_Integrations::get_mailketing_lists();
                                        foreach ( $lists as $l ) {
                                            echo '<option value="' . esc_attr($l['id']) . '"' . selected($saved_list, $l['id'], false) . '>' . esc_html($l['name']) . ' (ID: ' . esc_html($l['id']) . ')</option>';
                                        }
                                        ?>
                                    </select>
                                    <button type="button" class="eo-btn eo-btn-outline" onclick="eoFetchMkLists()">🔄 Ambil List</button>
                                </div>
                                <p class="eo-hint">Jika diisi, setiap lead yang punya email akan otomatis ditambahkan ke list ini sebagai subscriber.</p>
                            </div>
                        </div>
                        <div class="eo-test-row">
                            <input type="email" id="eo-test-email-addr" placeholder="Email penerima test">
                            <button type="button" class="eo-btn eo-btn-outline" onclick="eoTestMailketing()">🧪 Test Kirim Email</button>
                        </div>
                        <div id="eo-test-email-result" class="eo-test-result" style="margin-top:8px;"></div>
                    </div>
                </div>

                <!-- TAB: SMTP FALLBACK -->
                <div class="eo-tab-content" id="eo-tab-smtp" style="display:none">
                    <div class="eo-card">
                        <h3 class="eo-card-title">🔧 SMTP Fallback</h3>
                        <p style="color:var(--eo-muted);font-size:14px;margin-bottom:20px;">
                            Digunakan otomatis jika Mailketing API gagal (credits habis, token salah, dll). 
                            Bisa juga diisi dengan SMTP dari Mailketing atau provider lain.
                        </p>
                        <div class="eo-field-group">
                            <div class="eo-field eo-field-toggle">
                                <label>Aktifkan SMTP Fallback</label>
                                <label class="eo-switch">
                                    <input type="checkbox" name="eo_smtp_fallback_enabled" value="1"
                                           <?php checked( get_option('eo_smtp_fallback_enabled', '1'), '1' ); ?>>
                                    <span class="eo-slider"></span>
                                </label>
                            </div>
                            <div class="eo-field-row">
                                <div class="eo-field">
                                    <label>SMTP Host</label>
                                    <input type="text" name="eo_smtp_host"
                                           value="<?php echo esc_attr(get_option('eo_smtp_host','')); ?>"
                                           placeholder="smtp.mailketing.co.id">
                                    <p class="eo-hint">Untuk Mailketing SMTP: <code>smtp.mailketing.co.id</code></p>
                                </div>
                                <div class="eo-field">
                                    <label>Port</label>
                                    <input type="number" name="eo_smtp_port"
                                           value="<?php echo esc_attr(get_option('eo_smtp_port','587')); ?>"
                                           placeholder="587">
                                </div>
                            </div>
                            <div class="eo-field-row">
                                <div class="eo-field">
                                    <label>Username SMTP</label>
                                    <input type="text" name="eo_smtp_username"
                                           value="<?php echo esc_attr(get_option('eo_smtp_username','')); ?>"
                                           placeholder="username@domain.com">
                                </div>
                                <div class="eo-field">
                                    <label>Password SMTP</label>
                                    <input type="password" name="eo_smtp_password"
                                           value="<?php echo esc_attr(get_option('eo_smtp_password','')); ?>"
                                           placeholder="Password SMTP">
                                </div>
                            </div>
                            <div class="eo-field">
                                <label>Enkripsi</label>
                                <select name="eo_smtp_encryption" style="padding:9px 12px;border:1.5px solid var(--eo-border);border-radius:8px;font-size:14px;">
                                    <?php
                                    $enc = get_option('eo_smtp_encryption', 'tls');
                                    foreach ( ['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'] as $val => $label ) {
                                        echo '<option value="' . $val . '"' . selected($enc, $val, false) . '>' . $label . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="eo-test-row">
                            <input type="email" id="eo-test-smtp-addr" placeholder="Email test SMTP">
                            <button type="button" class="eo-btn eo-btn-outline" onclick="eoTestSmtp()">🧪 Test SMTP</button>
                            <span id="eo-test-smtp-result" class="eo-test-result"></span>
                        </div>
                    </div>
                </div>

                <!-- TAB: NOTIFIKASI -->
                <div class="eo-tab-content" id="eo-tab-notif" style="display:none">
                    <div class="eo-card">
                        <h3 class="eo-card-title">🔔 Notifikasi Internal (Lead Baru)</h3>
                        <div class="eo-field-group">
                            <div class="eo-field">
                                <label>Nomor WA Penerima Notifikasi</label>
                                <input type="text" name="eo_notify_wa"
                                       value="<?php echo esc_attr(get_option('eo_notify_wa','')); ?>"
                                       placeholder="628xxxx (format internasional)">
                                <p class="eo-hint">Nomor WA ini akan menerima notifikasi setiap ada lead baru masuk.</p>
                            </div>
                            <div class="eo-field">
                                <label>Email Penerima Notifikasi</label>
                                <input type="email" name="eo_notify_email"
                                       value="<?php echo esc_attr(get_option('eo_notify_email', get_option('admin_email'))); ?>"
                                       placeholder="admin@domain.com">
                                <p class="eo-hint">Email ini akan menerima notifikasi setiap ada lead baru masuk.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: HOMEPAGE -->
                <div class="eo-tab-content" id="eo-tab-homepage" style="display:none">
                    <div class="eo-card">
                        <h3 class="eo-card-title">🏠 Pengaturan Konten Homepage</h3>
                        <?php $opts = get_option('eo_site_options', []); ?>
                        <div class="eo-field-group">
                            <div class="eo-field">
                                <label>Hero Title</label>
                                <input type="text" name="eo_hero_title_tmp"
                                       value="<?php echo esc_attr($opts['hero_title'] ?? 'Essential Oil Premium Indonesia'); ?>"
                                       class="eo-input-large">
                            </div>
                            <div class="eo-field">
                                <label>Hero Subtitle</label>
                                <textarea name="eo_hero_subtitle_tmp" rows="2"><?php echo esc_textarea($opts['hero_subtitle'] ?? 'Distributor & Produsen Minyak Atsiri terpercaya.'); ?></textarea>
                            </div>
                            <div class="eo-field-row">
                                <div class="eo-field">
                                    <label>Teks Tombol Hero</label>
                                    <input type="text" name="eo_hero_btn_text_tmp"
                                           value="<?php echo esc_attr($opts['hero_btn_text'] ?? 'Lihat Semua Produk'); ?>">
                                </div>
                                <div class="eo-field">
                                    <label>URL Tombol Hero</label>
                                    <input type="text" name="eo_hero_btn_url_tmp"
                                           value="<?php echo esc_attr($opts['hero_btn_url'] ?? '#produk'); ?>">
                                </div>
                            </div>
                        </div>
                        <p class="eo-hint">⚠️ Field ini disimpan di tab Homepage saja — klik "Simpan Pengaturan" di bawah untuk menyimpan semua tab sekaligus.</p>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <?php submit_button('💾 Simpan Semua Pengaturan', 'primary', 'submit', false, ['class' => 'eo-btn eo-btn-primary eo-btn-lg']); ?>
                </div>
            </form>
        </div>

        <script>
        var _eoNonce = '<?php echo $nonce; ?>';
        var _eoAjax  = '<?php echo admin_url('admin-ajax.php'); ?>';

        function eoTab(id, btn) {
            document.querySelectorAll('.eo-tab-content').forEach(function(el) { el.style.display = 'none'; });
            document.querySelectorAll('.eo-tab').forEach(function(el) { el.classList.remove('active'); });
            document.getElementById('eo-tab-' + id).style.display = 'block';
            btn.classList.add('active');
        }

        function eoPost(action, extraData, successCb, failCb) {
            var params = Object.assign({ action: action, nonce: _eoNonce }, extraData);
            fetch(_eoAjax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params)
            }).then(r => r.json()).then(res => {
                if (res.success) successCb(res.data);
                else failCb(res.data);
            }).catch(err => failCb('Error: ' + err));
        }

        function setResult(elId, msg, ok) {
            var el = document.getElementById(elId);
            el.innerHTML = '<span style="color:' + (ok ? '#16a34a' : '#dc2626') + ';font-weight:600;">' + (ok ? '✅ ' : '❌ ') + msg + '</span>';
        }

        function eoTestMailketing() {
            var email = document.getElementById('eo-test-email-addr').value.trim();
            if (!email) { alert('Masukkan alamat email test.'); return; }
            document.getElementById('eo-test-email-result').innerHTML = '⏳ Mengirim...';
            eoPost('eo_test_mailketing', { to_email: email },
                function(msg) { setResult('eo-test-email-result', msg, true); },
                function(msg) { setResult('eo-test-email-result', msg, false); }
            );
        }

        function eoTestFonnte() {
            var num = document.getElementById('eo-test-wa-num').value.trim();
            if (!num) { alert('Masukkan nomor WA test.'); return; }
            document.getElementById('eo-test-wa-result').innerHTML = '⏳ Mengirim...';
            eoPost('eo_test_fonnte', { to_wa: num },
                function(msg) { setResult('eo-test-wa-result', msg, true); },
                function(msg) { setResult('eo-test-wa-result', msg, false); }
            );
        }

        function eoTestSmtp() {
            var email = document.getElementById('eo-test-smtp-addr').value.trim();
            if (!email) { alert('Masukkan email test SMTP.'); return; }
            document.getElementById('eo-test-smtp-result').innerHTML = '⏳ Mengirim...';
            eoPost('eo_test_smtp', { to_email: email },
                function(msg) { setResult('eo-test-smtp-result', msg, true); },
                function(msg) { setResult('eo-test-smtp-result', msg, false); }
            );
        }

        function eoFetchMkLists() {
            var sel = document.getElementById('eo-list-select');
            sel.innerHTML = '<option>⏳ Mengambil list...</option>';
            eoPost('eo_fetch_mk_lists', {},
                function(lists) {
                    sel.innerHTML = '<option value="">— Pilih list (opsional) —</option>';
                    lists.forEach(function(l) {
                        sel.innerHTML += '<option value="' + l.id + '">' + l.name + ' (ID: ' + l.id + ')</option>';
                    });
                    if (lists.length === 0) sel.innerHTML = '<option value="">Tidak ada list ditemukan</option>';
                },
                function(msg) {
                    sel.innerHTML = '<option value="">❌ ' + msg + '</option>';
                }
            );
        }
        </script>
        <?php
    }

    public static function save_site_options() {
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        check_admin_referer('eo_save_site_options');
        $opts = [
            'hero_title'    => sanitize_text_field($_POST['hero_title'] ?? ''),
            'hero_subtitle' => sanitize_textarea_field($_POST['hero_subtitle'] ?? ''),
            'hero_btn_text' => sanitize_text_field($_POST['hero_btn_text'] ?? ''),
            'hero_btn_url'  => esc_url_raw($_POST['hero_btn_url'] ?? ''),
        ];
        update_option('eo_site_options', $opts);
        wp_redirect( admin_url('admin.php?page=eo-settings&saved=1') );
        exit;
    }

    public static function ajax_test_mailketing() {
        if ( ! current_user_can('manage_options') ) wp_die();
        if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest') ) wp_send_json_error('Nonce gagal.');

        $to_email   = sanitize_email($_POST['to_email'] ?? '');
        $api_token  = get_option('eo_mailketing_api_key', '');
        $from_email = get_option('eo_mailketing_from_email', '');

        if ( ! $api_token )    { wp_send_json_error('API Token Mailketing belum diisi.'); return; }
        if ( ! $from_email )   { wp_send_json_error('From Email belum diisi.'); return; }
        if ( ! is_email($to_email) ) { wp_send_json_error('Email test tidak valid.'); return; }

        $html = EO_Integrations::wrap_email_html(
            '<p>Ini adalah email test dari <strong>EO Manager v3</strong>.</p>'
            . '<p>✅ Jika Anda menerima email ini, integrasi Mailketing API berjalan dengan baik.</p>'
            . '<p><small>API Token: <code>' . substr($api_token, 0, 8) . '...</code> | From: <code>' . esc_html($from_email) . '</code></small></p>',
            get_bloginfo('name')
        );

        /* Direct test ke Mailketing API tanpa fallback */
        $params = [
            'api_token'  => $api_token,
            'from_name'  => get_option('eo_mailketing_from_name', get_bloginfo('name')),
            'from_email' => $from_email,
            'recipient'  => $to_email,
            'subject'    => '🧪 Test Email EO Manager v3 — ' . get_bloginfo('name'),
            'content'    => $html,
        ];

        $response = wp_remote_post( 'https://api.mailketing.co.id/api/v1/send', [
            'timeout'   => 30,
            'sslverify' => true,
            'body'      => $params,
        ]);

        if ( is_wp_error($response) ) {
            wp_send_json_error('Connection error: ' . $response->get_error_message());
            return;
        }

        $code     = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body_arr = json_decode($body_raw, true);

        error_log('[EO Test Email] HTTP ' . $code . ': ' . $body_raw);

        $status = $body_arr['status'] ?? '';
        if ( $status === 'success' ) {
            wp_send_json_success('Email terkirim ke ' . $to_email . ' via Mailketing API. Cek inbox.');
        } else {
            $reason = $body_arr['response'] ?? $body_raw;
            wp_send_json_error('Mailketing API gagal: ' . $reason . ' (HTTP ' . $code . '). Cek debug.log untuk detail.');
        }
    }

    public static function ajax_test_fonnte() {
        if ( ! current_user_can('manage_options') ) wp_die();
        if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest') ) wp_send_json_error('Nonce gagal.');

        $to_wa = sanitize_text_field($_POST['to_wa'] ?? '');
        $token = get_option('eo_fonnte_token', '');

        if ( ! $token ) { wp_send_json_error('Token Fonnte belum diisi.'); return; }
        if ( ! $to_wa ) { wp_send_json_error('Nomor WA test tidak valid.'); return; }

        $msg    = "🧪 Test pesan dari *EO Manager v3*.\n\nJika Anda menerima pesan ini, integrasi Fonnte berjalan dengan baik. ✅\n\n_— " . get_bloginfo('name') . "_";
        $result = EO_Integrations::send_wa($to_wa, $msg);

        if ( $result ) {
            wp_send_json_success('Pesan test berhasil dikirim ke ' . $to_wa);
        } else {
            wp_send_json_error('Gagal mengirim. Cek debug.log untuk detail. Pastikan Token Fonnte valid dan device terhubung.');
        }
    }

    public static function ajax_fetch_mk_lists() {
        if ( ! current_user_can('manage_options') ) wp_die();
        if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest') ) wp_send_json_error('Nonce gagal.');

        delete_transient('eo_mailketing_lists');
        $lists = EO_Integrations::get_mailketing_lists();

        if ( empty($lists) ) {
            wp_send_json_error('Tidak ada list ditemukan. Pastikan API Token valid dan sudah membuat list di Mailketing.');
        } else {
            wp_send_json_success($lists);
        }
    }

    public static function ajax_test_smtp() {
        if ( ! current_user_can('manage_options') ) wp_die();
        if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest') ) wp_send_json_error('Nonce gagal.');

        $to_email = sanitize_email($_POST['to_email'] ?? '');
        if ( ! is_email($to_email) ) { wp_send_json_error('Email test tidak valid.'); return; }

        $host = get_option('eo_smtp_host', '');
        if ( ! $host ) { wp_send_json_error('SMTP Host belum diisi.'); return; }

        add_action('phpmailer_init', [ 'EO_Integrations', 'configure_smtp' ]);
        add_filter('wp_mail_content_type', function() { return 'text/html'; });

        $from_name  = get_option('eo_mailketing_from_name', get_bloginfo('name'));
        $from_email = get_option('eo_mailketing_from_email', get_option('admin_email'));
        add_filter('wp_mail_from',      function() use ($from_email) { return $from_email; });
        add_filter('wp_mail_from_name', function() use ($from_name)  { return $from_name; });

        $result = wp_mail(
            $to_email,
            '🧪 Test SMTP EO Manager v3 — ' . get_bloginfo('name'),
            '<p>Test SMTP berhasil dari EO Manager v3. ✅</p>'
        );

        remove_action('phpmailer_init', [ 'EO_Integrations', 'configure_smtp' ]);

        if ( $result ) {
            wp_send_json_success('SMTP test berhasil dikirim ke ' . $to_email . '.');
        } else {
            global $phpmailer;
            $err = isset($phpmailer) && method_exists($phpmailer, 'ErrorInfo') ? $phpmailer->ErrorInfo : 'Unknown error';
            wp_send_json_error('SMTP gagal: ' . $err . '. Cek konfigurasi host/port/user/password.');
        }
    }
}
