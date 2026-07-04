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

// Data removal will be gated by the delete_data_on_uninstall setting in a later phase.
