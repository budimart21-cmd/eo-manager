<?php
/**
 * EO Form Builder
 * - Shortcode [eo_form] dan [eo_form id="X"]
 * - Render form HTML dengan radio produk, field custom, submit via REST
 * - Semua WA/Email dikirim server-side (bypass CORS, tiru trik clo-wa-proxy)
 * - Nonce menggunakan wp_create_nonce('wp_rest') agar valid untuk guest/non-login
 */
class EO_Form_Builder {

    public static function save_form_config( $post_id, $config ) {
        update_post_meta( $post_id, '_eo_form_config', $config );
    }

    public static function get_form_config( $post_id ) {
        $default = [
            'fields'          => self::default_fields(),
            'wa_template'     => self::default_wa_template(),
            'email_subject'   => 'Terima kasih telah menghubungi kami — {site_name}',
            'email_template'  => self::default_email_template(),
            'notify_wa'       => '',
            'notify_email'    => '',
            'success_title'   => 'Terima Kasih!',
            'success_message' => 'Tim kami akan segera menghubungi Anda.',
            'coa_link'        => '',
            'products'        => [],
        ];
        $saved = get_post_meta( $post_id, '_eo_form_config', true );
        if ( is_array( $saved ) ) {
            return wp_parse_args( $saved, $default );
        }
        return $default;
    }

    public static function default_fields() {
        return [
            [ 'id' => 'nama',       'label' => 'Nama Lengkap',    'type' => 'text',  'required' => true,  'placeholder' => 'Nama Anda' ],
            [ 'id' => 'wa',         'label' => 'Nomor WhatsApp',  'type' => 'tel',   'required' => true,  'placeholder' => '08xx / +628xx' ],
            [ 'id' => 'email',      'label' => 'Email',           'type' => 'email', 'required' => false, 'placeholder' => 'email@domain.com' ],
            [ 'id' => 'perusahaan', 'label' => 'Nama Perusahaan', 'type' => 'text',  'required' => false, 'placeholder' => 'Opsional' ],
        ];
    }

    public static function default_wa_template() {
        return "Halo {nama}! 👋\n\nTerima kasih telah menghubungi *{site_name}*.\n\nPilihan: *{pilihan}*\n{harga}\n\nTim kami akan segera menghubungi Anda. 🙏\n\n_— Tim {site_name}_";
    }

    public static function default_email_template() {
        return '<p>Halo <strong>{nama}</strong>,</p><p>Terima kasih telah menghubungi <strong>{site_name}</strong>.</p><p>Pilihan Anda: <strong>{pilihan}</strong>{harga}</p><p>Tim kami akan segera menghubungi Anda.</p>';
    }

