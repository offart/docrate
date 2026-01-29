<?php
/**
 * Database Migration System
 *
 * Contract §B: Schema changes = proper Migration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Docrate_Migrator {

    /**
     * Option name for storing migration version
     */
    const VERSION_OPTION = 'docrate_db_version';

    /**
     * Current schema version
     */
    const CURRENT_VERSION = 1;

    /**
     * Run pending migrations
     *
     * @return array Results of migrations
     */
    public static function migrate() {
        $current_version = (int) get_option(self::VERSION_OPTION, 0);
        $results = array();

        if ($current_version >= self::CURRENT_VERSION) {
            return array('status' => 'up_to_date', 'version' => $current_version);
        }

        // Load migration files
        $migrations_dir = dirname(__FILE__) . '/migrations/';

        for ($version = $current_version + 1; $version <= self::CURRENT_VERSION; $version++) {
            $migration_file = $migrations_dir . 'migration_' . str_pad($version, 4, '0', STR_PAD_LEFT) . '.php';

            if (!file_exists($migration_file)) {
                $results[] = array(
                    'version' => $version,
                    'status' => 'error',
                    'message' => 'Migration file not found: ' . $migration_file
                );
                break;
            }

            require_once $migration_file;

            $class_name = 'Docrate_Migration_' . str_pad($version, 4, '0', STR_PAD_LEFT);

            if (!class_exists($class_name)) {
                $results[] = array(
                    'version' => $version,
                    'status' => 'error',
                    'message' => 'Migration class not found: ' . $class_name
                );
                break;
            }

            $migration = new $class_name();

            try {
                $migration->up();
                update_option(self::VERSION_OPTION, $version);
                $results[] = array(
                    'version' => $version,
                    'status' => 'success',
                    'message' => $migration->get_description()
                );
            } catch (Exception $e) {
                $results[] = array(
                    'version' => $version,
                    'status' => 'error',
                    'message' => $e->getMessage()
                );
                break;
            }
        }

        return array(
            'status' => 'migrated',
            'from_version' => $current_version,
            'to_version' => (int) get_option(self::VERSION_OPTION, 0),
            'migrations' => $results
        );
    }

    /**
     * Get current database version
     *
     * @return int
     */
    public static function get_version() {
        return (int) get_option(self::VERSION_OPTION, 0);
    }

    /**
     * Check if migrations are pending
     *
     * @return bool
     */
    public static function has_pending() {
        return self::get_version() < self::CURRENT_VERSION;
    }
}
