<?php
/**
 * Title: Team Schedule Table
 * Slug: orillia-eagles/schedule-query
 * Categories: orillia-eagles, query
 * Description: Chronological schedule table of Games and Practices from the game_event post type, sorted by date, with alternating rows.
 */
?>
<!-- wp:group {"metadata":{"name":"Schedule Table"},"className":"schedule-table","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group schedule-table"><!-- wp:group {"metadata":{"name":"Schedule Header Row"},"className":"schedule-row schedule-row--head","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group schedule-row schedule-row--head"><!-- wp:paragraph {"className":"schedule-cell schedule-cell--date"} -->
<p class="schedule-cell schedule-cell--date">Date</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"schedule-cell schedule-cell--time"} -->
<p class="schedule-cell schedule-cell--time">Time</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"schedule-cell schedule-cell--event"} -->
<p class="schedule-cell schedule-cell--event">Event</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"schedule-cell schedule-cell--level"} -->
<p class="schedule-cell schedule-cell--level">Level</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"schedule-cell schedule-cell--location"} -->
<p class="schedule-cell schedule-cell--location">Location</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:query {"query":{"perPage":50,"pages":0,"offset":0,"postType":"game_event","order":"asc","orderBy":"meta_value","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[],"format":[]},"namespace":"advanced-query-loop","className":"schedule-query","layout":{"type":"default"}} -->
<div class="wp-block-query schedule-query"><!-- wp:post-template {"className":"schedule-body"} -->
<!-- wp:group {"className":"schedule-row","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group schedule-row"><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"event_date"}}}},"className":"schedule-cell schedule-cell--date"} -->
<p class="schedule-cell schedule-cell--date"></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"event_time"}}}},"className":"schedule-cell schedule-cell--time"} -->
<p class="schedule-cell schedule-cell--time"></p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"schedule-cell schedule-cell--event","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group schedule-cell schedule-cell--event"><!-- wp:post-title {"level":0,"className":"schedule-event-title","fontSize":"small"} /-->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"event_opponent"}}}},"className":"schedule-event-opponent has-small-font-size"} -->
<p class="schedule-event-opponent has-small-font-size"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:post-terms {"term":"event_level","className":"schedule-cell schedule-cell--level"} /-->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"event_location"}}}},"className":"schedule-cell schedule-cell--location"} -->
<p class="schedule-cell schedule-cell--location"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>No upcoming events scheduled.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></div>
<!-- /wp:group -->