    /**
     * Render HTML form — dipanggil oleh shortcode
     */
    public static function render_form( $post_id ) {
        $config   = self::get_form_config( $post_id );
        $fields   = $config['fields']   ?? self::default_fields();
        $products = $config['products'] ?? [];

        // KUNCI ANTI-CORS: gunakan wp_create_nonce('wp_rest')
        // Ini adalah nonce standar WordPress REST API, valid untuk semua user termasuk tamu/guest
        $rest_nonce = wp_create_nonce( 'wp_rest' );

        ob_start();
        ?>
        <div class="eo-form-wrap" id="eo-form-wrap-<?php echo esc_attr($post_id); ?>"
             style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:28px 28px 24px;max-width:520px;margin:0 auto;font-family:'DM Sans','Segoe UI',sans-serif;">

            <div id="eo-form-body-<?php echo esc_attr($post_id); ?>">

                <?php if ( ! empty($products) ) : ?>
                <div class="eo-radio-group" style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
                    <?php foreach ( $products as $i => $prod ) :
                        $pval = sanitize_key( $prod['value'] ?? 'opt_' . $i );
                        $uid  = 'eo-opt-' . esc_attr($pval) . '-' . $post_id;
                    ?>
                    <label class="eo-radio-label <?php echo $i === 0 ? 'eo-selected' : ''; ?>"
                           id="<?php echo $uid; ?>"
                           onclick="eoSelectOption(this,'<?php echo esc_js($pval); ?>',<?php echo (int)$post_id; ?>)"
                           style="display:flex;align-items:center;gap:12px;padding:12px 16px;border:1.5px solid <?php echo $i===0 ? '#15803d' : '#e2e8f0'; ?>;background:<?php echo $i===0 ? '#f0fdf4' : '#fff'; ?>;border-radius:10px;cursor:pointer;transition:all .2s;">
                        <input type="radio" name="eo-pilihan-<?php echo (int)$post_id; ?>"
                               value="<?php echo esc_attr($pval); ?>"
                               style="accent-color:#15803d;"
                               <?php echo $i === 0 ? 'checked' : ''; ?>>
                        <span style="flex:1;font-weight:600;font-size:14px;color:#0f172a;"><?php echo esc_html($prod['label'] ?? ''); ?></span>
                        <?php if ( ! empty($prod['price']) ) : ?>
                        <span style="font-weight:700;color:#15803d;font-size:14px;"><?php echo esc_html($prod['price']); ?></span>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php foreach ( $fields as $field ) :
                    $fid = sanitize_key( $field['id'] ?? 'field' );
                    $req = ! empty( $field['required'] );
                    $eid = 'eo-' . $fid . '-' . $post_id;
                ?>
                <div style="margin-bottom:14px;">
                    <label for="<?php echo $eid; ?>" style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">
                        <?php echo esc_html( $field['label'] ?? $fid ); ?>
                        <?php if ($req) echo '<span style="color:#dc2626;margin-left:2px;">*</span>'; ?>
                    </label>
                    <?php if ( ($field['type'] ?? 'text') === 'textarea' ) : ?>
                    <textarea id="<?php echo $eid; ?>" placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                              rows="3" <?php echo $req ? 'required' : ''; ?>
                              style="width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;outline:none;transition:border-color .2s;"
                              onfocus="this.style.borderColor='#15803d'" onblur="this.style.borderColor='#e2e8f0'"></textarea>
                    <?php elseif ( ($field['type'] ?? 'text') === 'select' && ! empty($field['options']) ) : ?>
                    <select id="<?php echo $eid; ?>" <?php echo $req ? 'required' : ''; ?>
                            style="width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;background:#fff;outline:none;">
                        <option value="">-- Pilih --</option>
                        <?php foreach ( ($field['options'] ?? []) as $opt ) : ?>
                        <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else : ?>
                    <input type="<?php echo esc_attr($field['type'] ?? 'text'); ?>"
                           id="<?php echo $eid; ?>"
                           placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                           <?php echo $req ? 'required' : ''; ?>
                           style="width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s;"
                           onfocus="this.style.borderColor='#15803d'" onblur="this.style.borderColor='#e2e8f0'">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <button id="eo-submit-btn-<?php echo (int)$post_id; ?>"
                        onclick="eoSubmitForm(<?php echo (int)$post_id; ?>)"
                        type="button"
                        style="width:100%;background:#15803d;color:#fff;border:none;padding:14px 20px;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;transition:background .2s;font-family:inherit;">
                    <?php
                    // Teks tombol default: ambil dari produk pertama jika ada
                    if ( ! empty($products) ) {
                        $fp = $products[0];
                        echo ! empty($fp['price'])
                            ? '🛒 Pesan ' . esc_html($fp['label']) . ' (' . esc_html($fp['price']) . ') →'
                            : '📋 ' . esc_html($fp['label']) . ' →';
                    } else {
                        echo '✉️ Kirim →';
                    }
                    ?>
                </button>

                <!-- Data diteruskan ke JS — TIDAK ada token sensitif di sini -->
                <script>
                (function(){
                    window._eoForms = window._eoForms || {};
                    window._eoForms[<?php echo (int)$post_id; ?>] = {
                        post_id  : <?php echo (int)$post_id; ?>,
                        nonce    : <?php echo wp_json_encode( $rest_nonce ); ?>,
                        fields   : <?php echo wp_json_encode( $fields ); ?>,
                        products : <?php echo wp_json_encode( $products ); ?>,
                        success_title   : <?php echo wp_json_encode( $config['success_title'] ); ?>,
                        success_message : <?php echo wp_json_encode( $config['success_message'] ); ?>,
                        notify_wa_fallback : <?php echo wp_json_encode( $config['notify_wa'] ?: get_option('eo_notify_wa','') ); ?>
                    };
                })();
                </script>

            </div><!-- /eo-form-body -->

            <!-- Success state -->
            <div id="eo-form-success-<?php echo (int)$post_id; ?>" style="display:none;text-align:center;padding:32px 0;">
                <div style="font-size:52px;margin-bottom:12px;">✅</div>
                <h3 id="eo-success-title-<?php echo (int)$post_id; ?>"
                    style="font-size:20px;color:#0f172a;margin-bottom:8px;"><?php echo esc_html($config['success_title']); ?></h3>
                <p id="eo-success-msg-<?php echo (int)$post_id; ?>"
                   style="color:#64748b;font-size:15px;line-height:1.6;"><?php echo esc_html($config['success_message']); ?></p>
                <button onclick="eoResetForm(<?php echo (int)$post_id; ?>)"
                        type="button"
                        style="margin-top:20px;background:transparent;border:1.5px solid #15803d;color:#15803d;padding:10px 24px;border-radius:8px;cursor:pointer;font-weight:600;font-family:inherit;">
                    ← Isi Form Lagi
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode [eo_form] atau [eo_form id="123"]
     */
    public static function shortcode( $atts ) {
        $atts    = shortcode_atts([ 'id' => get_the_ID() ], $atts, 'eo_form' );
        $post_id = (int) $atts['id'];
        if ( ! $post_id ) return '';
        // Pastikan JS engine dimuat
        self::$needs_js = true;
        return self::render_form( $post_id );
    }

    // Flag: apakah halaman ini perlu JS engine?
    public static $needs_js = false;

    public static function check_product_page() {
        if ( is_singular('eo_product') ) {
            self::$needs_js = true;
        }
    }

    public static function init() {
        add_shortcode( 'eo_form', [ __CLASS__, 'shortcode' ] );
        add_action( 'wp_footer', [ __CLASS__, 'maybe_print_js' ], 20 );
        // Cek juga via hook the_content untuk halaman produk
        add_action( 'wp', [ __CLASS__, 'check_product_page' ] );
    }

    /**
     * Print JS engine hanya jika dibutuhkan di halaman ini
     */
    public static function maybe_print_js() {
        // Cek apakah shortcode ada di konten post manapun yang sudah di-render
        global $wp_query;
        if ( ! self::$needs_js ) {
            // Fallback check: scan post content
            $post = get_post();
            if ( $post && (
                has_shortcode( $post->post_content, 'eo_form' ) ||
                is_singular('eo_product')
            )) {
                self::$needs_js = true;
            }
        }
        if ( ! self::$needs_js ) return;
        self::print_js_engine();
    }

    public static function print_js_engine() {
        // REST API URL — sama-origin, tidak ada CORS
        $rest_url = esc_js( rest_url('eo/v1/submit-lead') );
        ?>
        <script>
        /* ============================================================
           EO Form Engine
           Cara kerja (tiru clo-wa-proxy):
           1. Browser → POST ke /wp-json/eo/v1/submit-lead (same-origin, NO CORS)
           2. WordPress (PHP) → simpan lead ke DB
           3. WordPress (PHP) → kirim WA via Fonnte (server-to-server)
           4. WordPress (PHP) → kirim Email via Mailketing (server-to-server)
           Semua keluar dari server PHP, bukan dari browser → tidak ada CORS
           ============================================================ */

        var _EO_REST_URL = '<?php echo $rest_url; ?>';

        /* ── Radio select ── */
        function eoSelectOption(labelEl, val, postId) {
            var wrap = document.getElementById('eo-form-wrap-' + postId);
            if (!wrap) return;
            wrap.querySelectorAll('.eo-radio-label').forEach(function(l) {
                l.style.borderColor = '#e2e8f0';
                l.style.background  = '#fff';
            });
            labelEl.style.borderColor = '#15803d';
            labelEl.style.background  = '#f0fdf4';
            var radio = labelEl.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;

            var cfg = (window._eoForms || {})[postId];
            if (!cfg) return;
            var prod = (cfg.products || []).find(function(p){ return p.value === val; });
            if (!prod) return;
            var btn = document.getElementById('eo-submit-btn-' + postId);
            if (!btn) return;
            btn.textContent = prod.price
                ? '🛒 Pesan ' + prod.label + ' (' + prod.price + ') →'
                : '📋 ' + prod.label + ' →';
        }

        /* ── Submit utama ── */
        function eoSubmitForm(postId) {
            var cfg = (window._eoForms || {})[postId];
            if (!cfg) { alert('Konfigurasi form tidak ditemukan.'); return; }

            var fields  = cfg.fields  || [];
            var data    = {};
            var valid   = true;

            /* Kumpulkan nilai field */
            fields.forEach(function(f) {
                var el = document.getElementById('eo-' + f.id + '-' + postId);
                if (!el) return;
                var v = el.value.trim();
                if (f.required && !v) {
                    el.style.borderColor = '#dc2626';
                    if (valid) { alert('Mohon isi: ' + f.label); el.focus(); }
                    valid = false;
                } else {
                    el.style.borderColor = '#e2e8f0';
                }
                data[f.id] = v;
            });
            if (!valid) return;

            /* Validasi WA */
            var waRaw = (data.wa || '').replace(/\D/g, '');
            if (waRaw.length < 9) {
                alert('Nomor WhatsApp tidak valid (minimal 9 angka).');
                return;
            }

            /* Pilihan produk */
            var selInput = document.querySelector(
                'input[name="eo-pilihan-' + postId + '"]:checked'
            );
            data._pilihan_value = selInput ? selInput.value : '';
            var prod = null;
            if (cfg.products && data._pilihan_value) {
                prod = cfg.products.find(function(p){ return p.value === data._pilihan_value; });
            }
            data._pilihan_label = prod ? (prod.label || '') : '';
            data._pilihan_price = prod ? (prod.price || '') : '';

            /* Disable tombol */
            var btn = document.getElementById('eo-submit-btn-' + postId);
            if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Memproses...'; }

            /* ============================================================
               POST ke WordPress REST API (SAME ORIGIN → tidak ada CORS)
               Header X-WP-Nonce: nonce 'wp_rest' yang di-generate server
               ============================================================ */
            fetch(_EO_REST_URL, {
                method  : 'POST',
                headers : {
                    'Content-Type' : 'application/json',
                    'X-WP-Nonce'   : cfg.nonce   /* nonce wp_rest, bukan token Fonnte */
                },
                body: JSON.stringify({
                    post_id : cfg.post_id,
                    fields  : data
                })
            })
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(res) {
                if (res.success) {
                    eoShowSuccess(postId, cfg, data, prod);
                } else {
                    /* Server berhasil dicapai tapi ada error logic — tetap tampil sukses
                       + buka WA manual sebagai fallback */
                    eoFallbackWA(cfg, data, prod);
                    eoShowSuccess(postId, cfg, data, prod);
                }
            })
            .catch(function(err) {
                console.error('[EO Form] fetch error:', err);
                if (btn) { btn.disabled = false; btn.innerHTML = '✉️ Kirim →'; }
                /* Fallback: buka WhatsApp langsung */
                eoFallbackWA(cfg, data, prod);
                /* Tetap tampil sukses agar UX tidak rusak */
                eoShowSuccess(postId, cfg, data, prod);
            });

            /* GA4 */
            if (typeof gtag === 'function') {
                gtag('event', 'eo_lead_submit', {
                    event_category : 'Lead',
                    event_label    : data._pilihan_label || 'no-product',
                    value          : data._pilihan_price || 0
                });
            }
        }

        /* Buka WA manual sebagai fallback jika semua gagal */
        function eoFallbackWA(cfg, data, prod) {
            var waNum = (cfg.notify_wa_fallback || '').replace(/\D/, '');
            if (!waNum) return;
            var msg = 'Halo, saya ' + (data.nama || '') +
                (data.perusahaan ? ' dari ' + data.perusahaan : '') +
                '. Saya ingin ' +
                (prod ? 'memesan ' + prod.label : 'menghubungi Anda') +
                '.';
            window.open('https://wa.me/' + waNum + '?text=' + encodeURIComponent(msg), '_blank');
        }

        function eoShowSuccess(postId, cfg, data, prod) {
            var body = document.getElementById('eo-form-body-' + postId);
            var succ = document.getElementById('eo-form-success-' + postId);
            var tit  = document.getElementById('eo-success-title-' + postId);
            var msg  = document.getElementById('eo-success-msg-' + postId);
            if (body) body.style.display = 'none';
            if (succ) succ.style.display = 'block';
            if (tit)  tit.textContent  = cfg.success_title   || 'Terima Kasih!';
            if (msg)  msg.textContent  = cfg.success_message || 'Tim kami akan segera menghubungi Anda.';
        }

        function eoResetForm(postId) {
            var body = document.getElementById('eo-form-body-' + postId);
            var succ = document.getElementById('eo-form-success-' + postId);
            var btn  = document.getElementById('eo-submit-btn-' + postId);
            if (body) body.style.display = 'block';
            if (succ) succ.style.display = 'none';
            if (btn)  { btn.disabled = false; btn.innerHTML = '✉️ Kirim →'; }
        }
        </script>
        <?php
    }
}

add_action( 'init', [ 'EO_Form_Builder', 'init' ] );
