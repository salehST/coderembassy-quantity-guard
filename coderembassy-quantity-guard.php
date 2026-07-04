<?php
/**
 * Plugin Name: CoderEmbassy Quantity Guard for WooCommerce
 * Plugin URI: https://coderembassy.com/
 * Description: Smart minimum, maximum, default, and step quantity rules for WooCommerce products and variations.
 * Version: 0.1.0
 * Author: CoderEmbassy
 * Author URI: https://coderembassy.com/
 * Text Domain: coderembassy-quantity-guard
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.9
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CEQG_VERSION', '0.1.0' );
define( 'CEQG_PLUGIN_FILE', __FILE__ );
define( 'CEQG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CEQG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CEQG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once CEQG_PLUGIN_DIR . 'includes/class-ceqg-activator.php';
require_once CEQG_PLUGIN_DIR . 'includes/class-ceqg-deactivator.php';
require_once CEQG_PLUGIN_DIR . 'includes/class-ceqg-plugin.php';

register_activation_hook( __FILE__, array( 'CEQG_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEQG_Deactivator', 'deactivate' ) );

add_action( 'before_woocommerce_init', 'ceqg_declare_woocommerce_compatibility' );
/**
 * Declare WooCommerce feature compatibility that is already safe in Phase 1.
 *
 * @return void
 */
function ceqg_declare_woocommerce_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			__FILE__,
			true
		);
	}
}

add_action( 'plugins_loaded', 'ceqg_run_plugin', 20 );
/**
 * Boot the plugin after other plugins have loaded.
 *
 * @return void
 */
function ceqg_run_plugin() {
	$plugin = new CEQG_Plugin();
	$plugin->run();
}
