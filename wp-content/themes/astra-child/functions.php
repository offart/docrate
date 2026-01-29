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
 * Load Docrate classes
 */
$docrate_includes = array(
    // Config (Contract §D)
    'config/Config.php',

    // Database (Contract §A, §B)
    'database/Schema.php',
    'database/Migrator.php',

    // Import (Contract §G, §H)
    'import/ImportLock.php',
    'import/Sanitizer.php',
    'import/ExcelParser.php',
);

foreach ($docrate_includes as $file) {
    $path = get_stylesheet_directory() . '/includes/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

/**
 * Admin notice for pending migrations
 * NOTE: Migrations run manually via Admin button only (not auto-run)
 */
function docrate_migration_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (class_exists('Docrate_Migrator') && Docrate_Migrator::has_pending()) {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>Docrate:</strong> Database migrations pending. ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=docrate-settings&action=migrate')) . '">Run migrations</a>';
        echo '</p></div>';
    }
}
add_action('admin_notices', 'docrate_migration_notice');
