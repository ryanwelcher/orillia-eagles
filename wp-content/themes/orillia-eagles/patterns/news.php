<?php
/**
 * Title: News Section with Featured Article
 * Slug: orillia-eagles/news
 * Categories: orillia-eagles, featured, query
 * Description: Dynamic news section showing the three latest posts with the newest article featured.
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"News Section"},"align":"full","className":"news","style":{"spacing":{"blockGap":"0"}},"backgroundColor":"base","layout":{"type":"default"},"anchor":"news"} -->
<section class="wp-block-group alignfull news has-base-background-color has-background" id="news"><!-- wp:group {"metadata":{"name":"News Inner"},"className":"news-inner","style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"default"}} -->
<div class="wp-block-group news-inner"><!-- wp:group {"metadata":{"name":"News Header"},"className":"news-header animate-on-scroll","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group news-header animate-on-scroll"><!-- wp:paragraph {"className":"section-label","textColor":"accent-1","fontSize":"small"} -->
<p class="section-label has-accent-1-color has-text-color has-small-font-size">News &amp; Updates</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"news-headline","textColor":"contrast","fontSize":"huge","fontFamily":"archivo-black"} -->
<h2 class="wp-block-heading news-headline has-contrast-color has-text-color has-archivo-black-font-family has-huge-font-size">From the Bench</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:query {"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[],"format":[],"disable_pagination":true},"namespace":"advanced-query-loop","className":"news-query","layout":{"type":"default"}} -->
<div class="wp-block-query news-query"><!-- wp:post-template {"className":"news-grid"} -->
<!-- wp:group {"tagName":"article","metadata":{"name":"News Card"},"className":"news-card animate-on-scroll","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<article class="wp-block-group news-card animate-on-scroll"><!-- wp:group {"metadata":{"name":"News Image Wrap"},"className":"news-card-image-wrap","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group news-card-image-wrap"><!-- wp:post-featured-image {"isLink":true,"sizeSlug":"full","className":"news-card-image"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"News Card Body"},"className":"news-card-body","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group news-card-body"><!-- wp:post-date {"isLink":true,"className":"news-card-date","textColor":"accent-1"} /-->

<!-- wp:post-title {"isLink":true,"level":3,"className":"news-card-title","textColor":"contrast","fontSize":"xl","fontFamily":"archivo-black"} /-->

<!-- wp:post-excerpt {"moreText":"","excerptLength":34,"className":"news-card-excerpt","textColor":"contrast-2","fontSize":"base"} /-->

<!-- wp:read-more {"content":"Read Full Story","className":"news-card-link","textColor":"accent-1","fontSize":"small"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:paragraph {"className":"news-empty","textColor":"contrast-2"} -->
<p class="news-empty has-contrast-2-color has-text-color">No news stories have been published yet.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
