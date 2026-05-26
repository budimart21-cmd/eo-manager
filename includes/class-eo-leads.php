<?php
/**
 * EO Leads — Database management
 * Tabel: {prefix}eo_leads
 */
class EO_Leads {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'eo_leads';
    }

    /**
     * Buat tabel saat aktivasi plugin
     */
    public static function create_table() {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id       BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            post_title    VARCHAR(255) NOT NULL DEFAULT '',
            nama          VARCHAR(255) NOT NULL DEFAULT '',
            wa            VARCHAR(50)  NOT NULL DEFAULT '',
            email         VARCHAR(255)          DEFAULT '',
            perusahaan    VARCHAR(255)          DEFAULT '',
            pilihan_label VARCHAR(255)          DEFAULT '',
            pilihan_price VARCHAR(100)          DEFAULT '',
            extra_fields  LONGTEXT              DEFAULT '',
            sumber_url    VARCHAR(500)          DEFAULT '',
            status        VARCHAR(50)  NOT NULL DEFAULT 'new',
            catatan       TEXT                  DEFAULT '',
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY created_at (created_at),
            KEY status (status),
            KEY nama (nama(100))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Simpan lead baru
     */
    public static function save( $data ) {
        global $wpdb;

        $extra = [];
        $core  = ['nama','wa','email','perusahaan','_pilihan_value','_pilihan_label','_pilihan_price'];
        foreach ( $data as $k => $v ) {
            if ( ! in_array($k, $core) ) $extra[$k] = $v;
        }

        $row = [
            'post_id'       => (int) ($data['post_id'] ?? 0),
            'post_title'    => get_the_title( $data['post_id'] ?? 0 ),
            'nama'          => sanitize_text_field( $data['nama'] ?? '' ),
            'wa'            => sanitize_text_field( $data['wa'] ?? '' ),
            'email'         => sanitize_email( $data['email'] ?? '' ),
            'perusahaan'    => sanitize_text_field( $data['perusahaan'] ?? '' ),
            'pilihan_label' => sanitize_text_field( $data['_pilihan_label'] ?? '' ),
            'pilihan_price' => sanitize_text_field( $data['_pilihan_price'] ?? '' ),
            'extra_fields'  => wp_json_encode( $extra ),
            'sumber_url'    => esc_url_raw( $data['_source_url'] ?? '' ),
            'status'        => 'new',
            'created_at'    => current_time( 'mysql' ),
        ];

        $wpdb->insert( self::table_name(), $row );
        return $wpdb->insert_id;
    }

    /**
     * Query leads dengan filter
     */
    public static function get_leads( $args = [] ) {
        global $wpdb;
        $table = self::table_name();

        $defaults = [
            'per_page'   => 50,
            'page'       => 1,
            'search'     => '',
            'post_id'    => 0,
            'status'     => '',
            'date_from'  => '',
            'date_to'    => '',
            'orderby'    => 'created_at',
            'order'      => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where  = [];
        $params = [];

        if ( $args['search'] ) {
            $s = '%' . $wpdb->esc_like( $args['search'] ) . '%';
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
        $order_col = in_array( $args['orderby'], ['id','nama','wa','created_at','status','post_title'] ) ? $args['orderby'] : 'created_at';
        $order_dir = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $offset    = ( (int)$args['page'] - 1 ) * (int)$args['per_page'];
        $limit     = (int) $args['per_page'];

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $data_sql  = "SELECT * FROM {$table} {$where_sql} ORDER BY {$order_col} {$order_dir} LIMIT %d OFFSET %d";

        $all_params_count = $params;
        $all_params_data  = array_merge( $params, [ $limit, $offset ] );

        $total = (int) $wpdb->get_var( $all_params_count ? $wpdb->prepare( $count_sql, $all_params_count ) : $count_sql );
        $rows  = $wpdb->get_results( $all_params_data ? $wpdb->prepare( $data_sql, $all_params_data ) : $data_sql );

        return [
            'total' => $total,
            'pages' => ceil( $total / $limit ),
            'items' => $rows,
        ];
    }

    /**
     * Update status lead
     */
    public static function update_status( $id, $status, $catatan = null ) {
        global $wpdb;
        $data = [ 'status' => sanitize_text_field($status) ];
        if ( $catatan !== null ) $data['catatan'] = sanitize_textarea_field($catatan);
        $wpdb->update( self::table_name(), $data, ['id' => (int)$id] );
    }

    /**
     * Delete lead
     */
    public static function delete( $id ) {
        global $wpdb;
        $wpdb->delete( self::table_name(), ['id' => (int)$id] );
    }

    /**
     * Stats untuk dashboard
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::table_name();
        return [
            'total'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
            'new'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='new'" ),
            'today'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at)=CURDATE()" ),
            'this_week' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" ),
        ];
    }

    /**
     * Get single lead by ID
     */
    public static function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table_name() . " WHERE id = %d", (int)$id ) );
    }

    /**
     * Export CSV
     */
    public static function export_csv( $args = [] ) {
        $result = self::get_leads( array_merge( $args, ['per_page' => 99999, 'page' => 1] ) );
        $rows   = $result['items'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv( $out, ['ID','Produk','Nama','WA','Email','Perusahaan','Pilihan','Harga','Status','Catatan','Waktu'] );
        foreach ( $rows as $row ) {
            fputcsv( $out, [
                $row->id, $row->post_title, $row->nama, $row->wa, $row->email,
                $row->perusahaan, $row->pilihan_label, $row->pilihan_price,
                $row->status, $row->catatan, $row->created_at
            ]);
        }
        fclose($out);
        exit;
    }
}
