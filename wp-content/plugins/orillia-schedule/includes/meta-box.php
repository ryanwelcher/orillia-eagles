<?php
/**
 * Schedule event custom fields: start/end dates, time, location, opponent, status, and title-link preference.
 *
 * The UI lives in the block editor as a PluginDocumentSettingPanel
 * (assets/js/schedule-details-panel.js). This file registers the
 * post meta and the admin list-table columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function orillia_schedule_register_meta() {
	$auth = function () {
		return current_user_can( 'edit_posts' );
	};

	register_post_meta(
		'game_event',
		'event_date',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		'game_event',
		'event_end_date',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		'game_event',
		'event_time',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		'game_event',
		'event_location',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		'game_event',
		'event_opponent',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => $auth,
		)
	);

	register_post_meta(
		'game_event',
		'event_status',
		array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'default'           => 'scheduled',
			'sanitize_callback' => function ( $value ) {
				return in_array( $value, array( 'scheduled', 'cancelled', 'postponed' ), true ) ? $value : 'scheduled';
			},
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'game_event',
		'event_link_enabled',
		array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'boolean',
			'default'       => false,
			'auth_callback' => $auth,
		)
	);
}
add_action( 'init', 'orillia_schedule_register_meta' );

/**
 * Enqueue the Document Settings Panel script on the Event edit screen only.
 */
function orillia_schedule_enqueue_panel_script() {
	$screen = get_current_screen();

	if ( ! $screen || 'game_event' !== $screen->post_type ) {
		return;
	}

	$script_path = plugin_dir_path( __DIR__ ) . 'assets/js/schedule-details-panel.js';

	wp_enqueue_script(
		'orillia-schedule-details-panel',
		plugins_url( 'assets/js/schedule-details-panel.js', __DIR__ ),
		array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-i18n' ),
		file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0',
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'orillia_schedule_enqueue_panel_script' );

/**
 * Show Date, Time, and Location as admin list-table columns.
 */
function orillia_schedule_columns( $columns ) {
	$columns['event_date']     = __( 'Date', 'orillia-schedule' );
	$columns['event_time']     = __( 'Time', 'orillia-schedule' );
	$columns['event_location'] = __( 'Location', 'orillia-schedule' );
	$columns['event_status']   = __( 'Status', 'orillia-schedule' );
	return $columns;
}
add_filter( 'manage_game_event_posts_columns', 'orillia_schedule_columns' );

function orillia_schedule_format_date_range( $post_id ) {
	$start = DateTimeImmutable::createFromFormat( '!Y-m-d', get_post_meta( $post_id, 'event_date', true ) );
	$end   = DateTimeImmutable::createFromFormat( '!Y-m-d', get_post_meta( $post_id, 'event_end_date', true ) );

	if ( ! $start ) {
		return '';
	}

	if ( ! $end || $end < $start ) {
		return wp_date( 'M j, Y', $start->getTimestamp() );
	}

	if ( $start->format( 'Y-m' ) === $end->format( 'Y-m' ) ) {
		return wp_date( 'M j', $start->getTimestamp() ) . ' - ' . wp_date( 'j, Y', $end->getTimestamp() );
	}

	if ( $start->format( 'Y' ) === $end->format( 'Y' ) ) {
		return wp_date( 'M j', $start->getTimestamp() ) . ' - ' . wp_date( 'M j, Y', $end->getTimestamp() );
	}

	return wp_date( 'M j, Y', $start->getTimestamp() ) . ' - ' . wp_date( 'M j, Y', $end->getTimestamp() );
}

function orillia_schedule_event_is_past( $post_id ) {
	$start = DateTimeImmutable::createFromFormat( '!Y-m-d', get_post_meta( $post_id, 'event_date', true ) );
	$end   = DateTimeImmutable::createFromFormat( '!Y-m-d', get_post_meta( $post_id, 'event_end_date', true ) );
	$today = DateTimeImmutable::createFromFormat( '!Y-m-d', current_time( 'Y-m-d' ) );

	if ( ! $start || ! $today ) {
		return false;
	}

	$effective_end = ( $end && $end >= $start ) ? $end : $start;
	return $effective_end < $today;
}

function orillia_schedule_column_content( $column, $post_id ) {
	if ( 'event_date' === $column ) {
		echo esc_html( orillia_schedule_format_date_range( $post_id ) );
	}
	if ( 'event_time' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'event_time', true ) );
	}
	if ( 'event_location' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'event_location', true ) );
	}
	if ( 'event_status' === $column ) {
		$status = get_post_meta( $post_id, 'event_status', true ) ?: 'scheduled';
		echo esc_html( ucfirst( $status ) );
	}
}
add_action( 'manage_game_event_posts_custom_column', 'orillia_schedule_column_content', 10, 2 );

