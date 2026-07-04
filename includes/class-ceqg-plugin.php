<?php
/**
 * Main plugin loader.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates plugin bootstrapping.
 */
class CEQG_Plugin {
	/**
	 * Run plugin hooks.
	 *
	 * @return void
	 */
	public function run() {
		$this->load_textdomain();

		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'render_woocommerce_missing_notice' ) );
			return;
		}
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'coderembassy-quantity-guard',
			false,
			dirname( CEQG_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Determine whether WooCommerce is available.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Render an admin notice when WooCommerce is missing.
	 *
	 * @return void
	 */
	public function render_woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = __(
			'CoderEmbassy Quantity Guard requires WooCommerce to be installed and active.',
			'coderembassy-quantity-guard'
		);

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}
