<?php
/**
 * Theme Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme setup
 */
function orillia_eagles_setup() {
    load_theme_textdomain( 'orillia-eagles', get_template_directory() . '/languages' );

    // Enable editor styles support and register editor stylesheets
    // Using add_editor_style() ensures styles are properly scoped to the editor canvas
    // and don't leak into the WordPress admin sidebar/UI
    add_theme_support( 'editor-styles' );
    add_editor_style( 'style.css' );
    add_editor_style( 'assets/css/styles.css' );
    add_editor_style( 'assets/css/editor.css' );
    add_editor_style( 'assets/css/editor-page.css' );
}
add_action( 'after_setup_theme', 'orillia_eagles_setup' );

/**
 * Register a dedicated block pattern category so the theme's custom patterns are
 * grouped together (and easy to find) in the Site Editor pattern explorer.
 */
function orillia_eagles_register_pattern_categories() {
    register_block_pattern_category(
        'orillia-eagles',
        array(
            'label'       => __( 'Orillia Eagles', 'orillia-eagles' ),
            'description' => __( 'Custom patterns built for the Orillia Eagles theme.', 'orillia-eagles' ),
        )
    );
}
add_action( 'init', 'orillia_eagles_register_pattern_categories' );

/**
 * Enqueue theme styles on frontend only.
 * Editor styles are loaded via add_editor_style() in theme setup to prevent
 * font styles from leaking into WordPress admin UI.
 */
function orillia_eagles_enqueue_frontend_styles() {
    // Use filemtime() for cache busting when CSS files change
    $styles_path = get_template_directory() . '/assets/css/styles.css';
    $styles_version = file_exists( $styles_path ) ? filemtime( $styles_path ) : wp_get_theme()->get( 'Version' );

    wp_enqueue_style(
        'orillia-eagles-base',
        get_stylesheet_uri(),
        array(),
        filemtime( get_stylesheet_directory() . '/style.css' )
    );

    wp_enqueue_style(
        'orillia-eagles-styles',
        get_template_directory_uri() . '/assets/css/styles.css',
        array( 'orillia-eagles-base' ),
        $styles_version
    );
}
add_action( 'wp_enqueue_scripts', 'orillia_eagles_enqueue_frontend_styles' );

/**
 * Enqueue theme scripts for frontend only
 */
function orillia_eagles_enqueue_scripts() {
    // Enqueue JavaScript if it exists, use filemtime() for cache busting
    $script_path = get_template_directory() . '/assets/js/script.js';
    $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : wp_get_theme()->get( 'Version' );

    if ( file_exists( $script_path ) ) {
        wp_enqueue_script(
            'orillia-eagles-script',
            get_template_directory_uri() . '/assets/js/script.js',
            array(),
            $script_version,
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'orillia_eagles_enqueue_scripts' );

/**
 * Hide the title field on the front page in the block editor.
 * The homepage content includes its own header — the WordPress post title
 * is redundant and visually confusing.
 *
 * Uses both admin_head (for elements outside the iframe) and
 * block_editor_settings_all (for elements inside the iframe) because
 * the title wrapper location varies by WordPress version.
 */
function orillia_eagles_hide_front_page_title_admin() {
    $front_page_id = (int) get_option( 'page_on_front' );
    if ( ! $front_page_id ) {
        return;
    }
    $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
    if ( $post_id !== $front_page_id ) {
        return;
    }
    echo '<style>.editor-visual-editor__post-title-wrapper, .edit-post-visual-editor__post-title-wrapper { display: none !important; }</style>';
}
add_action( 'admin_head', 'orillia_eagles_hide_front_page_title_admin' );

function orillia_eagles_hide_front_page_title_iframe( $settings, $context ) {
    $front_page_id = (int) get_option( 'page_on_front' );
    if ( ! $front_page_id || ! isset( $context->post ) ) {
        return $settings;
    }
    if ( (int) $context->post->ID !== $front_page_id ) {
        return $settings;
    }
    $settings['styles'][] = array(
        'css' => '.editor-visual-editor__post-title-wrapper, .edit-post-visual-editor__post-title-wrapper, .editor-post-title { display: none !important; }',
    );
    return $settings;
}
add_filter( 'block_editor_settings_all', 'orillia_eagles_hide_front_page_title_iframe', 10, 2 );

/**
 * Add JS class to html element for animation system.
 * Enqueued as external script to comply with Content Security Policy.
 */
function orillia_eagles_add_js_class() {
    $js_class_path = get_template_directory() . '/assets/js/js-class.js';
    $js_class_version = file_exists( $js_class_path ) ? filemtime( $js_class_path ) : wp_get_theme()->get( 'Version' );
    wp_enqueue_script( 'orillia-eagles-js-class', get_template_directory_uri() . '/assets/js/js-class.js', array(), $js_class_version, false );
}
add_action( 'wp_enqueue_scripts', 'orillia_eagles_add_js_class', 1 );

