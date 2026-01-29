<?php
/**
 * Configuration handler
 *
 * Contract §D: Secrets NOT in theme, NOT in git, NOT in DB
 * Access values ONLY through Config::get()
 */

if (!defined('ABSPATH')) {
    exit;
}

class Docrate_Config {

    /**
     * Cached config values
     *
     * @var array|null
     */
    private static $config = null;

    /**
     * Get a configuration value
     *
     * @param string $key     The config key (e.g., 'ai_api_key')
     * @param mixed  $default Default value if key not found
     * @return mixed
     */
    public static function get($key, $default = null) {
        self::load_config();

        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }

        return $default;
    }

    /**
     * Check if a configuration key exists
     *
     * @param string $key The config key
     * @return bool
     */
    public static function has($key) {
        self::load_config();
        return isset(self::$config[$key]);
    }

    /**
     * Load configuration from secrets file
     * File should be outside webroot in /www/[site]/private/
     */
    private static function load_config() {
        if (self::$config !== null) {
            return;
        }

        self::$config = array();

        // Secrets file path - outside webroot
        // Contract §D: Separate Staging/Production configs
        $secrets_file = defined('DOCRATE_SECRETS_FILE')
            ? DOCRATE_SECRETS_FILE
            : dirname(ABSPATH) . '/private/docrate-secrets.php';

        if (file_exists($secrets_file)) {
            $secrets = include $secrets_file;
            if (is_array($secrets)) {
                self::$config = $secrets;
            }
        }
    }

    /**
     * Get environment (staging/production)
     *
     * @return string
     */
    public static function get_environment() {
        return self::get('environment', 'production');
    }

    /**
     * Check if staging environment
     *
     * @return bool
     */
    public static function is_staging() {
        return self::get_environment() === 'staging';
    }
}
