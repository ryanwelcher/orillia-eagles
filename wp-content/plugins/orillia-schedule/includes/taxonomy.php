<?php
/**
 * Registers the "Event Type" and "Level" taxonomies for the schedule
 * and seeds their default terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function orillia_schedule_register_taxonomies() {
	$type_labels = array(
		'name'          => __( 'Event Types', 'orillia-schedule' ),
		'singular_name' => __( 'Event Type', 'orillia-schedule' ),
		'all_items'     => __( 'All Event Types', 'orillia-schedule' ),
		'edit_item'     => __( 'Edit Event Type', 'orillia-schedule' ),
		'add_new_item'  => __( 'Add New Event Type', 'orillia-schedule' ),
		'menu_name'     => __( 'Event Types', 'orillia-schedule' ),
	);

	register_taxonomy(
		'event_type',
		array( 'game_event' ),
		array(
			'labels'            => $type_labels,
			'public'            => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'event-type' ),
		)
	);

	$level_labels = array(
		'name'          => __( 'Levels', 'orillia-schedule' ),
		'singular_name' => __( 'Level', 'orillia-schedule' ),
		'all_items'     => __( 'All Levels', 'orillia-schedule' ),
		'edit_item'     => __( 'Edit Level', 'orillia-schedule' ),
		'add_new_item'  => __( 'Add New Level', 'orillia-schedule' ),
		'menu_name'     => __( 'Levels', 'orillia-schedule' ),
	);

	register_taxonomy(
		'event_level',
		array( 'game_event' ),
		array(
			'labels'            => $level_labels,
			'public'            => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'event-level' ),
		)
	);

	$opponent_labels = array(
		'name'          => __( 'Opponents', 'orillia-schedule' ),
		'singular_name' => __( 'Opponent', 'orillia-schedule' ),
		'all_items'     => __( 'All Opponents', 'orillia-schedule' ),
		'edit_item'     => __( 'Edit Opponent', 'orillia-schedule' ),
		'update_item'   => __( 'Update Opponent', 'orillia-schedule' ),
		'add_new_item'  => __( 'Add New Opponent', 'orillia-schedule' ),
		'new_item_name' => __( 'New Opponent Name', 'orillia-schedule' ),
		'search_items'  => __( 'Search Opponents', 'orillia-schedule' ),
		'not_found'     => __( 'No opponents found.', 'orillia-schedule' ),
		'back_to_items' => __( '&larr; Go to Opponents', 'orillia-schedule' ),
		'menu_name'     => __( 'Opponents', 'orillia-schedule' ),
	);

	// Opponents are a reusable list, not a public archive: managed under
	// Schedule → Opponents and picked in the Event Details panel (one per
	// event), with no front-end pages. The default editor and Quick Edit
	// boxes allow several terms, so they are hidden.
	register_taxonomy(
		'event_opponent',
		array( 'game_event' ),
		array(
			'labels'             => $opponent_labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => false,
			'show_admin_column'  => false,
			'hierarchical'       => false,
			'rewrite'            => false,
			'query_var'          => false,
		)
	);
}
add_action( 'init', 'orillia_schedule_register_taxonomies' );

/**
 * An event has at most one opponent. If a save sets more than one, keep the
 * one just added (or the first, if none is new).
 */
function orillia_schedule_single_opponent( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
	if ( 'event_opponent' !== $taxonomy || count( $tt_ids ) < 2 ) {
		return;
	}
	$added = array_values( array_diff( array_map( 'intval', $tt_ids ), array_map( 'intval', $old_tt_ids ) ) );
	$keep  = get_term_by( 'term_taxonomy_id', $added ? end( $added ) : (int) $tt_ids[0], 'event_opponent' );
	if ( $keep ) {
		wp_set_object_terms( $object_id, array( (int) $keep->term_id ), 'event_opponent' );
	}
}
add_action( 'set_object_terms', 'orillia_schedule_single_opponent', 10, 6 );

/**
 * Opponents used to be a free-text event_opponent field. Move any typed
 * opponents into the taxonomy once, then drop the old field.
 */
function orillia_schedule_migrate_opponents() {
	if ( get_option( 'orillia_schedule_opponents_migrated' ) ) {
		return;
	}
	$events = get_posts(
		array(
			'post_type'   => 'game_event',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => 'event_opponent', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);
	foreach ( $events as $event_id ) {
		$name = trim( (string) get_post_meta( $event_id, 'event_opponent', true ) );
		if ( '' !== $name ) {
			wp_set_object_terms( $event_id, array( $name ), 'event_opponent' );
		}
		delete_post_meta( $event_id, 'event_opponent' );
	}
	update_option( 'orillia_schedule_opponents_migrated', 1 );
}
add_action( 'init', 'orillia_schedule_migrate_opponents', 20 );

/** The opponent's name for an event, or '' if none. */
function orillia_schedule_get_opponent( $post_id ) {
	$terms = get_the_terms( $post_id, 'event_opponent' );
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
}

/**
 * Seed the default event types if they don't already exist.
 */
function orillia_schedule_seed_default_types() {
	$types = array( 'Game', 'Practice' );

	foreach ( $types as $type ) {
		if ( ! term_exists( $type, 'event_type' ) ) {
			wp_insert_term( $type, 'event_type' );
		}
	}
}

/**
 * Seed the default levels if they don't already exist.
 */
function orillia_schedule_seed_default_levels() {
	$levels = array( 'Mixed', 'Junior', 'Intermediate', 'Senior' );

	foreach ( $levels as $level ) {
		if ( ! term_exists( $level, 'event_level' ) ) {
			wp_insert_term( $level, 'event_level' );
		}
	}
}
