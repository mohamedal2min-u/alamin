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

	// A supplier's recommended price becomes the Regular price at once (saved like Save).
	$( document ).on( 'click', '#urme-select-form .urme-rrp-pick', function ( e ) {
		var $btn = $( this ),
			$tr = $btn.closest( 'tr' ),
			$reg = $tr.find( '.urme-price-edit input[name^="regular_price"]' ),
			price = parseInt( $btn.data( 'price' ), 10 );
		e.preventDefault();
		if ( ! $reg.length || ! price ) {
			return;
		}
		// Saved at once, with the row's current sale price (same server checks as Save).
		$reg.val( price );
		$tr.find( '.urme-price-edit button[value^="sale|"]' ).trigger( 'click' );
	} );

	// "Auto …98" goes to the server like the other row buttons (row_action auto98|key): it sets the
	// sale price and, when needed, raises the recommended (Regular) price by 20 % so the sale fits below it.

	// A typed but unsaved price is lost on reload: mark the row and warn before leaving the page.
	$( document ).on( 'input', '#urme-select-form .urme-price-edit input', function () {
		$( this ).closest( 'tr' ).addClass( 'urme-unsaved' );
	} );
	$( window ).on( 'beforeunload', function ( e ) {
		if ( $( '#urme-select-form tr.urme-unsaved' ).length && ! $form.data( 'urmeSubmitting' ) ) {
			e.preventDefault();
			return 'A price you typed is not saved yet.';
		}
	} );
	$form.on( 'submit', function () {
		$form.data( 'urmeSubmitting', true );
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

( function ( $ ) {
	// Catalog Brand filter: the supplier's in-stock count in black and "+N" (watches you can still
	// add) in green. A native <select> cannot colour part of an option, so the select stays (it is
	// what the form submits) and a small list is drawn over it.
	$( 'select.urme-brand-select' ).each( function () {
		var $sel = $( this ).hide(),
			$box = $( '<div class="urme-bsel"></div>' ).insertAfter( $sel ),
			$btn = $( '<button type="button" class="urme-bsel-btn" aria-haspopup="listbox" aria-expanded="false"></button>' ).appendTo( $box ),
			$list = $( '<ul class="urme-bsel-list" role="listbox" hidden></ul>' ).appendTo( $box );

		function line( $opt ) {
			var $out = $( '<span class="urme-bsel-line"></span>' ),
				avail = $opt.data( 'avail' ),
				missing = parseInt( $opt.data( 'missing' ), 10 ) || 0;
			if ( undefined === avail ) {
				return $out.text( $opt.text() ); // "All".
			}
			$out.append( $( '<span class="urme-bsel-name"></span>' ).text( $opt.val() ) );
			$out.append( $( '<span class="urme-bsel-n"></span>' ).text( avail ) );
			if ( missing ) {
				$out.append( $( '<span class="urme-bsel-add"></span>' ).text( '+' + missing ).attr( 'title', missing + ' in stock at the supplier, not synced yet' ) );
			}
			if ( '1' === String( $opt.data( 'off' ) ) ) {
				$out.append( $( '<span class="urme-bsel-off"></span>' ).text( 'sync off' ) );
			}
			return $out;
		}
		function show() {
			$btn.empty().append( line( $sel.find( 'option:selected' ) ) );
		}
		function close() {
			$list.prop( 'hidden', true );
			$btn.attr( 'aria-expanded', 'false' );
		}
		$sel.find( 'option' ).each( function () {
			var $opt = $( this );
			$( '<li role="option" tabindex="-1"></li>' ).append( line( $opt ) ).attr( 'aria-selected', $opt.is( ':selected' ) ? 'true' : 'false' ).data( 'value', $opt.val() ).appendTo( $list );
		} );
		show();

		$btn.on( 'click', function () {
			var open = $list.prop( 'hidden' );
			$list.prop( 'hidden', ! open );
			$btn.attr( 'aria-expanded', open ? 'true' : 'false' );
			if ( open ) {
				$list.find( '[aria-selected="true"]' ).trigger( 'focus' );
			}
		} );
		$list.on( 'click', 'li', function () {
			$sel.val( $( this ).data( 'value' ) ).trigger( 'change' );
			$list.find( 'li' ).attr( 'aria-selected', 'false' );
			$( this ).attr( 'aria-selected', 'true' );
			show();
			close();
			$btn.trigger( 'focus' );
		} );
		$list.on( 'keydown', 'li', function ( e ) {
			if ( 13 === e.which || 32 === e.which ) {
				e.preventDefault();
				$( this ).trigger( 'click' );
			} else if ( 40 === e.which || 38 === e.which ) {
				e.preventDefault();
				$( this )[ 40 === e.which ? 'next' : 'prev' ]().trigger( 'focus' );
			} else if ( 27 === e.which ) {
				close();
				$btn.trigger( 'focus' );
			}
		} );
		$( document ).on( 'click', function ( e ) {
			if ( ! $box.is( e.target ) && ! $box.has( e.target ).length ) {
				close();
			}
		} );
	} );
} )( jQuery );
