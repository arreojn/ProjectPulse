<?php

declare(strict_types=1);

const PROJECTPULSE_IMPORT_MAX_BYTES = 5242880;
const PROJECTPULSE_IMPORT_MAX_ROWS = 2000;

function import_validate_upload(array $file, array $allowedExtensions): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Choose a valid import file.');
    }

    $path = (string) $file['tmp_name'];
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > PROJECTPULSE_IMPORT_MAX_BYTES) {
        throw new RuntimeException('Import files must be between 1 byte and 5 MB.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Unsupported import file type.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
    $allowedMimeTypes = $extension === 'csv'
        ? ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel']
        : ['application/xml', 'text/xml', 'application/vnd.ms-excel', 'application/octet-stream'];

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        throw new RuntimeException('The uploaded file content does not match its file type.');
    }

    return $extension;
}

function import_validate_headers(array $headers, array $requiredHeaders): void
{
    $headers = array_values(array_filter(array_map(
        static fn ($value): string => strtolower(trim((string) $value)),
        $headers
    ), static fn (string $value): bool => $value !== ''));

    if (count($headers) !== count(array_unique($headers))) {
        throw new RuntimeException('Import headers must be unique.');
    }

    foreach ($requiredHeaders as $requiredHeader) {
        if (!in_array($requiredHeader, $headers, true)) {
            throw new RuntimeException('Import file is missing the required column: ' . $requiredHeader . '.');
        }
    }
}

function import_assert_row_limit(int $rowCount): void
{
    if ($rowCount > PROJECTPULSE_IMPORT_MAX_ROWS) {
        throw new RuntimeException('Import files may contain at most ' . PROJECTPULSE_IMPORT_MAX_ROWS . ' data rows.');
    }
}

function csv_safe_value($value): string
{
    $value = (string) $value;

    return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'" . $value : $value;
}

