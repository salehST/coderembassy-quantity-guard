<?php
/**
 * Uninstall handler.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'ceqg_settings', array() );

if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) || 'yes' !== $settings['delete_data_on_uninstall'] ) {
	return;
}

$meta_keys = array(
	'_ceqg_enable_product_rule',
	'_ceqg_min_qty',
	'_ceqg_max_qty',
	'_ceqg_step_qty',
	'_ceqg_default_qty',
	'_ceqg_custom_message',
	'_ceqg_enable_variation_rule',
	'_ceqg_variation_min_qty',
	'_ceqg_variation_max_qty',
	'_ceqg_variation_step_qty',
	'_ceqg_variation_default_qty',
	'_ceqg_variation_custom_message',
);

foreach ( $meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}

delete_option( 'ceqg_settings' );
