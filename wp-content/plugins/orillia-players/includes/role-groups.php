<?php
/**
 * Roster role groups: Coaching Staff and Executive.
 *
 * A role group is a parent roster role. Anyone with the parent role, or one
 * of its child roles (e.g. Coach and Assistant Coach under Coaching Staff, or
 * President/Founder and Treasurer under Executive), is listed under that
 * group's roster tab. On their roster card, the position line shows their
 * roster roles as their title instead of a hockey position. The parent roles
 * themselves are not shown.
 *
 * @package orillia-players
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parent roster role slugs that act as role groups.
 *
 * @return string[]
 */
function orillia_players_role_groups() {
	return array( 'coaching-staff', 'executive' );
}

/**
 * Check whether a roster entry has a group's parent role or one of its child roles.
 *
 * @param int    $post_id Post ID.
 * @param string $group   Parent roster role slug.
 * @return bool
 */
function orillia_players_in_role_group( $post_id, $group ) {
	$parent = get_term_by( 'slug', $group, 'roster_role' );

	if ( ! $parent ) {
		return false;
	}

	$role_ids   = get_term_children( $parent->term_id, 'roster_role' );
	$role_ids   = is_array( $role_ids ) ? $role_ids : array();
	$role_ids[] = $parent->term_id;

	return has_term( array_map( 'intval', $role_ids ), 'roster_role', $post_id );
}

/**
 * Check whether a roster entry is on the coaching staff.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function orillia_players_is_coach( $post_id ) {
	return orillia_players_in_role_group( $post_id, 'coaching-staff' );
}

/**
 * Check whether a roster entry is an executive.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function orillia_players_is_executive( $post_id ) {
	return orillia_players_in_role_group( $post_id, 'executive' );
}

/**
 * Add a roster_role-{group} class for each group a roster entry belongs to,
 * so the roster tabs and styles pick up people who only have a child role.
 *
 * @param string[] $classes   Post classes.
 * @param string[] $css_class Extra classes passed in.
 * @param int      $post_id   Post ID.
 * @return string[] Post classes.
 */
function orillia_players_role_group_post_class( $classes, $css_class, $post_id ) {
	if ( 'player' !== get_post_type( $post_id ) ) {
		return $classes;
	}

	foreach ( orillia_players_role_groups() as $group ) {
		$class = 'roster_role-' . $group;
		if ( ! in_array( $class, $classes, true ) && orillia_players_in_role_group( $post_id, $group ) ) {
			$classes[] = $class;
		}
	}

	return $classes;
}
add_filter( 'post_class', 'orillia_players_role_group_post_class', 10, 3 );

/**
 * Get the titles to show for a roster entry: every roster role except the
 * group parents.
 *
 * @param int $post_id Post ID.
 * @return string[] Role names.
 */
function orillia_players_role_titles( $post_id ) {
	$terms = get_the_terms( $post_id, 'roster_role' );

	if ( ! is_array( $terms ) ) {
		return array();
	}

	$titles = array();
	foreach ( $terms as $term ) {
		if ( ! in_array( $term->slug, orillia_players_role_groups(), true ) ) {
			$titles[] = $term->name;
		}
	}

	return $titles;
}

/**
 * Replace the roster-position text with the coach's or executive's titles.
 *
 * @param string $content Rendered paragraph.
 * @param array  $block   Parsed block.
 * @return string Paragraph content.
 */
function orillia_players_render_role_titles( $content, $block ) {
	$classes = explode( ' ', $block['attrs']['className'] ?? '' );
	$post_id = get_the_ID();

	if ( ! in_array( 'roster-position', $classes, true ) || ! $post_id ) {
		return $content;
	}

	if ( ! orillia_players_is_coach( $post_id ) && ! orillia_players_is_executive( $post_id ) ) {
		return $content;
	}

	$titles = implode( ' / ', orillia_players_role_titles( $post_id ) );

	// Coaching staff show their team in front, e.g. "Junior Coach" or "Intermediate/Senior Coach".
	$teams = orillia_players_is_coach( $post_id ) ? get_the_terms( $post_id, 'team' ) : false;
	if ( is_array( $teams ) && $teams ) {
		usort(
			$teams,
			function ( $a, $b ) {
				return $a->term_id - $b->term_id;
			}
		);
		$titles = implode( '/', wp_list_pluck( $teams, 'name' ) ) . ' ' . $titles;
	}

	return preg_replace_callback(
		'/(<p\b[^>]*>).*?(<\/p>)/s',
		function ( $matches ) use ( $titles ) {
			return $matches[1] . esc_html( $titles ) . $matches[2];
		},
		$content,
		1
	);
}
add_filter( 'render_block_core/paragraph', 'orillia_players_render_role_titles', 10, 2 );
