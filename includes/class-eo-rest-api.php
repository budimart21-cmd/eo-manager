<?php
/**
 * EO REST API v3 — robust untuk guest submission
 */
class EO_Rest_API {

    public static function register_routes() {

        // Route utama submit lead — guest friendly
        register_rest_route( 'eo/v1', '/submit-lead', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'submit_lead' ],
            'permission_callback' => '__return_true',
        ]);

        // Backward-compat legacy routes
        register_rest_route( 'clo/v1', '/send-wa', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'proxy_wa_legacy' ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route( 'clo/v1', '/send-email', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'proxy_email_legacy' ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route( 'clo/v1', '/notify-internal-email', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'proxy_notify_legacy' ],
            'permission_callback' => '__return_true',
        ]);

        // Admin CRUD
        register_rest_route( 'eo/v1', '/lead/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'update_lead_status' ],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ]);
        register_rest_route( 'eo/v1', '/lead/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'delete_lead' ],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ]);
    }

    public static function submit_lead( WP_REST_Request $request ) {

        // Rate limiting berdasarkan IP
        $ip_key = 'eo_rl_' . md5( $_SERVER['REMOTE_ADDR'] ?? 'x' );
        $count  = (int) get_transient( $ip_key );
        if ( $count >= 10 ) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Terlalu banyak permintaan. Coba lagi nanti.',
            ], 429);
        }
        set_transient( $ip_key, $count + 1, 10 * MINUTE_IN_SECONDS );

        // Ambil body — support JSON dan form-data
        $body = $request->get_json_params();
        if ( empty($body) ) {
            $body = $request->get_params();
        }

        $post_id = (int) ( $body['post_id'] ?? 0 );
        $fields  = $body['fields'] ?? [];

        // Jika fields kosong, coba ambil langsung dari body (fallback)
        if ( empty($fields) ) {
            $fields = $body;
            unset( $fields['post_id'] );
        }

        if ( empty($fields) ) {
            error_log('[EO Submit] Data kosong. Body: ' . wp_json_encode($body));
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Data kosong.',
            ], 400);
        }

        // Validasi minimal: nama dan wa wajib ada
        $nama = sanitize_text_field( $fields['nama'] ?? '' );
        $wa   = sanitize_text_field( $fields['wa']   ?? '' );

        if ( ! $nama || ! $wa ) {
            error_log('[EO Submit] Nama/WA kosong. Fields: ' . wp_json_encode($fields));
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Nama dan WhatsApp wajib diisi.',
            ], 400);
        }

        $fields['post_id']     = $post_id;
        $fields['_source_url'] = $request->get_header('referer') ?? '';

        // Simpan lead
        $lead_id = EO_Leads::save( $fields );

        if ( ! $lead_id ) {
            error_log('[EO Submit] Gagal simpan lead. Fields: ' . wp_json_encode($fields));
            // Tetap coba kirim notifikasi meski DB gagal
            $config = $post_id ? EO_Form_Builder::get_form_config( $post_id ) : [];
            EO_Integrations::send_followup( $fields, $config );
            EO_Integrations::notify_internal( $fields, $config );

            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Gagal menyimpan data ke database.',
            ], 500);
        }

        // Ambil config form dan kirim autoresponder
        $config = $post_id ? EO_Form_Builder::get_form_config( $post_id ) : [];

        EO_Integrations::send_followup( $fields, $config );
        EO_Integrations::notify_internal( $fields, $config );

        error_log('[EO Submit] Lead #' . $lead_id . ' tersimpan. Nama: ' . $nama . ' WA: ' . $wa);

        return new WP_REST_Response([
            'success' => true,
            'lead_id' => $lead_id,
        ], 200);
    }

    public static function proxy_wa_legacy( WP_REST_Request $request ) {
        $json    = $request->get_json_params() ?: [];
        $target  = sanitize_text_field( $json['target']  ?? $request->get_param('target')  ?? '' );
        $message = sanitize_textarea_field( $json['message'] ?? $request->get_param('message') ?? '' );
        $token_from_body = sanitize_text_field( $json['token'] ?? $request->get_param('token') ?? '' );

        if ( empty($target) || empty($message) ) {
            return new WP_REST_Response([ 'success' => false, 'error' => 'Parameter tidak lengkap.' ], 400);
        }

        $token = get_option('eo_fonnte_token', '') ?: $token_from_body;
        if ( ! $token ) {
            return new WP_REST_Response([ 'success' => false, 'error' => 'Fonnte token tidak tersedia.' ], 500);
        }

        $to = preg_replace('/\D/', '', $target);
        if ( substr($to, 0, 1) === '0' ) $to = '62' . substr($to, 1);
        if ( substr($to, 0, 2) !== '62' ) $to = '62' . $to;

        $response = wp_remote_post( 'https://api.fonnte.com/send', [
            'timeout' => 20,
            'headers' => [ 'Authorization' => $token ],
            'body'    => [
                'target'      => $to,
                'message'     => $message,
                'countryCode' => '62',
            ],
        ]);

        if ( is_wp_error($response) ) {
            return new WP_REST_Response([ 'success' => false, 'error' => $response->get_error_message() ], 500);
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body_raw  = wp_remote_retrieve_body($response);

        return new WP_REST_Response([
            'success'         => ( $http_code >= 200 && $http_code < 300 ),
            'upstream_status' => $http_code,
            'upstream_body'   => json_decode($body_raw, true) ?? $body_raw,
        ], 200);
    }

    public static function proxy_email_legacy( WP_REST_Request $request ) {
        $json   = $request->get_json_params() ?: [];
        $result = EO_Integrations::send_email(
            sanitize_email( $json['to_email'] ?? '' ),
            sanitize_text_field( $json['to_name'] ?? '' ),
            sanitize_text_field( $json['subject'] ?? '' ),
            wp_kses_post( $json['html_body'] ?? '' )
        );
        return new WP_REST_Response([ 'status' => (bool)$result ], 200);
    }

    public static function proxy_notify_legacy( WP_REST_Request $request ) {
        $json  = $request->get_json_params() ?: [];
        $nama  = sanitize_text_field( $json['nama']       ?? '' );
        $wa    = sanitize_text_field( $json['wa']         ?? '' );
        $email = sanitize_email(      $json['email']      ?? '' );
        $perus = sanitize_text_field( $json['perusahaan'] ?? '' );
        $pili  = sanitize_text_field( $json['pilihan']    ?? '' );

        EO_Leads::save([
            'nama'           => $nama,
            'wa'             => $wa,
            'email'          => $email,
            'perusahaan'     => $perus,
            '_pilihan_label' => $pili,
            '_pilihan_price' => '',
            'post_id'        => 0,
            '_source_url'    => $request->get_header('referer') ?? '',
        ]);

        $intern = get_option('eo_notify_email', get_option('admin_email'));
        if ( $intern ) {
            $html = '<p><b>🔔 Lead Baru</b></p>'
                . '<table><tr><td><b>Nama</b></td><td>' . esc_html($nama) . '</td></tr>'
                . '<tr><td><b>WA</b></td><td>' . esc_html($wa) . '</td></tr>'
                . '<tr><td><b>Email</b></td><td>' . esc_html($email) . '</td></tr>'
                . '<tr><td><b>Perusahaan</b></td><td>' . esc_html($perus) . '</td></tr>'
                . '<tr><td><b>Pilihan</b></td><td>' . esc_html($pili) . '</td></tr></table>';
            EO_Integrations::send_email( $intern, get_bloginfo('name'), '🔔 Lead: ' . $nama, $html );
        }

        return new WP_REST_Response([ 'status' => true ], 200);
    }

    public static function update_lead_status( WP_REST_Request $request ) {
        $id   = (int) $request->get_param('id');
        $json = $request->get_json_params() ?: [];
        EO_Leads::update_status(
            $id,
            sanitize_text_field( $json['status']  ?? 'new' ),
            sanitize_textarea_field( $json['catatan'] ?? '' )
        );
        return new WP_REST_Response([ 'success' => true ], 200);
    }

    public static function delete_lead( WP_REST_Request $request ) {
        EO_Leads::delete( (int) $request->get_param('id') );
        return new WP_REST_Response([ 'success' => true ], 200);
    }
}
