<?php
/**
 * Quick Edit support for the event time on the Schedule list screen.
 *
 * The Time column carries the stored 24-hour value in a hidden element;
 * assets/js/schedule-quick-edit.js copies it into the Quick Edit field.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add a Time field to Quick Edit. Fires once per custom column, so only
 * output it for the Time column.
 */
function orillia_schedule_quick_edit_box( $column, $post_type ) {
	if ( 'game_event' !== $post_type || 'event_time' !== $column ) {
		return;
	}
	?>
	<fieldset class="inline-edit-col-right">
		<div class="inline-edit-col">
			<label>
				<span class="title"><?php esc_html_e( 'Time', 'orillia-schedule' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="event_time" value="" placeholder="18:30" maxlength="5" inputmode="numeric" />
				</span>
			</label>
			<p class="description"><?php esc_html_e( '24-hour clock, e.g. 18:30. Shows on the site as 6:30 PM.', 'orillia-schedule' ); ?></p>
		</div>
	</fieldset>
	<?php
}
add_action( 'quick_edit_custom_box', 'orillia_schedule_quick_edit_box', 10, 2 );

/**
 * Save the Quick Edit time. Only on the inline-save request, so the block
 * editor (which saves meta over REST) is unaffected.
 */
function orillia_schedule_quick_edit_save( $post_id ) {
	if ( ! wp_doing_ajax() || 'inline-save' !== ( $_POST['action'] ?? '' ) || ! isset( $_POST['event_time'] ) ) {
		return;
	}
	if ( ! check_ajax_referer( 'inlineeditnonce', '_inline_edit', false ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// The registered sanitize callback normalizes valid times to HH:MM.
	update_post_meta( $post_id, 'event_time', wp_unslash( $_POST['event_time'] ) );
}
add_action( 'save_post_game_event', 'orillia_schedule_quick_edit_save' );

function orillia_schedule_enqueue_quick_edit( $hook ) {
	$screen = get_current_screen();
	if ( 'edit.php' !== $hook || ! $screen || 'game_event' !== $screen->post_type ) {
		return;
	}
	$script_path = plugin_dir_path( __DIR__ ) . 'assets/js/schedule-quick-edit.js';
	wp_enqueue_script(
		'orillia-schedule-quick-edit',
		plugins_url( 'assets/js/schedule-quick-edit.js', __DIR__ ),
		array( 'jquery', 'inline-edit-post' ),
		file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0',
		true
	);
}
add_action( 'admin_enqueue_scripts', 'orillia_schedule_enqueue_quick_edit' );
