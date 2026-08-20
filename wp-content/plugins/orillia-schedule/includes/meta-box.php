<?php
/**
 * Schedule event custom fields: date, time, location, opponent.
 *
 * The UI lives in the block editor as a PluginDocumentSettingPanel
 * (assets/js/schedule-details-panel.js). This file registers the
 * post meta and the admin list-table columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function orillia_schedule_register_meta() {
	$auth = function () {
		return current_user_can( 'edit_posts' );
	};

	register_post_meta(
		'game_event',
		'event_date',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		'game_event',
		'event_time',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		'game_event',
		'event_location',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		'game_event',
		'event_opponent',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => $auth,
		)
	);
}
add_action( 'init', 'orillia_schedule_register_meta' );

/**
 * Enqueue the Document Settings Panel script on the Event edit screen only.
 */
function orillia_schedule_enqueue_panel_script() {
	$screen = get_current_screen();

	if ( ! $screen || 'game_event' !== $screen->post_type ) {
		return;
	}

	$script_path = plugin_dir_path( __DIR__ ) . 'assets/js/schedule-details-panel.js';

	wp_enqueue_script(
		'orillia-schedule-details-panel',
		plugins_url( 'assets/js/schedule-details-panel.js', __DIR__ ),
		array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-i18n' ),
		file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0',
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'orillia_schedule_enqueue_panel_script' );

/**
 * Show Date, Time, and Location as admin list-table columns.
 */
function orillia_schedule_columns( $columns ) {
	$columns['event_date']     = __( 'Date', 'orillia-schedule' );
	$columns['event_time']     = __( 'Time', 'orillia-schedule' );
	$columns['event_location'] = __( 'Location', 'orillia-schedule' );
	return $columns;
}
add_filter( 'manage_game_event_posts_columns', 'orillia_schedule_columns' );

function orillia_schedule_column_content( $column, $post_id ) {
	if ( 'event_date' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'event_date', true ) );
	}
	if ( 'event_time' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'event_time', true ) );
	}
	if ( 'event_location' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'event_location', true ) );
	}
}
add_action( 'manage_game_event_posts_custom_column', 'orillia_schedule_column_content', 10, 2 );