/**
 * Format bound schedule dates without changing the stored sortable ISO value.
 */
function orillia_schedule_render_date( $block_content, $block, $instance ) {
	$class_name = $block['attrs']['className'] ?? '';
	$post_id    = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;

	if ( ! $post_id || false === strpos( $class_name, 'schedule-cell--date' ) ) {
		return $block_content;
	}

	$formatted = esc_html( orillia_schedule_format_date_range( $post_id ) );
	if ( '' === $formatted ) {
		return $block_content;
	}
	return preg_replace_callback(
		'/(<p\b[^>]*>).*?(<\/p>)/s',
		function ( $matches ) use ( $formatted ) {
			return $matches[1] . $formatted . $matches[2];
		},
		$block_content,
		1
	);
}
add_filter( 'render_block_core/paragraph', 'orillia_schedule_render_date', 10, 3 );

/**
 * Link schedule locations to a Google Maps search for the venue or address.
 */
function orillia_schedule_render_location_link( $block_content, $block, $instance ) {
	$class_name = $block['attrs']['className'] ?? '';
	$post_id    = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;

	if ( ! $post_id || false === strpos( $class_name, 'schedule-cell--location' ) ) {
		return $block_content;
	}

	$location = trim( get_post_meta( $post_id, 'event_location', true ) );
	if ( '' === $location ) {
		return $block_content;
	}

	$maps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $location );
	$label    = sprintf( __( 'Open %s in Google Maps', 'orillia-schedule' ), $location );
	$link     = '<a href="' . esc_url( $maps_url ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $label ) . '">' . esc_html( $location ) . '</a>';

	return preg_replace_callback(
		'/(<p\b[^>]*>).*?(<\/p>)/s',
		function ( $matches ) use ( $link ) {
			return $matches[1] . $link . $matches[2];
		},
		$block_content,
		1
	);
}
add_filter( 'render_block_core/paragraph', 'orillia_schedule_render_location_link', 11, 3 );

/**
 * Add the optional single-event link and a visible non-default status badge.
 */
function orillia_schedule_render_event_title( $block_content, $block, $instance ) {
	$class_name = $block['attrs']['className'] ?? '';
	$post_id    = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;

	if ( ! $post_id || false === strpos( $class_name, 'schedule-event-title' ) ) {
		return $block_content;
	}

	if ( get_post_meta( $post_id, 'event_link_enabled', true ) ) {
		$url           = esc_url( get_permalink( $post_id ) );
		$block_content = preg_replace( '/(<(?:h[1-6]|p)\b[^>]*>)(.*?)(<\/(?:h[1-6]|p)>)/s', '$1<a href="' . $url . '">$2</a>$3', $block_content, 1 );
	}

	$status = get_post_meta( $post_id, 'event_status', true ) ?: 'scheduled';
	if ( in_array( $status, array( 'cancelled', 'postponed' ), true ) ) {
		$label          = 'cancelled' === $status ? __( 'Cancelled', 'orillia-schedule' ) : __( 'Postponed', 'orillia-schedule' );
		$block_content .= '<span class="schedule-status schedule-status--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	return $block_content;
}
add_filter( 'render_block_core/post-title', 'orillia_schedule_render_event_title', 10, 3 );

/**
 * Add the event status as a row class for accessible visual treatment.
 */
function orillia_schedule_render_event_row( $block_content, $block, $instance ) {
	$class_name = $block['attrs']['className'] ?? '';
	$post_id    = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;

	if ( ! $post_id || false === strpos( $class_name, 'schedule-row' ) ) {
		return $block_content;
	}

	if ( false === strpos( $block_content, 'taxonomy-event_level' ) ) {
		$placeholder  = '<span class="schedule-cell schedule-cell--level schedule-cell--level-empty" aria-hidden="true"></span>';
		$block_content = preg_replace_callback(
			'/(<p\b[^>]*class="[^"]*schedule-cell--location[^"]*"[^>]*>)/',
			function ( $matches ) use ( $placeholder ) {
				return $placeholder . $matches[1];
			},
			$block_content,
			1
		);
	}

	$status    = get_post_meta( $post_id, 'event_status', true ) ?: 'scheduled';
	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( $processor->next_tag() ) {
		if ( 'scheduled' !== $status ) {
			$processor->add_class( 'schedule-row--' . $status );
		}
		if ( orillia_schedule_event_is_past( $post_id ) ) {
			$processor->add_class( 'schedule-row--past' );
		}
		return $processor->get_updated_html();
	}

	return $block_content;
}
add_filter( 'render_block_core/group', 'orillia_schedule_render_event_row', 10, 3 );
