<?php
/**
 * Player custom fields: jersey number and position.
 *
 * The UI for these fields lives entirely in the block editor as a
 * PluginDocumentSettingPanel (see assets/js/player-details-panel.js).
 * This file only registers the underlying post meta and the admin
 * list-table columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register post meta so fields are exposed to REST / the block editor,
 * and so the editor can read/write them via the core/editor data store.
 */
function orillia_players_register_meta() {
	register_post_meta(
		'player',
		'player_number',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'integer',
			'default'       => 0,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_post_meta(
		'player',
		'player_position',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'orillia_players_register_meta' );

/**
 * Enqueue the Document Settings Panel script on the Player edit screen only.
 */
function orillia_players_enqueue_panel_script() {
	$screen = get_current_screen();

	if ( ! $screen || 'player' !== $screen->post_type ) {
		return;
	}

	$script_path = plugin_dir_path( __DIR__ ) . 'assets/js/player-details-panel.js';

	wp_enqueue_script(
		'orillia-players-details-panel',
		plugins_url( 'assets/js/player-details-panel.js', __DIR__ ),
		array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-i18n' ),
		file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0',
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'orillia_players_enqueue_panel_script' );

/**
 * Show Number and Position as admin list table columns.
 */
function orillia_players_columns( $columns ) {
	$columns['player_number']   = __( 'Number', 'orillia-players' );
	$columns['player_position'] = __( 'Position', 'orillia-players' );
	return $columns;
}
add_filter( 'manage_player_posts_columns', 'orillia_players_columns' );

function orillia_players_column_content( $column, $post_id ) {
	if ( 'player_number' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'player_number', true ) );
	}
	if ( 'player_position' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'player_position', true ) );
	}
}
add_action( 'manage_player_posts_custom_column', 'orillia_players_column_content', 10, 2 );
