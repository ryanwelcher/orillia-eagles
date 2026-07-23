<?php
/**
 * Title: Archive
 * Slug: orillia-eagles/template-archive
 * Categories: hidden
 * Inserter: false
 */
?>
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"default"},"style":{"spacing":{"blockGap":"0"}}} -->
<main class="wp-block-group"><!-- wp:group {"backgroundColor":"contrast-2","textColor":"contrast","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"},"blockGap":"0"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-contrast-color has-contrast-2-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:paragraph {"textColor":"accent-2","fontSize":"small"} -->
<p class="has-accent-2-color has-text-color has-small-font-size" style="letter-spacing:0.12em;text-transform:uppercase">Archive</p>
<!-- /wp:paragraph -->

<!-- wp:query-title {"type":"archive","fontFamily":"archivo-black","fontSize":"huge"} /-->

<!-- wp:term-description {"textColor":"accent-3","fontSize":"lg"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"0"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:query {"query":{"inherit":true},"layout":{"type":"default"}} -->
<div class="wp-block-query"><!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"backgroundColor":"accent-1","style":{"spacing":{"padding":{"top":"0","bottom":"var:preset|spacing|40","left":"0","right":"0"},"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group has-accent-1-background-color has-background" style="padding-top:0;padding-right:0;padding-bottom:var(--wp--preset--spacing--40);padding-left:0"><!-- wp:post-featured-image {"isLink":true,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}}} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:var(--wp--preset--spacing--30);padding-bottom:0;padding-left:var(--wp--preset--spacing--30)"><!-- wp:post-date {"textColor":"accent-2","fontSize":"small","metadata":{"bindings":{"datetime":{"source":"core/post-data","args":{"field":"date"}}}}} /-->

<!-- wp:post-title {"isLink":true,"fontFamily":"archivo-black","fontSize":"xl"} /-->

<!-- wp:post-excerpt {"textColor":"accent-3","fontSize":"base"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph {"textColor":"accent-3"} -->
<p class="has-accent-3-color has-text-color" >No posts found in this archive.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results -->

<!-- wp:query-pagination {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous {"textColor":"accent-2"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"textColor":"accent-2"} /-->
<!-- /wp:query-pagination --></div>
<!-- /wp:query --></div>
<!-- /wp:group --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
