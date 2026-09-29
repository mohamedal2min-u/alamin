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
