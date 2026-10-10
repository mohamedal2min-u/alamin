( function ( $ ) {
	// Bulk select in the catalog.
	var $form = $( '#urme-select-form' );
	function refresh() {
		$form.find( '.urme-bulk' ).prop( 'disabled', ! $form.find( 'input[name="item_keys[]"]:checked' ).length );
	}
	$form.on( 'change', 'input[name="item_keys[]"]', refresh );
	$form.on( 'change', '.urme-check-all', function () {
		$form.find( 'input[name="item_keys[]"]' ).prop( 'checked', this.checked );
		refresh();
	} );

	// Enter in a catalog price field (regular or sale) saves that row (not the bulk selection).
	$( document ).on( 'keydown', '#urme-select-form .urme-price-edit input', function ( e ) {
		if ( 13 === e.which ) {
			e.preventDefault();
			$( this ).closest( '.urme-price-edit' ).find( 'button[value^="sale|"]' ).trigger( 'click' );
		}
	} );

	// A supplier's recommended price fills the Regular field only (nothing is saved until Save).
	$( document ).on( 'click', '#urme-select-form .urme-rrp-pick', function ( e ) {
		var $btn = $( this ),
			$tr = $btn.closest( 'tr' ),
			$reg = $tr.find( '.urme-price-edit input[name^="regular_price"]' ),
			price = parseInt( $btn.data( 'price' ), 10 );
		e.preventDefault();
		if ( ! $reg.length || ! price ) {
			return;
		}
		$reg.val( price ).trigger( 'focus' );
		$tr.find( '.urme-rrp-pick' ).removeClass( 'is-picked' );
		$btn.addClass( 'is-picked' );
		rowMessage( $tr, 'Regular (recommended) price set to ' + price + ' kr. Press Save to apply.', 'ok' );
	} );

	// "Auto …98" fills the Sale price field only (the discount); nothing is saved until Save,
	// so the regular (recommended) price can be set first.
	$( document ).on( 'click', '#urme-select-form button.urme-auto98', function ( e ) {
		var $btn = $( this ),
			$tr = $btn.closest( 'tr' ),
			$edit = $tr.find( '.urme-price-edit' ),
			$sale = $edit.find( 'input[name^="sale_price"]' ),
			$reg = $edit.find( 'input[name^="regular_price"]' ),
			price = parseInt( $btn.data( 'price' ), 10 ),
			reg;
		if ( ! $sale.length || ! price ) {
			return; // Server fallback.
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		$sale.val( price ).trigger( 'focus' );
		reg = parseFloat( String( $reg.val() || '' ).replace( /[\s\u00a0]/g, '' ).replace( ',', '.' ) );
		if ( ! reg || reg <= price ) {
			rowMessage( $tr, 'Sale price set to ' + price + ' kr. Enter a higher Regular (recommended) price, then Save.', 'ok' );
			$reg.trigger( 'focus' ).trigger( 'select' );
		} else {
			rowMessage( $tr, 'Sale price set to ' + price + ' kr. Press Save to apply.', 'ok' );
		}
	} );

	// Per-row catalog actions (Save sale price, Dropshipping, URME Lager, Sync now)
	// run over AJAX: the server does the work and returns the row re-rendered from current data.
	// Without JavaScript the buttons still submit the catalog form (same server logic).
	$( document ).on( 'click', '#urme-select-form button[name=row_action]', function ( e ) {
		var $btn = $( this ),
			$tr = $btn.closest( 'tr' ),
			action = String( $btn.val() ),
			isSale = 0 === action.indexOf( 'sale|' ),
			confirmText = $btn.data( 'confirm' ),
			cfg = window.urmeSS,
			data;
		if ( ! cfg || ! cfg.ajaxurl ) {
			if ( confirmText && ! window.confirm( confirmText ) ) {
				e.preventDefault();
			}
			return; // No AJAX config: normal form submit.
		}
		e.preventDefault();
		if ( $tr.data( 'urmeBusy' ) ) {
			return; // One request per row at a time (no double writes).
		}
		if ( confirmText && ! window.confirm( confirmText ) ) {
			return;
		}
		data = { action: 'urme_ss_row', nonce: cfg.nonce, row_action: action };
		if ( isSale ) {
			var $edit = $btn.closest( '.urme-price-edit' ),
				$reg = $edit.find( 'input[name^="regular_price"]' );
			data.sale_price = $edit.find( 'input[name^="sale_price"]' ).val();
			if ( $reg.length ) {
				data.regular_price = $reg.val();
			}
		}
		$tr.data( 'urmeBusy', true ).addClass( 'urme-busy' );
		var $controls = $tr.find( 'button, .urme-price-edit input' ).prop( 'disabled', true );
		var label = $btn.text();
		$btn.text( isSale ? 'Saving…' : 'Working…' );
		rowMessage( $tr, '', '' );

		$.post( cfg.ajaxurl, data )
			.done( function ( res ) {
				var d = ( res && res.data ) || {},
					ok = !! ( res && res.success ),
					text = ok && isSale && 'success' === d.type ? 'Saved ✓' : ( d.message || ( ok ? 'Done.' : 'Something went wrong.' ) );
				if ( d.row_html ) {
					var checked = $tr.find( 'input[name="item_keys[]"]' ).prop( 'checked' ),
						$new = $( $.parseHTML( d.row_html ) ).filter( 'tr' );
					if ( $new.length ) {
						// The row and the rows of the same watch at the other suppliers are replaced together.
						$tr.nextUntil( ':not(.urme-alt-row)' ).remove();
						$new.toggleClass( 'urme-shade', $tr.hasClass( 'urme-shade' ) );
						$tr.replaceWith( $new );
						$tr = $new.first();
						$tr.find( 'input[name="item_keys[]"]' ).prop( 'checked', !! checked );
						refresh();
					}
				}
				rowMessage( $tr, text, ok ? 'ok' : 'error' );
			} )
			.fail( function ( xhr ) {
				var d = xhr && xhr.responseJSON && xhr.responseJSON.data;
				rowMessage( $tr, ( d && d.message ) || 'Request failed (' + ( xhr ? xhr.status : '?' ) + '). Nothing was changed by this page; reload to see the current state.', 'error' );
			} )
			.always( function () {
				$tr.removeData( 'urmeBusy' ).removeClass( 'urme-busy' );
				if ( $.contains( document, $btn[ 0 ] ) ) {
					$controls.prop( 'disabled', false );
					$btn.text( label );
				}
			} );
	} );

	function rowMessage( $tr, text, kind ) {
		var $m = $tr.find( '.urme-row-msg' );
		$m.text( text ).removeClass( 'urme-row-ok urme-row-error' );
		clearTimeout( $m.data( 'urmeHide' ) );
		if ( kind ) {
			$m.addClass( 'ok' === kind ? 'urme-row-ok' : 'urme-row-error' );
		}
		if ( 'ok' === kind ) {
			// A success note clears itself; errors stay until the next action.
			$m.data( 'urmeHide', setTimeout( function () {
				$m.text( '' ).removeClass( 'urme-row-ok' );
			}, 6000 ) );
		}
	}

	// Confirmation for destructive-looking actions.
	$( document ).on( 'submit', 'form[data-confirm]', function ( e ) {
		if ( ! window.confirm( $( this ).data( 'confirm' ) ) ) {
			e.preventDefault();
		}
	} );

	// Long-running actions: show that something is happening.
	$( document ).on( 'submit', 'form.urme-inline', function () {
		$( this ).find( 'button' ).prop( 'disabled', true ).text( 'Working…' );
	} );
} )( jQuery );

( function ( $ ) {
	// Brand allowlist helpers in Settings.
	$( document ).on( 'click', '.urme-brands-all, .urme-brands-none', function () {
		$( '.urme-brand-list input[type=checkbox]' ).prop( 'checked', $( this ).hasClass( 'urme-brands-all' ) );
	} );
} )( jQuery );
