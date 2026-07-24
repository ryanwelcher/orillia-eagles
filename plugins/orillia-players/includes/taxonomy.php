<?php
/**
 * Registers the "Team" taxonomy for Players and seeds default terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function orillia_players_register_taxonomy() {
	$labels = array(
		'name'          => __( 'Teams', 'orillia-players' ),
		'singular_name' => __( 'Team', 'orillia-players' ),
		'search_items'  => __( 'Search Teams', 'orillia-players' ),
		'all_items'     => __( 'All Teams', 'orillia-players' ),
		'edit_item'     => __( 'Edit Team', 'orillia-players' ),
		'update_item'   => __( 'Update Team', 'orillia-players' ),
		'add_new_item'  => __( 'Add New Team', 'orillia-players' ),
		'new_item_name' => __( 'New Team Name', 'orillia-players' ),
		'menu_name'     => __( 'Teams', 'orillia-players' ),
	);

	$args = array(
		'labels'            => $labels,
		'public'            => true,
		'show_in_rest'      => true,
		'hierarchical'      => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'team' ),
	);

	register_taxonomy( 'team', array( 'player' ), $args );

	$role_labels = array(
		'name'          => __( 'Roster Roles', 'orillia-players' ),
		'singular_name' => __( 'Roster Role', 'orillia-players' ),
		'search_items'  => __( 'Search Roster Roles', 'orillia-players' ),
		'all_items'     => __( 'All Roster Roles', 'orillia-players' ),
		'edit_item'     => __( 'Edit Roster Role', 'orillia-players' ),
		'update_item'   => __( 'Update Roster Role', 'orillia-players' ),
		'add_new_item'  => __( 'Add New Roster Role', 'orillia-players' ),
		'new_item_name' => __( 'New Roster Role Name', 'orillia-players' ),
		'menu_name'     => __( 'Roster Roles', 'orillia-players' ),
	);

	register_taxonomy(
		'roster_role',
		array( 'player' ),
		array(
			'labels'            => $role_labels,
			'public'            => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'roster-role' ),
		)
	);
}
add_action( 'init', 'orillia_players_register_taxonomy' );

/**
 * Seed the three default teams if they don't already exist.
 */
function orillia_players_seed_default_teams() {
	$teams = array( 'Junior', 'Intermediate', 'Senior' );

	foreach ( $teams as $team ) {
		if ( ! term_exists( $team, 'team' ) ) {
			wp_insert_term( $team, 'team' );
		}
	}
}

/**
 * Seed the default roster roles.
 */
function orillia_players_seed_default_roles() {
	$roles = array( 'Player', 'Coach' );

	foreach ( $roles as $role ) {
		if ( ! term_exists( $role, 'roster_role' ) ) {
			wp_insert_term( $role, 'roster_role' );
		}
	}
}

/**
 * Keep coaches in the number-sorted roster query without exposing a jersey number.
 */
function orillia_players_sync_coach_sort_value( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( 'roster_role' !== $taxonomy || 'player' !== get_post_type( $object_id ) ) {
		return;
	}

	if ( has_term( 'coach', 'roster_role', $object_id ) ) {
		update_post_meta( $object_id, 'player_number', 999 );
	}
}
add_action( 'set_object_terms', 'orillia_players_sync_coach_sort_value', 10, 4 );
