<?php
/**
 * EO Post Types — Custom Post Type & Taxonomies
 */
class EO_Post_Types {

    public static function register() {
        // ── Custom Post Type: eo_product ──
        register_post_type( 'eo_product', [
            'labels' => [
                'name'               => 'Produk EO',
                'singular_name'      => 'Produk EO',
                'add_new'            => 'Tambah Produk',
                'add_new_item'       => 'Tambah Produk Baru',
                'edit_item'          => 'Edit Produk',
                'new_item'           => 'Produk Baru',
                'view_item'          => 'Lihat Landing Page',
                'search_items'       => 'Cari Produk',
                'not_found'          => 'Produk tidak ditemukan',
                'menu_name'          => 'Produk EO',
                'all_items'          => 'Semua Produk',
            ],
            'public'             => true,
            'has_archive'        => true,
            'rewrite'            => [ 'slug' => 'produk' ],
            'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ],
            'menu_icon'          => 'dashicons-drops',
            'menu_position'      => 5,
            'show_in_rest'       => true, // Gutenberg support
        ]);

        // ── Taxonomy: Category ──
        register_taxonomy( 'eo_product_category', 'eo_product', [
            'labels' => [
                'name'          => 'Kategori Produk',
                'singular_name' => 'Kategori',
                'search_items'  => 'Cari Kategori',
                'all_items'     => 'Semua Kategori',
                'edit_item'     => 'Edit Kategori',
                'add_new_item'  => 'Tambah Kategori Baru',
                'menu_name'     => 'Kategori',
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'kategori-eo' ],
        ]);

        // ── Taxonomy: Tag ──
        register_taxonomy( 'eo_product_tag', 'eo_product', [
            'labels' => [
                'name'          => 'Tag Produk',
                'singular_name' => 'Tag',
                'search_items'  => 'Cari Tag',
                'all_items'     => 'Semua Tag',
                'edit_item'     => 'Edit Tag',
                'add_new_item'  => 'Tambah Tag Baru',
                'menu_name'     => 'Tag Produk',
            ],
            'hierarchical'      => false,
            'public'            => true,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'tag-eo' ],
        ]);
    }
}
