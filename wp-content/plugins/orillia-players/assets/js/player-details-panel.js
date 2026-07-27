( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { Notice, TextControl, SelectControl } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { __ } = wp.i18n;
	const { createElement: el } = wp.element;

	const POSITIONS = [
		{ label: __( '— Select —', 'orillia-players' ), value: '' },
		{ label: __( 'Forward', 'orillia-players' ), value: 'Forward' },
		{ label: __( 'Defence', 'orillia-players' ), value: 'Defence' },
		{ label: __( 'Centre', 'orillia-players' ), value: 'Centre' },
		{ label: __( 'Goalie', 'orillia-players' ), value: 'Goalie' },
	];

	function PlayerDetailsPanel() {
		const { meta, roleIds, roles } = useSelect(
			( select ) => ( {
				meta: select( 'core/editor' ).getEditedPostAttribute( 'meta' ),
				roleIds: select( 'core/editor' ).getEditedPostAttribute( 'roster_role' ) || [],
				roles: select( 'core' ).getEntityRecords( 'taxonomy', 'roster_role', { per_page: 100 } ) || [],
			} ),
			[]
		);
		const { editPost } = useDispatch( 'core/editor' );

		const number = meta && meta.player_number && meta.player_number !== 999 ? meta.player_number : '';
		const position = meta && meta.player_position ? meta.player_position : '';
		const coachRole = roles.find( ( role ) => role.slug === 'coach' );
		const isCoach = coachRole && roleIds.includes( coachRole.id );

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'orillia-player-details',
				title: __( 'Player Details', 'orillia-players' ),
				icon: 'groups',
			},
			isCoach
				? el( Notice, {
					status: 'info',
					isDismissible: false,
				}, __( 'Coach cards use the selected Team automatically and do not display a jersey number.', 'orillia-players' ) )
				: el( wp.element.Fragment, null,
					el( TextControl, {
						label: __( 'Number', 'orillia-players' ),
						type: 'number',
						min: 0,
						max: 99,
						value: number,
						onChange: ( value ) => {
							editPost( {
								meta: { player_number: value === '' ? 0 : Number( value ) },
							} );
						},
					} ),
					el( SelectControl, {
						label: __( 'Position', 'orillia-players' ),
						value: position,
						options: POSITIONS,
						onChange: ( value ) => {
							editPost( { meta: { player_position: value } } );
						},
					} )
				)
		);
	}

	registerPlugin( 'orillia-player-details', {
		render: PlayerDetailsPanel,
	} );
} )( window.wp );
