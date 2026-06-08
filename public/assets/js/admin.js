/* global document */
/**
 * Billigoo admin interactions: invoice detail slide-over.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function setupPaTest() {
		var btn = document.getElementById( 'billigoo-test-pa' );
		var out = document.getElementById( 'billigoo-test-pa-result' );
		if ( ! btn || typeof window.ajaxurl === 'undefined' ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			out.textContent = '…';
			var body = new URLSearchParams();
			body.set( 'action', 'billigoo_test_pa' );
			body.set( 'nonce', btn.dataset.nonce || '' );
			window.fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					var data = ( res && res.data ) || {};
					out.textContent = data.message || ( res && res.success ? 'OK' : 'Erreur' );
					out.style.color = ( data.ok || ( res && res.success && data.ok !== false ) ) ? '#15803d' : '#b91c1c';
				} )
				.catch( function () { out.textContent = 'Erreur réseau'; out.style.color = '#b91c1c'; } );
		} );
	}

	ready( function () {
		setupPaTest();

		var overlay = document.getElementById( 'billigoo-slideover' );
		if ( ! overlay ) {
			return;
		}

		var fields = {
			number: overlay.querySelector( '[data-field="number"]' ),
			order: overlay.querySelector( '[data-field="order"]' ),
			client: overlay.querySelector( '[data-field="client"]' ),
			siret: overlay.querySelector( '[data-field="siret"]' ),
			date: overlay.querySelector( '[data-field="date"]' ),
			total: overlay.querySelector( '[data-field="total"]' ),
			status: overlay.querySelector( '[data-field="status"]' ),
			pdf: overlay.querySelector( '[data-action="pdf"]' ),
			xml: overlay.querySelector( '[data-action="xml"]' ),
			transmit: overlay.querySelector( '[data-action="transmit"]' ),
			edit: overlay.querySelector( '[data-action="edit"]' )
		};

		function setText( el, value ) {
			if ( el ) {
				el.textContent = value || '—';
			}
		}

		function open( row ) {
			setText( fields.number, row.dataset.number );
			setText( fields.order, '#' + ( row.dataset.order || '' ) );
			setText( fields.client, row.dataset.client );
			setText( fields.siret, row.dataset.siret );
			setText( fields.date, row.dataset.date );
			setText( fields.total, row.dataset.total );

			if ( fields.status ) {
				fields.status.textContent = row.dataset.statusLabel || '—';
				fields.status.className = 'billigoo-badge ' + ( row.dataset.statusClass || 'billigoo-badge--neutral' );
			}
			if ( fields.pdf ) { fields.pdf.href = row.dataset.pdf || '#'; }
			if ( fields.xml ) { fields.xml.href = row.dataset.xml || '#'; }
			if ( fields.transmit ) { fields.transmit.href = row.dataset.transmit || '#'; }
			if ( fields.edit ) { fields.edit.href = row.dataset.edit || '#'; }

			overlay.classList.add( 'is-open' );
			document.body.style.overflow = 'hidden';
		}

		function close() {
			overlay.classList.remove( 'is-open' );
			document.body.style.overflow = '';
		}

		document.querySelectorAll( '.billigoo-row[data-number]' ).forEach( function ( row ) {
			row.addEventListener( 'click', function ( e ) {
				// Let real links inside the row behave normally.
				if ( e.target.closest( 'a' ) ) {
					return;
				}
				open( row );
			} );
		} );

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay || e.target.closest( '[data-close]' ) ) {
				close();
			}
		} );

		document.addEventListener( 'keyup', function ( e ) {
			if ( e.key === 'Escape' ) {
				close();
			}
		} );
	} );
} )();
