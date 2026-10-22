( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { TextControl, SelectControl, ToggleControl } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __, sprintf } = wp.i18n;

	// Times are entered on a 24-hour clock (stored as HH:MM) and shown on the
	// site as 12-hour with AM/PM. Matches orillia_schedule_normalize_time().
	function parseTime( value ) {
		const match = /^([01]?\d|2[0-3]):([0-5]\d)$/.exec( ( value || '' ).trim() );
		return match ? { hours: Number( match[ 1 ] ), minutes: match[ 2 ] } : null;
	}

	function timeHelp( value ) {
		if ( ! ( value || '' ).trim() ) {
			return __( '24-hour clock, e.g. 18:30. Leave blank when a start time is not available.', 'orillia-schedule' );
		}
		const time = parseTime( value );
		if ( ! time ) {
			return __( 'Use the 24-hour HH:MM format, e.g. 08:00 or 18:30.', 'orillia-schedule' );
		}
		const hours12 = time.hours % 12 || 12;
		const period = time.hours < 12 ? 'AM' : 'PM';
		/* translators: %s: the time as it appears on the site, e.g. 6:30 PM */
		return sprintf( __( 'Shows on the site as %s.', 'orillia-schedule' ), `${ hours12 }:${ time.minutes } ${ period }` );
	}

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
					label: __( 'Time (24-hour, optional)', 'orillia-schedule' ),
					placeholder: '18:30',
					inputMode: 'numeric',
					maxLength: 5,
					help: timeHelp( meta.event_time ),
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
