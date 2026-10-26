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
		const inRoleGroup = ( slug ) => {
			const group = roles.find( ( role ) => role.slug === slug );
			return !! group && roles.some(
				( role ) => ( role.id === group.id || role.parent === group.id ) && roleIds.includes( role.id )
			);
		};
		const isCoach = inRoleGroup( 'coaching-staff' );
		const isExecutive = inRoleGroup( 'executive' );

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
				}, __( 'Coaching Staff cards show their other Roster Roles (e.g. Coach, Assistant Coach) as their title and do not display a jersey number.', 'orillia-players' ) )
				: isExecutive
				? el( Notice, {
					status: 'info',
					isDismissible: false,
				}, __( 'Executive cards show their other Roster Roles (e.g. President, Treasurer) as their title and do not display a jersey number.', 'orillia-players' ) )
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
