( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { TextControl } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	function EventDetailsPanel() {
		const postType = useSelect(
			( select ) => select( 'core/editor' ).getCurrentPostType(),
			[]
		);

		const meta = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
			[]
		);

		const { editPost } = useDispatch( 'core/editor' );

		if ( 'game_event' !== postType ) {
			return null;
		}

		const setMeta = ( key, value ) =>
			editPost( { meta: { ...meta, [ key ]: value } } );

		return el(
			PluginDocumentSettingPanel,
			{ name: 'orillia-schedule-details', title: __( 'Event Details', 'orillia-schedule' ) },
			el(
				Fragment,
				null,
				el( TextControl, {
					label: __( 'Date (YYYY-MM-DD)', 'orillia-schedule' ),
					type: 'date',
					value: meta.event_date || '',
					onChange: ( value ) => setMeta( 'event_date', value ),
				} ),
				el( TextControl, {
					label: __( 'Time', 'orillia-schedule' ),
					placeholder: __( 'e.g. 6:30 PM', 'orillia-schedule' ),
					value: meta.event_time || '',
					onChange: ( value ) => setMeta( 'event_time', value ),
				} ),
				el( TextControl, {
					label: __( 'Location', 'orillia-schedule' ),
					value: meta.event_location || '',
					onChange: ( value ) => setMeta( 'event_location', value ),
				} ),
				el( TextControl, {
					label: __( 'Opponent (games only)', 'orillia-schedule' ),
					value: meta.event_opponent || '',
					onChange: ( value ) => setMeta( 'event_opponent', value ),
				} )
			)
		);
	}

	registerPlugin( 'orillia-schedule-details', { render: EventDetailsPanel } );
} )( window.wp );
