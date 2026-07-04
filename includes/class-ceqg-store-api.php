<?php
/**
 * WooCommerce Store API validation.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces quantity rules in WooCommerce Store API and block cart flows.
 */
class CEQG_Store_API {
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
	 * Classic validation helper.
	 *
	 * @var CEQG_Validation
	 */
	private $validation;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rule_engine = new CEQG_Rule_Engine();
		$this->messages    = new CEQG_Messages();
		$this->validation  = new CEQG_Validation();
	}

	/**
	 * Register Store API hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( $this, 'filter_quantity_minimum' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( $this, 'filter_quantity_maximum' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_multiple_of', array( $this, 'filter_quantity_multiple_of' ), 10, 3 );
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_action( 'woocommerce_store_api_cart_errors', array( $this, 'add_cart_errors' ), 10, 2 );
	}

	/**
	 * Filter Store API minimum quantity.
	 *
	 * @param int        $minimum  Current minimum.
	 * @param WC_Product $product  Product object.
	 * @param array|null $cart_item Optional cart item.
	 * @return int
	 */
	public function filter_quantity_minimum( $minimum, $product = null, $cart_item = null ) {
		if ( ! $this->is_enabled() || ! $product || $this->is_sold_individually( $product ) ) {
			return $minimum;
		}

		$rule = $this->get_rule_for_product( $product, $cart_item );

		return max( absint( $minimum ), absint( $rule['min'] ) );
	}

	/**
	 * Filter Store API maximum quantity without raising WooCommerce stock limits.
	 *
	 * @param int|string $maximum   Current maximum.
	 * @param WC_Product $product   Product object.
	 * @param array|null $cart_item Optional cart item.
	 * @return int|string
	 */
	public function filter_quantity_maximum( $maximum, $product = null, $cart_item = null ) {
		if ( ! $this->is_enabled() || ! $product || $this->is_sold_individually( $product ) ) {
			return $maximum;
		}

		$rule = $this->get_rule_for_product( $product, $cart_item );

		if ( '' === $rule['max'] ) {
			return $maximum;
		}

		if ( is_numeric( $maximum ) && (int) $maximum > 0 ) {
			return min( (int) $maximum, absint( $rule['max'] ) );
		}

		return absint( $rule['max'] );
	}

	/**
	 * Filter Store API quantity multiple.
	 *
	 * Store API multiple_of cannot represent offset steps, so the server-side
	 * validation remains the source of truth when min is not a multiple of step.
	 *
	 * @param int        $multiple_of Current multiple.
	 * @param WC_Product $product     Product object.
	 * @param array|null $cart_item   Optional cart item.
	 * @return int
	 */
	public function filter_quantity_multiple_of( $multiple_of, $product = null, $cart_item = null ) {
		if ( ! $this->is_enabled() || ! $product || $this->is_sold_individually( $product ) ) {
			return $multiple_of;
		}

		$rule = $this->get_rule_for_product( $product, $cart_item );

		return max( 1, absint( $rule['step'] ) );
	}

	/**
	 * Validate Store API add-to-cart requests.
	 *
	 * @param mixed $arg1 Hook argument.
	 * @param mixed $arg2 Hook argument.
	 * @param mixed $arg3 Hook argument.
	 * @return void
	 */
	public function validate_add_to_cart( $arg1 = null, $arg2 = null, $arg3 = null ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$context = $this->parse_add_to_cart_context( array( $arg1, $arg2, $arg3 ) );

		if ( empty( $context['product'] ) || $this->is_sold_individually( $context['product'] ) ) {
			return;
		}

		$rule       = $this->rule_engine->resolve_rule( $context['product_id'], $context['variation_id'] );
		$error_type = $this->validation->validate_quantity( $context['quantity'], $rule );

		if ( '' === $error_type ) {
			return;
		}

		$this->throw_route_exception(
			$this->get_error_code( $error_type ),
			$this->messages->get_message( $error_type, $rule, $context['product_id'], $context['variation_id'] )
		);
	}

	/**
	 * Add Store API cart errors before block checkout.
	 *
	 * @param WP_Error $errors Store API cart errors.
	 * @param WC_Cart  $cart   WooCommerce cart.
	 * @return void
	 */
	public function add_cart_errors( $errors, $cart = null ) {
		if ( ! $this->is_enabled() || ! ( $errors instanceof WP_Error ) ) {
			return;
		}

		if ( ! $cart && function_exists( 'WC' ) ) {
			$cart = WC()->cart;
		}

		if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( $this->cart_item_is_sold_individually( $cart_item ) ) {
				continue;
			}

			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;
			$quantity     = isset( $cart_item['quantity'] ) ? $this->validation->sanitize_quantity( $cart_item['quantity'] ) : 0;
			$rule         = $this->rule_engine->resolve_rule( $product_id, $variation_id );
			$error_type   = $this->validation->validate_quantity( $quantity, $rule );

			if ( '' === $error_type ) {
				continue;
			}

			$errors->add(
				$this->get_error_code( $error_type ),
				$this->messages->get_message( $error_type, $rule, $product_id, $variation_id )
			);
		}
	}

	/**
	 * Parse product and quantity data from Store API add-to-cart hook args.
	 *
	 * @param array $args Hook arguments.
	 * @return array
	 */
	private function parse_add_to_cart_context( $args ) {
		$product = null;
		$request = null;

		foreach ( $args as $arg ) {
			if ( $arg && is_object( $arg ) && method_exists( $arg, 'get_id' ) && method_exists( $arg, 'is_type' ) ) {
				$product = $arg;
				continue;
			}

			if ( $arg && is_object( $arg ) && method_exists( $arg, 'get_param' ) ) {
				$request = $arg;
			}
		}

		$quantity = 1;
		$item_id  = 0;

		if ( $request ) {
			$raw_quantity = $request->get_param( 'quantity' );
			$quantity     = null === $raw_quantity || '' === $raw_quantity ? 1 : $this->validation->sanitize_quantity( $raw_quantity );
			$item_id      = absint( $request->get_param( 'id' ) );
		}

		if ( ! $product && $item_id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $item_id );
		}

		$product_id   = 0;
		$variation_id = 0;

		if ( $product ) {
			if ( $product->is_type( 'variation' ) ) {
				$variation_id = absint( $product->get_id() );
				$product_id   = absint( $product->get_parent_id() );
			} else {
				$product_id = absint( $product->get_id() );
			}
		}

		return array(
			'product'      => $product,
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => max( 0, $quantity ),
		);
	}

	/**
	 * Resolve a rule for a product and optional cart item.
	 *
	 * @param WC_Product $product   Product object.
	 * @param array|null $cart_item Optional cart item.
	 * @return array
	 */
	private function get_rule_for_product( $product, $cart_item = null ) {
		if ( is_array( $cart_item ) ) {
			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			if ( $product_id > 0 || $variation_id > 0 ) {
				return $this->rule_engine->resolve_rule( $product_id, $variation_id );
			}
		}

		if ( $product->is_type( 'variation' ) ) {
			return $this->rule_engine->resolve_rule( $product->get_parent_id(), $product->get_id() );
		}

		return $this->rule_engine->resolve_rule( $product->get_id() );
	}

	/**
	 * Throw a Store API route exception when the class is available.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @throws Automattic\WooCommerce\StoreApi\Exceptions\RouteException Store API route exception.
	 * @return void
	 */
	private function throw_route_exception( $code, $message ) {
		if ( class_exists( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException::class ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				$code,
				$message,
				400
			);
		}
	}

	/**
	 * Get a Store API error code.
	 *
	 * @param string $error_type Error type.
	 * @return string
	 */
	private function get_error_code( $error_type ) {
		if ( CEQG_Messages::TYPE_MAX === $error_type ) {
			return 'ceqg_max_qty';
		}

		if ( CEQG_Messages::TYPE_STEP === $error_type ) {
			return 'ceqg_step_qty';
		}

		return 'ceqg_min_qty';
	}

	/**
	 * Determine whether a product is sold individually.
	 *
	 * @param WC_Product $product Product object.
	 * @return bool
	 */
	private function is_sold_individually( $product ) {
		return is_object( $product ) && method_exists( $product, 'is_sold_individually' ) && $product->is_sold_individually();
	}

	/**
	 * Determine whether a cart item is sold individually.
	 *
	 * @param array $cart_item Cart item data.
	 * @return bool
	 */
	private function cart_item_is_sold_individually( $cart_item ) {
		if ( isset( $cart_item['data'] ) && $this->is_sold_individually( $cart_item['data'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether Store API validation is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$settings = CEQG_Settings::get_settings();

		return 'yes' === $settings['enabled'];
	}
}
