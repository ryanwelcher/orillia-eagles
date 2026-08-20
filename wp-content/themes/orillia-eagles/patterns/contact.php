<?php
/**
 * Title: Contact Section with Form & Details
 * Slug: orillia-eagles/contact
 * Categories: orillia-eagles, featured
 * Description: Contact section with a message form and contact details.
 */
?>

<!-- wp:group {"tagName":"section","metadata":{"name":"Contact Section"},"align":"full","className":"contact","style":{"spacing":{"blockGap":"0"}},"backgroundColor":"base","layout":{"type":"default"},"anchor":"contact"} -->
<section class="wp-block-group alignfull contact has-base-background-color has-background" id="contact"><!-- wp:group {"metadata":{"name":"Contact Inner"},"className":"contact-inner","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group contact-inner"><!-- wp:group {"metadata":{"name":"Contact Info"},"className":"contact-info animate-on-scroll","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group contact-info animate-on-scroll"><!-- wp:paragraph {"className":"section-label","textColor":"accent-1","fontSize":"small"} -->
<p class="section-label has-accent-1-color has-text-color has-small-font-size">Get in Touch</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"contact-headline","textColor":"contrast","fontSize":"huge","fontFamily":"archivo-black"} -->
<h2 class="wp-block-heading contact-headline has-contrast-color has-text-color has-archivo-black-font-family has-huge-font-size">We'd love to hear from you.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"contact-body has-text-color","textColor":"contrast-2","fontSize":"lg"} -->
<p class="contact-body has-text-color has-contrast-2-color has-lg-font-size">Questions about joining? Interested in sponsoring? Just want to say hi? Drop us a line and we'll get back to you.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":"Contact Details"},"className":"contact-details","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group contact-details"><!-- wp:group {"metadata":{"name":"Contact Detail - Email"},"className":"contact-detail-item","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group contact-detail-item"><!-- wp:paragraph {"className":"contact-detail-label has-text-color","textColor":"accent-1"} -->
<p class="contact-detail-label has-text-color has-accent-1-color has-text-color">Email</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"contact-detail-value","textColor":"contrast","fontSize":"base"} -->
<p class="contact-detail-value has-contrast-color has-text-color has-base-font-size"><a href="mailto:info@orilliaeagles.ca">info@orilliaeagles.ca</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Contact Detail - Home Ice"},"className":"contact-detail-item","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group contact-detail-item"><!-- wp:paragraph {"className":"contact-detail-label has-text-color","textColor":"accent-1"} -->
<p class="contact-detail-label has-text-color has-accent-1-color has-text-color">Home Ice</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"contact-detail-value","textColor":"contrast","fontSize":"base"} -->
<p class="contact-detail-value has-contrast-color has-text-color has-base-font-size">Orillia Recreation Centre</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Contact Detail - Practice"},"className":"contact-detail-item","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group contact-detail-item"><!-- wp:paragraph {"className":"contact-detail-label has-text-color","textColor":"accent-1"} -->
<p class="contact-detail-label has-text-color has-accent-1-color has-text-color">Practice</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"contact-detail-value","textColor":"contrast","fontSize":"base"} -->
<p class="contact-detail-value has-contrast-color has-text-color has-base-font-size">Saturdays, 10:00 AM</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Contact Form Wrap"},"className":"contact-form-wrap animate-on-scroll","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group contact-form-wrap animate-on-scroll"><!-- wp:jetpack/contact-form {"subject":"New website contact","to":"info@orilliaeagles.ca","jetpackCRM":false,"variationName":"default","lock":{"remove":true,"move":true},"className":"contact-form","layout":{"type":"default"}} -->
<div class="wp-block-jetpack-contact-form contact-form"><!-- wp:jetpack/field-name {"required":true,"fieldVariant":"name","className":"wp-block-jetpack-field-name form-group contact-name"} -->
<div><!-- wp:jetpack/label {"label":"Name","className":"form-label"} /-->

<!-- wp:jetpack/input {"placeholder":"Your full name","className":"form-input"} /--></div>
<!-- /wp:jetpack/field-name -->

<!-- wp:jetpack/field-email {"required":true,"className":"wp-block-jetpack-field-email form-group contact-email"} -->
<div><!-- wp:jetpack/label {"label":"Email","className":"form-label"} /-->

<!-- wp:jetpack/input {"placeholder":"your@email.com","type":"email","className":"form-input"} /--></div>
<!-- /wp:jetpack/field-email -->

<!-- wp:jetpack/field-select {"id":"","required":true,"options":["General information","New player","Sponsorship","Volunteering"],"className":"form-group contact-interest form-select"} -->
<div><!-- wp:jetpack/label {"label":"I'm interested in..."} /-->

<!-- wp:jetpack/input {"placeholder":"Select one option","type":"dropdown","style":{"border":{"style":"solid"}}} /--></div>
<!-- /wp:jetpack/field-select -->

<!-- wp:jetpack/field-textarea {"required":true,"className":"wp-block-jetpack-field-textarea form-group contact-message"} -->
<div><!-- wp:jetpack/label {"label":"Message","className":"form-label"} /-->

<!-- wp:jetpack/input {"placeholder":"Tell us a bit about yourself...","type":"textarea","className":"form-input form-textarea"} /--></div>
<!-- /wp:jetpack/field-textarea -->

<!-- wp:button {"tagName":"button","type":"submit","lock":{"move":false,"remove":true},"className":"form-submit"} -->
<div class="wp-block-button form-submit"><button type="submit" class="wp-block-button__link wp-element-button">Send Message</button></div>
<!-- /wp:button --></div>
<!-- /wp:jetpack/contact-form --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->