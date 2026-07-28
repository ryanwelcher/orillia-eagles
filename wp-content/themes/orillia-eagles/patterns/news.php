<?php
/**
 * Title: News Section with Featured Article
 * Slug: orillia-eagles/news
 * Categories: orillia-eagles, featured
 * Description: News section with a featured article image and a set of supporting news cards.
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

<!-- wp:group {"metadata":{"name":"News Grid"},"className":"news-grid","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group news-grid"><!-- wp:group {"tagName":"article","metadata":{"name":"Featured News Card"},"className":"news-card news-cardu002du002dfeatured animate-on-scroll","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<article class="wp-block-group news-card news-card--featured animate-on-scroll"><!-- wp:group {"metadata":{"name":"Featured Image Wrap"},"className":"news-card-image-wrap","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group news-card-image-wrap"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"news-card-image"} -->
<figure class="wp-block-image size-full news-card-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/news-feature.jpg' ) ); ?>" alt="Orillia Eagles players on the bench during a game"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Featured Card Body"},"className":"news-card-body","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group news-card-body"><!-- wp:paragraph {"className":"news-card-date has-text-color","textColor":"accent-1"} -->
<p class="news-card-date has-text-color has-accent-1-color has-text-color">March 22, 2026</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"className":"news-card-title","textColor":"contrast","fontSize":"xxx-large","fontFamily":"archivo-black"} -->
<h3 class="wp-block-heading news-card-title has-contrast-color has-text-color has-archivo-black-font-family has-xxx-large-font-size">Eagles Soar at Regional Tournament</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"news-card-excerpt has-text-color","textColor":"contrast-2","fontSize":"base"} -->
<p class="news-card-excerpt has-text-color has-contrast-2-color has-base-font-size">The team brought home the Sportsmanship Award at the Lake Country Regional Tournament this past weekend. Every player stepped up, gave their best, and reminded everyone in the arena what hockey is really about.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"news-card-link","textColor":"accent-1","fontSize":"small"} -->
<p class="news-card-link has-accent-1-color has-text-color has-small-font-size"><a href="#">Read Full Story</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></article>
<!-- /wp:group -->

<!-- wp:group {"tagName":"article","metadata":{"name":"News Card - Spring Registration"},"className":"news-card animate-on-scroll","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<article class="wp-block-group news-card animate-on-scroll"><!-- wp:group {"metadata":{"name":"Card Body - Spring Registration"},"className":"news-card-body","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group news-card-body"><!-- wp:paragraph {"className":"news-card-date has-text-color","textColor":"accent-1"} -->
<p class="news-card-date has-text-color has-accent-1-color has-text-color">March 15, 2026</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"className":"news-card-title","textColor":"contrast","fontSize":"xl","fontFamily":"archivo-black"} -->
<h3 class="wp-block-heading news-card-title has-contrast-color has-text-color has-archivo-black-font-family has-xl-font-size">Spring Registration Now Open</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"news-card-excerpt has-text-color","textColor":"contrast-2","fontSize":"base"} -->
<p class="news-card-excerpt has-text-color has-contrast-2-color has-base-font-size">New and returning players can register for the upcoming spring season. No experience required — just bring a smile and a willingness to try. Equipment loans available for new families.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"news-card-link","textColor":"accent-1","fontSize":"small"} -->
<p class="news-card-link has-accent-1-color has-text-color has-small-font-size"><a href="#">Registration Details</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></article>
<!-- /wp:group -->

<!-- wp:group {"tagName":"article","metadata":{"name":"News Card - Community Skate"},"className":"news-card animate-on-scroll","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<article class="wp-block-group news-card animate-on-scroll"><!-- wp:group {"metadata":{"name":"Card Body - Community Skate"},"className":"news-card-body","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group news-card-body"><!-- wp:paragraph {"className":"news-card-date has-text-color","textColor":"accent-1"} -->
<p class="news-card-date has-text-color has-accent-1-color has-text-color">February 28, 2026</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"className":"news-card-title","textColor":"contrast","fontSize":"xl","fontFamily":"archivo-black"} -->
<h3 class="wp-block-heading news-card-title has-contrast-color has-text-color has-archivo-black-font-family has-xl-font-size">Community Skate Raises $4,200</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"news-card-excerpt has-text-color","textColor":"contrast-2","fontSize":"base"} -->
<p class="news-card-excerpt has-text-color has-contrast-2-color has-base-font-size">Our annual fundraiser brought together over 150 community members for an afternoon of skating, hot chocolate, and incredible generosity. Funds go directly to player equipment and ice time.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"news-card-link","textColor":"accent-1","fontSize":"small"} -->
<p class="news-card-link has-accent-1-color has-text-color has-small-font-size"><a href="#">See the Highlights</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></article>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
