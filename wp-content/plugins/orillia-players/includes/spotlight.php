<?php
/**
 * Turn Player Spotlight form submissions into draft posts.
 *
 * When someone submits the Jetpack form on the Player Spotlight page
 * (slug `player-spotlight`), a draft post is created in the Player Spotlight
 * category using the theme's "Player Spotlight" template:
 *
 * - The title is "Meet" plus the first name and last initial.
 * - Number, team, position and seasons are saved as post meta and shown in
 *   the template's header (trading card + facts table).
 * - The body holds the "joining the Eagles" answer as a pull quote, the
 *   "love about hockey" picks as tags, the favourites as tiles, and every
 *   other answer under a short heading.
 * - If a roster player has the same name and a photo, that photo becomes
 *   the featured image (the card photo).
 * - An optional action photo (post meta, picked in the editor sidebar)
 *   becomes the header background.
 *
 * Contact details and form bookkeeping (email, consent, who filled it out)
 * are left out of the post. They stay in the Jetpack Feedback entry.
 *
 * For submissions made before this existed, run:
 *     studio wp orillia-spotlight generate
 * Add --regenerate to rebuild posts that are still drafts.
 *
 * @package orillia-players
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ORILLIA_SPOTLIGHT_PAGE_SLUG    = 'player-spotlight';
const ORILLIA_SPOTLIGHT_CATEGORY     = 'player-spotlight';
const ORILLIA_SPOTLIGHT_TEMPLATE     = 'single-player-spotlight';
const ORILLIA_SPOTLIGHT_FEEDBACK_KEY = '_orillia_spotlight_feedback_id';
const ORILLIA_SPOTLIGHT_POST_KEY     = '_orillia_spotlight_post_id';
const ORILLIA_SPOTLIGHT_ACTION_PHOTO = 'spotlight_action_photo';

/**
 * Post meta keys for the header facts, with their sidebar labels.
 *
 * @return array<string, string> Meta key => label.
 */
function orillia_spotlight_fact_keys() {
	return array(
		'spotlight_number'   => __( 'Number', 'orillia-players' ),
		'spotlight_team'     => __( 'Team', 'orillia-players' ),
		'spotlight_position' => __( 'Position', 'orillia-players' ),
		'spotlight_seasons'  => __( 'Seasons', 'orillia-players' ),
	);
}

/**
 * Register the spotlight post meta so the editor and block bindings can use it.
 */
function orillia_spotlight_register_meta() {
	$can_edit = function () {
		return current_user_can( 'edit_posts' );
	};

	foreach ( array_keys( orillia_spotlight_fact_keys() ) as $key ) {
		register_post_meta(
			'post',
			$key,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $can_edit,
			)
		);
	}

	register_post_meta(
		'post',
		ORILLIA_SPOTLIGHT_ACTION_PHOTO,
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'integer',
			'default'       => 0,
			'auth_callback' => $can_edit,
		)
	);
}
add_action( 'init', 'orillia_spotlight_register_meta' );

/**
 * Enqueue the Player Spotlight sidebar panel in the post editor.
 */
function orillia_spotlight_enqueue_panel_script() {
	$screen = get_current_screen();

	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}

	$script_path = plugin_dir_path( __DIR__ ) . 'assets/js/spotlight-panel.js';

	wp_enqueue_script(
		'orillia-spotlight-panel',
		plugins_url( 'assets/js/spotlight-panel.js', __DIR__ ),
		array( 'wp-plugins', 'wp-editor', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-core-data', 'wp-i18n' ),
		file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0',
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'orillia_spotlight_enqueue_panel_script' );

/*
 * ---------------------------------------------------------------------------
 * Reading a submission
 * ---------------------------------------------------------------------------
 */

/**
 * Normalize a form label so small punctuation differences still match.
 *
 * @param string $label Form label.
 * @return string Lowercase letters and digits only.
 */
function orillia_spotlight_label_key( $label ) {
	return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( (string) $label ) ) );
}

/**
 * What each known form question becomes in the post.
 *
 * Roles: `fact` (header meta, value = meta key), `quote` (pull quote),
 * `loves` (tags), `favourite` (tile, value = tile label), `answer`
 * (Q&A item, value = short heading), `private` (left out).
 * Questions not listed here become answers under their full label.
 *
 * @return array<string, array{0: string, 1: string}> Normalized label => [ role, value ].
 */
