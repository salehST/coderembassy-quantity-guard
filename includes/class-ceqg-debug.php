<?php
/**
 * Admin rule debugger.
 *
 * @package CoderEmbassy_Quantity_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders admin-only rule debugging output.
 */
class CEQG_Debug {
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
	 * Render product rule preview.
	 *
	 * @param WC_Product $product Product object.
	 * @return void
	 */
	public function render_product_rule_preview( $product ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $product ) {
			return;
		}

		$rule = $this->rule_engine->resolve_rule( $product->get_id() );

		?>
		<div class="ceqg-rule-preview-box">
			<p><strong><?php echo esc_html__( 'Rule debugger', 'coderembassy-quantity-guard' ); ?></strong></p>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Active source', 'coderembassy-quantity-guard' ); ?></th>
						<td><?php echo esc_html( $rule['source_label'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Minimum', 'coderembassy-quantity-guard' ); ?></th>
						<td><?php echo esc_html( $rule['min'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Maximum', 'coderembassy-quantity-guard' ); ?></th>
						<td><?php echo esc_html( '' === $rule['max'] ? __( 'None', 'coderembassy-quantity-guard' ) : $rule['max'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Step', 'coderembassy-quantity-guard' ); ?></th>
						<td><?php echo esc_html( $rule['step'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Default', 'coderembassy-quantity-guard' ); ?></th>
						<td><?php echo esc_html( $rule['default'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Message source', 'coderembassy-quantity-guard' ); ?></th>
						<td><?php echo esc_html( $this->get_message_source_label( $rule ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Minimum message sample', 'coderembassy-quantity-guard' ); ?></th>
						<td><?php echo $this->messages->get_escaped_message( CEQG_Messages::TYPE_MIN, $rule, $product->get_id() ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Why this rule applies', 'coderembassy-quantity-guard' ); ?></th>
						<td><?php echo esc_html( $this->get_rule_reason( $rule['source'] ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Describe whether the active message is custom or global.
	 *
	 * @param array $rule Resolved rule.
	 * @return string
	 */
	private function get_message_source_label( $rule ) {
		if ( ! empty( $rule['custom_message'] ) ) {
			return __( 'Custom message from the active rule', 'coderembassy-quantity-guard' );
		}

		return __( 'Global message templates', 'coderembassy-quantity-guard' );
	}

	/**
	 * Explain why a rule source is active.
	 *
	 * @param string $source Rule source.
	 * @return string
	 */
	private function get_rule_reason( $source ) {
		if ( 'variation' === $source ) {
			return __( 'The selected variation has its own enabled rule, so it replaces product and global rules.', 'coderembassy-quantity-guard' );
		}

		if ( 'product' === $source ) {
			return __( 'This product has its own enabled rule, so it replaces the global rule.', 'coderembassy-quantity-guard' );
		}

		return __( 'No product or variation rule is active, so the global rule applies.', 'coderembassy-quantity-guard' );
	}
}
