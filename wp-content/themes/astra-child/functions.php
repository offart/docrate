<?php
/**
 * Astra Child Theme - Docrate
 *
 * Doctors Rating Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

// Theme version
define('DOCRATE_VERSION', '1.0.0');

/**
 * Enqueue parent and child theme styles
 */
function docrate_enqueue_styles() {
    // Parent theme style
    wp_enqueue_style(
        'astra-parent-style',
        get_template_directory_uri() . '/style.css',
        array(),
        DOCRATE_VERSION
    );

    // Child theme style
    wp_enqueue_style(
        'astra-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('astra-parent-style'),
        DOCRATE_VERSION
    );
}
add_action('wp_enqueue_scripts', 'docrate_enqueue_styles');

/**
 * Load Config class
 */
require_once get_stylesheet_directory() . '/includes/config/Config.php';
