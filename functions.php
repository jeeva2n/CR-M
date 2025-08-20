<?php
define('DATA_PATH', __DIR__ . '/data/');

// --- NEW FUNCTION: To generate a unique, incremental 5-digit Order ID ---
function generateNewOrderId(): int {
    $orders = getCsvData('orders.csv');
    
    // If there are no orders yet, start with the first 5-digit number.
    if (empty($orders)) {
        return 10000;
    }
    
    // Get all existing order IDs
    $all_ids = array_column($orders, 'order_id');
    
    // Find the highest ID and add 1 to it
    $new_id = max($all_ids) + 1;
    
    return $new_id;
}
// --- END NEW FUNCTION ---


/**
 * Reads data from a CSV file and returns it as an array of associative arrays.
 */
function getCsvData(string $filename): array {
    $filePath = DATA_PATH . $filename;
    if (!file_exists($filePath)) { return []; }

    $data = [];
    if (($handle = fopen($filePath, 'r')) !== FALSE) {
        $header = fgetcsv($handle);
        if ($header === FALSE) return [];

        while (($row = fgetcsv($handle)) !== FALSE) {
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
    if (!file_exists($filePath)) { return false; }

    $data = [];
    $header = [];
    $updated = false;

    if (($handle = fopen($filePath, 'r')) !== FALSE) {
        $header = fgetcsv($handle);
        if ($header === FALSE) return false;

        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($header) == count($row)) {
                $data[] = array_combine($header, $row);
            }
        }
        fclose($handle);
    } else { return false; }

    foreach ($data as &$row_ref) { // Use a different variable name for reference
        if (isset($row_ref[$id_column]) && $row_ref[$id_column] === $id) {
            foreach ($new_data as $key => $value) {
                if (isset($row_ref[$key])) {
                    $row_ref[$key] = $value;
                }
            }
            $updated = true;
            break;
        }
    }

    if ($updated) {
        if (($handle = fopen($filePath, 'w')) !== FALSE) {
            fputcsv($handle, $header);
            foreach ($data as $row_data) { // Use another variable name here
                fputcsv($handle, array_values($row_data));
            }
            fclose($handle);
            return true;
        }
    }
    
    return false;
}
