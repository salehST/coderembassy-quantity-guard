<?php
/**
 * Customer-facing message builder.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds consistent Quantity Guard messages.
 */
class CEQG_Messages {
	const TYPE_MIN  = 'min';
	const TYPE_MAX  = 'max';
	const TYPE_STEP = 'step';

	/**
	 * Get a validation message for a rule and error type.
	 *
	 * @param string $type         Error type: min, max, or step.
	 * @param array  $rule         Resolved rule.
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @return string
	 */
	public function get_message( $type, $rule, $product_id = 0, $variation_id = 0 ) {
		$type     = $this->sanitize_type( $type );
		$template = $this->get_template( $type, $rule );

		return $this->replace_placeholders( $template, $rule, $product_id, $variation_id );
	}

	/**
	 * Get an escaped validation message.
	 *
	 * @param string $type         Error type: min, max, or step.
	 * @param array  $rule         Resolved rule.
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @return string
	 */
	public function get_escaped_message( $type, $rule, $product_id = 0, $variation_id = 0 ) {
		return esc_html( $this->get_message( $type, $rule, $product_id, $variation_id ) );
	}

	/**
	 * Get a lightweight rule summary for product-page guidance.
	 *
	 * @param array $rule Resolved rule.
	 * @return string
	 */
	public function get_rule_summary( $rule ) {
		$template = __( 'Quantity: minimum {min_qty}, maximum {max_qty}, step {step_qty}.', 'coderembassy-quantity-guard' );

		return $this->replace_placeholders( $template, $rule );
	}

	/**
	 * Get an escaped rule summary.
	 *
	 * @param array $rule Resolved rule.
	 * @return string
	 */
	public function get_escaped_rule_summary( $rule ) {
		return esc_html( $this->get_rule_summary( $rule ) );
	}

	/**
	 * Pick the correct message template.
	 *
	 * @param string $type Error type.
	 * @param array  $rule Resolved rule.
	 * @return string
	 */
	private function get_template( $type, $rule ) {
		if ( ! empty( $rule['custom_message'] ) && is_scalar( $rule['custom_message'] ) ) {
			return sanitize_textarea_field( (string) $rule['custom_message'] );
		}

		if ( isset( $rule['messages'][ $type ] ) && is_scalar( $rule['messages'][ $type ] ) && '' !== $rule['messages'][ $type ] ) {
			return sanitize_textarea_field( (string) $rule['messages'][ $type ] );
		}

		$defaults = CEQG_Settings::get_defaults();

		if ( self::TYPE_MAX === $type ) {
			return $defaults['message_max'];
		}

		if ( self::TYPE_STEP === $type ) {
			return $defaults['message_step'];
		}

		return $defaults['message_min'];
	}

	/**
	 * Replace supported placeholders.
	 *
	 * @param string $template     Message template.
	 * @param array  $rule         Resolved rule.
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @return string
	 */
	private function replace_placeholders( $template, $rule, $product_id = 0, $variation_id = 0 ) {
		$template = sanitize_textarea_field( $template );

		$message = strtr(
			$template,
			array(
				'{product_name}' => $this->get_product_name( $product_id, $variation_id ),
				'{min_qty}'      => $this->get_rule_value( $rule, 'min', 1 ),
				'{max_qty}'      => $this->format_max_quantity( $rule ),
				'{step_qty}'     => $this->get_rule_value( $rule, 'step', 1 ),
				'{default_qty}'  => $this->get_rule_value( $rule, 'default', 1 ),
				'{rule_source}'  => $this->get_rule_value( $rule, 'source_label', __( 'Global Rule', 'coderembassy-quantity-guard' ) ),
			)
		);

		return sanitize_textarea_field( $message );
	}

	/**
	 * Sanitize and constrain message type.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	private function sanitize_type( $type ) {
		$type = sanitize_key( $type );

		if ( in_array( $type, array( self::TYPE_MIN, self::TYPE_MAX, self::TYPE_STEP ), true ) ) {
			return $type;
		}

		return self::TYPE_MIN;
	}

	/**
	 * Get a safe value from a rule array.
	 *
	 * @param array  $rule     Resolved rule.
	 * @param string $key      Rule key.
	 * @param mixed  $fallback Fallback value.
	 * @return string
	 */
	private function get_rule_value( $rule, $key, $fallback ) {
		if ( ! isset( $rule[ $key ] ) || ! is_scalar( $rule[ $key ] ) ) {
			return (string) $fallback;
		}

		return sanitize_text_field( (string) $rule[ $key ] );
	}

	/**
	 * Format the max quantity placeholder.
	 *
	 * @param array $rule Resolved rule.
	 * @return string
	 */
	private function format_max_quantity( $rule ) {
		if ( ! isset( $rule['max'] ) || '' === $rule['max'] ) {
			return __( 'no maximum', 'coderembassy-quantity-guard' );
		}

		return sanitize_text_field( (string) $rule['max'] );
	}

	/**
	 * Get the product or variation name for placeholders.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return string
	 */
	private function get_product_name( $product_id, $variation_id = 0 ) {
		$lookup_id = $variation_id > 0 ? absint( $variation_id ) : absint( $product_id );

		if ( $lookup_id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $lookup_id );

			if ( $product ) {
				return sanitize_text_field( $product->get_name() );
			}
		}

		return __( 'This product', 'coderembassy-quantity-guard' );
	}
}
