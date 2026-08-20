<?php
/**
 * Plugin Name: Orillia Eagles Schedule
 * Description: Team schedule (games & practices) with Event Type and Level taxonomies plus date/time/location/opponent fields.
 * Version: 1.0.0
 * Author: Studio Code
 * Text Domain: orillia-schedule
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/post-type.php';
require_once __DIR__ . '/includes/taxonomy.php';
require_once __DIR__ . '/includes/meta-box.php';

/**
 * On activation: register CPT/taxonomies so rewrite rules pick them up,
 * seed default terms, then flush rewrite rules.
 */
function orillia_schedule_activate() {
	orillia_schedule_register_post_type();
	orillia_schedule_register_taxonomies();
	orillia_schedule_seed_default_types();
	orillia_schedule_seed_default_levels();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'orillia_schedule_activate' );

function orillia_schedule_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'orillia_schedule_deactivate' );

/**
 * Advanced Query Loop exposes orderBy=meta_value on its Query block, but
 * WP_Query also requires a top-level meta_key to sort by. Set it (and treat
 * the value as a DATE) whenever a game_event query orders by meta_value, so the
 * schedule table sorts chronologically by event_date without extra config.
 */
function orillia_schedule_aql_query_vars( $query_args, $block_query, $inherited ) {
	if ( isset( $block_query['postType'] ) && 'game_event' === $block_query['postType']
		&& isset( $query_args['orderby'] ) && 'meta_value' === $query_args['orderby'] ) {
		$query_args['meta_key']  = 'event_date'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query_args['meta_type'] = 'DATE';
	}
	return $query_args;
}
add_filter( 'aql_query_vars', 'orillia_schedule_aql_query_vars', 10, 3 );
