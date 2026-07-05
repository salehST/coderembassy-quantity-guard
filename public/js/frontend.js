( function ( $ ) {
	'use strict';

	function formatMessage( rule ) {
		if ( rule.message ) {
			return rule.message;
		}

		var template = ceqgFrontend.messageLabel || 'Quantity: minimum {min_qty}, maximum {max_qty}, step {step_qty}.';
		var max = rule.max === '' ? ceqgFrontend.noneLabel : rule.max;

		return template
			.replace( '{min_qty}', rule.min )
			.replace( '{max_qty}', max )
			.replace( '{step_qty}', rule.step );
	}

	function getQuantityInput( $form ) {
		return $form.find( '.quantity input.qty' ).first();
	}

	function getMessageNode( $form ) {
		return $form.find( '[data-ceqg-rule-message]' ).first();
	}

	function rememberOriginalState( $form ) {
		var $qty = getQuantityInput( $form );
		var $message = getMessageNode( $form );

		if ( $form.data( 'ceqgOriginalState' ) ) {
			return;
		}

		$form.data( 'ceqgOriginalState', {
			min: $qty.attr( 'min' ),
			max: $qty.attr( 'max' ),
			step: $qty.attr( 'step' ),
			value: $qty.val(),
			message: $message.text()
		} );
	}

	function restoreOriginalState( $form ) {
		var state = $form.data( 'ceqgOriginalState' );
		var $qty = getQuantityInput( $form );
		var $message = getMessageNode( $form );

		if ( ! state ) {
			return;
		}

		setOrRemoveAttr( $qty, 'min', state.min );
		setOrRemoveAttr( $qty, 'max', state.max );
		setOrRemoveAttr( $qty, 'step', state.step );
		$qty.val( state.value );
		$message.text( state.message );
	}

	function setOrRemoveAttr( $node, attr, value ) {
		if ( value === undefined || value === null || value === '' ) {
			$node.removeAttr( attr );
			return;
		}

		$node.attr( attr, value );
	}

	function applyRule( $form, rule, updateValue ) {
		var $qty = getQuantityInput( $form );
		var $message = getMessageNode( $form );

		if ( ! rule || ! $qty.length ) {
			return;
		}

		rememberOriginalState( $form );
		$form.data( 'ceqgActiveRule', rule );

		$qty.attr( 'min', rule.min );
		setOrRemoveAttr( $qty, 'max', rule.max );
		$qty.attr( 'step', rule.step );

		if ( updateValue || ! $qty.val() ) {
			$qty.val( rule.default );
		}

		if ( $message.length ) {
			$message.text( formatMessage( rule ) );
		}
	}

	function getSelectedVariationRule( $form ) {
		var variationId = parseInt( $form.find( 'input[name="variation_id"]' ).val(), 10 );
		var variations = $form.data( 'product_variations' );

		if ( ! variationId || ! $.isArray( variations ) ) {
			return $form.data( 'ceqgActiveRule' );
		}

		for ( var index = 0; index < variations.length; index++ ) {
			if ( parseInt( variations[ index ].variation_id, 10 ) === variationId && variations[ index ].ceqg_rule ) {
				return variations[ index ].ceqg_rule;
			}
		}

		return $form.data( 'ceqgActiveRule' );
	}

	function applySelectedVariationRule( $form, updateValue ) {
		var rule = getSelectedVariationRule( $form );

		if ( rule ) {
			applyRule( $form, rule, updateValue );
		}
	}

	$( function () {
		$( '.variations_form' )
			.on( 'found_variation', function ( event, variation ) {
				if ( variation && variation.ceqg_rule ) {
					applyRule( $( this ), variation.ceqg_rule, true );
					setTimeout( applyRule.bind( null, $( this ), variation.ceqg_rule, false ), 0 );
				}
			} )
			.on( 'show_variation woocommerce_variation_has_changed', function () {
				var $form = $( this );

				setTimeout( function () {
					applySelectedVariationRule( $form, false );
				}, 0 );
			} )
			.on( 'reset_data hide_variation', function () {
				restoreOriginalState( $( this ) );
			} );

		$( document ).on( 'focus mousedown touchstart keydown', '.variations_form .quantity input.qty', function () {
			applySelectedVariationRule( $( this ).closest( '.variations_form' ), false );
		} );

		$( '.variations_form' ).each( function () {
			var $form = $( this );

			setTimeout( function () {
				applySelectedVariationRule( $form, true );
			}, 0 );
		} );
	} );
}( jQuery ) );
