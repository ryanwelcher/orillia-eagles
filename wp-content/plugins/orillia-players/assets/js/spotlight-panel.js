/**
 * "Player Spotlight" panel in the post editor sidebar.
 *
 * Shown only for posts using the Player Spotlight template. Edits the
 * optional action photo (the header background) and the header facts.
 */
( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { MediaUpload, MediaUploadCheck } = wp.blockEditor;
	const { Button, TextControl, Flex } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { __ } = wp.i18n;
	const { createElement: el, Fragment } = wp.element;

	const TEMPLATE = 'single-player-spotlight';
	const PHOTO_KEY = 'spotlight_action_photo';
	const FACTS = [
		{ key: 'spotlight_number', label: __( 'Number', 'orillia-players' ) },
		{ key: 'spotlight_team', label: __( 'Team', 'orillia-players' ) },
		{ key: 'spotlight_position', label: __( 'Position', 'orillia-players' ) },
		{ key: 'spotlight_seasons', label: __( 'Seasons', 'orillia-players' ) },
	];

	function SpotlightPanel() {
		const { template, meta, photo } = useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			const postMeta = editor.getEditedPostAttribute( 'meta' ) || {};
			const photoId = postMeta[ PHOTO_KEY ];
			return {
				template: editor.getEditedPostAttribute( 'template' ),
				meta: postMeta,
				photo: photoId ? select( 'core' ).getMedia( photoId ) : null,
			};
		}, [] );
		const { editPost } = useDispatch( 'core/editor' );

		if ( template !== TEMPLATE ) {
			return null;
		}

		const setMeta = ( changes ) => editPost( { meta: changes } );
		const photoId = meta[ PHOTO_KEY ] || 0;
		const preview = photo && ( ( photo.media_details && photo.media_details.sizes && photo.media_details.sizes.medium && photo.media_details.sizes.medium.source_url ) || photo.source_url );

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'orillia-spotlight',
				title: __( 'Player Spotlight', 'orillia-players' ),
				icon: 'star-filled',
			},
			el( 'p', { style: { marginTop: 0, fontWeight: 600 } }, __( 'Action photo (optional)', 'orillia-players' ) ),
			el( 'p', { style: { marginTop: 0, color: '#757575' } }, __( 'Shown behind the header. The featured image is the card photo.', 'orillia-players' ) ),
			el(
				MediaUploadCheck,
				null,
				el( MediaUpload, {
					allowedTypes: [ 'image' ],
					value: photoId,
					onSelect: ( media ) => setMeta( { [ PHOTO_KEY ]: media.id } ),
					render: ( { open } ) =>
						el(
							Fragment,
							null,
							preview &&
								el(
									Button,
									{
										onClick: open,
										label: __( 'Replace action photo', 'orillia-players' ),
										style: { display: 'block', height: 'auto', padding: 0, marginBottom: '8px' },
									},
									el( 'img', { src: preview, alt: '', style: { display: 'block', width: '100%', borderRadius: '2px' } } )
								),
							el(
								Flex,
								{ justify: 'flex-start', gap: 2 },
								el(
									Button,
									{ variant: 'secondary', onClick: open },
									photoId ? __( 'Replace', 'orillia-players' ) : __( 'Set action photo', 'orillia-players' )
								),
								photoId
									? el(
										Button,
										{ variant: 'tertiary', isDestructive: true, onClick: () => setMeta( { [ PHOTO_KEY ]: 0 } ) },
										__( 'Remove', 'orillia-players' )
									)
									: null
							)
						),
				} )
			),
			el( 'p', { style: { margin: '24px 0 8px', fontWeight: 600 } }, __( 'Header facts', 'orillia-players' ) ),
			FACTS.map( ( fact ) =>
				el( TextControl, {
					key: fact.key,
					label: fact.label,
					value: meta[ fact.key ] || '',
					onChange: ( value ) => setMeta( { [ fact.key ]: value } ),
				} )
			)
		);
	}

	registerPlugin( 'orillia-spotlight', { render: SpotlightPanel } );
} )( window.wp );
