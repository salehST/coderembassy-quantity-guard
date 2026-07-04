<?php
/**
 * Classic WooCommerce validation.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces quantity rules in classic WooCommerce cart flows.
 */
class CEQG_Validation {
	/**
	 * Rule engine.
	 *
	 * @var CEQG_Rule_Engine
	 */
	private $rule_engine;

	/**
	 * Message builder.
	 *
	 * @var CEQG_Messages
	 */
	private $messages;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rule_engine = new CEQG_Rule_Engine();
		$this->messages    = new CEQG_Messages();
	}

	/**
	 * Register validation hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 6 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_cart_update' ), 10, 4 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_items' ) );
	}

	/**
	 * Validate classic and AJAX add-to-cart requests.
	 *
	 * @param bool  $passed         Existing validation state.
	 * @param int   $product_id     Product ID.
	 * @param int   $quantity       Requested quantity.
	 * @param int   $variation_id   Variation ID.
	 * @param array $variations     Variation attributes.
	 * @param array $cart_item_data Cart item data.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		unset( $variations, $cart_item_data );

		if ( ! $passed || ! $this->is_enabled() ) {
			return $passed;
		}

		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$quantity     = $this->sanitize_quantity( $quantity );

		if ( $this->is_sold_individually( $product_id, $variation_id ) ) {
			return $passed;
		}

		$rule       = $this->rule_engine->resolve_rule( $product_id, $variation_id );
		$error_type = $this->validate_quantity( $quantity, $rule );

		if ( '' === $error_type ) {
			return $passed;
		}

		wc_add_notice(
			$this->messages->get_message( $error_type, $rule, $product_id, $variation_id ),
			'error'
		);

		return false;
	}

	/**
	 * Validate classic cart quantity updates.
	 *
	 * @param bool   $passed        Existing validation state.
	 * @param string $cart_item_key Cart item key.
	 * @param array  $values        Cart item values.
	 * @param int    $quantity      Requested quantity.
	 * @return bool
	 */
	public function validate_cart_update( $passed, $cart_item_key, $values, $quantity ) {
		unset( $cart_item_key );

		if ( ! $passed || ! $this->is_enabled() ) {
			return $passed;
		}

		$product_id   = isset( $values['product_id'] ) ? absint( $values['product_id'] ) : 0;
		$variation_id = isset( $values['variation_id'] ) ? absint( $values['variation_id'] ) : 0;
		$quantity     = $this->sanitize_quantity( $quantity );

		if ( $this->cart_item_is_sold_individually( $values ) ) {
			return $passed;
		}

		$rule       = $this->rule_engine->resolve_rule( $product_id, $variation_id );
		$error_type = $this->validate_quantity( $quantity, $rule );

		if ( '' === $error_type ) {
			return $passed;
		}

		wc_add_notice(
			$this->messages->get_message( $error_type, $rule, $product_id, $variation_id ),
			'error'
		);

		return false;
	}

	/**
	 * Validate existing cart contents before cart/checkout completion.
	 *
	 * @return void
	 */
	public function validate_cart_items() {
		if ( ! $this->is_enabled() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( $this->cart_item_is_sold_individually( $cart_item ) ) {
				continue;
			}

			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;
			$quantity     = isset( $cart_item['quantity'] ) ? $this->sanitize_quantity( $cart_item['quantity'] ) : 0;
			$rule         = $this->rule_engine->resolve_rule( $product_id, $variation_id );
			$error_type   = $this->validate_quantity( $quantity, $rule );

			if ( '' === $error_type ) {
				continue;
			}

			wc_add_notice(
				$this->messages->get_message( $error_type, $rule, $product_id, $variation_id ),
				'error'
			);
		}
	}

	/**
	 * Validate a quantity against a resolved rule.
	 *
	 * @param int   $quantity Requested quantity.
	 * @param array $rule     Resolved rule.
	 * @return string Error type, or empty string when valid.
	 */
	public function validate_quantity( $quantity, $rule ) {
		$quantity = $this->sanitize_quantity( $quantity );
		$min      = isset( $rule['min'] ) ? absint( $rule['min'] ) : 1;
		$max      = isset( $rule['max'] ) && '' !== $rule['max'] ? absint( $rule['max'] ) : '';
		$step     = isset( $rule['step'] ) ? absint( $rule['step'] ) : 1;

		$min  = max( 1, $min );
		$step = max( 1, $step );

		if ( $quantity < $min ) {
			return CEQG_Messages::TYPE_MIN;
		}

		if ( '' !== $max && $quantity > $max ) {
			return CEQG_Messages::TYPE_MAX;
		}

		if ( $step > 1 && 0 !== ( $quantity - $min ) % $step ) {
			return CEQG_Messages::TYPE_STEP;
		}

		return '';
	}

	/**
	 * Sanitize a submitted quantity.
	 *
	 * @param mixed $quantity Raw quantity.
	 * @return int
	 */
	public function sanitize_quantity( $quantity ) {
		if ( ! is_scalar( $quantity ) ) {
			return 0;
		}

		return max( 0, absint( $quantity ) );
	}

	/**
	 * Determine whether a product is sold individually.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return bool
	 */
	private function is_sold_individually( $product_id, $variation_id = 0 ) {
		$lookup_id = $variation_id > 0 ? absint( $variation_id ) : absint( $product_id );

		if ( 0 === $lookup_id || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $lookup_id );

		return $product && $product->is_sold_individually();
	}

	/**
	 * Determine whether a cart item product is sold individually.
	 *
	 * @param array $cart_item Cart item data.
	 * @return bool
	 */
	private function cart_item_is_sold_individually( $cart_item ) {
		if ( isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) && method_exists( $cart_item['data'], 'is_sold_individually' ) ) {
			return $cart_item['data']->is_sold_individually();
		}

		$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

		return $this->is_sold_individually( $product_id, $variation_id );
	}

	/**
	 * Check whether plugin validation is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$settings = CEQG_Settings::get_settings();

		return 'yes' === $settings['enabled'];
	}
}
