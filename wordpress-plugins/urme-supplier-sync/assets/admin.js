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

	// Confirmation for per-row catalog buttons (they submit the catalog form).
	$( document ).on( 'click', 'button[data-confirm]', function ( e ) {
		if ( ! window.confirm( $( this ).data( 'confirm' ) ) ) {
			e.preventDefault();
		}
	} );

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
	// Mode switcher on Selected watches: local fields only for "Local first";
	// confirm before local units are dropped by switching to "Supplier now".
	function sync( $form ) {
		$form.find( '.urme-local-fields' ).toggle( 'local' === $form.find( 'select[name=mode]' ).val() );
	}
	$( '.urme-mode-form' ).each( function () {
		sync( $( this ) );
	} );
	$( document ).on( 'change', '.urme-mode-form select[name=mode]', function () {
		sync( $( this ).closest( 'form' ) );
	} );
	$( document ).on( 'submit', '.urme-mode-form', function ( e ) {
		var $form = $( this ),
			units = parseInt( $form.data( 'local-units' ), 10 ) || 0;
		if ( 'supplier' === $form.find( 'select[name=mode]' ).val() && units > 0 ) {
			if ( ! window.confirm( units + ' local unit(s) are still tracked. Switch to Supplier now and stop tracking them?' ) ) {
				e.preventDefault();
				return;
			}
			$form.find( 'input[name=confirm_drop]' ).val( '1' );
		}
	} );
} )( jQuery );
