<?php
/**
 * Plugin Name: Orillia Eagles Players
 * Description: Roster management with Team and Roster Role taxonomies plus player number/position fields.
 * Version: 1.1.0
 * Author: Studio Code
 * Text Domain: orillia-players
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/post-type.php';
require_once __DIR__ . '/includes/taxonomy.php';
require_once __DIR__ . '/includes/meta-box.php';

/**
 * On activation: register CPT/taxonomy so rewrite rules pick them up,
 * seed the default team terms, then flush rewrite rules.
 */
function orillia_players_activate() {
	orillia_players_register_post_type();
	orillia_players_register_taxonomy();
	orillia_players_seed_default_teams();
	orillia_players_seed_default_roles();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'orillia_players_activate' );

function orillia_players_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'orillia_players_deactivate' );

/**
 * Advanced Query Loop exposes orderBy=meta_value_num on its Query block,
 * but WP_Query also requires a top-level meta_key to sort by. Set it
 * automatically whenever a Player query orders by player_number so the
 * roster grid sorts by jersey number without extra editor configuration.
 */
function orillia_players_aql_query_vars( $query_args, $block_query, $inherited ) {
	if ( isset( $block_query['postType'] ) && 'player' === $block_query['postType']
		&& isset( $query_args['orderby'] ) && 'meta_value_num' === $query_args['orderby'] ) {
		$query_args['meta_key'] = 'player_number'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}
	return $query_args;
}
add_filter( 'aql_query_vars', 'orillia_players_aql_query_vars', 10, 3 );
