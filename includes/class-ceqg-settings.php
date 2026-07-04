<?php
/**
 * Global settings screen.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the WooCommerce admin settings page.
 */
class CEQG_Settings {
	const OPTION_NAME  = 'ceqg_settings';
	const MENU_SLUG    = 'ceqg-quantity-guard';
	const NONCE_ACTION = 'ceqg_save_settings';
	const NONCE_NAME   = 'ceqg_settings_nonce';

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'                  => 'yes',
			'global_min'               => 1,
			'global_max'               => '',
			'global_step'              => 1,
			'global_default'           => 1,
			'message_min'              => __( '{product_name} requires a minimum quantity of {min_qty}.', 'coderembassy-quantity-guard' ),
			'message_max'              => __( '{product_name} allows a maximum quantity of {max_qty}.', 'coderembassy-quantity-guard' ),
			'message_step'             => __( '{product_name} must be purchased in multiples of {step_qty}.', 'coderembassy-quantity-guard' ),
			'delete_data_on_uninstall' => 'no',
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::get_defaults() );
	}

	/**
	 * Register the WooCommerce submenu page.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->page_hook = add_submenu_page(
			'woocommerce',
			__( 'Quantity Guard', 'coderembassy-quantity-guard' ),
			__( 'Quantity Guard', 'coderembassy-quantity-guard' ),
			$this->get_capability(),
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue settings page assets only on the plugin settings screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'ceqg-admin',
			CEQG_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			CEQG_VERSION
		);

		wp_enqueue_script(
			'ceqg-admin',
			CEQG_PLUGIN_URL . 'admin/js/admin.js',
			array(),
			CEQG_VERSION,
			true
		);
	}

	/**
	 * Save settings when the settings form is posted.
	 *
	 * @return void
	 */
	public function maybe_save_settings() {
		if ( empty( $_POST['ceqg_settings_submit'] ) ) {
			return;
		}

		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Quantity Guard settings.', 'coderembassy-quantity-guard' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$raw_settings = array();

		if ( isset( $_POST['ceqg_settings'] ) && is_array( $_POST['ceqg_settings'] ) ) {
			$raw_settings = wp_unslash( $_POST['ceqg_settings'] );
		}

		$warnings = array();
		$settings = $this->sanitize_settings( $raw_settings, $warnings );

		update_option( self::OPTION_NAME, $settings );

		$redirect_url = add_query_arg(
			array(
				'page'         => self::MENU_SLUG,
				'ceqg_updated' => '1',
			),
			admin_url( 'admin.php' )
		);

		if ( ! empty( $warnings ) ) {
			$redirect_url = add_query_arg( 'ceqg_warning', rawurlencode( implode( ' ', $warnings ) ), $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Quantity Guard settings.', 'coderembassy-quantity-guard' ) );
		}

		$settings = self::get_settings();

		?>
		<div class="wrap ceqg-settings">
			<h1><?php echo esc_html__( 'Quantity Guard', 'coderembassy-quantity-guard' ); ?></h1>

			<?php $this->render_notices(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

				<h2><?php echo esc_html__( 'General', 'coderembassy-quantity-guard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Enable Quantity Guard', 'coderembassy-quantity-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="ceqg_settings[enabled]" value="yes" <?php checked( 'yes', $settings['enabled'] ); ?> />
								<?php echo esc_html__( 'Enable quantity rules on the storefront.', 'coderembassy-quantity-guard' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Global Quantity Rules', 'coderembassy-quantity-guard' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->render_number_field(
						'global_min',
						__( 'Minimum quantity', 'coderembassy-quantity-guard' ),
						$settings['global_min'],
						__( 'The smallest quantity customers can buy when no product or variation rule overrides it.', 'coderembassy-quantity-guard' )
					);
					$this->render_number_field(
						'global_max',
						__( 'Maximum quantity', 'coderembassy-quantity-guard' ),
						$settings['global_max'],
						__( 'Leave empty for no plugin-defined maximum.', 'coderembassy-quantity-guard' ),
						true
					);
					$this->render_number_field(
						'global_step',
						__( 'Step quantity', 'coderembassy-quantity-guard' ),
						$settings['global_step'],
						__( 'Customers must buy in this increment.', 'coderembassy-quantity-guard' )
					);
					$this->render_number_field(
						'global_default',
						__( 'Default quantity', 'coderembassy-quantity-guard' ),
						$settings['global_default'],
						__( 'Used to prefill product-page quantity inputs.', 'coderembassy-quantity-guard' )
					);
					?>
				</table>

				<h2><?php echo esc_html__( 'Messages', 'coderembassy-quantity-guard' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->render_textarea_field(
						'message_min',
						__( 'Minimum quantity message', 'coderembassy-quantity-guard' ),
						$settings['message_min']
					);
					$this->render_textarea_field(
						'message_max',
						__( 'Maximum quantity message', 'coderembassy-quantity-guard' ),
						$settings['message_max']
					);
					$this->render_textarea_field(
						'message_step',
						__( 'Step quantity message', 'coderembassy-quantity-guard' ),
						$settings['message_step']
					);
					?>
				</table>

				<h2><?php echo esc_html__( 'Debug Tools', 'coderembassy-quantity-guard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Delete data on uninstall', 'coderembassy-quantity-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="ceqg_settings[delete_data_on_uninstall]" value="yes" <?php checked( 'yes', $settings['delete_data_on_uninstall'] ); ?> />
								<?php echo esc_html__( 'Remove Quantity Guard settings and rule meta when uninstalling the plugin.', 'coderembassy-quantity-guard' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save settings', 'coderembassy-quantity-guard' ), 'primary', 'ceqg_settings_submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render settings notices.
	 *
	 * @return void
	 */
	private function render_notices() {
		if ( isset( $_GET['ceqg_updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['ceqg_updated'] ) ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Quantity Guard settings saved.', 'coderembassy-quantity-guard' )
			);
		}

		if ( ! empty( $_GET['ceqg_warning'] ) ) {
			$warning = sanitize_text_field( wp_unslash( $_GET['ceqg_warning'] ) );

			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html( $warning )
			);
		}
	}

	/**
	 * Render a number input row.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Field label.
	 * @param mixed  $value       Field value.
	 * @param string $description Field description.
	 * @param bool   $allow_empty Whether the field may be empty.
	 * @return void
	 */
	private function render_number_field( $key, $label, $value, $description, $allow_empty = false ) {
		?>
		<tr>
			<th scope="row">
				<label for="ceqg_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<input
					type="number"
					id="ceqg_<?php echo esc_attr( $key ); ?>"
					name="ceqg_settings[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( $value ); ?>"
					min="<?php echo $allow_empty ? '0' : '1'; ?>"
					step="1"
					class="small-text"
				/>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a textarea row.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return void
	 */
	private function render_textarea_field( $key, $label, $value ) {
		?>
		<tr>
			<th scope="row">
				<label for="ceqg_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<textarea
					id="ceqg_<?php echo esc_attr( $key ); ?>"
					name="ceqg_settings[<?php echo esc_attr( $key ); ?>]"
					rows="3"
					class="large-text"
				><?php echo esc_textarea( $value ); ?></textarea>
				<p class="description">
					<?php echo esc_html__( 'Available placeholders: {product_name}, {min_qty}, {max_qty}, {step_qty}, {default_qty}, {rule_source}', 'coderembassy-quantity-guard' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Sanitize raw settings.
	 *
	 * @param array $raw_settings Raw submitted settings.
	 * @param array $warnings     Warning messages passed by reference.
	 * @return array
	 */
	private function sanitize_settings( $raw_settings, &$warnings = array() ) {
		$defaults = self::get_defaults();

		$settings = array(
			'enabled'                  => $this->sanitize_checkbox( $raw_settings, 'enabled' ),
			'global_min'               => $this->sanitize_positive_int( $raw_settings, 'global_min', $defaults['global_min'] ),
			'global_max'               => $this->sanitize_optional_positive_int( $raw_settings, 'global_max' ),
			'global_step'              => $this->sanitize_positive_int( $raw_settings, 'global_step', $defaults['global_step'] ),
			'global_default'           => $this->sanitize_positive_int( $raw_settings, 'global_default', $defaults['global_default'] ),
			'message_min'              => $this->sanitize_message( $raw_settings, 'message_min', $defaults['message_min'] ),
			'message_max'              => $this->sanitize_message( $raw_settings, 'message_max', $defaults['message_max'] ),
			'message_step'             => $this->sanitize_message( $raw_settings, 'message_step', $defaults['message_step'] ),
			'delete_data_on_uninstall' => $this->sanitize_checkbox( $raw_settings, 'delete_data_on_uninstall' ),
		);

		if ( '' !== $settings['global_max'] && $settings['global_max'] < $settings['global_min'] ) {
			$settings['global_max'] = $settings['global_min'];
		}

		$settings['global_default'] = $this->normalize_quantity_to_rule(
			$settings['global_default'],
			$settings['global_min'],
			$settings['global_max'],
			$settings['global_step']
		);

		if ( 0 !== $settings['global_min'] % $settings['global_step'] ) {
			$warnings[] = __(
				'Tip: for the smoothest Cart and Checkout block controls, use a global minimum that is a multiple of the global step.',
				'coderembassy-quantity-guard'
			);
		}

		return $settings;
	}

	/**
	 * Normalize checkbox values to yes/no.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @return string
	 */
	private function sanitize_checkbox( $settings, $key ) {
		$value = $this->get_raw_scalar( $settings, $key );

		return 'yes' === sanitize_text_field( $value ) ? 'yes' : 'no';
	}

	/**
	 * Sanitize a required positive integer.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @param int    $fallback Fallback value.
	 * @return int
	 */
	private function sanitize_positive_int( $settings, $key, $fallback ) {
		$value = $this->get_raw_scalar( $settings, $key, $fallback );
		$value = absint( $value );

		return max( 1, $value );
	}

	/**
	 * Sanitize an optional positive integer.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @return int|string
	 */
	private function sanitize_optional_positive_int( $settings, $key ) {
		$value = $this->get_raw_scalar( $settings, $key );

		if ( '' === trim( $value ) ) {
			return '';
		}

		return max( 1, absint( $value ) );
	}

	/**
	 * Sanitize a message template.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @param string $fallback Fallback message.
	 * @return string
	 */
	private function sanitize_message( $settings, $key, $fallback ) {
		$message = sanitize_textarea_field( $this->get_raw_scalar( $settings, $key, $fallback ) );

		return '' === $message ? $fallback : $message;
	}

	/**
	 * Safely read a scalar submitted value.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback value.
	 * @return string
	 */
	private function get_raw_scalar( $settings, $key, $fallback = '' ) {
		if ( ! isset( $settings[ $key ] ) || ! is_scalar( $settings[ $key ] ) ) {
			return (string) $fallback;
		}

		return (string) $settings[ $key ];
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
	 * Required capability for managing plugin settings.
	 *
	 * @return string
	 */
	private function get_capability() {
		return 'manage_woocommerce';
	}
}
