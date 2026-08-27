( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { TextControl, SelectControl, ToggleControl } = wp.components;
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
					label: __( 'Start date', 'orillia-schedule' ),
					type: 'date',
					value: meta.event_date || '',
					onChange: ( value ) => setMeta( 'event_date', value ),
				} ),
				el( TextControl, {
					label: __( 'End date (optional)', 'orillia-schedule' ),
					help: __( 'Use for tournaments or events spanning more than one day.', 'orillia-schedule' ),
					type: 'date',
					value: meta.event_end_date || '',
					onChange: ( value ) => setMeta( 'event_end_date', value ),
				} ),
				el( TextControl, {
					label: __( 'Time (optional)', 'orillia-schedule' ),
					placeholder: __( 'e.g. 6:30 PM', 'orillia-schedule' ),
					help: __( 'Leave blank when a start time is not available.', 'orillia-schedule' ),
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
				} ),
				el( SelectControl, {
					label: __( 'Event status', 'orillia-schedule' ),
					value: meta.event_status || 'scheduled',
					options: [
						{ label: __( 'Scheduled', 'orillia-schedule' ), value: 'scheduled' },
						{ label: __( 'Cancelled', 'orillia-schedule' ), value: 'cancelled' },
						{ label: __( 'Postponed', 'orillia-schedule' ), value: 'postponed' },
					],
					onChange: ( value ) => setMeta( 'event_status', value ),
				} ),
				el(
					'div',
					{ style: { marginTop: '16px' } },
					el( ToggleControl, {
						label: __( 'Link title to event page', 'orillia-schedule' ),
						help: meta.event_link_enabled
							? __( 'The schedule title opens this event’s page.', 'orillia-schedule' )
							: __( 'The schedule title is displayed as plain text.', 'orillia-schedule' ),
						checked: Boolean( meta.event_link_enabled ),
						onChange: ( value ) => setMeta( 'event_link_enabled', value ),
					} )
				)
			)
		);
	}

	registerPlugin( 'orillia-schedule-details', { render: EventDetailsPanel } );
} )( window.wp );
