<?php
/**
 * Title: Roster Query
 * Slug: handmade-jewelry-website/roster-query
 * Categories: query
 * Description: Player roster grid pulling from the Player custom post type via Advanced Query Loop, with team filter tabs.
 */

$roster_teams = get_terms(
	array(
		'taxonomy'   => 'team',
		'hide_empty' => true,
		'slug'       => array( 'junior', 'senior', 'intermediate' ),
	)
);
if ( is_wp_error( $roster_teams ) ) {
	$roster_teams = array();
}

$roster_team_slugs = wp_list_pluck( $roster_teams, 'slug' );
?>
<?php if ( $roster_team_slugs ) : ?>
<!-- wp:group {"className":"roster-tabs","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"},"metadata":{"name":"Roster Team Tabs"}} -->
<div class="wp-block-group roster-tabs"><!-- wp:buttons {"className":"roster-tabs-buttons","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
<div class="wp-block-buttons roster-tabs-buttons">
<?php if ( in_array( 'junior', $roster_team_slugs, true ) ) : ?>
<!-- wp:button {"className":"roster-tab roster-tab--junior"} -->
<div class="wp-block-button roster-tab roster-tab--junior"><a class="wp-block-button__link wp-element-button" href="#roster">Junior</a></div>
<!-- /wp:button -->
<?php endif; ?>
<?php if ( in_array( 'intermediate', $roster_team_slugs, true ) ) : ?>
<!-- wp:button {"className":"roster-tab roster-tab--intermediate"} -->
<div class="wp-block-button roster-tab roster-tab--intermediate"><a class="wp-block-button__link wp-element-button" href="#roster">Intermediate</a></div>
<!-- /wp:button -->
<?php endif; ?>
<?php if ( in_array( 'senior', $roster_team_slugs, true ) ) : ?>
<!-- wp:button {"className":"roster-tab roster-tab--senior"} -->
<div class="wp-block-button roster-tab roster-tab--senior"><a class="wp-block-button__link wp-element-button" href="#roster">Senior</a></div>
<!-- /wp:button -->
<?php endif; ?>
</div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
<?php endif; ?>

<!-- wp:query {"query":{"perPage":50,"pages":0,"offset":0,"postType":"player","order":"asc","orderBy":"meta_value_num","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[],"format":[]},"namespace":"advanced-query-loop","className":"roster-query","layout":{"type":"default"}} -->
<div class="wp-block-query roster-query"><!-- wp:post-template {"className":"roster-grid"} -->
<!-- wp:group {"className":"roster-player animate-on-scroll","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group roster-player animate-on-scroll"><!-- wp:post-featured-image {"sizeSlug":"large","className":"roster-player-photo"} /-->

<!-- wp:paragraph {"textColor":"accent-1","className":"roster-number has-text-color has-archivo-black-font-family","fontSize":"xx-large","metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"player_number"}}}}} -->
<p class="roster-number has-text-color has-archivo-black-font-family has-accent-1-color has-xx-large-font-size">#</p>
<!-- /wp:paragraph -->

<!-- wp:post-title {"textColor":"base","className":"roster-name has-text-color","fontSize":"lg","isLink":false} /-->

<!-- wp:paragraph {"className":"roster-position","fontSize":"small","metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"player_position"}}}}} -->
<p class="roster-position has-small-font-size"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>No players found.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
