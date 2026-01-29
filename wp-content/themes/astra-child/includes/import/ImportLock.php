<?php
/**
 * Import Lock Manager
 *
 * Contract §G: Lock REQUIRED - no 2 updates run in parallel
 */

if (!defined('ABSPATH')) {
    exit;
}

class Docrate_ImportLock {

    /**
     * Lock option name
     */
    const LOCK_OPTION = 'docrate_import_lock';

    /**
     * Lock timeout in seconds (default: 30 minutes)
     */
    const LOCK_TIMEOUT = 1800;

    /**
     * Attempt to acquire import lock
     *
     * @param string $source Import source identifier
     * @return bool|WP_Error True if lock acquired, WP_Error if locked
     */
    public static function acquire($source = 'manual') {
        $lock = get_option(self::LOCK_OPTION);

        // Check if there's an existing valid lock
        if ($lock && is_array($lock)) {
            $lock_time = isset($lock['time']) ? (int) $lock['time'] : 0;

            // Check if lock has expired
            if ((time() - $lock_time) < self::LOCK_TIMEOUT) {
                return new WP_Error(
                    'import_locked',
                    sprintf(
                        'Import already running since %s (source: %s). Please wait.',
                        date('Y-m-d H:i:s', $lock_time),
                        isset($lock['source']) ? $lock['source'] : 'unknown'
                    )
                );
            }
        }

        // Acquire lock
        $lock_data = array(
            'source' => $source,
            'time' => time(),
            'pid' => getmypid()
        );

        update_option(self::LOCK_OPTION, $lock_data);

        // Verify we got the lock (race condition check)
        $verify = get_option(self::LOCK_OPTION);
        if (!$verify || $verify['pid'] !== $lock_data['pid']) {
            return new WP_Error('lock_failed', 'Failed to acquire import lock');
        }

        return true;
    }

    /**
     * Release import lock
     *
     * @return bool
     */
    public static function release() {
        return delete_option(self::LOCK_OPTION);
    }

    /**
     * Check if import is currently locked
     *
     * @return bool|array False if not locked, lock data if locked
     */
    public static function is_locked() {
        $lock = get_option(self::LOCK_OPTION);

        if (!$lock || !is_array($lock)) {
            return false;
        }

        $lock_time = isset($lock['time']) ? (int) $lock['time'] : 0;

        // Check if lock has expired
        if ((time() - $lock_time) >= self::LOCK_TIMEOUT) {
            self::release();
            return false;
        }

        return $lock;
    }

    /**
     * Force release lock (admin only)
     *
     * @return bool
     */
    public static function force_release() {
        // Log this action
        if (function_exists('Docrate_Logger')) {
            Docrate_Logger::log('import', 'Lock force released by admin');
        }

        return self::release();
    }

    /**
     * Get lock status info
     *
     * @return array
     */
    public static function get_status() {
        $lock = self::is_locked();

        if (!$lock) {
            return array(
                'locked' => false,
                'message' => 'No active import'
            );
        }

        return array(
            'locked' => true,
            'source' => isset($lock['source']) ? $lock['source'] : 'unknown',
            'started' => date('Y-m-d H:i:s', $lock['time']),
            'running_for' => time() - $lock['time'],
            'message' => sprintf(
                'Import running from %s (started %s ago)',
                $lock['source'],
                human_time_diff($lock['time'])
            )
        );
    }
}