function orillia_spotlight_field_roles() {
	$roles = array(
		'Jersey number'                                    => array( 'fact', 'spotlight_number' ),
		'Which team do you play on?'                       => array( 'fact', 'spotlight_team' ),
		'What position do you play?'                       => array( 'fact', 'spotlight_position' ),
		'How long have you played with the Eagles?'        => array( 'fact', 'spotlight_seasons' ),
		'What would you tell someone who is thinking about joining the Eagles?' => array( 'quote', '' ),
		'What do you love most about hockey? (pick as many as you like)' => array( 'loves', '' ),
		'Favourite NHL team'                               => array( 'favourite', __( 'NHL team', 'orillia-players' ) ),
		'Favourite hockey player'                          => array( 'favourite', __( 'Hockey player', 'orillia-players' ) ),
		'What song gets you pumped up before a game?'      => array( 'favourite', __( 'Pump-up song', 'orillia-players' ) ),
		'What is your favourite food?'                     => array( 'favourite', __( 'Food', 'orillia-players' ) ),
		'What is your favourite hockey memory with the Eagles?' => array( 'answer', __( 'Favourite Eagles memory', 'orillia-players' ) ),
		"What is something you've gotten better at this season?" => array( 'answer', __( 'Better at this season', 'orillia-players' ) ),
		"What do you like to do when you're not playing hockey?" => array( 'answer', __( 'Off the ice', 'orillia-players' ) ),
		'If you could have any superpower, what would it be?' => array( 'answer', __( 'Superpower', 'orillia-players' ) ),
		"What is something fun most people don't know about you?" => array( 'answer', __( 'Fun fact', 'orillia-players' ) ),
		"Is there anyone you'd like to give a shout-out to? (a coach, teammate, family member, volunteer…)" => array( 'answer', __( 'Shout-out', 'orillia-players' ) ),
		'Who filled out this form?'                        => array( 'private', '' ),
	);

	$keyed = array();
	foreach ( $roles as $label => $role ) {
		$keyed[ orillia_spotlight_label_key( $label ) ] = $role;
	}

	return $keyed;
}

/**
 * Sort a feedback entry's answers into the parts of a spotlight post.
 *
 * @param int $feedback_id Feedback post ID.
 * @return array|null {
 *     @type string   $name       Full name as submitted.
 *     @type array    $facts      Meta key => value.
 *     @type string   $quote      "Joining the Eagles" answer.
 *     @type string[] $loves      Picked options.
 *     @type array    $favourites List of [ label, value ].
 *     @type array    $answers    List of [ heading, answer ].
 * }
 */
function orillia_spotlight_read_feedback( $feedback_id ) {
	if ( ! class_exists( '\Automattic\Jetpack\Forms\ContactForm\Feedback' ) ) {
		return null;
	}

	$feedback = \Automattic\Jetpack\Forms\ContactForm\Feedback::get( $feedback_id );

	if ( ! $feedback ) {
		return null;
	}

	$roles = orillia_spotlight_field_roles();
	$data  = array(
		'name'       => '',
		'facts'      => array(),
		'quote'      => '',
		'loves'      => array(),
		'favourites' => array(),
		'answers'    => array(),
	);

	foreach ( $feedback->get_fields() as $field ) {
		$type  = $field->get_type();
		$label = trim( (string) $field->get_label() );
		$value = $field->get_value();

		if ( 'name' === $type && '' === $data['name'] ) {
			$data['name'] = trim( (string) $value );
			continue;
		}

		if ( in_array( $type, array( 'email', 'consent', 'hidden' ), true ) ) {
			continue;
		}

		$value = is_array( $value )
			? array_values( array_filter( array_map( 'trim', array_map( 'strval', $value ) ), 'strlen' ) )
			: trim( (string) $value );

		if ( '' === $label || array() === $value || '' === $value ) {
			continue;
		}

		list( $role, $extra ) = $roles[ orillia_spotlight_label_key( $label ) ] ?? array( 'answer', $label );

		if ( 'private' === $role ) {
			continue;
		}

		// Multi-pick answers only make sense as tags; flatten anything else.
		if ( is_array( $value ) && 'loves' !== $role ) {
			$value = implode( ', ', $value );
		}

		switch ( $role ) {
			case 'fact':
				$data['facts'][ $extra ] = 'spotlight_number' === $extra ? ltrim( $value, '#' ) : $value;
				break;
			case 'quote':
				$data['quote'] = $value;
				break;
			case 'loves':
				$data['loves'] = (array) $value;
				break;
			case 'favourite':
				$data['favourites'][] = array( $extra, $value );
				break;
			default:
				$data['answers'][] = array( $extra, $value );
		}
	}

	if ( '' === $data['name'] ) {
		$data['name'] = __( 'Player', 'orillia-players' );
	}

	return $data;
}

