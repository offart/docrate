<?php
/**
 * Input Sanitizer
 *
 * Contract §H: Scraped Data = Untrusted Input
 * All text from import goes through Sanitization before DB
 * No untrusted HTML saved as-is
 */

if (!defined('ABSPATH')) {
    exit;
}

class Docrate_Sanitizer {

    /**
     * Sanitize a text field from import
     *
     * @param string $value Raw value
     * @return string Sanitized value
     */
    public static function text($value) {
        if (!is_string($value)) {
            return '';
        }

        // Remove any HTML tags
        $value = wp_strip_all_tags($value);

        // Decode HTML entities
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

        // Normalize whitespace
        $value = preg_replace('/\s+/', ' ', $value);

        // Trim
        $value = trim($value);

        return $value;
    }

    /**
     * Sanitize a name (person name)
     *
     * @param string $value Raw name
     * @return string Sanitized name
     */
    public static function name($value) {
        $value = self::text($value);

        // Remove numbers (names shouldn't have numbers)
        $value = preg_replace('/[0-9]/', '', $value);

        // Remove extra punctuation (keep hyphens and apostrophes for names like O'Brien)
        $value = preg_replace('/[^\p{L}\s\-\']/u', '', $value);

        // Normalize multiple spaces/hyphens
        $value = preg_replace('/[\s\-]+/', ' ', $value);

        return trim($value);
    }

    /**
     * Sanitize a phone number
     *
     * @param string $value Raw phone
     * @return string Sanitized phone (digits and formatting only)
     */
    public static function phone($value) {
        if (!is_string($value)) {
            return '';
        }

        // Keep only digits, plus sign, hyphens, spaces, parentheses
        $value = preg_replace('/[^0-9\+\-\s\(\)]/', '', $value);

        // Normalize whitespace
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    /**
     * Sanitize an email
     *
     * @param string $value Raw email
     * @return string Sanitized email or empty if invalid
     */
    public static function email($value) {
        $value = self::text($value);
        $value = strtolower($value);

        // Use WordPress sanitize_email
        $sanitized = sanitize_email($value);

        // Validate
        if (!is_email($sanitized)) {
            return '';
        }

        return $sanitized;
    }

    /**
     * Sanitize a license number
     *
     * @param string $value Raw license
     * @return string Sanitized license
     */
    public static function license($value) {
        if (!is_string($value)) {
            return '';
        }

        // Keep only alphanumeric and common separators
        $value = preg_replace('/[^a-zA-Z0-9\-\/]/', '', $value);

        return trim($value);
    }

    /**
     * Sanitize specialty text
     *
     * @param string $value Raw specialty
     * @return string Sanitized specialty
     */
    public static function specialty($value) {
        $value = self::text($value);

        // Remove anything that looks like HTML or code
        $value = preg_replace('/<[^>]*>/', '', $value);

        // Keep Hebrew, English, spaces, hyphens, parentheses
        $value = preg_replace('/[^\p{Hebrew}\p{Latin}\s\-\(\)\/,]/u', '', $value);

        return trim($value);
    }

    /**
     * Sanitize an entire row of import data
     *
     * @param array $row Raw row data
     * @param array $field_types Field name => type mapping
     * @return array Sanitized row
     */
    public static function row($row, $field_types = array()) {
        $sanitized = array();

        foreach ($row as $field => $value) {
            $type = isset($field_types[$field]) ? $field_types[$field] : 'text';

            switch ($type) {
                case 'name':
                    $sanitized[$field] = self::name($value);
                    break;
                case 'phone':
                    $sanitized[$field] = self::phone($value);
                    break;
                case 'email':
                    $sanitized[$field] = self::email($value);
                    break;
                case 'license':
                    $sanitized[$field] = self::license($value);
                    break;
                case 'specialty':
                    $sanitized[$field] = self::specialty($value);
                    break;
                default:
                    $sanitized[$field] = self::text($value);
            }
        }

        return $sanitized;
    }

    /**
     * Mask phone number for logging
     * Contract §L: Masking for phone numbers
     *
     * @param string $phone Phone number
     * @return string Masked phone (e.g., 050-***-1234)
     */
    public static function mask_phone($phone) {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($digits) < 7) {
            return '***';
        }

        // Show first 3 and last 4 digits
        return substr($digits, 0, 3) . '-***-' . substr($digits, -4);
    }
}
