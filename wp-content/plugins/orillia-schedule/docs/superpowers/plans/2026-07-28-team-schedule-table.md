# Team Schedule Table Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a dynamic, chronological schedule table (Games & Practices) to the Orillia Eagles site via a new `orillia-schedule` plugin and two theme patterns, rendered with Advanced Query Loop and styled as an alternating-row table.

**Architecture:** A dedicated `orillia-schedule` plugin registers a `game_event` CPT, two taxonomies (`event_type`, `event_level`), and four meta fields, mirroring the existing `orillia-players` plugin exactly (including the `aql_query_vars` sort filter and an editor `PluginDocumentSettingPanel`). The frontend is two theme patterns — a section wrapper embedding an Advanced Query Loop query — with `.schedule-*` styles in the theme's `assets/css/styles.css`.

**Tech Stack:** WordPress block theme, PHP, Advanced Query Loop plugin, `@wordpress/scripts`-free vanilla `wp.*` global JS (matching `player-details-panel.js`), WordPress Studio (`studio wp ...`).

## Global Constraints

- **All WP-CLI commands run through Studio:** prefix every `wp` command with `studio` (e.g. `studio wp post-type list`).
- **The plugin directory is gitignored** (`/wp-content/plugins/*` in the site-root `.gitignore`). Plugin-file `git commit`s are no-ops — do **not** force-add plugin files. For plugin tasks, the **verification command is the checkpoint** (no commit step).
- **Theme files ARE tracked.** Pattern files (`themes/orillia-eagles/patterns/*`) and CSS (`themes/orillia-eagles/assets/css/styles.css`) get real commits. Commit messages end with the standard co-author trailer used in this repo.
- **Mirror `orillia-players` conventions verbatim:** text-domain style, `orillia_schedule_*` function prefix, `show_in_rest => true`, `edit_posts` auth callbacks, dashicon menu, seed-on-activation + `flush_rewrite_rules()`.
- **CPT slug:** `game_event`. **Taxonomies:** `event_type` (Game, Practice), `event_level` (Mixed, Junior, Intermediate, Senior). **Meta:** `event_date` (YYYY-MM-DD), `event_time`, `event_location`, `event_opponent`.
- **Pattern category:** `orillia-eagles` (already registered in the theme's `functions.php`).
- **Text domain:** `orillia-schedule`.

---

## File Structure

**Plugin (untracked):**
- `plugins/orillia-schedule/orillia-schedule.php` — bootstrap, activation, AQL sort filter.
- `plugins/orillia-schedule/includes/post-type.php` — `game_event` CPT.
- `plugins/orillia-schedule/includes/taxonomy.php` — `event_type` + `event_level` + seeding.
- `plugins/orillia-schedule/includes/meta-box.php` — meta registration, panel enqueue, admin columns.
- `plugins/orillia-schedule/assets/js/schedule-details-panel.js` — editor Document Settings Panel.

**Theme (tracked):**
- `themes/orillia-eagles/patterns/schedule-query.php` — Advanced Query Loop table.
- `themes/orillia-eagles/patterns/schedule-section.php` — section wrapper embedding the query.
- `themes/orillia-eagles/assets/css/styles.css` — append `.schedule-*` styles.

---

## Task 1: Plugin bootstrap + `game_event` CPT

**Files:**
- Create: `plugins/orillia-schedule/orillia-schedule.php`
- Create: `plugins/orillia-schedule/includes/post-type.php`

**Interfaces:**
- Produces: `orillia_schedule_register_post_type()` (registers CPT `game_event`); plugin bootstrap that `require_once`s include files and wires the activation hook.

- [ ] **Step 1: Create the CPT file**

Create `plugins/orillia-schedule/includes/post-type.php`:

```php
<?php
/**
 * Registers the "Schedule" (game_event) custom post type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function orillia_schedule_register_post_type() {
	$labels = array(
		'name'               => __( 'Schedule', 'orillia-schedule' ),
		'singular_name'      => __( 'Event', 'orillia-schedule' ),
		'add_new'            => __( 'Add New', 'orillia-schedule' ),
		'add_new_item'       => __( 'Add New Event', 'orillia-schedule' ),
		'edit_item'          => __( 'Edit Event', 'orillia-schedule' ),
		'new_item'           => __( 'New Event', 'orillia-schedule' ),
		'view_item'          => __( 'View Event', 'orillia-schedule' ),
		'view_items'         => __( 'View Schedule', 'orillia-schedule' ),
		'search_items'       => __( 'Search Schedule', 'orillia-schedule' ),
		'not_found'          => __( 'No events found', 'orillia-schedule' ),
		'not_found_in_trash' => __( 'No events found in Trash', 'orillia-schedule' ),
		'all_items'          => __( 'All Events', 'orillia-schedule' ),
		'menu_name'          => __( 'Schedule', 'orillia-schedule' ),
	);

	$args = array(
		'labels'        => $labels,
		'public'        => true,
		'show_in_rest'  => true,
		'menu_icon'     => 'dashicons-calendar-alt',
		'menu_position' => 21,
		'supports'      => array( 'title', 'custom-fields' ),
		'has_archive'   => 'schedule',
		'rewrite'       => array( 'slug' => 'schedule', 'with_front' => false ),
		'show_in_menu'  => true,
		'hierarchical'  => false,
	);

	register_post_type( 'game_event', $args );
}
add_action( 'init', 'orillia_schedule_register_post_type' );
```

- [ ] **Step 2: Create the bootstrap file**

Create `plugins/orillia-schedule/orillia-schedule.php`:

```php
<?php
/**
 * Plugin Name: Orillia Eagles Schedule
 * Description: Team schedule (games & practices) with Event Type and Level taxonomies plus date/time/location/opponent fields.
 * Version: 1.0.0
 * Author: Studio Code
 * Text Domain: orillia-schedule
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/post-type.php';
require_once __DIR__ . '/includes/taxonomy.php';
require_once __DIR__ . '/includes/meta-box.php';

/**
 * On activation: register CPT/taxonomies so rewrite rules pick them up,
 * seed default terms, then flush rewrite rules.
 */
function orillia_schedule_activate() {
	orillia_schedule_register_post_type();
	orillia_schedule_register_taxonomies();
	orillia_schedule_seed_default_types();
	orillia_schedule_seed_default_levels();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'orillia_schedule_activate' );

function orillia_schedule_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'orillia_schedule_deactivate' );
```

> Note: this file references `orillia_schedule_register_taxonomies()`, `orillia_schedule_seed_default_types()`, `orillia_schedule_seed_default_levels()` (Task 2) and requires `meta-box.php` (Task 3). PHP resolves these at call time, but the plugin will fatal on load until Task 2 and Task 3 create those files. Create the `includes/taxonomy.php` and `includes/meta-box.php` stubs are **not** needed — proceed straight to Task 2 and Task 3 before activating. Activation verification for the whole plugin happens at the end of Task 3.

- [ ] **Step 3: Verify the CPT file parses**

Run: `studio wp eval 'require "wp-content/plugins/orillia-schedule/includes/post-type.php"; echo function_exists("orillia_schedule_register_post_type") ? "OK" : "MISSING";'`
Expected: `OK`

(No commit — plugin directory is gitignored.)

---

## Task 2: Taxonomies + term seeding

**Files:**
- Create: `plugins/orillia-schedule/includes/taxonomy.php`

**Interfaces:**
- Consumes: CPT `game_event` from Task 1.
- Produces: `orillia_schedule_register_taxonomies()`, `orillia_schedule_seed_default_types()`, `orillia_schedule_seed_default_levels()`. Taxonomies `event_type` (terms: Game, Practice) and `event_level` (terms: Mixed, Junior, Intermediate, Senior).

- [ ] **Step 1: Create the taxonomy file**

Create `plugins/orillia-schedule/includes/taxonomy.php`:

```php
<?php
/**
 * Registers the "Event Type" and "Level" taxonomies for the schedule
 * and seeds their default terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function orillia_schedule_register_taxonomies() {
	$type_labels = array(
		'name'          => __( 'Event Types', 'orillia-schedule' ),
		'singular_name' => __( 'Event Type', 'orillia-schedule' ),
		'all_items'     => __( 'All Event Types', 'orillia-schedule' ),
		'edit_item'     => __( 'Edit Event Type', 'orillia-schedule' ),
		'add_new_item'  => __( 'Add New Event Type', 'orillia-schedule' ),
		'menu_name'     => __( 'Event Types', 'orillia-schedule' ),
	);

	register_taxonomy(
		'event_type',
		array( 'game_event' ),
		array(
			'labels'            => $type_labels,
			'public'            => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'event-type' ),
		)
	);

	$level_labels = array(
		'name'          => __( 'Levels', 'orillia-schedule' ),
		'singular_name' => __( 'Level', 'orillia-schedule' ),
		'all_items'     => __( 'All Levels', 'orillia-schedule' ),
		'edit_item'     => __( 'Edit Level', 'orillia-schedule' ),
		'add_new_item'  => __( 'Add New Level', 'orillia-schedule' ),
		'menu_name'     => __( 'Levels', 'orillia-schedule' ),
	);

	register_taxonomy(
		'event_level',
		array( 'game_event' ),
		array(
			'labels'            => $level_labels,
			'public'            => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'event-level' ),
		)
	);
}
add_action( 'init', 'orillia_schedule_register_taxonomies' );

/**
 * Seed the default event types if they don't already exist.
 */
function orillia_schedule_seed_default_types() {
	$types = array( 'Game', 'Practice' );

	foreach ( $types as $type ) {
		if ( ! term_exists( $type, 'event_type' ) ) {
			wp_insert_term( $type, 'event_type' );
		}
	}
}

/**
 * Seed the default levels if they don't already exist.
 */
function orillia_schedule_seed_default_levels() {
	$levels = array( 'Mixed', 'Junior', 'Intermediate', 'Senior' );

	foreach ( $levels as $level ) {
		if ( ! term_exists( $level, 'event_level' ) ) {
			wp_insert_term( $level, 'event_level' );
		}
	}
}
```

- [ ] **Step 2: Verify the taxonomy file parses**

Run: `studio wp eval 'require "wp-content/plugins/orillia-schedule/includes/taxonomy.php"; echo function_exists("orillia_schedule_register_taxonomies") && function_exists("orillia_schedule_seed_default_levels") ? "OK" : "MISSING";'`
Expected: `OK`

(No commit — plugin directory is gitignored.)

---

## Task 3: Meta registration + admin columns + panel enqueue

**Files:**
- Create: `plugins/orillia-schedule/includes/meta-box.php`

**Interfaces:**
- Consumes: CPT `game_event` (Task 1).
- Produces: registered meta `event_date`, `event_time`, `event_location`, `event_opponent`; admin list columns; enqueues `schedule-details-panel.js` (Task 4) on the `game_event` edit screen.

- [ ] **Step 1: Create the meta-box file**

Create `plugins/orillia-schedule/includes/meta-box.php`:

```php
<?php
/**
 * Schedule event custom fields: date, time, location, opponent.
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
	return $columns;
}
add_filter( 'manage_game_event_posts_columns', 'orillia_schedule_columns' );

function orillia_schedule_column_content( $column, $post_id ) {
	if ( 'event_date' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'event_date', true ) );
	}
	if ( 'event_time' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'event_time', true ) );
	}
	if ( 'event_location' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'event_location', true ) );
	}
}
add_action( 'manage_game_event_posts_custom_column', 'orillia_schedule_column_content', 10, 2 );
```

- [ ] **Step 2: Activate the plugin (now that all required files exist)**

Run: `studio wp plugin activate orillia-schedule`
Expected: `Plugin 'orillia-schedule' activated.`

- [ ] **Step 3: Verify CPT, taxonomies, and seeded terms**

Run: `studio wp post-type list --field=name | grep game_event && studio wp term list event_type --field=name && studio wp term list event_level --field=name`
Expected output includes: `game_event`, then `Game` / `Practice`, then `Mixed` / `Junior` / `Intermediate` / `Senior`.

- [ ] **Step 4: Verify meta is registered for REST**

Run: `studio wp eval 'global $wp_meta_keys; echo isset( get_registered_meta_keys("post","game_event")["event_date"] ) ? "OK" : "MISSING";'`
Expected: `OK`

(No commit — plugin directory is gitignored.)

---

## Task 4: Editor Document Settings Panel

**Files:**
- Create: `plugins/orillia-schedule/assets/js/schedule-details-panel.js`

**Interfaces:**
- Consumes: meta keys `event_date`, `event_time`, `event_location`, `event_opponent` (Task 3), read/written via the `core/editor` store.
- Produces: an "Event Details" panel in the block editor sidebar for `game_event` posts.

- [ ] **Step 1: Read the existing roster panel for the exact API shape**

Run: `cat wp-content/plugins/orillia-players/assets/js/player-details-panel.js`
Expected: a `PluginDocumentSettingPanel` using `wp.plugins.registerPlugin`, `wp.data.useSelect`/`useDispatch`, and `wp.element.createElement`. Match this structure and the global-`wp.*` (no-build) style.

- [ ] **Step 2: Create the panel script**

Create `plugins/orillia-schedule/assets/js/schedule-details-panel.js`:

```js
( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { TextControl } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	function EventDetailsPanel() {
		const postType = useSelect(
			( select ) => select( 'core/editor' ).getCurrentPostType(),
			[]
		);

		const meta = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
			[]
		);

		const { editPost } = useDispatch( 'core/editor' );

		if ( 'game_event' !== postType ) {
			return null;
		}

		const setMeta = ( key, value ) =>
			editPost( { meta: { ...meta, [ key ]: value } } );

		return el(
			PluginDocumentSettingPanel,
			{ name: 'orillia-schedule-details', title: __( 'Event Details', 'orillia-schedule' ) },
			el(
				Fragment,
				null,
				el( TextControl, {
					label: __( 'Date (YYYY-MM-DD)', 'orillia-schedule' ),
					type: 'date',
					value: meta.event_date || '',
					onChange: ( value ) => setMeta( 'event_date', value ),
				} ),
				el( TextControl, {
					label: __( 'Time', 'orillia-schedule' ),
					placeholder: __( 'e.g. 6:30 PM', 'orillia-schedule' ),
					value: meta.event_time || '',
					onChange: ( value ) => setMeta( 'event_time', value ),
				} ),
				el( TextControl, {
					label: __( 'Location', 'orillia-schedule' ),
					value: meta.event_location || '',
					onChange: ( value ) => setMeta( 'event_location', value ),
				} ),
				el( TextControl, {
					label: __( 'Opponent (games only)', 'orillia-schedule' ),
					value: meta.event_opponent || '',
					onChange: ( value ) => setMeta( 'event_opponent', value ),
				} )
			)
		);
	}

	registerPlugin( 'orillia-schedule-details', { render: EventDetailsPanel } );
} )( window.wp );
```

> Note: if the roster panel (Step 1) uses `wp.editPost.PluginDocumentSettingPanel` instead of `wp.editor.PluginDocumentSettingPanel`, match whichever it uses and adjust the script dependency array accordingly (`wp-edit-post` vs `wp-editor`). Prefer exactly what the working roster file does.

- [ ] **Step 3: Verify in the editor**

Open a new Event in the block editor (`studio wp` has no headless editor; do this in the browser at the Studio site URL → Schedule → Add New). Confirm the "Event Details" panel appears with Date, Time, Location, Opponent fields, that entering values and saving persists them, and that `studio wp post meta list <new_id>` shows the `event_*` keys.

(No commit — plugin directory is gitignored.)

---

## Task 5: Advanced Query Loop date-sort integration

**Files:**
- Modify: `plugins/orillia-schedule/orillia-schedule.php` (append filter)

**Interfaces:**
- Consumes: meta `event_date` (Task 3); the `aql_query_vars` filter exposed by the Advanced Query Loop plugin (same hook the roster uses).
- Produces: `orillia_schedule_aql_query_vars()` — sets `meta_key`/`meta_type` when a `game_event` query orders by `meta_value`.

- [ ] **Step 1: Confirm the roster's filter signature to copy it exactly**

Run: `grep -n "aql_query_vars" wp-content/plugins/orillia-players/orillia-players.php`
Expected: an `add_filter( 'aql_query_vars', 'orillia_players_aql_query_vars', 10, 3 )` with a 3-arg callback `( $query_args, $block_query, $inherited )`.

- [ ] **Step 2: Append the sort filter to the bootstrap**

Add to the end of `plugins/orillia-schedule/orillia-schedule.php`:

```php
/**
 * Advanced Query Loop exposes orderBy=meta_value on its Query block, but
 * WP_Query also requires a top-level meta_key to sort by. Set it (and treat
 * the value as a DATE) whenever a game_event query orders by meta_value, so the
 * schedule table sorts chronologically by event_date without extra config.
 */
function orillia_schedule_aql_query_vars( $query_args, $block_query, $inherited ) {
	if ( isset( $block_query['postType'] ) && 'game_event' === $block_query['postType']
		&& isset( $query_args['orderby'] ) && 'meta_value' === $query_args['orderby'] ) {
		$query_args['meta_key']  = 'event_date'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query_args['meta_type'] = 'DATE';
	}
	return $query_args;
}
add_filter( 'aql_query_vars', 'orillia_schedule_aql_query_vars', 10, 3 );
```

- [ ] **Step 3: Verify the filter parses and is hooked**

Run: `studio wp eval 'echo has_filter("aql_query_vars","orillia_schedule_aql_query_vars") !== false ? "OK" : "MISSING";'`
Expected: `OK`

(No commit — plugin directory is gitignored.)

---

## Task 6: Schedule query pattern (the table)

**Files:**
- Create: `themes/orillia-eagles/patterns/schedule-query.php`

**Interfaces:**
- Consumes: CPT `game_event`, meta `event_date`/`event_time`/`event_location`/`event_opponent`, taxonomy display of `event_level`/`event_type`, and the AQL date-sort from Task 5.
- Produces: a registered pattern `orillia-eagles/schedule-query`.

- [ ] **Step 1: Read the roster query pattern to match block-markup conventions**

Run: `cat wp-content/themes/orillia-eagles/patterns/roster-query.php`
Expected: an `advanced-query-loop` namespaced `wp:query` with `wp:post-template`, `core/post-meta` bindings, and a `wp:query-no-results` block. Match its structure.

- [ ] **Step 2: Create the query pattern**

Create `themes/orillia-eagles/patterns/schedule-query.php`:

```php
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
```

- [ ] **Step 3: Verify the pattern registers**

Run: `studio wp eval 'echo WP_Block_Patterns_Registry::get_instance()->is_registered("orillia-eagles/schedule-query") ? "OK" : "MISSING";'`
Expected: `OK`

- [ ] **Step 4: Commit (theme file — tracked)**

```bash
git add wp-content/themes/orillia-eagles/patterns/schedule-query.php
git commit -m "Add schedule-query pattern (chronological game_event table)"
```

---

## Task 7: Schedule section pattern

**Files:**
- Create: `themes/orillia-eagles/patterns/schedule-section.php`

**Interfaces:**
- Consumes: pattern `orillia-eagles/schedule-query` (Task 6).
- Produces: a registered pattern `orillia-eagles/schedule-section` (the insertable, `featured` section).

- [ ] **Step 1: Read the roster section pattern to match the header/wrapper markup**

Run: `cat wp-content/themes/orillia-eagles/patterns/roster-section.php`
Expected: a full-width `wp:group` section with a section-label paragraph, a headline, and a `wp:pattern` reference embedding the query pattern. Match this shape.

- [ ] **Step 2: Create the section pattern**

Create `themes/orillia-eagles/patterns/schedule-section.php`:

```php
<?php
/**
 * Title: Schedule Section
 * Slug: orillia-eagles/schedule-section
 * Categories: orillia-eagles, featured
 * Description: Full schedule section heading that embeds the schedule-query table pattern (Games & Practices sorted by date).
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"Schedule Section"},"align":"full","className":"schedule","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"},"anchor":"schedule"} -->
<section class="wp-block-group alignfull schedule" id="schedule"><!-- wp:group {"metadata":{"name":"Schedule Inner"},"className":"schedule-inner","style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"default"}} -->
<div class="wp-block-group schedule-inner"><!-- wp:group {"metadata":{"name":"Schedule Header"},"className":"schedule-header animate-on-scroll","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group schedule-header animate-on-scroll"><!-- wp:paragraph {"className":"has-text-align-center section-label","style":{"typography":{"textAlign":"center"}},"textColor":"accent-1","fontSize":"small"} -->
<p class="has-text-align-center section-label has-accent-1-color has-text-color has-small-font-size">Mark Your Calendar</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"schedule-headline","style":{"typography":{"textAlign":"center"}},"fontSize":"huge","fontFamily":"archivo-black"} -->
<h2 class="wp-block-heading has-text-align-center schedule-headline has-archivo-black-font-family has-huge-font-size">Game Schedule</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"orillia-eagles/schedule-query"} /--></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
```

- [ ] **Step 3: Verify the pattern registers**

Run: `studio wp eval 'echo WP_Block_Patterns_Registry::get_instance()->is_registered("orillia-eagles/schedule-section") ? "OK" : "MISSING";'`
Expected: `OK`

- [ ] **Step 4: Commit (theme file — tracked)**

```bash
git add wp-content/themes/orillia-eagles/patterns/schedule-section.php
git commit -m "Add schedule-section pattern wrapping the schedule table"
```

---

## Task 8: Table styles (alternating rows)

**Files:**
- Modify: `themes/orillia-eagles/assets/css/styles.css` (append `.schedule-*` block)

**Interfaces:**
- Consumes: the class names emitted by Tasks 6–7 (`.schedule-table`, `.schedule-row`, `.schedule-row--head`, `.schedule-cell` and its modifiers).
- Produces: a 5-column CSS-grid table with a styled header row and alternating body-row background.

- [ ] **Step 1: Find where the roster styles live, to append nearby and reuse tokens**

Run: `grep -n "roster-grid\|roster-player\|\.roster" wp-content/themes/orillia-eagles/assets/css/styles.css | head`
Expected: existing `.roster-*` rules. Append the schedule block after them and reuse the same CSS custom properties / color approach you see there (e.g. spacing and color variables).

- [ ] **Step 2: Append the schedule styles**

Add to the end of `themes/orillia-eagles/assets/css/styles.css`:

```css
/* -------------------------------------------------------------------------
 * Schedule table
 * ---------------------------------------------------------------------- */
.schedule-inner {
	max-width: var(--wp--style--global--wide-size, 1200px);
	margin-inline: auto;
	padding-inline: clamp(1rem, 4vw, 2.5rem);
}

.schedule-table {
	width: 100%;
	overflow-x: auto;
}

.schedule-row {
	display: grid;
	grid-template-columns: 1fr 1fr 2fr 1fr 1.5fr;
	align-items: center;
	gap: 1rem;
	padding: 0.85rem 1.25rem;
	min-width: 640px;
}

.schedule-row--head {
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	background: var(--wp--preset--color--contrast, #111);
	color: var(--wp--preset--color--base, #fff);
}

/* Alternating body rows. The head row is a sibling, so target rows inside
   the post-template wrapper only. */
.schedule-body .schedule-row:nth-child(odd) {
	background: rgba(0, 0, 0, 0.04);
}

.schedule-body .schedule-row:nth-child(even) {
	background: rgba(0, 0, 0, 0.10);
}

.schedule-cell {
	margin: 0;
}

.schedule-cell--event {
	display: flex;
	flex-direction: column;
	gap: 0.15rem;
}

.schedule-event-opponent:empty {
	display: none;
}

.schedule-cell--level {
	font-weight: 600;
}
```

- [ ] **Step 3: Verify the CSS loads (cache-busted by filemtime)**

Run: `grep -c "schedule-row" wp-content/themes/orillia-eagles/assets/css/styles.css`
Expected: a count ≥ 4. (The theme enqueues this file with `filemtime()` cache-busting, so the change is live on next page load.)

- [ ] **Step 4: Commit (theme file — tracked)**

```bash
git add wp-content/themes/orillia-eagles/assets/css/styles.css
git commit -m "Add schedule table styles with alternating rows"
```

---

## Task 9: Seed sample data + end-to-end visual verification

**Files:** none (data + manual verification only).

**Interfaces:**
- Consumes: everything above.

- [ ] **Step 1: Seed four sample events via WP-CLI**

Run each command (adjust dates as desired; note ascending-date order should surface them soonest-first):

```bash
studio wp post create --post_type=game_event --post_status=publish --post_title="vs. Barrie Colts" --porcelain
```
Then, using the returned ID (call it `ID1`):
```bash
studio wp post meta set ID1 event_date "2026-09-12"
studio wp post meta set ID1 event_time "6:30 PM"
studio wp post meta set ID1 event_location "Rotary Place, Orillia"
studio wp post meta set ID1 event_opponent "Barrie Colts"
studio wp post term set ID1 event_type "Game"
studio wp post term set ID1 event_level "Intermediate"
```
Repeat for a Practice (no opponent):
```bash
studio wp post create --post_type=game_event --post_status=publish --post_title="Team Practice" --porcelain
studio wp post meta set ID2 event_date "2026-09-08"
studio wp post meta set ID2 event_time "5:00 PM"
studio wp post meta set ID2 event_location "Brian Orser Arena"
studio wp post term set ID2 event_type "Practice"
studio wp post term set ID2 event_level "Mixed"
```
Add at least two more (one Junior game, one Senior practice) with varied dates so sorting and alternating rows are visible.

- [ ] **Step 2: Insert the section pattern on a page**

In the browser (Studio site URL → Pages → the page you want, e.g. a new "Schedule" page), insert the **Schedule Section** pattern (it appears under the *Orillia Eagles* / *Featured* category). Save.

- [ ] **Step 3: Verify rendering with a screenshot**

Use the Studio MCP `take_screenshot` tool (or load the page URL) and confirm:
- The header row (Date · Time · Event · Level · Location) shows.
- Events are listed **earliest date first** (Practice 09-08 before Game 09-12).
- Body rows **alternate background color**.
- Practice rows show **no opponent** line (the `:empty` rule hides it); game rows show the opponent.
- The no-results message does **not** appear (data present).

- [ ] **Step 4: Fix-and-recheck loop**

If sorting is wrong, re-verify Task 5's filter (`has_filter` check) and that the query's `orderBy` is `meta_value`. If a column is empty, confirm the meta key spelling matches Task 3. If rows don't alternate, confirm `.schedule-body` wraps the post-template (Task 6, `wp:post-template {"className":"schedule-body"}`). Re-screenshot until all five checks pass.

---

## Self-Review notes

- **Spec coverage:** CPT (T1), taxonomies + seeding (T2), meta (T3), editor panel (T4), AQL date sort (T5), query table pattern (T6), section pattern (T7), alternating-row styles (T8), verification with sample data (T9). All spec sections mapped. Out-of-scope items (filter tabs, hiding past events, custom binding source) intentionally omitted.
- **Type/name consistency:** CPT `game_event`, taxonomies `event_type`/`event_level`, meta `event_date`/`event_time`/`event_location`/`event_opponent`, function prefix `orillia_schedule_`, pattern slugs `orillia-eagles/schedule-query` + `orillia-eagles/schedule-section`, CSS classes `.schedule-row`/`.schedule-cell`/`.schedule-body` used identically across tasks.
- **Placeholder scan:** none.
