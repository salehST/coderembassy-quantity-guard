<?php
/**
 * Plugin deactivation tasks.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin deactivation.
 */
class CEQG_Deactivator {
	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Deactivation intentionally leaves user data untouched.
	}
}
