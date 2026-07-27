<?php
/**
 * Custom Block Bindings sources exposing live roster counts.
 *
 * Registers two sources so patterns can display the number of players and the
 * number of coaches through block bindings (e.g. bound to a Heading/Paragraph
 * `content` attribute) instead of hardcoding the numbers.
 *
 * @package orillia-players
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Count published players that hold a given roster role.
 *
 * Uses a lightweight query: it only asks for one row and reads `found_posts`,
 * so no full post objects are hydrated.
 *
 * @param string $role_slug The `roster_role` term slug (e.g. 'player', 'coach').
 * @return int Number of matching published players.
 */
function orillia_players_count_by_role( $role_slug ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'player',
			'post_status'    => 'publish',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'roster_role',
					'field'    => 'slug',
					'terms'    => $role_slug,
				),
			),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	return (int) $query->found_posts;
}

/**
 * Register the roster-count block bindings sources.
 *
 * Block bindings values must be strings, so the counts are cast on the way out.
 */
function orillia_players_register_block_bindings() {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}

	register_block_bindings_source(
		'orillia-players/player-count',
		array(
			'label'              => __( 'Player Count', 'orillia-players' ),
			'get_value_callback' => function () {
				return (string) orillia_players_count_by_role( 'player' );
			},
		)
	);

	register_block_bindings_source(
		'orillia-players/coach-count',
		array(
			'label'              => __( 'Coach Count', 'orillia-players' ),
			'get_value_callback' => function () {
				return (string) orillia_players_count_by_role( 'coach' );
			},
		)
	);
}
add_action( 'init', 'orillia_players_register_block_bindings' );

/**
 * Enqueue the editor-only preview script.
 *
 * Server registration handles front-end rendering; this client registration
 * (via wp.blocks.registerBlockBindingsSource) lets the bound value show as the
 * real count in the editor canvas rather than the source label. The counts are
 * a snapshot passed in at load time — no build step required.
 */
function orillia_players_enqueue_block_bindings_editor() {
	$path = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/block-bindings-editor.js';
	$url  = plugins_url( 'assets/js/block-bindings-editor.js', dirname( __FILE__ ) );

	wp_enqueue_script(
		'orillia-players-block-bindings-editor',
		$url,
		array( 'wp-blocks' ),
		file_exists( $path ) ? (string) filemtime( $path ) : '1.1.0',
		true
	);

	wp_localize_script(
		'orillia-players-block-bindings-editor',
		'orilliaPlayersBindings',
		array(
			'counts' => array(
				'player' => orillia_players_count_by_role( 'player' ),
				'coach'  => orillia_players_count_by_role( 'coach' ),
			),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'orillia_players_enqueue_block_bindings_editor' );
