<?php
/**
 * Frontend quantity behavior.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies quantity rules to the storefront.
 */
class CEQG_Frontend {
	/**
	 * Rule engine.
	 *
	 * @var CEQG_Rule_Engine
	 */
	private $rule_engine;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rule_engine = new CEQG_Rule_Engine();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'filter_quantity_input_args' ), 10, 2 );
		add_action( 'woocommerce_after_add_to_cart_quantity', array( $this, 'render_quantity_message' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'filter_loop_add_to_cart_args' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'filter_loop_add_to_cart_url' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'filter_loop_add_to_cart_text' ), 10, 2 );
	}

	/**
	 * Apply quantity rule attributes to WooCommerce quantity inputs.
	 *
	 * @param array      $args    Quantity input arguments.
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	public function filter_quantity_input_args( $args, $product ) {
		if ( ! $this->is_enabled() || ! $product || $product->is_sold_individually() ) {
			return $args;
		}

		$rule = $this->get_rule_for_product( $product );

		$current_min       = isset( $args['min_value'] ) ? absint( $args['min_value'] ) : 1;
		$args['min_value'] = max( $current_min, $rule['min'] );
		$args['step']      = $rule['step'];

		$args = $this->apply_max_value( $args, $rule );

		if ( $this->is_single_product_screen() && $this->can_prefill_default_quantity( $args, $rule ) ) {
			$args['input_value'] = $rule['default'];
		}

		return $args;
	}

	/**
	 * Render a customer-facing rule message near the quantity input.
	 *
	 * @return void
	 */
	public function render_quantity_message() {
		global $product;

		if ( ! $this->is_enabled() || ! $product || $product->is_sold_individually() ) {
			return;
		}

		$rule = $this->get_rule_for_product( $product );

		printf(
			'<p class="ceqg-rule-message" data-ceqg-rule-message>%s</p>',
			esc_html( $this->format_rule_message( $rule ) )
		);
	}

	/**
	 * Enqueue frontend assets where they are needed.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_enabled() || ! $this->is_single_product_screen() ) {
			return;
		}

		wp_enqueue_style(
			'ceqg-frontend',
			CEQG_PLUGIN_URL . 'public/css/frontend.css',
			array(),
			CEQG_VERSION
		);

		wp_enqueue_script(
			'ceqg-frontend',
			CEQG_PLUGIN_URL . 'public/js/frontend.js',
			array( 'jquery' ),
			CEQG_VERSION,
			true
		);

		wp_localize_script(
			'ceqg-frontend',
			'ceqgFrontend',
			array(
				'noneLabel'    => __( 'none', 'coderembassy-quantity-guard' ),
				'messageLabel' => __( 'Quantity: minimum {min}, maximum {max}, step {step}.', 'coderembassy-quantity-guard' ),
			)
		);
	}

	/**
	 * Remove AJAX behavior from archive buttons when quantity 1 would fail.
	 *
	 * @param array      $args    Loop button args.
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	public function filter_loop_add_to_cart_args( $args, $product ) {
		if ( ! $this->requires_product_page_quantity_selection( $product ) ) {
			return $args;
		}

		if ( isset( $args['class'] ) ) {
			$args['class'] = trim( str_replace( 'ajax_add_to_cart', '', $args['class'] ) );
		}

		return $args;
	}

	/**
	 * Route archive add-to-cart buttons to the product page when needed.
	 *
	 * @param string     $url     Add-to-cart URL.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public function filter_loop_add_to_cart_url( $url, $product ) {
		if ( ! $this->requires_product_page_quantity_selection( $product ) ) {
			return $url;
		}

		return get_permalink( $product->get_id() );
	}

	/**
	 * Adjust archive button text when quantity selection is needed.
	 *
	 * @param string     $text    Button text.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public function filter_loop_add_to_cart_text( $text, $product ) {
		if ( ! $this->requires_product_page_quantity_selection( $product ) ) {
			return $text;
		}

		return __( 'Select quantity', 'coderembassy-quantity-guard' );
	}

	/**
	 * Apply a rule maximum without raising WooCommerce stock-based maximums.
	 *
	 * @param array $args Quantity input args.
	 * @param array $rule Resolved rule.
	 * @return array
	 */
	private function apply_max_value( $args, $rule ) {
		if ( '' === $rule['max'] ) {
			return $args;
		}

		$current_max = isset( $args['max_value'] ) ? $args['max_value'] : '';

		if ( is_numeric( $current_max ) && (int) $current_max > 0 ) {
			$args['max_value'] = min( (int) $current_max, $rule['max'] );
			return $args;
		}

		$args['max_value'] = $rule['max'];

		return $args;
	}

	/**
	 * Determine if default quantity can safely be prefilled.
	 *
	 * @param array $args Quantity input args.
	 * @param array $rule Resolved rule.
	 * @return bool
	 */
	private function can_prefill_default_quantity( $args, $rule ) {
		$max = isset( $args['max_value'] ) ? $args['max_value'] : '';

		if ( is_numeric( $max ) && (int) $max > 0 && $rule['default'] > (int) $max ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine whether a loop product should link to the product page.
	 *
	 * @param WC_Product $product Product object.
	 * @return bool
	 */
	private function requires_product_page_quantity_selection( $product ) {
		if ( ! $this->is_enabled() || ! $product || ! $product->is_purchasable() || $product->is_sold_individually() ) {
			return false;
		}

		if ( ! $product->is_type( 'simple' ) ) {
			return false;
		}

		$rule = $this->get_rule_for_product( $product );

		return $rule['min'] > 1 || $rule['step'] > 1;
	}

	/**
	 * Resolve a rule for a product object, including variation objects.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function get_rule_for_product( $product ) {
		if ( $product->is_type( 'variation' ) ) {
			return $this->rule_engine->resolve_rule( $product->get_parent_id(), $product->get_id() );
		}

		return $this->rule_engine->resolve_rule( $product->get_id() );
	}

	/**
	 * Format a lightweight frontend rule message.
	 *
	 * @param array $rule Resolved rule.
	 * @return string
	 */
	private function format_rule_message( $rule ) {
		return strtr(
			__( 'Quantity: minimum {min}, maximum {max}, step {step}.', 'coderembassy-quantity-guard' ),
			array(
				'{min}'  => $rule['min'],
				'{max}'  => '' === $rule['max'] ? __( 'none', 'coderembassy-quantity-guard' ) : $rule['max'],
				'{step}' => $rule['step'],
			)
		);
	}

	/**
	 * Check whether plugin quantity behavior is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$settings = CEQG_Settings::get_settings();

		return 'yes' === $settings['enabled'];
	}

	/**
	 * Check if the current request is a single product screen.
	 *
	 * @return bool
	 */
	private function is_single_product_screen() {
		return function_exists( 'is_product' ) && is_product();
	}
}
