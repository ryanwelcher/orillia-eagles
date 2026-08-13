<?php
/**
 * Registers the "Player" custom post type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function orillia_players_register_post_type() {
	$labels = array(
		'name'                  => __( 'Players', 'orillia-players' ),
		'singular_name'         => __( 'Player', 'orillia-players' ),
		'add_new'               => __( 'Add New', 'orillia-players' ),
		'add_new_item'          => __( 'Add New Player', 'orillia-players' ),
		'edit_item'             => __( 'Edit Player', 'orillia-players' ),
		'new_item'              => __( 'New Player', 'orillia-players' ),
		'view_item'             => __( 'View Player', 'orillia-players' ),
		'view_items'            => __( 'View Players', 'orillia-players' ),
		'search_items'          => __( 'Search Players', 'orillia-players' ),
		'not_found'             => __( 'No players found', 'orillia-players' ),
		'not_found_in_trash'    => __( 'No players found in Trash', 'orillia-players' ),
		'all_items'             => __( 'All Players', 'orillia-players' ),
		'archives'              => __( 'Player Archives', 'orillia-players' ),
		'menu_name'             => __( 'Players', 'orillia-players' ),
		'featured_image'        => __( 'Player Photo', 'orillia-players' ),
		'set_featured_image'    => __( 'Set player photo', 'orillia-players' ),
		'remove_featured_image' => __( 'Remove player photo', 'orillia-players' ),
	);

	$args = array(
		'labels'       => $labels,
		'public'       => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-groups',
		'menu_position'=> 20,
		'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
		'has_archive'  => false,
		'rewrite'      => false,
		'show_in_menu' => true,
		'hierarchical' => false,
	);

	register_post_type( 'player', $args );
}
add_action( 'init', 'orillia_players_register_post_type' );
