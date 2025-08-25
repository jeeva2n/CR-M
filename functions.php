<?php
define('DATA_PATH', __DIR__ . '/data/');

function generateUniqueId(string $filename, string $columnName, string $prefix = ''): string {
    $filePath = DATA_PATH . $filename;
    $existingIds = [];
    if (file_exists($filePath) && ($handle = fopen($filePath, "r")) !== false) {
        $headers = fgetcsv($handle);
        $idIndex = $headers ? array_search($columnName, $headers) : false;
        if ($idIndex !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                if (isset($row[$idIndex]) && $row[$idIndex] !== '') { $existingIds[] = $row[$idIndex]; }
            }
        }
        fclose($handle);
    }
    do {
        $letters = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $prefix), 0, 3));
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        while (strlen($letters) < 3) { $letters .= $alphabet[random_int(0, 25)]; }
        $numbers = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $uniqueId = $letters . $numbers;
    } while (in_array($uniqueId, $existingIds, true));
    return $uniqueId;
}

function getCsvData(string $filename): array {
    $filePath = DATA_PATH . $filename;
    if (!file_exists($filePath)) { return []; }
    $data = [];
    if (($handle = fopen($filePath, 'r')) !== FALSE) {
        $header = fgetcsv($handle);
        if ($header === FALSE) return [];
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($header) == count($row)) { $data[] = array_combine($header, $row); }
        }
        fclose($handle);
    }
    return $data;
}

function appendCsvData(string $filename, array $data): void {
    $filePath = DATA_PATH . $filename;
    $handle = fopen($filePath, 'a');
    fputcsv($handle, $data);
    fclose($handle);
}

function sanitize_input(string $data): string {
    return htmlspecialchars(stripslashes(trim($data)));
}

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
            if (count($header) == count($row)) { $data[] = array_combine($header, $row); }
        }
        fclose($handle);
    } else { return false; }
    foreach ($data as &$row_ref) {
        if (isset($row_ref[$id_column]) && $row_ref[$id_column] === $id) {
            foreach ($new_data as $key => $value) {
                if (isset($row_ref[$key])) { $row_ref[$key] = $value; }
            }
            $updated = true;
            break;
        }
    }
    if ($updated) {
        if (($handle = fopen($filePath, 'w')) !== FALSE) {
            fputcsv($handle, $header);
            foreach ($data as $row_data) { fputcsv($handle, array_values($row_data)); }
            fclose($handle);
            return true;
        }
    }
    return false;
}
