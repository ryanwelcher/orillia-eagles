<?php
/**
 * Registers the "Schedule" (game_event) custom post type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function orillia_schedule_register_post_type() {
	$labels = array(
		'name'               => __( 'Schedule', 'orillia-schedule' ),
		'singular_name'      => __( 'Event', 'orillia-schedule' ),
		'add_new'            => __( 'Add New', 'orillia-schedule' ),
		'add_new_item'       => __( 'Add New Event', 'orillia-schedule' ),
		'edit_item'          => __( 'Edit Event', 'orillia-schedule' ),
		'new_item'           => __( 'New Event', 'orillia-schedule' ),
		'view_item'          => __( 'View Event', 'orillia-schedule' ),
		'view_items'         => __( 'View Schedule', 'orillia-schedule' ),
		'search_items'       => __( 'Search Schedule', 'orillia-schedule' ),
		'not_found'          => __( 'No events found', 'orillia-schedule' ),
		'not_found_in_trash' => __( 'No events found in Trash', 'orillia-schedule' ),
		'all_items'          => __( 'All Events', 'orillia-schedule' ),
		'menu_name'          => __( 'Schedule', 'orillia-schedule' ),
	);

	$args = array(
		'labels'             => $labels,
		// Events are surfaced only through the schedule table pattern (a query
		// loop), never via their own archive or pretty single URLs, so the post
		// type stays out of front-end routing (no archive, no rewrite, hidden
		// from search) while remaining fully editable in the admin.
		// publicly_queryable must be true so the type counts as "viewable"
		// (is_post_type_viewable) — the core Query Loop block only renders
		// viewable post types, so without this the schedule table shows no rows.
		'public'             => false,
		'publicly_queryable' => true,
		'exclude_from_search' => true,
		'has_archive'        => false,
		'rewrite'            => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'menu_icon'          => 'dashicons-calendar-alt',
		'menu_position'      => 21,
		// 'editor' is required so WordPress loads the block editor for this
		// type (use_block_editor_for_post_type checks editor support); the
		// Event Details PluginDocumentSettingPanel only renders in the block
		// editor. 'custom-fields' exposes the meta to REST for the panel.
		'supports'           => array( 'title', 'editor', 'custom-fields' ),
		'hierarchical'       => false,
	);

	register_post_type( 'game_event', $args );
}
add_action( 'init', 'orillia_schedule_register_post_type' );
