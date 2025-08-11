<?php
define('DATA_PATH', __DIR__ . '/data/');
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');

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
 * Formats currency with Indian Rupee symbol
 */
function formatCurrency(float $amount): string {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

/**
 * Formats currency with Indian Rupee symbol and code
 */
function formatCurrencyWithCode(float $amount): string {
    return CURRENCY_SYMBOL . number_format($amount, 2) . ' ' . CURRENCY_CODE;
}
