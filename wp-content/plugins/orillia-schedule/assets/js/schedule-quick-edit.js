/**
 * Fill the Quick Edit Time field with the event's stored 24-hour time, which
 * the Time column keeps in a hidden element next to the formatted value.
 */
( function ( $ ) {
	if ( ! window.inlineEditPost ) {
		return;
	}
	const edit = window.inlineEditPost.edit;
	window.inlineEditPost.edit = function ( id ) {
		edit.apply( this, arguments );
		const postId = typeof id === 'object' ? parseInt( this.getId( id ), 10 ) : id;
		const time = $( '#post-' + postId + ' .orillia-schedule-time-raw' ).text();
		$( '#edit-' + postId + ' input[name="event_time"]' ).val( time );
	};
} )( jQuery );
