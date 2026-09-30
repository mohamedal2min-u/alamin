/**
 * WooCommerce > Products Quick Edit: "Move to URME Lager" above Stock qty, only for a
 * Dropshipping product (marked in its Fulfillment cell). Ticking it empties Stock qty so
 * URME's own count is typed; unticking restores the previous value.
 */
( function ( $ ) {
	$( '#the-list' ).on( 'click', '.editinline', function () {
		var id = String( $( this ).closest( 'tr' ).attr( 'id' ) || '' ).replace( 'post-', '' ),
			drop = $( '#post-' + id ).find( '.urme-qe-dropship' ).length > 0;
		// After WordPress and WooCommerce have filled the edit row.
		setTimeout( function () {
			var $row = $( '#edit-' + id ),
				$box = $row.find( '.urme-qe-lager' ),
				$qty = $row.find( '.stock_qty_field' );
			if ( ! $box.length ) {
				return;
			}
			if ( $qty.length ) {
				$box.insertBefore( $qty );
			}
			$box.find( 'input' ).prop( 'checked', false ).removeData( 'urmeOld' );
			$box.toggle( drop );
		}, 0 );
	} );

	$( document ).on( 'change', '.urme-qe-lager input', function () {
		var $row = $( this ).closest( '.inline-edit-row' ),
			$stock = $row.find( 'input[name="_stock"]' ),
			$manage = $row.find( 'input[name="_manage_stock"]' );
		if ( this.checked ) {
			$( this ).data( 'urmeOld', $stock.val() );
			if ( ! $manage.prop( 'checked' ) ) {
				$manage.prop( 'checked', true ).trigger( 'change' );
			}
			$stock.val( '' ).trigger( 'focus' );
		} else if ( undefined !== $( this ).data( 'urmeOld' ) ) {
			$stock.val( $( this ).data( 'urmeOld' ) );
		}
	} );
} )( jQuery );