/**
 * The generated post title: "Meet" plus first name and last initial.
 *
 * @param string $name Full name.
 * @return string
 */
function orillia_spotlight_title( $name ) {
	/* translators: %s: player's first name and last initial. */
	return sprintf( __( 'Meet %s', 'orillia-players' ), orillia_players_short_name( $name ) );
}

/*
 * ---------------------------------------------------------------------------
 * Creating and rebuilding posts
 * ---------------------------------------------------------------------------
 */

/**
 * Create a draft spotlight post right after the feedback entry is saved.
 *
 * @param int   $feedback_id Feedback post ID.
 * @param array $fields      Form fields (unused).
 * @param bool  $is_spam     Whether the submission is spam.
 */
function orillia_spotlight_on_submission( $feedback_id, $fields, $is_spam ) {
	if ( $is_spam ) {
		return;
	}

	orillia_spotlight_create_post( $feedback_id );
}
add_action( 'grunion_after_feedback_post_inserted', 'orillia_spotlight_on_submission', 10, 3 );

/**
 * Get the ID of the Player Spotlight page.
 *
 * @return int Page ID, or 0 if the page does not exist.
 */
function orillia_spotlight_page_id() {
	$page = get_page_by_path( ORILLIA_SPOTLIGHT_PAGE_SLUG );
	return $page ? (int) $page->ID : 0;
}

/**
 * Check whether a feedback entry came from the Player Spotlight form.
 *
 * @param WP_Post $feedback Feedback post.
 * @return bool
 */
function orillia_spotlight_is_spotlight_feedback( $feedback ) {
	$page_id = orillia_spotlight_page_id();

	if ( ! $page_id ) {
		return false;
	}

	$source_id = (int) get_post_meta( $feedback->ID, '_feedback_source_post_id', true );

	return $page_id === $source_id || $page_id === (int) $feedback->post_parent;
}

/**
 * Build a draft spotlight post from a feedback entry.
 *
 * Does nothing if the entry is not from the spotlight form, or if a post
 * was already made for it.
 *
 * @param int $feedback_id Feedback post ID.
 * @return int|WP_Error|false New post ID, an error, or false if skipped.
 */
function orillia_spotlight_create_post( $feedback_id ) {
	$feedback_post = get_post( $feedback_id );

	if ( ! $feedback_post || 'feedback' !== $feedback_post->post_type ) {
		return false;
	}

	if ( ! orillia_spotlight_is_spotlight_feedback( $feedback_post ) ) {
		return false;
	}

	if ( get_post_meta( $feedback_id, ORILLIA_SPOTLIGHT_POST_KEY, true ) ) {
		return false;
	}

	$data = orillia_spotlight_read_feedback( $feedback_id );

	if ( ! $data ) {
		return false;
	}

	$source_page = get_post( orillia_spotlight_page_id() );
	$category    = get_category_by_slug( ORILLIA_SPOTLIGHT_CATEGORY );

	$post_id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'draft',
			'post_title'    => orillia_spotlight_title( $data['name'] ),
			'post_content'  => orillia_spotlight_content( $data ),
			'post_author'   => $source_page ? (int) $source_page->post_author : 0,
			'post_category' => $category ? array( (int) $category->term_id ) : array(),
			'page_template' => ORILLIA_SPOTLIGHT_TEMPLATE,
			'meta_input'    => array_merge(
				$data['facts'],
				array( ORILLIA_SPOTLIGHT_FEEDBACK_KEY => $feedback_id )
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $feedback_id, ORILLIA_SPOTLIGHT_POST_KEY, $post_id );

	$photo_id = orillia_spotlight_roster_photo( $data['name'] );
	if ( $photo_id ) {
		set_post_thumbnail( $post_id, $photo_id );
	}

	return $post_id;
}

/**
 * Rebuild an existing spotlight post in the current layout.
 *
 * Replaces the body and header facts from the original submission. Keeps the
 * post's status, slug, date, featured image and action photo. An action
 * photo added to the old in-body photo slot is moved to the action photo
 * field. A title still in the old "Player Spotlight: Name" form becomes
 * "Meet Name"; any other title is kept.
 *
 * @param int $post_id Spotlight post ID.
 * @return true|WP_Error
 */
