<?php
/**
 * Central rule engine.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves effective quantity rules for products and variations.
 */
class CEQG_Rule_Engine {
	const PRODUCT_ENABLE_META = '_ceqg_enable_product_rule';
	const PRODUCT_MIN_META    = '_ceqg_min_qty';
	const PRODUCT_MAX_META    = '_ceqg_max_qty';
	const PRODUCT_STEP_META   = '_ceqg_step_qty';
	const PRODUCT_DEFAULT_META = '_ceqg_default_qty';
	const PRODUCT_MESSAGE_META = '_ceqg_custom_message';

	const VARIATION_ENABLE_META = '_ceqg_enable_variation_rule';
	const VARIATION_MIN_META    = '_ceqg_variation_min_qty';
	const VARIATION_MAX_META    = '_ceqg_variation_max_qty';
	const VARIATION_STEP_META   = '_ceqg_variation_step_qty';
	const VARIATION_DEFAULT_META = '_ceqg_variation_default_qty';
	const VARIATION_MESSAGE_META = '_ceqg_variation_custom_message';

	/**
	 * Resolve the active rule for a product or variation.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return array
	 */
	public function resolve_rule( $product_id, $variation_id = 0 ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		if ( $variation_id > 0 ) {
			$variation_rule = $this->get_variation_rule( $variation_id );

			if ( ! empty( $variation_rule ) ) {
				return $variation_rule;
			}

			if ( 0 === $product_id ) {
				$product_id = $this->get_parent_product_id( $variation_id );
			}
		}

		if ( $product_id > 0 ) {
			$product_rule = $this->get_product_rule( $product_id );

			if ( ! empty( $product_rule ) ) {
				return $product_rule;
			}
		}

		return $this->get_global_rule();
	}

	/**
	 * Get the normalized global rule.
	 *
	 * @return array
	 */
	public function get_global_rule() {
		$settings = CEQG_Settings::get_settings();

		return $this->build_rule(
			'global',
			__( 'Global Rule', 'coderembassy-quantity-guard' ),
			array(
				'min'            => $settings['global_min'],
				'max'            => $settings['global_max'],
				'step'           => $settings['global_step'],
				'default'        => $settings['global_default'],
				'custom_message' => '',
				'messages'       => $this->get_global_messages( $settings ),
			)
		);
	}

	/**
	 * Get a normalized product-level rule when enabled.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function get_product_rule( $product_id ) {
		$product = $this->get_product( $product_id );

		if ( ! $product ) {
			return array();
		}

		if ( 'yes' !== $this->sanitize_checkbox_value( $product->get_meta( self::PRODUCT_ENABLE_META, true ) ) ) {
			return array();
		}

		return $this->build_rule(
			'product',
			__( 'Product Rule', 'coderembassy-quantity-guard' ),
			array(
				'min'            => $product->get_meta( self::PRODUCT_MIN_META, true ),
				'max'            => $product->get_meta( self::PRODUCT_MAX_META, true ),
				'step'           => $product->get_meta( self::PRODUCT_STEP_META, true ),
				'default'        => $product->get_meta( self::PRODUCT_DEFAULT_META, true ),
				'custom_message' => $product->get_meta( self::PRODUCT_MESSAGE_META, true ),
				'messages'       => $this->get_global_messages(),
			)
		);
	}

	/**
	 * Get a normalized variation-level rule when enabled.
	 *
	 * @param int $variation_id Variation ID.
	 * @return array
	 */
	public function get_variation_rule( $variation_id ) {
		$variation = $this->get_product( $variation_id );

		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			return array();
		}

		if ( 'yes' !== $this->sanitize_checkbox_value( $variation->get_meta( self::VARIATION_ENABLE_META, true ) ) ) {
			return array();
		}

