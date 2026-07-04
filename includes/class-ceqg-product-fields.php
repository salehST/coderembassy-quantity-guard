<?php
/**
 * Product-level rule fields.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds product-level Quantity Guard controls to WooCommerce products.
 */
class CEQG_Product_Fields {
	const NONCE_ACTION = 'ceqg_save_product_fields';
	const NONCE_NAME   = 'ceqg_product_fields_nonce';
	const WARNING_KEY  = 'ceqg_product_field_warning_';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_warnings' ) );
	}

	/**
	 * Add the Quantity Guard product data tab.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['ceqg_quantity_guard'] = array(
			'label'    => __( 'Quantity Guard', 'coderembassy-quantity-guard' ),
			'target'   => 'ceqg_quantity_guard_product_data',
			'class'    => array(),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Render the product data panel.
	 *
	 * @return void
	 */
	public function render_product_data_panel() {
		global $post;

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return;
		}

		?>
		<div id="ceqg_quantity_guard_product_data" class="panel woocommerce_options_panel hidden">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'ceqg_enable_product_rule',
						'name'        => 'ceqg_enable_product_rule',
						'label'       => __( 'Enable product quantity rule', 'coderembassy-quantity-guard' ),
						'description' => __( 'Use this product rule instead of the global rule.', 'coderembassy-quantity-guard' ),
						'value'       => $this->get_product_meta( $product, CEQG_Rule_Engine::PRODUCT_ENABLE_META, 'no' ),
					)
				);

				$this->render_number_field(
					'ceqg_min_qty',
					__( 'Minimum quantity', 'coderembassy-quantity-guard' ),
					$this->get_product_meta( $product, CEQG_Rule_Engine::PRODUCT_MIN_META, '' ),
					__( 'Positive integer. Defaults to 1 when empty or invalid.', 'coderembassy-quantity-guard' )
				);

				$this->render_number_field(
					'ceqg_max_qty',
					__( 'Maximum quantity', 'coderembassy-quantity-guard' ),
					$this->get_product_meta( $product, CEQG_Rule_Engine::PRODUCT_MAX_META, '' ),
					__( 'Leave empty for no plugin-defined maximum.', 'coderembassy-quantity-guard' ),
					true
				);

				$this->render_number_field(
					'ceqg_step_qty',
					__( 'Step quantity', 'coderembassy-quantity-guard' ),
					$this->get_product_meta( $product, CEQG_Rule_Engine::PRODUCT_STEP_META, '' ),
					__( 'Positive integer. Defaults to 1 when empty or invalid.', 'coderembassy-quantity-guard' )
				);

				$this->render_number_field(
					'ceqg_default_qty',
					__( 'Default quantity', 'coderembassy-quantity-guard' ),
					$this->get_product_meta( $product, CEQG_Rule_Engine::PRODUCT_DEFAULT_META, '' ),
					__( 'Prefills the product-page quantity field.', 'coderembassy-quantity-guard' )
				);
				?>
				<p class="form-field ceqg_custom_message_field">
					<label for="ceqg_custom_message"><?php echo esc_html__( 'Custom message', 'coderembassy-quantity-guard' ); ?></label>
					<textarea
						id="ceqg_custom_message"
						name="ceqg_custom_message"
						rows="3"
						class="short"
					><?php echo esc_textarea( $this->get_product_meta( $product, CEQG_Rule_Engine::PRODUCT_MESSAGE_META, '' ) ); ?></textarea>
					<span class="description">
						<?php echo esc_html__( 'Optional. Used for this product rule when validation fails.', 'coderembassy-quantity-guard' ); ?>
					</span>
				</p>
			</div>

			<div class="options_group ceqg-rule-preview">
				<?php
				$debug = new CEQG_Debug();
				$debug->render_product_rule_preview( $product );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product-level fields.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function save_product_fields( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) && is_scalar( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return;
		}

		$enabled        = $this->get_posted_checkbox( 'ceqg_enable_product_rule' );
		$min            = $this->get_posted_positive_int( 'ceqg_min_qty', 1 );
		$max            = $this->get_posted_optional_positive_int( 'ceqg_max_qty' );
		$step           = $this->get_posted_positive_int( 'ceqg_step_qty', 1 );
		$default        = $this->get_posted_positive_int( 'ceqg_default_qty', $min );
		$custom_message = $this->get_posted_message( 'ceqg_custom_message' );

		if ( '' !== $max && $max < $min ) {
			$max = $min;
		}

		$default = $this->normalize_quantity_to_rule( $default, $min, $max, $step );

		$product->update_meta_data( CEQG_Rule_Engine::PRODUCT_ENABLE_META, $enabled );
		$product->update_meta_data( CEQG_Rule_Engine::PRODUCT_MIN_META, $min );
		$product->update_meta_data( CEQG_Rule_Engine::PRODUCT_MAX_META, $max );
		$product->update_meta_data( CEQG_Rule_Engine::PRODUCT_STEP_META, $step );
		$product->update_meta_data( CEQG_Rule_Engine::PRODUCT_DEFAULT_META, $default );
		$product->update_meta_data( CEQG_Rule_Engine::PRODUCT_MESSAGE_META, $custom_message );
		$product->save();

		if ( 'yes' === $enabled && 0 !== $min % $step ) {
			$this->store_admin_warning(
				__(
					'Quantity Guard tip: for smoother Cart and Checkout block controls, use a product minimum that is a multiple of the product step.',
					'coderembassy-quantity-guard'
				)
			);
		}
	}

	/**
	 * Render stored warning notices.
	 *
	 * @return void
	 */
	public function render_admin_warnings() {
		$warning = get_transient( $this->get_warning_transient_key() );

		if ( ! $warning ) {
			return;
		}

		delete_transient( $this->get_warning_transient_key() );

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html( $warning )
		);
	}

	/**
	 * Render a WooCommerce-style number input field.
	 *
	 * @param string $id          Field ID.
	 * @param string $label       Field label.
	 * @param mixed  $value       Field value.
	 * @param string $description Field description.
	 * @param bool   $allow_empty Whether the field may be empty.
	 * @return void
	 */
	private function render_number_field( $id, $label, $value, $description, $allow_empty = false ) {
		woocommerce_wp_text_input(
			array(
				'id'                => $id,
				'name'              => $id,
				'label'             => $label,
				'value'             => $value,
				'type'              => 'number',
				'desc_tip'          => true,
				'description'       => $description,
				'custom_attributes' => array(
					'min'  => $allow_empty ? '0' : '1',
					'step' => '1',
				),
			)
		);
	}

	/**
	 * Read product meta through the WooCommerce CRUD object.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $key     Meta key.
	 * @param mixed      $default Default value.
	 * @return mixed
	 */
	private function get_product_meta( $product, $key, $default = '' ) {
		$value = $product->get_meta( $key, true );

		return '' === $value ? $default : $value;
	}

	/**
	 * Read a posted checkbox value.
	 *
	 * @param string $key Posted field key.
	 * @return string
	 */
	private function get_posted_checkbox( $key ) {
		$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] )
			? sanitize_text_field( wp_unslash( $_POST[ $key ] ) )
			: 'no';

		return 'yes' === $value ? 'yes' : 'no';
	}

	/**
	 * Read a posted positive integer.
	 *
	 * @param string $key      Posted field key.
	 * @param int    $fallback Fallback value.
	 * @return int
	 */
	private function get_posted_positive_int( $key, $fallback ) {
		$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $fallback;

		return max( 1, absint( $value ) );
	}

	/**
	 * Read a posted optional positive integer.
	 *
	 * @param string $key Posted field key.
	 * @return int|string
	 */
	private function get_posted_optional_positive_int( $key ) {
		$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';

		if ( '' === trim( (string) $value ) ) {
			return '';
		}

		return max( 1, absint( $value ) );
	}

	/**
	 * Read a posted custom message.
	 *
	 * @param string $key Posted field key.
	 * @return string
	 */
	private function get_posted_message( $key ) {
		$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';

		return sanitize_textarea_field( $value );
	}

	/**
	 * Normalize a default quantity so it respects min/max/step.
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

	/**
	 * Store a one-time admin warning.
	 *
	 * @param string $message Warning message.
	 * @return void
	 */
	private function store_admin_warning( $message ) {
		set_transient( $this->get_warning_transient_key(), sanitize_text_field( $message ), MINUTE_IN_SECONDS );
	}

	/**
	 * Build the current user's warning transient key.
	 *
	 * @return string
	 */
	private function get_warning_transient_key() {
		return self::WARNING_KEY . get_current_user_id();
	}
}
