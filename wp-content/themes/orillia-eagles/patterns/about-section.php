<?php
/**
 * Title: About: Our Story with Roster Stats
 * Slug: orillia-eagles/about-section
 * Categories: orillia-eagles, about, featured
 * Description: "Our Story" about section with image, intro text, and three roster stats. The players and coaches numbers pull live counts through the orillia-players block bindings.
 * Keywords: about, story, stats, roster, players, coaches
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"About Section"},"align":"full","className":"about","style":{"spacing":{"blockGap":"0"}},"backgroundColor":"base-2","layout":{"type":"default"},"anchor":"about"} -->
<section class="wp-block-group alignfull about has-base-2-background-color has-background" id="about"><!-- wp:group {"metadata":{"name":"About Inner"},"className":"about-inner","style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"default"}} -->
<div class="wp-block-group about-inner"><!-- wp:group {"metadata":{"name":"About Lead"},"className":"about-lead animate-on-scroll","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group about-lead animate-on-scroll"><!-- wp:paragraph {"className":"section-label","textColor":"accent-1","fontSize":"small"} -->
<p class="section-label has-accent-1-color has-text-color has-small-font-size">Our Story</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"about-headline","textColor":"contrast","fontSize":"huge","fontFamily":"archivo-black"} -->
<h2 class="wp-block-heading about-headline has-contrast-color has-text-color has-archivo-black-font-family has-huge-font-size"><span class="about-headline-gold">Every player</span> deserves a place on the ice.</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"About Grid"},"className":"about-grid","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group about-grid"><!-- wp:group {"metadata":{"name":"About Image Wrap"},"className":"about-image-wrap animate-on-scroll","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group about-image-wrap animate-on-scroll"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"about-image"} -->
<figure class="wp-block-image size-full about-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/about-team.jpg' ) ); ?>" alt="<?php esc_attr_e( 'Orillia Eagles players together on the bench', 'orillia-eagles' ); ?>"/></figure>
<!-- /wp:image -->

<!-- wp:group {"metadata":{"name":"About Image Accent [Decorative]"},"className":"about-image-accent miles-editor-hidden","style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-group about-image-accent miles-editor-hidden"></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"About Text"},"className":"about-text animate-on-scroll","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group about-text animate-on-scroll"><!-- wp:paragraph {"className":"about-body has-text-color","textColor":"contrast-2","fontSize":"lg"} -->
<p class="about-body has-text-color has-contrast-2-color has-lg-font-size">The Orillia Eagles are more than a hockey team. We're a family of players, parents, and volunteers united by the belief that the joy of sport belongs to everyone — regardless of ability.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"about-body has-text-color","textColor":"contrast-2","fontSize":"lg"} -->
<p class="about-body has-text-color has-contrast-2-color has-lg-font-size">Founded in Orillia, Ontario, our program gives athletes with special needs the chance to experience the thrill of skating, the camaraderie of a locker room, and the pride of wearing a team jersey. Our volunteer coaches adapt every drill, every game, and every celebration to ensure each player grows, laughs, and feels like a champion.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":"About Values"},"className":"about-values","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group about-values"><!-- wp:group {"metadata":{"name":"About Value - Players"},"className":"about-value","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group about-value"><!-- wp:paragraph {"className":"about-value-number has-text-color has-archivo-black-font-family","textColor":"accent-1","fontSize":"xxx-large","metadata":{"bindings":{"content":{"source":"orillia-players/player-count"}}}} -->
<p class="about-value-number has-text-color has-archivo-black-font-family has-accent-1-color has-xxx-large-font-size">14</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"about-value-label has-text-color","textColor":"contrast-2","fontSize":"small"} -->
<p class="about-value-label has-text-color has-contrast-2-color has-small-font-size">Rostered Players</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"About Value - Coaches"},"className":"about-value","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group about-value"><!-- wp:paragraph {"className":"about-value-number has-text-color has-archivo-black-font-family","textColor":"accent-1","fontSize":"xxx-large","metadata":{"bindings":{"content":{"source":"orillia-players/coach-count"}}}} -->
<p class="about-value-number has-text-color has-archivo-black-font-family has-accent-1-color has-xxx-large-font-size">8</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"about-value-label has-text-color","textColor":"contrast-2","fontSize":"small"} -->
<p class="about-value-label has-text-color has-contrast-2-color has-small-font-size">Volunteer Coaches</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"About Value - Community"},"className":"about-value","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group about-value"><!-- wp:paragraph {"className":"about-value-number has-text-color has-archivo-black-font-family","textColor":"accent-1","fontSize":"xxx-large"} -->
<p class="about-value-number has-text-color has-archivo-black-font-family has-accent-1-color has-xxx-large-font-size">1</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"about-value-label has-text-color","textColor":"contrast-2","fontSize":"small"} -->
<p class="about-value-label has-text-color has-contrast-2-color has-small-font-size">Incredible Community</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
