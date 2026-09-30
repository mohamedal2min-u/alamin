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

	// Enter in a catalog sale price field saves that row (not the bulk selection).
	$( document ).on( 'keydown', '#urme-select-form .urme-price-edit input', function ( e ) {
		if ( 13 === e.which ) {
			e.preventDefault();
			$( this ).closest( '.urme-price-edit' ).find( 'button' ).trigger( 'click' );
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
			data.sale_price = $btn.closest( '.urme-price-edit' ).find( 'input' ).val();
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
						$tr.replaceWith( $new );
						$tr = $new;
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
		if ( kind ) {
			$m.addClass( 'ok' === kind ? 'urme-row-ok' : 'urme-row-error' );
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

( function ( $ ) {
	// URME Lager stock has priority: the server refuses Dropshipping while the stock is above 0 (no override).
	$( document ).on( 'submit', '.urme-mode-form', function ( e ) {
		var $form = $( this ),
			units = parseInt( $form.data( 'local-units' ), 10 ) || 0;
		if ( 'supplier' === $form.find( 'select[name=mode]' ).val() && units > 0 ) {
			e.preventDefault();
			window.alert( 'URME Lager stock is ' + units + '. Dropshipping can only start when the URME Lager stock is 0.' );
		}
	} );
} )( jQuery );
