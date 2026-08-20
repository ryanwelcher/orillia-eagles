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
}
add_action( 'init', 'orillia_schedule_register_taxonomies' );

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
