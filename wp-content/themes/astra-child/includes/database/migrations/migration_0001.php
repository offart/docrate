<?php
/**
 * Migration 0001: Initial Schema
 *
 * Creates base tables for:
 * - Doctors (Domain: Doctor Identity)
 * - Arrangements (Domain: Arrangements)
 * - Specialties (Domain: Specialties)
 * - Import logs (Domain: Import History)
 * - AI Mappings (Domain: Normalization Mappings)
 * - Entry logs (for counting visits)
 * - Ratings placeholder (Phase 1: internal only)
 *
 * Contract §A: Custom Tables for search data
 * Contract §B: Each Domain has SSOT
 * Contract §E: Soft-delete for business data
 */

if (!defined('ABSPATH')) {
    exit;
}

class Docrate_Migration_0001 {

    /**
     * Get migration description
     *
     * @return string
     */
    public function get_description() {
        return 'Initial schema - doctors, arrangements, specialties, imports, mappings';
    }

    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'docrate_';

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // =====================================================
        // DOCTORS TABLE (Domain: Doctor Identity)
        // SSOT for doctor basic information
        // =====================================================
        $sql_doctors = "CREATE TABLE {$prefix}doctors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            license_number VARCHAR(50) DEFAULT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(100) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_license (license_number),
            KEY idx_name (last_name, first_name),
            KEY idx_city (city),
            KEY idx_deleted (deleted_at)
        ) $charset_collate;";

        dbDelta($sql_doctors);

        // =====================================================
        // SPECIALTIES TABLE (Domain: Specialties)
        // Links doctors to their specialties (source + normalized)
        // =====================================================
        $sql_specialties = "CREATE TABLE {$prefix}specialties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            source_specialty VARCHAR(255) NOT NULL,
            normalized_specialty VARCHAR(255) DEFAULT NULL,
            source_company VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_doctor (doctor_id),
            KEY idx_normalized (normalized_specialty),
            KEY idx_source (source_company)
        ) $charset_collate;";

        dbDelta($sql_specialties);

        // =====================================================
        // ARRANGEMENTS TABLE (Domain: Arrangements)
        // Which insurance companies have agreements with which doctors
        // =====================================================
        $sql_arrangements = "CREATE TABLE {$prefix}arrangements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            insurance_company VARCHAR(100) NOT NULL,
            arrangement_type VARCHAR(100) DEFAULT NULL,
            source_file VARCHAR(255) DEFAULT NULL,
            import_id BIGINT UNSIGNED DEFAULT NULL,
            valid_from DATE DEFAULT NULL,
            valid_until DATE DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_doctor (doctor_id),
            KEY idx_company (insurance_company),
            KEY idx_import (import_id),
            KEY idx_deleted (deleted_at)
        ) $charset_collate;";

        dbDelta($sql_arrangements);

        // =====================================================
        // IMPORT LOGS TABLE (Domain: Import History)
        // Tracks import runs, successes, failures
        // =====================================================
        $sql_import_logs = "CREATE TABLE {$prefix}import_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(100) NOT NULL,
            filename VARCHAR(255) DEFAULT NULL,
            status ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            total_rows INT UNSIGNED DEFAULT 0,
            processed_rows INT UNSIGNED DEFAULT 0,
            new_doctors INT UNSIGNED DEFAULT 0,
            updated_doctors INT UNSIGNED DEFAULT 0,
            errors_count INT UNSIGNED DEFAULT 0,
            error_message TEXT DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source (source),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";

        dbDelta($sql_import_logs);

        // =====================================================
        // IMPORT ROWS TABLE (Domain: Import History)
        // Individual row results from imports (for debugging)
        // =====================================================
        $sql_import_rows = "CREATE TABLE {$prefix}import_rows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            import_id BIGINT UNSIGNED NOT NULL,
            source_row INT UNSIGNED NOT NULL,
            status ENUM('success', 'error', 'skipped') NOT NULL,
            doctor_id BIGINT UNSIGNED DEFAULT NULL,
            error_message VARCHAR(500) DEFAULT NULL,
            raw_data TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_import (import_id),
            KEY idx_status (status)
        ) $charset_collate;";

        dbDelta($sql_import_rows);

        // =====================================================
        // AI MAPPINGS TABLE (Domain: Normalization Mappings)
        // Contract §I: Mappings table = SSOT
        // =====================================================
        $sql_ai_mappings = "CREATE TABLE {$prefix}ai_mappings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mapping_type ENUM('specialty', 'city', 'company') NOT NULL DEFAULT 'specialty',
            source_value VARCHAR(255) NOT NULL,
            normalized_value VARCHAR(255) NOT NULL,
            confidence DECIMAL(3,2) DEFAULT NULL,
            is_manual_override TINYINT(1) NOT NULL DEFAULT 0,
            approved_by BIGINT UNSIGNED DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_source_type (mapping_type, source_value),
            KEY idx_normalized (normalized_value),
            KEY idx_manual (is_manual_override)
        ) $charset_collate;";

        dbDelta($sql_ai_mappings);

        // =====================================================
        // ENTRY LOGS TABLE
        // Tracks visits to the platform (for "how many entered")
        // Contract §L: Minimal PII in logs
        // =====================================================
        $sql_entry_logs = "CREATE TABLE {$prefix}entry_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            entry_source VARCHAR(50) DEFAULT NULL,
            referrer VARCHAR(255) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            ip_hash VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_session (session_id),
            KEY idx_created (created_at),
            KEY idx_source (entry_source)
        ) $charset_collate;";

        dbDelta($sql_entry_logs);

        // =====================================================
        // RATINGS TABLE (Phase 1: internal only)
        // Contract §J: DB schema placeholders allowed
        // =====================================================
        $sql_ratings = "CREATE TABLE {$prefix}ratings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doctor_id BIGINT UNSIGNED NOT NULL,
            rating_type ENUM('internal', 'user') NOT NULL DEFAULT 'internal',
            score DECIMAL(2,1) NOT NULL,
            source VARCHAR(100) DEFAULT NULL,
            import_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_doctor (doctor_id),
            KEY idx_type (rating_type),
            KEY idx_deleted (deleted_at)
        ) $charset_collate;";

        dbDelta($sql_ratings);
    }

    /**
     * Rollback the migration (if needed)
     */
    public function down() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'docrate_';

        // Drop tables in reverse order (foreign key considerations)
        $tables = array(
            'ratings',
            'entry_logs',
            'ai_mappings',
            'import_rows',
            'import_logs',
            'arrangements',
            'specialties',
            'doctors'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$prefix}{$table}");
        }
    }
}
