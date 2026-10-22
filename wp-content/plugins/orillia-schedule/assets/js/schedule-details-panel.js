( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { TextControl, SelectControl, ToggleControl, FormTokenField } = wp.components;
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

	// The default Opponents panel lets an event have several; the picker in
	// Event Details below keeps it to one.
	wp.data.dispatch( 'core/editor' ).removeEditorPanel( 'taxonomy-panel-event_opponent' );

	/**
	 * Pick one opponent from the event_opponent list, or type a new name to
	 * add it to the list.
	 */
	function OpponentField() {
		const opponents = useSelect(
			( select ) =>
				select( 'core' ).getEntityRecords( 'taxonomy', 'event_opponent', { per_page: -1, orderby: 'name', order: 'asc' } ) || [],
			[]
		);
		const selectedIds = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'event_opponent' ) || [],
			[]
		);
		const { editPost } = useDispatch( 'core/editor' );
		const { saveEntityRecord } = useDispatch( 'core' );

		const selected = opponents.filter( ( term ) => selectedIds.includes( term.id ) ).map( ( term ) => term.name );

		const onChange = async ( tokens ) => {
			const last = tokens[ tokens.length - 1 ];
			const name = ( typeof last === 'string' ? last : last?.value || '' ).trim();
			if ( ! name ) {
				editPost( { event_opponent: [] } );
				return;
			}
			const existing = opponents.find( ( term ) => term.name.toLowerCase() === name.toLowerCase() );
			const term = existing || ( await saveEntityRecord( 'taxonomy', 'event_opponent', { name } ) );
			if ( term ) {
				editPost( { event_opponent: [ term.id ] } );
			}
		};

		return el( FormTokenField, {
			label: __( 'Opponent (games only)', 'orillia-schedule' ),
			value: selected,
			suggestions: opponents.map( ( term ) => term.name ),
			maxLength: 1,
			onChange,
			__experimentalExpandOnFocus: true,
			__experimentalShowHowTo: false,
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
		} );
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
				el( OpponentField ),
				el(
					'p',
					{ className: 'components-form-token-field__help' },
					__( 'Pick an opponent, or type a new name to add it. Manage the list under Schedule → Opponents.', 'orillia-schedule' )
				),
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
