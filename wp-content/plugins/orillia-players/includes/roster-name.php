<?php
/**
 * Shorten player names on the roster grid.
 *
 * The roster grid shows each name through a Post Title block with the
 * `roster-name` class. While that block renders, its title is shortened to
 * the first name plus the last initial (e.g. "Ryan Mckinnon" -> "Ryan M."),
 * or to "Coach" plus the first name for anyone with the coach roster role.
 * The stored post title is unchanged, so the full name still shows in the
 * admin and everywhere else on the site.
 *
 * @package orillia-players
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert a full name to the first name plus the last initial.
 *
 * Names with only one word are returned unchanged.
 *
 * @param string $name Full name.
 * @return string Shortened name.
 */
function orillia_players_short_name( $name ) {
	$parts = preg_split( '/\s+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );

	if ( count( $parts ) < 2 ) {
		return $name;
	}

	$last_initial = mb_strtoupper( mb_substr( end( $parts ), 0, 1 ) );

	return $parts[0] . ' ' . $last_initial . '.';
}

/**
 * `the_title` callback used only while a roster-name block renders.
 *
 * @param string $title   Post title.
 * @param int    $post_id Post ID.
 * @return string Shortened title.
 */
function orillia_players_roster_title_filter( $title, $post_id = 0 ) {
	if ( $post_id && has_term( 'coach', 'roster_role', $post_id ) ) {
		$parts = preg_split( '/\s+/u', trim( $title ), -1, PREG_SPLIT_NO_EMPTY );
		/* translators: %s: coach's first name. */
		return sprintf( __( 'Coach %s', 'orillia-players' ), $parts[0] ?? $title );
	}

	return orillia_players_short_name( $title );
}

/**
 * Check whether a parsed block is the roster-name Post Title block.
 *
 * @param array $block Parsed block.
 * @return bool
 */
function orillia_players_is_roster_name_block( $block ) {
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) ) {
		return false;
	}

	$classes = explode( ' ', $block['attrs']['className'] ?? '' );

	return in_array( 'roster-name', $classes, true );
}

/**
 * Turn the title filter on before a roster-name block renders.
 *
 * @param string|null $pre_render Short-circuit value.
 * @param array       $block      Parsed block.
 * @return string|null Unchanged short-circuit value.
 */
function orillia_players_roster_name_start( $pre_render, $block ) {
	if ( orillia_players_is_roster_name_block( $block ) ) {
		add_filter( 'the_title', 'orillia_players_roster_title_filter', 20, 2 );
	}
	return $pre_render;
}
add_filter( 'pre_render_block', 'orillia_players_roster_name_start', 10, 2 );

/**
 * Turn the title filter off after a roster-name block renders.
 *
 * @param string $content Rendered block content.
 * @param array  $block   Parsed block.
 * @return string Unchanged block content.
 */
function orillia_players_roster_name_end( $content, $block ) {
	if ( orillia_players_is_roster_name_block( $block ) ) {
		remove_filter( 'the_title', 'orillia_players_roster_title_filter', 20 );
	}
	return $content;
}
add_filter( 'render_block', 'orillia_players_roster_name_end', 10, 2 );
