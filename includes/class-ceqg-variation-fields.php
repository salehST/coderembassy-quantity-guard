<?php
/**
 * Variation-level rule fields.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds variation-level Quantity Guard controls to WooCommerce variations.
 */
class CEQG_Variation_Fields {
	const NONCE_ACTION = 'ceqg_save_variation_fields';
	const NONCE_NAME   = 'ceqg_variation_fields_nonce';
	const WARNING_KEY  = 'ceqg_variation_field_warning_';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
		add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_rule_data' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'render_admin_warnings' ) );
	}

	/**
	 * Render variation rule fields.
	 *
	 * @param int     $loop           Variation loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post.
	 * @return void
	 */
	public function render_variation_fields( $loop, $variation_data, $variation ) {
		if ( ! $variation instanceof WP_Post ) {
			return;
		}

		$variation_product = wc_get_product( $variation->ID );

		if ( ! $variation_product ) {
			return;
		}

		$field_prefix = 'ceqg_variation_';

		?>
		<div class="form-row form-row-full ceqg-variation-fields">
			<input type="hidden" name="<?php echo esc_attr( self::NONCE_NAME ); ?>" value="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>" />
			<p>
				<label>
					<input
						type="checkbox"
						name="ceqg_enable_variation_rule[<?php echo esc_attr( $loop ); ?>]"
						value="yes"
						<?php checked( 'yes', $this->get_variation_meta( $variation_product, CEQG_Rule_Engine::VARIATION_ENABLE_META, 'no' ) ); ?>
					/>
					<?php echo esc_html__( 'Enable variation quantity rule', 'coderembassy-quantity-guard' ); ?>
				</label>
			</p>

			<?php
			$this->render_number_field(
				$field_prefix . 'min_qty',
				$loop,
				__( 'Minimum quantity', 'coderembassy-quantity-guard' ),
				$this->get_variation_meta( $variation_product, CEQG_Rule_Engine::VARIATION_MIN_META, '' ),
				false
			);
			$this->render_number_field(
				$field_prefix . 'max_qty',
				$loop,
				__( 'Maximum quantity', 'coderembassy-quantity-guard' ),
				$this->get_variation_meta( $variation_product, CEQG_Rule_Engine::VARIATION_MAX_META, '' ),
				true
			);
			$this->render_number_field(
				$field_prefix . 'step_qty',
				$loop,
				__( 'Step quantity', 'coderembassy-quantity-guard' ),
				$this->get_variation_meta( $variation_product, CEQG_Rule_Engine::VARIATION_STEP_META, '' ),
				false
			);
			$this->render_number_field(
				$field_prefix . 'default_qty',
				$loop,
				__( 'Default quantity', 'coderembassy-quantity-guard' ),
				$this->get_variation_meta( $variation_product, CEQG_Rule_Engine::VARIATION_DEFAULT_META, '' ),
				false
			);
			?>

			<p class="form-row form-row-full">
				<label for="<?php echo esc_attr( $field_prefix . 'custom_message_' . $loop ); ?>">
					<?php echo esc_html__( 'Custom message', 'coderembassy-quantity-guard' ); ?>
				</label>
				<textarea
					id="<?php echo esc_attr( $field_prefix . 'custom_message_' . $loop ); ?>"
					name="ceqg_variation_custom_message[<?php echo esc_attr( $loop ); ?>]"
					rows="2"
					class="short"
				><?php echo esc_textarea( $this->get_variation_meta( $variation_product, CEQG_Rule_Engine::VARIATION_MESSAGE_META, '' ) ); ?></textarea>
			</p>
		</div>
		<?php
	}

	/**
	 * Save variation-level fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop index.
	 * @return void
	 */
	public function save_variation_fields( $variation_id, $loop ) {
		$variation_id = absint( $variation_id );
		$loop         = absint( $loop );

		if ( ! current_user_can( 'edit_post', $variation_id ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) && is_scalar( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		$variation = wc_get_product( $variation_id );

		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			return;
		}

		$enabled        = $this->get_posted_checkbox( 'ceqg_enable_variation_rule', $loop );
		$min            = $this->get_posted_positive_int( 'ceqg_variation_min_qty', $loop, 1 );
		$max            = $this->get_posted_optional_positive_int( 'ceqg_variation_max_qty', $loop );
		$step           = $this->get_posted_positive_int( 'ceqg_variation_step_qty', $loop, 1 );
		$default        = $this->get_posted_positive_int( 'ceqg_variation_default_qty', $loop, $min );
		$custom_message = $this->get_posted_message( 'ceqg_variation_custom_message', $loop );

		if ( '' !== $max && $max < $min ) {
			$max = $min;
			$this->store_admin_warning(
				__(
					'Quantity Guard adjusted the variation maximum to match the variation minimum because maximum cannot be lower than minimum.',
					'coderembassy-quantity-guard'
				)
			);
		}

		$default = $this->normalize_quantity_to_rule( $default, $min, $max, $step );

		$variation->update_meta_data( CEQG_Rule_Engine::VARIATION_ENABLE_META, $enabled );
		$variation->update_meta_data( CEQG_Rule_Engine::VARIATION_MIN_META, $min );
		$variation->update_meta_data( CEQG_Rule_Engine::VARIATION_MAX_META, $max );
		$variation->update_meta_data( CEQG_Rule_Engine::VARIATION_STEP_META, $step );
		$variation->update_meta_data( CEQG_Rule_Engine::VARIATION_DEFAULT_META, $default );
		$variation->update_meta_data( CEQG_Rule_Engine::VARIATION_MESSAGE_META, $custom_message );
		$variation->save();

		if ( 'yes' === $enabled && 0 !== $min % $step ) {
			$this->store_admin_warning(
				__(
					'Quantity Guard tip: for smoother Cart and Checkout block controls, use a variation minimum that is a multiple of the variation step.',
					'coderembassy-quantity-guard'
				)
			);
		}
	}

	/**
	 * Add resolved rule data to WooCommerce variation JSON.
	 *
	 * @param array                $variation_data Variation data sent to frontend.
	 * @param WC_Product          $product        Parent product.
	 * @param WC_Product_Variation $variation      Variation product.
	 * @return array
	 */
	public function add_variation_rule_data( $variation_data, $product, $variation ) {
		if ( ! $product || ! $variation ) {
			return $variation_data;
		}

		$engine = new CEQG_Rule_Engine();
		$messages = new CEQG_Messages();
		$rule   = $engine->resolve_rule( $product->get_id(), $variation->get_id() );
		$max_qty = isset( $variation_data['max_qty'] ) ? $variation_data['max_qty'] : '';

		if ( '' !== $rule['max'] ) {
			$max_qty = is_numeric( $max_qty ) && (int) $max_qty > 0
				? min( (int) $max_qty, $rule['max'] )
				: $rule['max'];
		}

		$variation_data['min_qty'] = max(
			isset( $variation_data['min_qty'] ) ? absint( $variation_data['min_qty'] ) : 1,
			absint( $rule['min'] )
		);
		$variation_data['max_qty'] = $max_qty;
		$variation_data['step']    = absint( $rule['step'] );

		$variation_data['ceqg_rule'] = array(
			'source'       => $rule['source'],
			'source_label' => $rule['source_label'],
			'min'          => $rule['min'],
			'max'          => $rule['max'],
			'step'         => $rule['step'],
			'default'      => $rule['default'],
			'message'      => $messages->get_rule_summary( $rule ),
		);

		return $variation_data;
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
	 * Render a variation number input.
	 *
	 * @param string $name        Field name.
	 * @param int    $loop        Variation loop index.
	 * @param string $label       Field label.
	 * @param mixed  $value       Field value.
	 * @param bool   $allow_empty Whether empty is allowed.
	 * @return void
	 */
	private function render_number_field( $name, $loop, $label, $value, $allow_empty ) {
		$field_id = $name . '_' . $loop;

		?>
		<p class="form-row form-row-first">
			<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input
				type="number"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $loop ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				min="<?php echo $allow_empty ? '0' : '1'; ?>"
				step="1"
				class="short"
			/>
		</p>
		<?php
	}

	/**
	 * Read variation meta through the WooCommerce CRUD object.
	 *
	 * @param WC_Product_Variation $variation Variation product.
	 * @param string               $key       Meta key.
	 * @param mixed                $default   Default value.
	 * @return mixed
	 */
	private function get_variation_meta( $variation, $key, $default = '' ) {
		$value = $variation->get_meta( $key, true );

		return '' === $value ? $default : $value;
	}

	/**
	 * Read an indexed checkbox from submitted variation fields.
	 *
	 * @param string $key  Posted field key.
	 * @param int    $loop Variation loop index.
	 * @return string
	 */
	private function get_posted_checkbox( $key, $loop ) {
		$value = $this->get_posted_array_value( $key, $loop );

		return 'yes' === sanitize_text_field( $value ) ? 'yes' : 'no';
	}

	/**
	 * Read an indexed positive integer from submitted variation fields.
	 *
	 * @param string $key      Posted field key.
	 * @param int    $loop     Variation loop index.
	 * @param int    $fallback Fallback value.
	 * @return int
	 */
	private function get_posted_positive_int( $key, $loop, $fallback ) {
		$value = $this->get_posted_array_value( $key, $loop, $fallback );

		return max( 1, absint( $value ) );
	}

	/**
	 * Read an indexed optional positive integer from submitted variation fields.
	 *
	 * @param string $key  Posted field key.
	 * @param int    $loop Variation loop index.
	 * @return int|string
	 */
	private function get_posted_optional_positive_int( $key, $loop ) {
		$value = $this->get_posted_array_value( $key, $loop );

		if ( '' === trim( $value ) ) {
			return '';
		}

		return max( 1, absint( $value ) );
	}

	/**
	 * Read an indexed custom message from submitted variation fields.
	 *
	 * @param string $key  Posted field key.
	 * @param int    $loop Variation loop index.
	 * @return string
	 */
	private function get_posted_message( $key, $loop ) {
		return sanitize_textarea_field( $this->get_posted_array_value( $key, $loop ) );
	}

	/**
	 * Safely read an indexed value from submitted variation fields.
	 *
	 * @param string $key      Posted field key.
	 * @param int    $loop     Variation loop index.
	 * @param mixed  $fallback Fallback value.
	 * @return string
	 */
	private function get_posted_array_value( $key, $loop, $fallback = '' ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
			return (string) $fallback;
		}

		$values = wp_unslash( $_POST[ $key ] );

		if ( ! isset( $values[ $loop ] ) || ! is_scalar( $values[ $loop ] ) ) {
			return (string) $fallback;
		}

		return (string) $values[ $loop ];
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
		$key      = $this->get_warning_transient_key();
		$existing = get_transient( $key );
		$message  = sanitize_text_field( $message );

		if ( $existing ) {
			$message = sanitize_text_field( $existing ) . ' ' . $message;
		}

		set_transient( $key, $message, MINUTE_IN_SECONDS );
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
