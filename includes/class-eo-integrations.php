<?php
/**
 * EO Integrations — Fonnte WA & Mailketing Email
 *
 * v3 Changes:
 * - Tambah variabel {nama_produk} di template WA & Email
 * - Harga diformat Rp xxx.xxx,- / FREE dari pilihan_price_num (integer)
 * - Endpoint Mailketing: POST form-data, api_token (bukan Bearer)
 * - Fallback SMTP via wp_mail jika Mailketing gagal
 */
class EO_Integrations {

    /* ============================================================
       HELPER: Format harga integer → "Rp xxx.xxx,-" atau "FREE"
       ============================================================ */
    public static function format_price( $price_num ) {
        $num = (int) $price_num;
        if ( $num === 0 ) return 'FREE';
        return 'Rp ' . number_format( $num, 0, ',', '.' ) . ',-';
    }

    /* ============================================================
       KIRIM WA VIA FONNTE
       ============================================================ */
    public static function send_wa( $to, $message ) {
        $token = get_option('eo_fonnte_token', '');
        if ( ! $token ) {
            error_log('[EO WA] Token Fonnte kosong. Set di EO Manager → Pengaturan.');
            return false;
        }
        if ( ! $to || ! $message ) return false;

        $to = preg_replace('/\D/', '', $to);
        if ( substr($to, 0, 1) === '0' ) $to = '62' . substr($to, 1);
        if ( substr($to, 0, 2) !== '62' ) $to = '62' . $to;
        if ( strlen($to) < 10 ) {
            error_log('[EO WA] Nomor terlalu pendek: ' . $to);
            return false;
        }

        $response = wp_remote_post( 'https://api.fonnte.com/send', [
            'timeout'   => 20,
            'sslverify' => true,
            'headers'   => [ 'Authorization' => $token ],
            'body'      => [
                'target'      => $to,
                'message'     => $message,
                'countryCode' => '62',
            ],
        ]);

        if ( is_wp_error($response) ) {
            error_log('[EO WA] wp_remote_post error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode( wp_remote_retrieve_body($response), true );

        if ( $code < 200 || $code >= 300 ) {
            error_log('[EO WA] Fonnte HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
            return false;
        }

        return $body['status'] ?? true;
    }

    /* ============================================================
       KIRIM EMAIL VIA MAILKETING API
       URL    : https://api.mailketing.co.id/api/v1/send
       Method : POST form-data
       Params : api_token, from_name, from_email, recipient, subject, content
       ============================================================ */
    public static function send_email( $to_email, $to_name, $subject, $html_body ) {
        $api_token  = get_option('eo_mailketing_api_key', '');
        $from_name  = get_option('eo_mailketing_from_name',  get_bloginfo('name'));
        $from_email = get_option('eo_mailketing_from_email', get_option('admin_email'));

        if ( ! $api_token ) {
            error_log('[EO Email] Mailketing API Token kosong. Fallback ke SMTP.');
            return self::send_email_smtp( $to_email, $to_name, $subject, $html_body );
        }
        if ( ! is_email($to_email) ) {
            error_log('[EO Email] Email tidak valid: ' . $to_email);
            return false;
        }
        if ( ! $from_email || ! is_email($from_email) ) {
            error_log('[EO Email] From email tidak valid: ' . $from_email);
            return false;
        }

        $params = [
            'api_token'  => $api_token,
            'from_name'  => $from_name,
            'from_email' => $from_email,
            'recipient'  => $to_email,
            'subject'    => $subject,
            'content'    => $html_body,
        ];

        error_log('[EO Email] Mengirim ke: ' . $to_email . ' | Subject: ' . $subject);

        $response = wp_remote_post( 'https://api.mailketing.co.id/api/v1/send', [
            'timeout'   => 30,
            'sslverify' => true,
            'headers'   => [ 'Accept' => 'application/json' ],
            'body'      => $params,
        ]);

        if ( is_wp_error($response) ) {
            error_log('[EO Email] wp_remote_post error: ' . $response->get_error_message() . ' — Fallback ke SMTP');
            return self::send_email_smtp( $to_email, $to_name, $subject, $html_body );
        }

        $code     = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body_arr = json_decode($body_raw, true);

        error_log('[EO Email] Mailketing response HTTP ' . $code . ': ' . $body_raw);

        if ( ($body_arr['status'] ?? '') === 'success' ) {
            return true;
        }

        $reason = $body_arr['response'] ?? $body_raw;
        error_log('[EO Email] Mailketing API gagal: ' . $reason . ' — Fallback ke SMTP');

        $use_smtp_fallback = get_option('eo_smtp_fallback_enabled', '1');
        if ( $use_smtp_fallback ) {
            return self::send_email_smtp( $to_email, $to_name, $subject, $html_body );
        }

        return false;
    }

    /* ============================================================
       ADD SUBSCRIBER KE LIST MAILKETING
       ============================================================ */
    public static function add_subscriber_to_list( $email, $first_name, $last_name = '', $phone = '', $company = '' ) {
        $api_token = get_option('eo_mailketing_api_key', '');
        $list_id   = get_option('eo_mailketing_list_id', '');

        if ( ! $api_token || ! $list_id || ! is_email($email) ) return false;

        $response = wp_remote_post( 'https://api.mailketing.co.id/api/v1/addsubtolist', [
            'timeout'   => 20,
            'sslverify' => true,
            'body'      => [
                'api_token'  => $api_token,
                'list_id'    => $list_id,
                'email'      => $email,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'phone'      => $phone,
                'company'    => $company,
            ],
        ]);

        if ( is_wp_error($response) ) {
            error_log('[EO Subscriber] Gagal add ke list: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body($response), true );
        $ok   = ( $body['status'] ?? '' ) === 'success';
        error_log('[EO Subscriber] Add to list: ' . ( $ok ? 'sukses' : ($body['response'] ?? 'gagal') ) . ' | email: ' . $email);
        return $ok;
    }

    /* ============================================================
       GET ALL LIST dari akun Mailketing
       ============================================================ */
    public static function get_mailketing_lists() {
        $api_token = get_option('eo_mailketing_api_key', '');
        if ( ! $api_token ) return [];

        $cached = get_transient('eo_mailketing_lists');
        if ( $cached !== false ) return $cached;

        $response = wp_remote_post( 'https://api.mailketing.co.id/api/v1/getlist', [
            'timeout' => 15,
            'body'    => [ 'api_token' => $api_token ],
        ]);

        if ( is_wp_error($response) ) return [];

        $body  = json_decode( wp_remote_retrieve_body($response), true );
        $lists = [];
        if ( isset($body['data']) && is_array($body['data']) ) {
            foreach ( $body['data'] as $l ) {
                $lists[] = [
                    'id'   => $l['id']   ?? '',
                    'name' => $l['name'] ?? 'List #' . ($l['id'] ?? ''),
                ];
            }
        }

        set_transient('eo_mailketing_lists', $lists, 300);
        return $lists;
    }

    /* ============================================================
       FALLBACK: Kirim via wp_mail (SMTP)
       ============================================================ */
    private static function send_email_smtp( $to_email, $to_name, $subject, $html_body ) {
        $smtp_host = get_option('eo_smtp_host', '');
        if ( $smtp_host ) {
            add_action('phpmailer_init', [ __CLASS__, 'configure_smtp' ]);
        }

        $from_name  = get_option('eo_mailketing_from_name',  get_bloginfo('name'));
        $from_email = get_option('eo_mailketing_from_email', get_option('admin_email'));

        $content_type_fn = function() { return 'text/html'; };
        $from_fn         = function() use ($from_email) { return $from_email; };
        $from_name_fn    = function() use ($from_name)  { return $from_name; };

        add_filter('wp_mail_content_type', $content_type_fn);
        add_filter('wp_mail_from',         $from_fn);
        add_filter('wp_mail_from_name',    $from_name_fn);

        $result = wp_mail($to_email, $subject, $html_body);

        remove_filter('wp_mail_content_type', $content_type_fn);
        remove_filter('wp_mail_from',         $from_fn);
        remove_filter('wp_mail_from_name',    $from_name_fn);

        if ( $smtp_host ) {
            remove_action('phpmailer_init', [ __CLASS__, 'configure_smtp' ]);
        }

        error_log('[EO SMTP] wp_mail ke ' . $to_email . ': ' . ($result ? 'sukses' : 'gagal'));
        return $result;
    }

    public static function configure_smtp( $phpmailer ) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = get_option('eo_smtp_host', '');
        $phpmailer->Port       = (int) get_option('eo_smtp_port', 587);
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = get_option('eo_smtp_username', '');
        $phpmailer->Password   = get_option('eo_smtp_password', '');
        $enc = get_option('eo_smtp_encryption', 'tls');
        $phpmailer->SMTPSecure = $enc === 'none' ? '' : $enc;
    }

    /* ============================================================
       FOLLOW-UP KE CUSTOMER (WA + Email)

       Variabel tersedia:
         {nama}        — nama customer
         {wa}          — nomor WA customer
         {email}       — email customer
         {perusahaan}  — nama perusahaan customer
         {pilihan}     — label pilihan produk/paket
         {harga}       — harga terformat (Rp xxx.xxx,- / FREE / kosong jika tidak ada)
         {nama_produk} — judul post/produk WordPress
         {site_name}   — nama situs
         {site_url}    — URL situs
         {coa_link}    — link COA/katalog (dari config form)
       ============================================================ */
    public static function send_followup( $data, $config ) {
        $nama        = $data['nama']           ?? '';
        $wa          = $data['wa']             ?? '';
        $email       = $data['email']          ?? '';
        $perus       = $data['perusahaan']     ?? '';
        $pilihan     = $data['_pilihan_label'] ?? '';
        $price_raw   = $data['_pilihan_price'] ?? '';
        $price_num   = (int) preg_replace('/\D/', '', $price_raw);
        $coa         = $config['coa_link']     ?? '';
        $post_id     = (int) ($data['post_id'] ?? 0);
        $nama_produk = $post_id ? get_the_title($post_id) : '';

        // Format harga: kosong jika tidak ada pilihan, FREE/Rp jika ada
        $harga_wa    = '';
        $harga_email = '';
        if ( $pilihan !== '' ) {
            $harga_fmt   = self::format_price( $price_num );
            $harga_wa    = "\nHarga: *{$harga_fmt}*";
            $harga_email = ' — ' . $harga_fmt;
        }

        $vars_wa = [
            '{nama}'        => $nama,
            '{wa}'          => $wa,
            '{email}'       => $email,
            '{perusahaan}'  => $perus,
            '{pilihan}'     => $pilihan,
            '{harga}'       => $harga_wa,
            '{nama_produk}' => $nama_produk,
            '{site_name}'   => get_bloginfo('name'),
            '{site_url}'    => home_url(),
            '{coa_link}'    => $coa ?: '-',
        ];

        $vars_email = array_merge( $vars_wa, [ '{harga}' => $harga_email ] );

        /* WA ke customer */
        if ( $wa ) {
            $tmpl   = $config['wa_template'] ?? self::_default_wa_template();
            $msg_wa = strtr( $tmpl, $vars_wa );
            self::send_wa( $wa, $msg_wa );
        }

        /* Email ke customer */
        if ( $email ) {
            $subj_tmpl  = $config['email_subject']  ?? 'Terima kasih — {site_name}';
            $body_tmpl  = $config['email_template'] ?? self::_default_email_body();
            $subject    = strtr( $subj_tmpl, $vars_email );
            $body_inner = strtr( $body_tmpl, $vars_email );
            $html       = self::wrap_email_html( $body_inner, get_bloginfo('name') );
            self::send_email( $email, $nama, $subject, $html );

            $nameParts = explode(' ', $nama, 2);
            self::add_subscriber_to_list(
                $email,
                $nameParts[0] ?? $nama,
                $nameParts[1] ?? '',
                $wa,
                $perus
            );
        }
    }

    /* ============================================================
       NOTIFIKASI INTERNAL (WA + Email ke owner/admin)
       ============================================================ */
    public static function notify_internal( $data, $config ) {
        $nama        = $data['nama']           ?? '';
        $wa          = $data['wa']             ?? '';
        $email       = $data['email']          ?? '';
        $perus       = $data['perusahaan']     ?? '';
        $pilihan     = $data['_pilihan_label'] ?? '';
        $price_raw   = $data['_pilihan_price'] ?? '';
        $price_num   = (int) preg_replace('/\D/', '', $price_raw);
        $sumber      = $data['_source_url']    ?? get_the_title($data['post_id'] ?? 0);
        $post_id     = (int) ($data['post_id'] ?? 0);
        $nama_produk = $post_id ? get_the_title($post_id) : '';
        $site        = get_bloginfo('name');
        $waktu       = current_time('d/m/Y H:i');
        $harga_fmt   = $pilihan !== '' ? self::format_price($price_num) : '—';

        $wa_bisnis = $config['notify_wa'] ?: get_option('eo_notify_wa', '');
        if ( $wa_bisnis ) {
            $msg = "🔔 *LEAD BARU — {$site}*\n\n"
                . "👤 Nama: {$nama}\n"
                . "📱 WA: {$wa}\n"
                . ( $email ? "📧 Email: {$email}\n" : '' )
                . ( $perus ? "🏢 Perusahaan: {$perus}\n" : '' )
                . ( $nama_produk ? "🏷️ Produk: {$nama_produk}\n" : '' )
                . "📦 Pilihan: *{$pilihan}*\n"
                . "💰 Harga: {$harga_fmt}\n"
                . "🕐 Waktu: {$waktu}\n"
                . "🌐 Sumber: {$sumber}";
            self::send_wa( $wa_bisnis, $msg );
        }

        $email_intern = $config['notify_email'] ?: get_option('eo_notify_email', get_option('admin_email'));
        if ( $email_intern ) {
            $html = "<h2>🔔 Lead Baru — {$site}</h2>"
                . "<table border='0' cellpadding='6' style='font-family:sans-serif;font-size:14px;border-collapse:collapse;width:100%;'>"
                . "<tr style='background:#f0fdf4'><td style='width:130px'><b>Nama</b></td><td>" . esc_html($nama) . "</td></tr>"
                . "<tr><td><b>WhatsApp</b></td><td><a href='https://wa.me/" . preg_replace('/\D/','',esc_attr($wa)) . "'>" . esc_html($wa) . "</a></td></tr>"
                . "<tr style='background:#f0fdf4'><td><b>Email</b></td><td>" . esc_html($email ?: '-') . "</td></tr>"
                . "<tr><td><b>Perusahaan</b></td><td>" . esc_html($perus ?: '-') . "</td></tr>"
                . "<tr style='background:#f0fdf4'><td><b>Produk</b></td><td>" . esc_html($nama_produk ?: '-') . "</td></tr>"
                . "<tr><td><b>Pilihan</b></td><td>" . esc_html($pilihan ?: '-') . "</td></tr>"
                . "<tr style='background:#f0fdf4'><td><b>Harga</b></td><td><strong>" . esc_html($harga_fmt) . "</strong></td></tr>"
                . "<tr><td><b>Waktu</b></td><td>{$waktu}</td></tr>"
                . "<tr style='background:#f0fdf4'><td><b>Sumber</b></td><td>" . esc_html($sumber) . "</td></tr>"
                . "</table>"
                . "<p style='margin-top:16px'><a href='" . admin_url('admin.php?page=eo-crm') . "' style='background:#15803d;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:700'>Lihat di CRM Dashboard →</a></p>";
            self::send_email( $email_intern, $site, "🔔 Lead Baru: {$nama} — {$harga_fmt}", $html );
        }
    }

    /* ── Helper: wrap HTML email ── */
    public static function wrap_email_html( $inner, $site_name ) {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f1f5f9;">'
            . '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;max-width:580px;margin:32px auto;background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">'
            . '<div style="background:linear-gradient(135deg,#0e3620,#16a34a);padding:24px 28px;">'
            . '<h2 style="color:#fff;margin:0;font-size:18px;font-weight:700;">🌿 ' . esc_html($site_name) . '</h2>'
            . '</div>'
            . '<div style="padding:28px;color:#1e293b;font-size:15px;line-height:1.7;">' . $inner . '</div>'
            . '<div style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;text-align:center;">'
            . esc_html($site_name) . ' · Email otomatis dari EO Manager · <a href="' . home_url() . '" style="color:#94a3b8;">' . home_url() . '</a></div>'
            . '</div></body></html>';
    }

    /* Private default templates — dipakai jika config form kosong */
    private static function _default_wa_template() {
        return "Halo {nama}! 👋\n\nTerima kasih telah menghubungi *{site_name}*.\n\nProduk: *{nama_produk}*\nPilihan: *{pilihan}*{harga}\n\nTim kami akan segera menghubungi Anda. 🙏\n\n_— Tim {site_name}_";
    }

    private static function _default_email_body() {
        return '<p>Halo <strong>{nama}</strong>,</p>'
             . '<p>Terima kasih telah menghubungi <strong>{site_name}</strong>.</p>'
             . '<p>Produk: <strong>{nama_produk}</strong><br>Pilihan Anda: <strong>{pilihan}</strong>{harga}</p>'
             . '<p>Tim kami akan segera menghubungi Anda dalam waktu 1×24 jam.</p>'
             . '<p>Salam,<br><strong>Tim {site_name}</strong></p>';
    }
}
