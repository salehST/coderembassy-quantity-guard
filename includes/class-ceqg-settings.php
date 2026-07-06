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
		add_action( 'current_screen', array( $this, 'maybe_suppress_third_party_notices' ) );
		add_action( 'admin_head', array( $this, 'suppress_third_party_notices' ), 0 );
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

		wp_enqueue_style( 'dashicons' );

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
	 * Remove global admin notices on the Quantity Guard screen.
	 *
	 * @return void
	 */
	public function maybe_suppress_third_party_notices() {
		$this->suppress_third_party_notices();
	}

	/**
	 * Suppress notices from other plugins only on this plugin's settings page.
	 *
	 * Quantity Guard renders its own save and warning notices inside the page.
	 *
	 * @return void
	 */
	public function suppress_third_party_notices() {
		if ( ! $this->is_settings_screen() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
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
		$logo_url = CEQG_PLUGIN_URL . 'admin/images/logo-light.png';
		$max_display = '' === $settings['global_max'] ? __( 'No limit', 'coderembassy-quantity-guard' ) : $settings['global_max'];
		$status_label = 'yes' === $settings['enabled']
			? __( 'Active', 'coderembassy-quantity-guard' )
			: __( 'Paused', 'coderembassy-quantity-guard' );
		$status_class = 'yes' === $settings['enabled'] ? 'active' : 'paused';
		$status_icon  = 'yes' === $settings['enabled'] ? 'dashicons-yes-alt' : 'dashicons-controls-pause';

		?>
		<div class="wrap ceqg-settings">
			<div class="ceqg-shell">
				<header class="ceqg-hero">
					<div class="ceqg-hero__content">
						<div class="ceqg-hero__top">
							<img class="ceqg-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr__( 'CoderEmbassy', 'coderembassy-quantity-guard' ); ?>" />
							<div class="ceqg-badges" aria-label="<?php echo esc_attr__( 'Plugin status', 'coderembassy-quantity-guard' ); ?>">
								<span class="ceqg-badge ceqg-badge--free"><?php echo esc_html__( 'FREE', 'coderembassy-quantity-guard' ); ?></span>
								<span class="ceqg-badge"><?php echo esc_html( sprintf( 'v%s', CEQG_VERSION ) ); ?></span>
								<span class="ceqg-badge"><?php echo esc_html__( 'HPOS ready', 'coderembassy-quantity-guard' ); ?></span>
								<span class="ceqg-badge"><?php echo esc_html__( 'Blocks ready', 'coderembassy-quantity-guard' ); ?></span>
							</div>
						</div>
						<p class="ceqg-kicker"><?php echo esc_html__( 'WooCommerce quantity control', 'coderembassy-quantity-guard' ); ?></p>
						<h1><?php echo esc_html__( 'Quantity Guard for WooCommerce', 'coderembassy-quantity-guard' ); ?></h1>
						<p class="ceqg-hero__copy">
							<?php echo esc_html__( 'Set storewide quantity rules, then override them per product or variation when a product needs different buying limits.', 'coderembassy-quantity-guard' ); ?>
						</p>
					</div>

					<aside class="ceqg-hero__summary" aria-label="<?php echo esc_attr__( 'Current global quantity rule summary', 'coderembassy-quantity-guard' ); ?>">
						<div class="ceqg-summary-heading">
							<span><?php echo esc_html__( 'Current setup', 'coderembassy-quantity-guard' ); ?></span>
							<strong><?php echo esc_html__( 'Global fallback rule', 'coderembassy-quantity-guard' ); ?></strong>
						</div>
						<div class="ceqg-status ceqg-status--<?php echo esc_attr( $status_class ); ?>">
							<span class="dashicons <?php echo esc_attr( $status_icon ); ?>" aria-hidden="true"></span>
							<?php echo esc_html( $status_label ); ?>
						</div>
						<div class="ceqg-rule-metrics">
							<div>
								<span><?php echo esc_html__( 'Min', 'coderembassy-quantity-guard' ); ?></span>
								<strong><?php echo esc_html( $settings['global_min'] ); ?></strong>
							</div>
							<div>
								<span><?php echo esc_html__( 'Max', 'coderembassy-quantity-guard' ); ?></span>
								<strong><?php echo esc_html( $max_display ); ?></strong>
							</div>
							<div>
								<span><?php echo esc_html__( 'Step', 'coderembassy-quantity-guard' ); ?></span>
								<strong><?php echo esc_html( $settings['global_step'] ); ?></strong>
							</div>
							<div>
								<span><?php echo esc_html__( 'Default', 'coderembassy-quantity-guard' ); ?></span>
								<strong><?php echo esc_html( $settings['global_default'] ); ?></strong>
							</div>
						</div>
					</aside>
				</header>

				<?php $this->render_notices(); ?>

				<form class="ceqg-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

					<section class="ceqg-card">
						<div class="ceqg-card__header">
							<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
							<div>
								<h2><?php echo esc_html__( 'General', 'coderembassy-quantity-guard' ); ?></h2>
								<p><?php echo esc_html__( 'Control whether Quantity Guard rules run on the storefront.', 'coderembassy-quantity-guard' ); ?></p>
							</div>
						</div>
						<div class="ceqg-card__body">
							<label class="ceqg-toggle">
								<input type="checkbox" name="ceqg_settings[enabled]" value="yes" <?php checked( 'yes', $settings['enabled'] ); ?> />
								<span class="ceqg-toggle__track" aria-hidden="true"></span>
								<span>
									<strong><?php echo esc_html__( 'Enable Quantity Guard', 'coderembassy-quantity-guard' ); ?></strong>
									<small><?php echo esc_html__( 'Apply quantity rules to products, cart, checkout, and compatible WooCommerce blocks.', 'coderembassy-quantity-guard' ); ?></small>
								</span>
							</label>
						</div>
					</section>

					<section class="ceqg-card">
						<div class="ceqg-card__header">
							<span class="dashicons dashicons-cart" aria-hidden="true"></span>
							<div>
								<h2><?php echo esc_html__( 'Global Quantity Rules', 'coderembassy-quantity-guard' ); ?></h2>
								<p><?php echo esc_html__( 'Fallback rules used when a product or variation has no custom rule.', 'coderembassy-quantity-guard' ); ?></p>
							</div>
						</div>
						<div class="ceqg-card__body ceqg-grid ceqg-grid--rules">
							<?php
							$this->render_number_field(
								'global_min',
								__( 'Minimum quantity', 'coderembassy-quantity-guard' ),
								$settings['global_min'],
								__( 'Smallest quantity customers can buy.', 'coderembassy-quantity-guard' )
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
								__( 'Prefills product-page quantity inputs.', 'coderembassy-quantity-guard' )
							);
							?>
						</div>
					</section>

					<section class="ceqg-card">
						<div class="ceqg-card__header">
							<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
							<div>
								<h2><?php echo esc_html__( 'Messages', 'coderembassy-quantity-guard' ); ?></h2>
								<p><?php echo esc_html__( 'Customer-facing validation text for product, cart, checkout, and Store API errors.', 'coderembassy-quantity-guard' ); ?></p>
							</div>
						</div>
						<div class="ceqg-card__body ceqg-grid ceqg-grid--messages">
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
						</div>
					</section>

					<section class="ceqg-card">
						<div class="ceqg-card__header">
							<span class="dashicons dashicons-database-remove" aria-hidden="true"></span>
							<div>
								<h2><?php echo esc_html__( 'Maintenance', 'coderembassy-quantity-guard' ); ?></h2>
								<p><?php echo esc_html__( 'Choose what happens to Quantity Guard data during uninstall.', 'coderembassy-quantity-guard' ); ?></p>
							</div>
						</div>
						<div class="ceqg-card__body">
							<label class="ceqg-checkline">
								<input type="checkbox" name="ceqg_settings[delete_data_on_uninstall]" value="yes" <?php checked( 'yes', $settings['delete_data_on_uninstall'] ); ?> />
								<span class="ceqg-checkline__box" aria-hidden="true"></span>
								<span class="ceqg-checkline__content">
									<strong><?php echo esc_html__( 'Delete data on uninstall', 'coderembassy-quantity-guard' ); ?></strong>
									<small><?php echo esc_html__( 'Remove Quantity Guard settings and rule meta when uninstalling the plugin.', 'coderembassy-quantity-guard' ); ?></small>
								</span>
							</label>
						</div>
					</section>

					<div class="ceqg-actions">
						<div>
							<strong><?php echo esc_html__( 'Storefront rules update immediately after saving.', 'coderembassy-quantity-guard' ); ?></strong>
							<span><?php echo esc_html__( 'Product and variation overrides continue to take priority over global rules.', 'coderembassy-quantity-guard' ); ?></span>
						</div>
						<?php submit_button( __( 'Save settings', 'coderembassy-quantity-guard' ), 'primary ceqg-save-button', 'ceqg_settings_submit', false ); ?>
					</div>
				</form>
			</div>
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
				'<div class="notice notice-success is-dismissible ceqg-notice"><p>%s</p></div>',
				esc_html__( 'Quantity Guard settings saved.', 'coderembassy-quantity-guard' )
			);
		}

		if ( ! empty( $_GET['ceqg_warning'] ) ) {
			$warning = sanitize_text_field( wp_unslash( $_GET['ceqg_warning'] ) );

			printf(
				'<div class="notice notice-warning is-dismissible ceqg-notice"><p>%s</p></div>',
				esc_html( $warning )
			);
		}
	}

	/**
	 * Determine whether the current admin screen is this plugin settings page.
	 *
	 * @return bool
	 */
	private function is_settings_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && $this->page_hook && $screen->id === $this->page_hook;
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
		<div class="ceqg-field">
			<label class="ceqg-field__label" for="ceqg_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input
				type="number"
				id="ceqg_<?php echo esc_attr( $key ); ?>"
				name="ceqg_settings[<?php echo esc_attr( $key ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				min="<?php echo $allow_empty ? '0' : '1'; ?>"
				step="1"
				inputmode="numeric"
				class="ceqg-input ceqg-input--number"
			/>
			<p class="ceqg-field__help"><?php echo esc_html( $description ); ?></p>
		</div>
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
		<div class="ceqg-field ceqg-field--wide">
			<label class="ceqg-field__label" for="ceqg_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<textarea
				id="ceqg_<?php echo esc_attr( $key ); ?>"
				name="ceqg_settings[<?php echo esc_attr( $key ); ?>]"
				rows="3"
				class="ceqg-input ceqg-textarea"
			><?php echo esc_textarea( $value ); ?></textarea>
			<p class="ceqg-field__help">
				<?php echo esc_html__( 'Placeholders: {product_name}, {min_qty}, {max_qty}, {step_qty}, {default_qty}, {rule_source}', 'coderembassy-quantity-guard' ); ?>
			</p>
		</div>
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
			$warnings[] = __(
				'Maximum quantity was lower than the minimum, so it was adjusted to match the minimum.',
				'coderembassy-quantity-guard'
			);
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
