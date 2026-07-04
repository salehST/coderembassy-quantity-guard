( function ( $ ) {
	'use strict';

	function formatMessage( rule ) {
		var template = ceqgFrontend.messageLabel || 'Quantity: minimum {min}, maximum {max}, step {step}.';
		var max = rule.max === '' ? ceqgFrontend.noneLabel : rule.max;

		return template
			.replace( '{min}', rule.min )
			.replace( '{max}', max )
			.replace( '{step}', rule.step );
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

	function applyRule( $form, rule ) {
		var $qty = getQuantityInput( $form );
		var $message = getMessageNode( $form );

		if ( ! rule || ! $qty.length ) {
			return;
		}

		rememberOriginalState( $form );

		$qty.attr( 'min', rule.min );
		setOrRemoveAttr( $qty, 'max', rule.max );
		$qty.attr( 'step', rule.step );
		$qty.val( rule.default );

		if ( $message.length ) {
			$message.text( formatMessage( rule ) );
		}
	}

	$( function () {
		$( '.variations_form' )
			.on( 'found_variation', function ( event, variation ) {
				if ( variation && variation.ceqg_rule ) {
					applyRule( $( this ), variation.ceqg_rule );
				}
			} )
			.on( 'reset_data hide_variation', function () {
				restoreOriginalState( $( this ) );
			} );
	} );
}( jQuery ) );
