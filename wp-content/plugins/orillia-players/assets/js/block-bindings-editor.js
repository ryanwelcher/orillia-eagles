/**
 * Editor preview for the roster-count block bindings.
 *
 * The server registration (register_block_bindings_source) renders the counts
 * on the front end, but the editor canvas needs a client-side `getValues` to
 * preview the actual number. The counts are provided by wp_localize_script as
 * `orilliaPlayersBindings.counts`.
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.blocks.registerBlockBindingsSource ) {
		return;
	}

	var data = window.orilliaPlayersBindings || {};
	var counts = data.counts || {};

	var toContent = function ( value ) {
		return { content: String( value == null ? '' : value ) };
	};

	wp.blocks.registerBlockBindingsSource( {
		name: 'orillia-players/player-count',
		getValues: function () {
			return toContent( counts.player );
		},
	} );

	wp.blocks.registerBlockBindingsSource( {
		name: 'orillia-players/coach-count',
		getValues: function () {
			return toContent( counts.coach );
		},
	} );
} )( window.wp );
