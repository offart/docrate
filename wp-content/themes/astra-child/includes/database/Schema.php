<?php
/**
 * Database Schema Definitions
 *
 * Contract §A: Search data in Custom Tables (NOT wp_posts/wp_postmeta)
 * Contract §B: Each Domain has one source of truth (SSOT)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Docrate_Schema {

    /**
     * Table prefix for Docrate tables
     */
    const TABLE_PREFIX = 'docrate_';

    /**
     * Get full table name with WordPress prefix
     *
     * @param string $table Table name without prefix
     * @return string Full table name
     */
    public static function table($table) {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_PREFIX . $table;
    }

    /**
     * Get all Docrate table names
     *
     * @return array Table names
     */
    public static function get_tables() {
        return array(
            'doctors'        => self::table('doctors'),
            'arrangements'   => self::table('arrangements'),
            'specialties'    => self::table('specialties'),
            'import_logs'    => self::table('import_logs'),
            'import_rows'    => self::table('import_rows'),
            'ai_mappings'    => self::table('ai_mappings'),
            'entry_logs'     => self::table('entry_logs'),
            'ratings'        => self::table('ratings'),
        );
    }

    /**
     * Check if a table exists
     *
     * @param string $table Table name without prefix
     * @return bool
     */
    public static function table_exists($table) {
        global $wpdb;
        $full_name = self::table($table);
        return $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $full_name
        )) === $full_name;
    }
}
