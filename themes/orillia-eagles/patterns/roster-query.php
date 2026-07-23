<?php
/**
 * Title: Team Roster: Players & Coaches
 * Slug: handmade-jewelry-website/roster-query
 * Categories: query
 * Description: Team roster grid showing Player and Coach roles from the shared roster post type, with Junior, Intermediate, and Senior filters.
 */
?>
<!-- wp:group {"metadata":{"name":"Roster Team Tabs"},"className":"roster-tabs","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
<div class="wp-block-group roster-tabs"><!-- wp:buttons {"className":"roster-tabs-buttons","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
<div class="wp-block-buttons roster-tabs-buttons"><!-- wp:button {"className":"roster-tab roster-tab--junior"} -->
<div class="wp-block-button roster-tab roster-tab--junior"><a class="wp-block-button__link wp-element-button" href="#roster">Junior</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"roster-tab roster-tab--intermediate"} -->
<div class="wp-block-button roster-tab roster-tab--intermediate"><a class="wp-block-button__link wp-element-button" href="#roster">Intermediate/Senior</a></div>
<!-- /wp:button -->
 
<!-- wp:button {"className":"roster-tab roster-tab--coaches"} -->
<div class="wp-block-button roster-tab roster-tab--coaches"><a class="wp-block-button__link wp-element-button" href="#roster">Coaches</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:query {"query":{"perPage":50,"pages":0,"offset":0,"postType":"player","order":"asc","orderBy":"meta_value_num","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[],"format":[]},"namespace":"advanced-query-loop","className":"roster-query","layout":{"type":"default"}} -->
<div class="wp-block-query roster-query"><!-- wp:post-template {"className":"roster-grid"} -->
<!-- wp:group {"className":"roster-player animate-on-scroll","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group roster-player animate-on-scroll"><!-- wp:post-featured-image {"sizeSlug":"large","className":"roster-player-photo"} /-->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"player_number"}}}},"className":"roster-number has-text-color has-archivo-black-font-family","textColor":"accent-1","fontSize":"xx-large"} -->
<p class="roster-number has-text-color has-archivo-black-font-family has-accent-1-color has-xx-large-font-size">#</p>
<!-- /wp:paragraph -->

<!-- wp:post-title {"className":"roster-name has-text-color","textColor":"base","fontSize":"lg"} /-->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"player_position"}}}},"className":"roster-position","fontSize":"small"} -->
<p class="roster-position has-small-font-size"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>No players or coaches found.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
