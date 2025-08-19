<?php
define('DATA_PATH', __DIR__ . '/data/');

/**
 * Reads data from a CSV file and returns it as an array of associative arrays.
 */
function getCsvData(string $filename): array {
    $filePath = DATA_PATH . $filename;
    if (!file_exists($filePath)) {
        return [];
    }

    $data = [];
    if (($handle = fopen($filePath, 'r')) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        if ($header === FALSE) return [];

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // --- FIX: Only combine if the column count matches the header count ---
            if (count($header) == count($row)) {
                $data[] = array_combine($header, $row);
            }
        }
        fclose($handle);
    }
    return $data;
}

/**
 * Appends a new row of data to a CSV file.
 */
function appendCsvData(string $filename, array $data): void {
    $filePath = DATA_PATH . $filename;
    $handle = fopen($filePath, 'a');
    fputcsv($handle, $data);
    fclose($handle);
}

/**
 * A simple helper to prevent Cross-Site Scripting (XSS).
 */
function sanitize_input(string $data): string {
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Updates a specific row in a CSV file.
 */
function updateCsvRow(string $filename, string $id, string $id_column, array $new_data): bool {
    $filePath = DATA_PATH . $filename;
    if (!file_exists($filePath)) {
        return false;
    }

    $data = [];
    $header = [];
    $updated = false;

    // Read the entire file into memory
    if (($handle = fopen($filePath, 'r')) !== FALSE) {
        $header = fgetcsv($handle);
        if ($header === FALSE) return false; // Added safety check

        while (($row = fgetcsv($handle)) !== FALSE) {
            // --- FIX: The same fix is applied here for robustness ---
            if (count($header) == count($row)) {
                $data[] = array_combine($header, $row);
            }
        }
        fclose($handle);
    } else {
        return false;
    }

    // Find the row and update it
    foreach ($data as &$row) {
        if (isset($row[$id_column]) && $row[$id_column] === $id) {
            foreach ($new_data as $key => $value) {
                if (isset($row[$key])) {
                    $row[$key] = $value;
                }
            }
            $updated = true;
            break;
        }
    }

    // If a row was updated, write everything back to the file
    if ($updated) {
        if (($handle = fopen($filePath, 'w')) !== FALSE) {
            fputcsv($handle, $header);
            foreach ($data as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
            return true;
        }
    }
    
    return false;
}
