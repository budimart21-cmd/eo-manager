<?php
/**
 * EO Leads — Database management
 * Safe insert: deteksi kolom yang ada sebelum insert
 */
class EO_Leads {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'eo_leads';
    }

    public static function create_table() {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id           BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            post_title        VARCHAR(255) NOT NULL DEFAULT '',
            nama              VARCHAR(255) NOT NULL DEFAULT '',
            wa                VARCHAR(50)  NOT NULL DEFAULT '',
            email             VARCHAR(255)          DEFAULT '',
            perusahaan        VARCHAR(255)          DEFAULT '',
            pilihan_label     VARCHAR(255)          DEFAULT '',
            pilihan_price     VARCHAR(100)          DEFAULT '',
            pilihan_price_num BIGINT(20)   NOT NULL DEFAULT 0,
            extra_fields      LONGTEXT              DEFAULT '',
            sumber_url        VARCHAR(500)          DEFAULT '',
            status            VARCHAR(50)  NOT NULL DEFAULT 'new',
            catatan           TEXT                  DEFAULT '',
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY created_at (created_at),
            KEY status (status),
            KEY nama (nama(100))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Pastikan kolom pilihan_price_num ada (untuk upgrade dari versi lama)
        self::maybe_add_price_num_column();
    }

    /**
     * Tambah kolom pilihan_price_num jika belum ada (safe upgrade)
     */
    public static function maybe_add_price_num_column() {
        global $wpdb;
        $table   = self::table_name();
        $col     = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'pilihan_price_num'" );
        if ( empty($col) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN pilihan_price_num BIGINT(20) NOT NULL DEFAULT 0 AFTER pilihan_price" );
            error_log('[EO DB] Kolom pilihan_price_num berhasil ditambahkan.');
        }
    }

    /**
     * Simpan lead baru — backward compatible
     */
    public static function save( $data ) {
        global $wpdb;

        // Kumpulkan extra fields (bukan core fields)
        $core  = ['nama','wa','email','perusahaan','_pilihan_value','_pilihan_label','_pilihan_price','post_id','_source_url'];
        $extra = [];
        foreach ( $data as $k => $v ) {
            if ( ! in_array($k, $core, true ) ) $extra[$k] = $v;
        }

        // Harga: support lama (teks "Rp 150.000") dan baru (angka "150000")
        $price_raw = trim( $data['_pilihan_price'] ?? '' );
        $price_num = (int) preg_replace('/\D/', '', $price_raw);

        $row = [
            'post_id'           => (int) ($data['post_id'] ?? 0),
            'post_title'        => get_the_title( (int)($data['post_id'] ?? 0) ),
            'nama'              => sanitize_text_field( $data['nama']        ?? '' ),
            'wa'                => sanitize_text_field( $data['wa']          ?? '' ),
            'email'             => sanitize_email(      $data['email']       ?? '' ),
            'perusahaan'        => sanitize_text_field( $data['perusahaan']  ?? '' ),
            'pilihan_label'     => sanitize_text_field( $data['_pilihan_label'] ?? '' ),
            'pilihan_price'     => sanitize_text_field( $price_raw ),
            'pilihan_price_num' => $price_num,
            'extra_fields'      => wp_json_encode( $extra ),
            'sumber_url'        => esc_url_raw( $data['_source_url'] ?? '' ),
            'status'            => 'new',
            'created_at'        => current_time( 'mysql' ),
        ];

        // Cek apakah kolom pilihan_price_num ada; jika tidak, hapus dari row
        $col = $wpdb->get_results( "SHOW COLUMNS FROM " . self::table_name() . " LIKE 'pilihan_price_num'" );
        if ( empty($col) ) {
            unset( $row['pilihan_price_num'] );
            // Coba tambahkan kolom
            self::maybe_add_price_num_column();
        }

        $result = $wpdb->insert( self::table_name(), $row );

        if ( $result === false ) {
            error_log('[EO DB] Insert gagal. Error: ' . $wpdb->last_error);
            error_log('[EO DB] Query: ' . $wpdb->last_query);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Query leads dengan filter
     */
    public static function get_leads( $args = [] ) {
        global $wpdb;
        $table = self::table_name();

        $defaults = [
            'per_page'  => 50,
            'page'      => 1,
            'search'    => '',
            'post_id'   => 0,
            'status'    => '',
            'date_from' => '',
            'date_to'   => '',
            'orderby'   => 'created_at',
            'order'     => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where  = [];
        $params = [];

        if ( $args['search'] ) {
            $s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(nama LIKE %s OR wa LIKE %s OR email LIKE %s OR perusahaan LIKE %s OR pilihan_label LIKE %s)';
            $params   = array_merge( $params, [ $s, $s, $s, $s, $s ] );
        }
        if ( $args['post_id'] ) {
            $where[]  = 'post_id = %d';
            $params[] = (int) $args['post_id'];
        }
        if ( $args['status'] ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if ( $args['date_from'] ) {
            $where[]  = 'created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( $args['date_to'] ) {
            $where[]  = 'created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $allowed   = ['id','nama','wa','created_at','status','post_title','pilihan_price_num'];
        $order_col = in_array( $args['orderby'], $allowed ) ? $args['orderby'] : 'created_at';
        $order_dir = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $offset    = ( (int)$args['page'] - 1 ) * (int)$args['per_page'];
        $limit     = (int) $args['per_page'];

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $data_sql  = "SELECT * FROM {$table} {$where_sql} ORDER BY {$order_col} {$order_dir} LIMIT %d OFFSET %d";

        $all_params_count = $params;
        $all_params_data  = array_merge( $params, [ $limit, $offset ] );

        $total = (int) $wpdb->get_var(
            $all_params_count
                ? $wpdb->prepare( $count_sql, $all_params_count )
                : $count_sql
        );
        $rows = $wpdb->get_results(
            $all_params_data
                ? $wpdb->prepare( $data_sql, $all_params_data )
                : $data_sql
        );

        return [
            'total' => $total,
            'pages' => $limit > 0 ? (int) ceil( $total / $limit ) : 1,
            'items' => $rows ?: [],
        ];
    }

    public static function update_status( $id, $status, $catatan = null ) {
        global $wpdb;
        $data = [ 'status' => sanitize_text_field($status) ];
        if ( $catatan !== null ) $data['catatan'] = sanitize_textarea_field($catatan);
        $wpdb->update( self::table_name(), $data, ['id' => (int)$id] );
    }

    public static function delete( $id ) {
        global $wpdb;
        $wpdb->delete( self::table_name(), ['id' => (int)$id] );
    }

    public static function get_stats() {
        global $wpdb;
        $table = self::table_name();

        // Cek apakah kolom price_num ada
        $has_price_col = ! empty(
            $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'pilihan_price_num'" )
        );

        $stats = [
            'total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
            'new'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='new'" ),
            'today'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at)=CURDATE()" ),
            'this_week'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" ),
            'omset_total'=> 0,
            'omset_month'=> 0,
        ];

        if ( $has_price_col ) {
            $stats['omset_total'] = (int) $wpdb->get_var( "SELECT COALESCE(SUM(pilihan_price_num),0) FROM {$table}" );
            $stats['omset_month'] = (int) $wpdb->get_var( "SELECT COALESCE(SUM(pilihan_price_num),0) FROM {$table} WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())" );
        }

        return $stats;
    }

    public static function count_by_post( $post_id ) {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::table_name() . " WHERE post_id = %d",
                (int) $post_id
            )
        );
    }

    public static function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table_name() . " WHERE id = %d",
                (int) $id
            )
        );
    }

    public static function get_omset_stats( $args = [] ) {
        global $wpdb;
        $table = self::table_name();

        // Cek kolom price_num
        $has_price_col = ! empty(
            $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'pilihan_price_num'" )
        );
        if ( ! $has_price_col ) {
            return [ 'total_leads' => 0, 'total_omset' => 0, 'rows' => [] ];
        }

        $defaults = [
            'post_id'   => 0,
            'status'    => '',
            'date_from' => '',
            'date_to'   => '',
            'group_by'  => 'day',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where  = [ "pilihan_price_num > 0" ];
        $params = [];

        if ( $args['post_id'] ) {
            $where[]  = 'post_id = %d';
            $params[] = (int) $args['post_id'];
        }
        if ( $args['status'] ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if ( $args['date_from'] ) {
            $where[]  = 'created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( $args['date_to'] ) {
            $where[]  = 'created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $summary_sql = "SELECT COUNT(*) as total_leads, COALESCE(SUM(pilihan_price_num),0) as total_omset FROM {$table} {$where_sql}";
        $summary = $wpdb->get_row( $params ? $wpdb->prepare( $summary_sql, $params ) : $summary_sql );

        if ( $args['group_by'] === 'product' ) {
            $group_sql = "SELECT post_title as label, COUNT(*) as leads, COALESCE(SUM(pilihan_price_num),0) as omset
                          FROM {$table} {$where_sql} GROUP BY post_id, post_title ORDER BY omset DESC";
        } elseif ( $args['group_by'] === 'month' ) {
            $group_sql = "SELECT DATE_FORMAT(created_at,'%Y-%m') as label, COUNT(*) as leads, COALESCE(SUM(pilihan_price_num),0) as omset
                          FROM {$table} {$where_sql} GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY label ASC";
        } else {
            $group_sql = "SELECT DATE(created_at) as label, COUNT(*) as leads, COALESCE(SUM(pilihan_price_num),0) as omset
                          FROM {$table} {$where_sql} GROUP BY DATE(created_at) ORDER BY label ASC LIMIT 60";
        }

        $rows = $wpdb->get_results( $params ? $wpdb->prepare( $group_sql, $params ) : $group_sql );

        return [
            'total_leads' => (int) ($summary->total_leads ?? 0),
            'total_omset' => (int) ($summary->total_omset ?? 0),
            'rows'        => $rows ?: [],
        ];
    }

    public static function export_csv( $args = [] ) {
        $result = self::get_leads( array_merge( $args, ['per_page' => 99999, 'page' => 1] ) );
        $rows   = $result['items'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv( $out, ['ID','Produk','Nama','WA','Email','Perusahaan','Pilihan','Harga','Harga (Angka)','Status','Catatan','Waktu'] );
        foreach ( $rows as $row ) {
            fputcsv( $out, [
                $row->id, $row->post_title, $row->nama, $row->wa, $row->email,
                $row->perusahaan, $row->pilihan_label, $row->pilihan_price,
                $row->pilihan_price_num ?? 0,
                $row->status, $row->catatan, $row->created_at,
            ]);
        }
        fclose($out);
        exit;
    }
}
