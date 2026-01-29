<?php
/**
 * Excel Parser
 *
 * Parses Excel files from different insurance companies
 * Contract §H: All data is sanitized before use
 */

if (!defined('ABSPATH')) {
    exit;
}

class Docrate_ExcelParser {

    /**
     * Supported file extensions
     */
    const SUPPORTED_EXTENSIONS = array('xlsx', 'xls', 'csv');

    /**
     * Parse an Excel file
     *
     * @param string $file_path Path to the file
     * @param array $options Parsing options
     * @return array|WP_Error Parsed data or error
     */
    public static function parse($file_path, $options = array()) {
        // Check file exists
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'File not found: ' . $file_path);
        }

        // Check extension
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (!in_array($extension, self::SUPPORTED_EXTENSIONS)) {
            return new WP_Error(
                'unsupported_format',
                'Unsupported file format: ' . $extension
            );
        }

        // Parse based on extension
        switch ($extension) {
            case 'csv':
                return self::parse_csv($file_path, $options);
            case 'xlsx':
            case 'xls':
                return self::parse_excel($file_path, $options);
            default:
                return new WP_Error('unknown_format', 'Unknown format');
        }
    }

    /**
     * Parse CSV file
     *
     * @param string $file_path Path to CSV
     * @param array $options Options
     * @return array|WP_Error
     */
    private static function parse_csv($file_path, $options = array()) {
        $defaults = array(
            'delimiter' => ',',
            'enclosure' => '"',
            'header_row' => 1,
            'skip_empty' => true
        );
        $options = wp_parse_args($options, $defaults);

        $rows = array();
        $headers = array();
        $row_number = 0;

        if (($handle = fopen($file_path, 'r')) !== false) {
            while (($data = fgetcsv($handle, 0, $options['delimiter'], $options['enclosure'])) !== false) {
                $row_number++;

                // Skip rows before header
                if ($row_number < $options['header_row']) {
                    continue;
                }

                // Header row
                if ($row_number === $options['header_row']) {
                    $headers = array_map('trim', $data);
                    continue;
                }

                // Skip empty rows
                if ($options['skip_empty'] && self::is_empty_row($data)) {
                    continue;
                }

                // Create associative array
                $row = array();
                foreach ($headers as $index => $header) {
                    $row[$header] = isset($data[$index]) ? $data[$index] : '';
                }

                $rows[] = array(
                    'row_number' => $row_number,
                    'data' => $row
                );
            }
            fclose($handle);
        }

        return array(
            'headers' => $headers,
            'rows' => $rows,
            'total_rows' => count($rows),
            'file' => basename($file_path)
        );
    }

    /**
     * Parse Excel file (xlsx/xls)
     * Note: Requires PhpSpreadsheet library
     *
     * @param string $file_path Path to Excel
     * @param array $options Options
     * @return array|WP_Error
     */
    private static function parse_excel($file_path, $options = array()) {
        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            // Fallback: try to use a simpler parser or return error
            return new WP_Error(
                'library_missing',
                'PhpSpreadsheet library required for Excel parsing. Please install via Composer.'
            );
        }

        $defaults = array(
            'header_row' => 1,
            'sheet_index' => 0,
            'skip_empty' => true
        );
        $options = wp_parse_args($options, $defaults);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $sheet = $spreadsheet->getSheet($options['sheet_index']);
            $data = $sheet->toArray();

            $headers = array();
            $rows = array();

            foreach ($data as $row_number => $row_data) {
                $actual_row = $row_number + 1;

                // Skip rows before header
                if ($actual_row < $options['header_row']) {
                    continue;
                }

                // Header row
                if ($actual_row === $options['header_row']) {
                    $headers = array_map('trim', $row_data);
                    continue;
                }

                // Skip empty rows
                if ($options['skip_empty'] && self::is_empty_row($row_data)) {
                    continue;
                }

                // Create associative array
                $row = array();
                foreach ($headers as $index => $header) {
                    if (empty($header)) continue;
                    $row[$header] = isset($row_data[$index]) ? $row_data[$index] : '';
                }

                $rows[] = array(
                    'row_number' => $actual_row,
                    'data' => $row
                );
            }

            return array(
                'headers' => $headers,
                'rows' => $rows,
                'total_rows' => count($rows),
                'file' => basename($file_path)
            );

        } catch (Exception $e) {
            return new WP_Error('parse_error', 'Failed to parse Excel: ' . $e->getMessage());
        }
    }

    /**
     * Check if a row is empty
     *
     * @param array $row Row data
     * @return bool
     */
    private static function is_empty_row($row) {
        foreach ($row as $cell) {
            if (!empty(trim((string) $cell))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Detect column mapping based on header names
     *
     * @param array $headers Headers from file
     * @return array Mapping of standard field => source column
     */
    public static function detect_column_mapping($headers) {
        $mappings = array(
            'first_name' => array('שם פרטי', 'first name', 'firstname', 'שם'),
            'last_name' => array('שם משפחה', 'last name', 'lastname', 'משפחה'),
            'license_number' => array('מספר רישיון', 'רישיון', 'license', 'license number', 'lic'),
            'phone' => array('טלפון', 'phone', 'tel', 'telephone', 'נייד', 'mobile'),
            'email' => array('אימייל', 'מייל', 'email', 'e-mail'),
            'city' => array('עיר', 'city', 'ישוב'),
            'address' => array('כתובת', 'address', 'רחוב'),
            'specialty' => array('התמחות', 'specialty', 'specialization', 'תחום'),
        );

        $result = array();
        $headers_lower = array_map(function($h) {
            return mb_strtolower(trim($h));
        }, $headers);

        foreach ($mappings as $field => $possible_names) {
            foreach ($possible_names as $name) {
                $index = array_search(mb_strtolower($name), $headers_lower);
                if ($index !== false) {
                    $result[$field] = $headers[$index];
                    break;
                }
            }
        }

        return $result;
    }
}