		return $this->build_rule(
			'variation',
			__( 'Variation Rule', 'coderembassy-quantity-guard' ),
			array(
				'min'            => $variation->get_meta( self::VARIATION_MIN_META, true ),
				'max'            => $variation->get_meta( self::VARIATION_MAX_META, true ),
				'step'           => $variation->get_meta( self::VARIATION_STEP_META, true ),
				'default'        => $variation->get_meta( self::VARIATION_DEFAULT_META, true ),
				'custom_message' => $variation->get_meta( self::VARIATION_MESSAGE_META, true ),
				'messages'       => $this->get_global_messages(),
			)
		);
	}

	/**
	 * Build a normalized rule array.
	 *
	 * Enabled product and variation rules are whole-rule overrides. Invalid
	 * fields are normalized locally instead of borrowing from lower priorities.
	 *
	 * @param string $source       Rule source.
	 * @param string $source_label Human-readable rule source.
	 * @param array  $raw_rule     Raw rule values.
	 * @return array
	 */
	private function build_rule( $source, $source_label, $raw_rule ) {
		$min     = $this->sanitize_positive_int( $raw_rule['min'], 1 );
		$max     = $this->sanitize_optional_positive_int( $raw_rule['max'] );
		$step    = $this->sanitize_positive_int( $raw_rule['step'], 1 );
		$default = $this->sanitize_positive_int( $raw_rule['default'], $min );

		if ( '' !== $max && $max < $min ) {
			$max = $min;
		}

		$default = $this->normalize_quantity_to_rule( $default, $min, $max, $step );

		return array(
			'source'         => sanitize_key( $source ),
			'source_label'   => sanitize_text_field( $source_label ),
			'min'            => $min,
			'max'            => $max,
			'step'           => $step,
			'default'        => $default,
			'custom_message' => $this->sanitize_message( $raw_rule['custom_message'] ),
			'messages'       => $this->sanitize_messages( $raw_rule['messages'] ),
		);
	}

	/**
	 * Get global message templates.
	 *
	 * @param array|null $settings Optional settings array.
	 * @return array
	 */
	private function get_global_messages( $settings = null ) {
		if ( null === $settings ) {
			$settings = CEQG_Settings::get_settings();
		}

		return array(
			'min'  => isset( $settings['message_min'] ) ? $settings['message_min'] : '',
			'max'  => isset( $settings['message_max'] ) ? $settings['message_max'] : '',
			'step' => isset( $settings['message_step'] ) ? $settings['message_step'] : '',
		);
	}

	/**
	 * Safely retrieve a WooCommerce product object.
	 *
	 * @param int $product_id Product ID.
	 * @return WC_Product|false
	 */
	private function get_product( $product_id ) {
		$product_id = absint( $product_id );

		if ( 0 === $product_id || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		return wc_get_product( $product_id );
	}

	/**
	 * Get a variation parent product ID.
	 *
	 * @param int $variation_id Variation ID.
	 * @return int
	 */
	private function get_parent_product_id( $variation_id ) {
		$variation = $this->get_product( $variation_id );

		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			return 0;
		}

		return absint( $variation->get_parent_id() );
	}

	/**
	 * Sanitize a required positive integer.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $fallback Fallback value.
	 * @return int
	 */
	private function sanitize_positive_int( $value, $fallback ) {
		if ( ! is_scalar( $value ) ) {
			$value = $fallback;
		}

		return max( 1, absint( $value ) );
	}

	/**
	 * Sanitize an optional positive integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int|string
	 */
	private function sanitize_optional_positive_int( $value ) {
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			return '';
		}

		return max( 1, absint( $value ) );
	}

	/**
	 * Sanitize yes/no stored values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_checkbox_value( $value ) {
		if ( ! is_scalar( $value ) ) {
			return 'no';
		}

		return 'yes' === sanitize_text_field( (string) $value ) ? 'yes' : 'no';
	}

	/**
	 * Sanitize a custom message.
	 *
	 * @param mixed $message Raw message.
	 * @return string
	 */
	private function sanitize_message( $message ) {
		if ( ! is_scalar( $message ) ) {
			return '';
		}

		return sanitize_textarea_field( (string) $message );
	}

	/**
	 * Sanitize message templates.
	 *
	 * @param mixed $messages Raw message templates.
	 * @return array
	 */
	private function sanitize_messages( $messages ) {
		$defaults = CEQG_Settings::get_defaults();

		if ( ! is_array( $messages ) ) {
			$messages = array();
		}

		return array(
			'min'  => $this->sanitize_message(
				isset( $messages['min'] ) ? $messages['min'] : $defaults['message_min']
			),
			'max'  => $this->sanitize_message(
				isset( $messages['max'] ) ? $messages['max'] : $defaults['message_max']
			),
			'step' => $this->sanitize_message(
				isset( $messages['step'] ) ? $messages['step'] : $defaults['message_step']
			),
		);
	}

	/**
	 * Normalize a quantity so it respects min/max/step.
	 *
	 * @param int        $quantity Quantity to normalize.
	 * @param int        $min      Minimum quantity.
	 * @param int|string $max      Maximum quantity, or empty string.
	 * @param int        $step     Step quantity.
	 * @return int
	 */
	private function normalize_quantity_to_rule( $quantity, $min, $max, $step ) {
		$quantity = max( absint( $quantity ), absint( $min ) );

		if ( '' !== $max ) {
			$quantity = min( $quantity, absint( $max ) );
		}

		if ( $step > 1 && 0 !== ( $quantity - $min ) % $step ) {
			$quantity = $min;
		}

		return $quantity;
	}
}
