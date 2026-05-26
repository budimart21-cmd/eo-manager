<?php
/**
 * Plugin Name:     EO Manager — Leads, CRM & Autoresponder
 * Plugin URI:      https://solusimarketing.xyz
 * Description:     Kelola produk, landing page, custom form, leads CRM, integrasi Fonnte WA & Mailketing email autoresponder. Compatible dengan GeneratePress & Gutenberg.
 * Version:         3.0.0
 * Author:          Solusi Marketing
 * Author URI:      https://solusimarketing.xyz
 * Text Domain:     eo-manager
 * Requires PHP:    7.4
 * Requires WP:     6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EO_PLUGIN_VERSION', '3.0.0' );
define( 'EO_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'EO_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );

/* =========================================================
   LOAD MODULES
   ========================================================= */
require_once EO_PLUGIN_DIR . 'includes/class-eo-post-types.php';
require_once EO_PLUGIN_DIR . 'includes/class-eo-form-builder.php';
require_once EO_PLUGIN_DIR . 'includes/class-eo-leads.php';
require_once EO_PLUGIN_DIR . 'includes/class-eo-integrations.php';
require_once EO_PLUGIN_DIR . 'includes/class-eo-rest-api.php';
require_once EO_PLUGIN_DIR . 'includes/class-eo-settings.php';
require_once EO_PLUGIN_DIR . 'includes/class-eo-display-settings.php';
require_once EO_PLUGIN_DIR . 'admin/class-eo-admin.php';
require_once EO_PLUGIN_DIR . 'admin/class-eo-crm-page.php';
require_once EO_PLUGIN_DIR . 'admin/class-eo-products-page.php';

/* =========================================================
   ACTIVATION / DEACTIVATION
   ========================================================= */
register_activation_hook( __FILE__, function() {
    EO_Post_Types::register();
    flush_rewrite_rules();
    EO_Leads::create_table();
});

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* =========================================================
   INIT ALL MODULES
   ========================================================= */
add_action( 'init', [ 'EO_Post_Types', 'register' ] );
add_action( 'rest_api_init', [ 'EO_Rest_API', 'register_routes' ] );
add_action( 'plugins_loaded', function() {
    EO_Admin::init();
    EO_CRM_Page::init();
    EO_Products_Page::init();
    EO_Settings::init();
    EO_Display_Settings::init();
});
