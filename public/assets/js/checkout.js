/* global jQuery, billigooCheckout */
( function ( $ ) {
	'use strict';

	$( function () {
		var $wrap = $( '#billigoo-b2b-fields' );
		if ( ! $wrap.length ) {
			return;
		}

		var $checkbox    = $( '#billigoo_is_business' );
		var $conditional = $wrap.find( '.billigoo-b2b-conditional' );
		var $siret       = $( '#billigoo_siret' );
		var $feedback    = $( '#billigoo-siret-feedback' );

		function toggle() {
			$conditional.toggle( $checkbox.is( ':checked' ) );
		}
		$checkbox.on( 'change', toggle );
		toggle();

		var timer = null;
		$siret.on( 'input', function () {
			var value = $siret.val();
			window.clearTimeout( timer );
			$feedback.removeClass( 'is-valid is-invalid' ).text( '' );
			if ( value.replace( /\D/g, '' ).length < 14 ) {
				return;
			}
			timer = window.setTimeout( function () {
				$.post( billigooCheckout.ajaxUrl, {
					action: 'billigoo_validate_siret',
					nonce: billigooCheckout.nonce,
					siret: value
				} ).done( function ( res ) {
					if ( res && res.data && res.data.valid ) {
						$feedback.addClass( 'is-valid' ).text( billigooCheckout.i18n.valid );
					} else {
						$feedback.addClass( 'is-invalid' ).text( billigooCheckout.i18n.invalid );
					}
				} );
			}, 300 );
		} );
	} );
} )( jQuery );