function orillia_spotlight_rebuild_post( $post_id ) {
	$post        = get_post( $post_id );
	$feedback_id = (int) get_post_meta( $post_id, ORILLIA_SPOTLIGHT_FEEDBACK_KEY, true );
	$data        = $feedback_id ? orillia_spotlight_read_feedback( $feedback_id ) : null;

	if ( ! $post || ! $data ) {
		return new WP_Error( 'orillia_spotlight_rebuild', "Post {$post_id} has no readable submission." );
	}

	if ( ! get_post_meta( $post_id, ORILLIA_SPOTLIGHT_ACTION_PHOTO, true ) ) {
		foreach ( parse_blocks( $post->post_content ) as $block ) {
			$classes = explode( ' ', $block['attrs']['className'] ?? '' );
			if ( 'core/image' === $block['blockName'] && in_array( 'spotlight-action-photo', $classes, true ) && ! empty( $block['attrs']['id'] ) ) {
				update_post_meta( $post_id, ORILLIA_SPOTLIGHT_ACTION_PHOTO, (int) $block['attrs']['id'] );
				break;
			}
		}
	}

	$title = $post->post_title;
	if ( preg_match( '/^Player Spotlight:\s*(.+)$/u', $title, $match ) ) {
		/* translators: %s: name from the old title. */
		$title = sprintf( __( 'Meet %s', 'orillia-players' ), $match[1] );
	}

	foreach ( array_keys( orillia_spotlight_fact_keys() ) as $key ) {
		if ( isset( $data['facts'][ $key ] ) ) {
			update_post_meta( $post_id, $key, $data['facts'][ $key ] );
		}
	}

	$result = wp_update_post(
		array(
			'ID'            => $post_id,
			'post_title'    => $title,
			'post_content'  => wp_slash( orillia_spotlight_content( $data ) ),
			'page_template' => ORILLIA_SPOTLIGHT_TEMPLATE,
		),
		true
	);

	return is_wp_error( $result ) ? $result : true;
}

/**
 * Find the featured image of the roster player with this exact name.
 *
 * @param string $name Player's full name.
 * @return int Attachment ID, or 0 if no match.
 */
