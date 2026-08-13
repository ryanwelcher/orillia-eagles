<?php
/**
 * Title: Single Post
 * Slug: orillia-eagles/template-single
 * Categories: hidden
 * Inserter: false
 */
?>

<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:group {"tagName":"section","metadata":{"name":"Post Hero"},"align":"full","className":"post-hero","style":{"spacing":{"blockGap":"0"}},"backgroundColor":"contrast","textColor":"base","layout":{"type":"default"}} -->
<section class="wp-block-group alignfull post-hero has-base-color has-contrast-background-color has-text-color has-background"><!-- wp:cover {"useFeaturedImage":true,"dimRatio":90,"minHeight":40,"minHeightUnit":"vh","gradient":"scrim-bottom","contentPosition":"bottom left","align":"full","className":"post-hero-cover has-background-gradient","style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-bottom-left post-hero-cover has-background-gradient" style="min-height:40vh"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-90 has-background-dim has-background-gradient has-scrim-bottom-gradient-background"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"metadata":{"name":"Post Hero Meta"},"className":"post-hero-meta","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group post-hero-meta"><!-- wp:post-terms {"term":"category","className":"post-hero-category","textColor":"base","fontSize":"small"} /-->

<!-- wp:post-date {"metadata":{"bindings":{"datetime":{"source":"core/post-data","args":{"field":"date"}}}},"className":"post-hero-date has-text-color","textColor":"white-muted","fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"level":1,"className":"post-hero-title","fontSize":"huge","fontFamily":"archivo-black"} /--></div></div>
<!-- /wp:cover --></section>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Post Content Section"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"blockGap":"0"}},"backgroundColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)"><!-- wp:post-content {"align":"wide","className":"post-body","layout":{"type":"constrained"}} /-->

<!-- wp:group {"metadata":{"name":"Post Tags Row"},"className":"post-tags-row","style":{"spacing":{"blockGap":"var:preset|spacing|20","margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group post-tags-row" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:post-terms {"term":"post_tag","prefix":"Tagged: ","className":"post-tags","textColor":"contrast-2","fontSize":"small"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Post Navigation"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"blockGap":"0"}},"backgroundColor":"base-2","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:group {"className":"post-nav-row","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
<div class="wp-block-group post-nav-row"><!-- wp:post-navigation-link {"type":"previous","label":"← Previous Post","className":"post-nav-link","textColor":"contrast","fontSize":"base"} /-->

<!-- wp:post-navigation-link {"label":"Next Post →","className":"post-nav-link","textColor":"contrast","fontSize":"base"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

</main>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"orillia-eagles/contact"} /-->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->