function orillia_spotlight_roster_photo( $name ) {
	$players = get_posts(
		array(
			'post_type'      => 'player',
			'post_status'    => 'publish',
			'title'          => $name,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	return $players ? (int) get_post_thumbnail_id( $players[0] ) : 0;
}

/*
 * ---------------------------------------------------------------------------
 * Post body markup
 * ---------------------------------------------------------------------------
 */

/**
 * Serialize a spotlight's body as block markup.
 *
 * @param array $data Output of orillia_spotlight_read_feedback().
 * @return string Block markup.
 */
function orillia_spotlight_content( $data ) {
	$blocks = array();

	if ( '' !== $data['quote'] ) {
		$parts      = preg_split( '/\s+/u', trim( $data['name'] ), -1, PREG_SPLIT_NO_EMPTY );
		$first_name = $parts[0] ?? $data['name'];

		$blocks[] = sprintf(
			"<!-- wp:quote {\"className\":\"spotlight-quote\"} -->\n<blockquote class=\"wp-block-quote spotlight-quote\"><!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph --><cite>%s</cite></blockquote>\n<!-- /wp:quote -->",
			nl2br( esc_html( $data['quote'] ), false ),
			/* translators: %s: player's first name. */
			esc_html( sprintf( __( '%s, on joining the Eagles', 'orillia-players' ), $first_name ) )
		);
	}

	if ( $data['loves'] ) {
		$blocks[] = orillia_spotlight_section_heading( __( 'What I love most about hockey', 'orillia-players' ) );

		$items = array_map(
			function ( $love ) {
				return sprintf( "<!-- wp:list-item -->\n<li>%s</li>\n<!-- /wp:list-item -->", esc_html( $love ) );
			},
			$data['loves']
		);

		$blocks[] = "<!-- wp:list {\"className\":\"spotlight-tags\"} -->\n<ul class=\"wp-block-list spotlight-tags\">" . implode( "\n\n", $items ) . "</ul>\n<!-- /wp:list -->";
	}

	if ( $data['favourites'] ) {
		$blocks[] = orillia_spotlight_section_heading( __( 'Favourites', 'orillia-players' ) );

		$tiles = array_map(
			function ( $favourite ) {
				return sprintf(
					"<!-- wp:group {\"className\":\"spotlight-favourite\",\"layout\":{\"type\":\"flex\",\"orientation\":\"vertical\"}} -->\n<div class=\"wp-block-group spotlight-favourite\"><!-- wp:paragraph {\"className\":\"spotlight-favourite-label\"} -->\n<p class=\"spotlight-favourite-label\">%s</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph {\"className\":\"spotlight-favourite-value\"} -->\n<p class=\"spotlight-favourite-value\">%s</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->",
					esc_html( $favourite[0] ),
					esc_html( $favourite[1] )
				);
			},
			$data['favourites']
		);

		$blocks[] = "<!-- wp:group {\"className\":\"spotlight-favourites\",\"layout\":{\"type\":\"grid\",\"minimumColumnWidth\":\"11rem\"}} -->\n<div class=\"wp-block-group spotlight-favourites\">" . implode( "\n\n", $tiles ) . "</div>\n<!-- /wp:group -->";
	}

	if ( $data['answers'] ) {
		$items = array_map(
			function ( $answer ) {
				return sprintf(
					"<!-- wp:group {\"className\":\"spotlight-answer\",\"layout\":{\"type\":\"flex\",\"orientation\":\"vertical\"}} -->\n<div class=\"wp-block-group spotlight-answer\"><!-- wp:heading {\"level\":3,\"className\":\"spotlight-answer-heading\"} -->\n<h3 class=\"wp-block-heading spotlight-answer-heading\">%s</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->",
					esc_html( $answer[0] ),
					nl2br( esc_html( $answer[1] ), false )
				);
			},
			$data['answers']
		);

		$blocks[] = "<!-- wp:group {\"className\":\"spotlight-answers\",\"layout\":{\"type\":\"grid\",\"minimumColumnWidth\":\"18rem\"}} -->\n<div class=\"wp-block-group spotlight-answers\">" . implode( "\n\n", $items ) . "</div>\n<!-- /wp:group -->";
	}

	return implode( "\n\n", $blocks );
}

/**
 * Small gold section heading block.
 *
 * @param string $text Heading text.
 * @return string Block markup.
 */
function orillia_spotlight_section_heading( $text ) {
	return sprintf(
		"<!-- wp:heading {\"className\":\"spotlight-section-heading\"} -->\n<h2 class=\"wp-block-heading spotlight-section-heading\">%s</h2>\n<!-- /wp:heading -->",
		esc_html( $text )
	);
}

/*
 * ---------------------------------------------------------------------------
 * Template rendering
 * ---------------------------------------------------------------------------
 */

/**
 * Get the class names of a parsed block.
 *
 * @param array $block Parsed block.
 * @return string[]
 */
function orillia_spotlight_block_classes( $block ) {
	return explode( ' ', $block['attrs']['className'] ?? '' );
}

/**
 * Adjust the spotlight template's blocks as they render.
 *
 * - Hero group: put the action photo behind it, if one is set.
 * - Card nameplate (Post Title): drop the leading "Meet ".
 * - Page title (Post Title): break the line after "Meet".
 * - Fact rows, the number watermark/badge and the card position: hide them
 *   when the value is empty. The watermark and badge are decorative, so
 *   they are hidden from screen readers.
 *
 * @param string $content Rendered block content.
 * @param array  $block   Parsed block.
 * @return string
 */
function orillia_spotlight_render_block( $content, $block ) {
	$name = $block['blockName'] ?? '';

	if ( 'core/group' !== $name && 'core/post-title' !== $name && 'core/paragraph' !== $name ) {
		return $content;
	}

	$classes = orillia_spotlight_block_classes( $block );

	if ( 'core/group' === $name && in_array( 'spotlight-hero', $classes, true ) ) {
		$photo_id = (int) get_post_meta( get_the_ID(), ORILLIA_SPOTLIGHT_ACTION_PHOTO, true );
		$photo    = $photo_id ? wp_get_attachment_image(
			$photo_id,
			'full',
			false,
			array(
				'class'         => 'spotlight-action-bg',
				'alt'           => '',
				'aria-hidden'   => 'true',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'sizes'         => '100vw',
			)
		) : '';

		if ( ! $photo ) {
			return $content;
		}

		$processor = new WP_HTML_Tag_Processor( $content );
		if ( $processor->next_tag() ) {
			$processor->add_class( 'has-action-photo' );
			$content = $processor->get_updated_html();
		}

		// Insert the photo and its scrim right after the opening tag.
		return preg_replace( '/^(\s*<[^>]+>)/', '$1' . $photo . '<span class="spotlight-action-scrim" aria-hidden="true"></span>', $content, 1 );
	}

	if ( 'core/post-title' === $name && in_array( 'spotlight-nameplate', $classes, true ) ) {
		return preg_replace( '/>\s*Meet\s+/u', '>', $content, 1 );
	}

	if ( 'core/post-title' === $name && in_array( 'spotlight-title', $classes, true ) ) {
		return preg_replace( '/>\s*Meet\s+/u', '>Meet<br>', $content, 1 );
	}

	if ( 'core/group' === $name && in_array( 'spotlight-fact', $classes, true )
		&& preg_match( '/<p[^>]*spotlight-fact-value[^>]*>\s*<\/p>/', $content ) ) {
		return '';
	}

	if ( 'core/paragraph' === $name && array_intersect( array( 'spotlight-watermark', 'spotlight-card-number', 'spotlight-card-position' ), $classes ) ) {
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return '';
		}

		if ( in_array( 'spotlight-card-position', $classes, true ) ) {
			return $content;
		}

		$processor = new WP_HTML_Tag_Processor( $content );
		if ( $processor->next_tag( 'p' ) ) {
			$processor->set_attribute( 'aria-hidden', 'true' );
			$content = $processor->get_updated_html();
		}
	}

	return $content;
}
add_filter( 'render_block', 'orillia_spotlight_render_block', 10, 2 );

/*
 * ---------------------------------------------------------------------------
 * WP-CLI
 * ---------------------------------------------------------------------------
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Create draft spotlight posts for existing Player Spotlight submissions.
	 *
	 * Skips spam and any submission that already has a post.
	 *
	 * ## OPTIONS
	 *
	 * [--regenerate]
	 * : Rebuild existing posts that are still drafts in the current layout,
	 * keeping their featured image and action photo. Published posts are kept.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orillia-spotlight generate
	 *     wp orillia-spotlight generate --regenerate
	 */
	WP_CLI::add_command(
		'orillia-spotlight generate',
		function ( $args, $assoc_args ) {
			$regenerate = ! empty( $assoc_args['regenerate'] );
			$page_id    = orillia_spotlight_page_id();

			if ( ! $page_id ) {
				WP_CLI::error( 'No page with the slug "' . ORILLIA_SPOTLIGHT_PAGE_SLUG . '" was found.' );
			}

			$ids = get_posts(
				array(
					'post_type'      => 'feedback',
					'post_status'    => 'publish',
					'post_parent'    => $page_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'date',
					'order'          => 'ASC',
				)
			);

			$created = 0;
			$rebuilt = 0;
			foreach ( $ids as $feedback_id ) {
				$existing = (int) get_post_meta( $feedback_id, ORILLIA_SPOTLIGHT_POST_KEY, true );

				if ( $existing && ! get_post( $existing ) ) {
					delete_post_meta( $feedback_id, ORILLIA_SPOTLIGHT_POST_KEY );
					$existing = 0;
				}

				if ( $existing ) {
					if ( $regenerate && 'draft' === get_post_status( $existing ) ) {
						$result = orillia_spotlight_rebuild_post( $existing );
						if ( is_wp_error( $result ) ) {
							WP_CLI::warning( $result->get_error_message() );
						} else {
							WP_CLI::log( "Rebuilt draft post {$existing}" );
							++$rebuilt;
						}
					}
					continue;
				}

				$result = orillia_spotlight_create_post( $feedback_id );

				if ( is_wp_error( $result ) ) {
					WP_CLI::warning( "Feedback {$feedback_id}: " . $result->get_error_message() );
				} elseif ( $result ) {
					WP_CLI::log( "Feedback {$feedback_id} -> draft post {$result}" );
					++$created;
				}
			}

			WP_CLI::success( "Created {$created} and rebuilt {$rebuilt} spotlight post(s)." );
		}
	);

	/**
	 * Rebuild one spotlight post in the current layout, even if published.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : The spotlight post to rebuild.
	 *
	 * ## EXAMPLES
	 *
	 *     wp orillia-spotlight rebuild 123
	 */
	WP_CLI::add_command(
		'orillia-spotlight rebuild',
		function ( $args ) {
			$result = orillia_spotlight_rebuild_post( (int) $args[0] );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}

			WP_CLI::success( "Rebuilt post {$args[0]}." );
		}
	);
}
