<?php

declare(strict_types=1);

function learner_management_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    learner_ensure_column('learners', 'mother_tongue', 'VARCHAR(120) NULL AFTER birthdate');
    learner_ensure_column('learners', 'religion', 'VARCHAR(120) NULL AFTER mother_tongue');
    learner_ensure_column('learners', 'address_house_number', 'VARCHAR(120) NULL AFTER religion');
    learner_ensure_column('learners', 'address_barangay', 'VARCHAR(120) NULL AFTER address_house_number');
    learner_ensure_column('learners', 'address_city_municipality', 'VARCHAR(120) NULL AFTER address_barangay');
    learner_ensure_column('learners', 'address_province', 'VARCHAR(120) NULL AFTER address_city_municipality');

    $bootstrapped = true;
}

function learner_ensure_column(string $tableName, string $columnName, string $definition): void
{
    $statement = database()->prepare(
        'SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :table_schema
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        'table_schema' => DB_NAME,
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);
    $row = $statement->fetch();

    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    database()->exec(sprintf(
        'ALTER TABLE %s ADD COLUMN %s %s',
        $tableName,
        $columnName,
        $definition
    ));
}

function learner_default_city_municipality(): string
{
    return 'Candon City';
}

function learner_default_mother_tongue(): string
{
    return 'Iloko';
}

function learner_default_province(): string
{
    return 'Ilocos Sur';
}

function learner_religion_options(): array
{
    return [
        'Christianity',
        'Islam',
        'Others',
    ];
}

function learner_religion_options_with_selected(?string $selectedReligion): array
{
    $options = learner_religion_options();
    $selectedReligion = trim((string) $selectedReligion);

    if ($selectedReligion !== '' && !in_array($selectedReligion, $options, true)) {
        array_unshift($options, $selectedReligion);
    }

    return $options;
}

function learner_form_defaults(array $overrides = []): array
{
    return array_merge([
        'id' => null,
        'lrn' => '',
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'birthdate' => '',
        'mother_tongue' => learner_default_mother_tongue(),
        'religion' => '',
        'address_house_number' => '',
        'address_barangay' => '',
        'address_city_municipality' => learner_default_city_municipality(),
        'address_province' => learner_default_province(),
        'sex' => '',
        'current_status' => 'active',
        'grade_level' => '',
        'section_id' => '',
    ], $overrides);
}

function learner_profile_form_defaults(array $overrides = []): array
{
    return array_merge([
        'learner_id' => '',
        'lrn' => '',
        'learner_name' => '',
        'birthdate' => '',
        'mother_tongue' => learner_default_mother_tongue(),
        'religion' => '',
        'address_house_number' => '',
        'address_barangay' => '',
        'address_city_municipality' => learner_default_city_municipality(),
        'address_province' => learner_default_province(),
    ], $overrides);
}

function learner_import_clean_string(?string $value): string
{
    $clean = trim((string) $value);

    return preg_replace('/^\xEF\xBB\xBF/', '', $clean) ?? $clean;
}

function learner_normalize_status(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = str_replace(['-', ' '], '_', $normalized);

    return match ($normalized) {
        'active' => 'active',
        'inactive' => 'inactive',
        'graduated' => 'graduated',
        'transferred', 'transfered' => 'transferred',
        default => $normalized,
    };
}

function learner_normalize_sex(string $value): string
{
    return strtolower(trim($value));
}

function learner_normalize_birthdate(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        return $value;
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('Y-m-d', $timestamp);
}

function learner_grade_level_options(): array
{
    return [
        'Kinder',
        'Grade 1',
        'Grade 2',
        'Grade 3',
        'Grade 4',
        'Grade 5',
        'Grade 6',
        'Grade 7',
        'Grade 8',
        'Grade 9',
        'Grade 10',
        'Grade 11',
        'Grade 12',
    ];
}

function current_school_year(): ?array
{
    learner_management_bootstrap();

    $statement = database()->query(
        'SELECT id, label, start_date, end_date
         FROM school_years
         ORDER BY is_current DESC, start_date DESC, id DESC
         LIMIT 1'
    );

    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function require_current_school_year(): array
{
    $schoolYear = current_school_year();

    if ($schoolYear === null) {
        throw new RuntimeException('Create a school year first before managing learners.');
    }

    return $schoolYear;
}

function learner_sections(): array
{
    learner_management_bootstrap();
    $schoolYear = require_current_school_year();

    $statement = database()->prepare(
        'SELECT id, name, grade_level
         FROM sections
         WHERE school_year_id = :school_year_id
         ORDER BY grade_level ASC, name ASC'
    );
    $statement->execute(['school_year_id' => $schoolYear['id']]);

    return $statement->fetchAll();
}

function learner_school_years(int $learnerId): array
{
    $statement = database()->prepare(
        'SELECT DISTINCT
            sy.id,
            sy.label
         FROM learner_enrollments le
         INNER JOIN school_years sy ON sy.id = le.school_year_id
         WHERE le.learner_id = :learner_id
         ORDER BY sy.start_date DESC'
    );
    $statement->execute(['learner_id' => $learnerId]);
    return $statement->fetchAll();
}

function learner_list_filters(): array
{
    return [
        'keyword' => trim((string) ($_GET['keyword'] ?? '')),
        'status' => learner_normalize_status((string) ($_GET['status'] ?? '')),
        'grade_level' => trim((string) ($_GET['grade_level'] ?? '')),
        'section_id' => trim((string) ($_GET['section_id'] ?? '')),
    ];
}

function learner_list(array $filters): array
{
    learner_management_bootstrap();
    $schoolYear = require_current_school_year();

    $conditions = ['1 = 1'];
    $params = ['school_year_id' => $schoolYear['id']];

    if (!empty($filters['keyword'])) {
        $conditions[] = '(l.learner_number LIKE :keyword OR l.lrn LIKE :keyword OR l.first_name LIKE :keyword OR l.middle_name LIKE :keyword OR l.last_name LIKE :keyword)';
        $params['keyword'] = '%' . $filters['keyword'] . '%';
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'l.current_status = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['grade_level'])) {
        $conditions[] = 'COALESCE(le.grade_level, \'\') = :grade_level';
        $params['grade_level'] = $filters['grade_level'];
    }

    if (!empty($filters['section_id'])) {
        $conditions[] = 'COALESCE(le.section_id, 0) = :section_id';
        $params['section_id'] = (int) $filters['section_id'];
    }

    $statement = database()->prepare(
        'SELECT
            l.id,
            l.learner_number,
            l.lrn,
            l.first_name,
            l.middle_name,
            l.last_name,
            l.birthdate,
            l.mother_tongue,
            l.religion,
            l.address_house_number,
            l.address_barangay,
            l.address_city_municipality,
            l.address_province,
            l.sex,
            l.current_status,
            le.grade_level,
            le.section_id,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            sy.label AS school_year_label
         FROM learners l
         LEFT JOIN learner_enrollments le
            ON le.learner_id = l.id
           AND le.school_year_id = :school_year_id
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN school_years sy ON sy.id = le.school_year_id
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY l.last_name ASC, l.first_name ASC, l.id ASC'
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function learner_find(int $learnerId): ?array
{
    learner_management_bootstrap();
    $schoolYear = require_current_school_year();

    $statement = database()->prepare(
        'SELECT
            l.id,
            l.learner_number,
            l.lrn,
            l.first_name,
            l.middle_name,
            l.last_name,
            l.birthdate,
            l.mother_tongue,
            l.religion,
            l.address_house_number,
            l.address_barangay,
            l.address_city_municipality,
            l.address_province,
            l.sex,
            l.current_status,
            COALESCE(le.grade_level, \'\') AS grade_level,
            COALESCE(le.section_id, \'\') AS section_id,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            sy.label AS school_year_label
         FROM learners l
         LEFT JOIN learner_enrollments le
            ON le.learner_id = l.id
           AND le.school_year_id = :school_year_id
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN school_years sy ON sy.id = le.school_year_id
         WHERE l.id = :learner_id
         LIMIT 1'
    );
    $statement->execute([
        'school_year_id' => $schoolYear['id'],
        'learner_id' => $learnerId,
    ]);

    $row = $statement->fetch();

    return $row === false ? null : learner_form_defaults($row);
}

function learner_normalize_payload(array $input): array
{
    $payload = learner_form_defaults([
        'id' => isset($input['id']) && $input['id'] !== '' ? (int) $input['id'] : null,
        'lrn' => preg_replace('/\D+/', '', trim((string) ($input['lrn'] ?? ''))) ?? '',
        'first_name' => trim((string) ($input['first_name'] ?? '')),
        'middle_name' => trim((string) ($input['middle_name'] ?? '')),
        'last_name' => trim((string) ($input['last_name'] ?? '')),
        'birthdate' => learner_normalize_birthdate((string) ($input['birthdate'] ?? $input['bday'] ?? '')),
        'mother_tongue' => trim((string) ($input['mother_tongue'] ?? '')),
        'religion' => trim((string) ($input['religion'] ?? '')),
        'address_house_number' => trim((string) ($input['address_house_number'] ?? '')),
        'address_barangay' => trim((string) ($input['address_barangay'] ?? '')),
        'address_city_municipality' => trim((string) ($input['address_city_municipality'] ?? '')) !== ''
            ? trim((string) ($input['address_city_municipality'] ?? ''))
            : learner_default_city_municipality(),
        'address_province' => trim((string) ($input['address_province'] ?? '')) !== ''
            ? trim((string) ($input['address_province'] ?? ''))
            : learner_default_province(),
        'sex' => learner_normalize_sex((string) ($input['sex'] ?? '')),
        'current_status' => learner_normalize_status((string) ($input['current_status'] ?? 'active')),
        'grade_level' => trim((string) ($input['grade_level'] ?? '')),
        'section_id' => trim((string) ($input['section_id'] ?? '')),
    ]);

    return $payload;
}

function learner_validate_payload(array $payload): array
{
    $errors = [];

    if ($payload['lrn'] === '' || strlen($payload['lrn']) !== 12) {
        $errors[] = 'LRN must be exactly 12 digits.';
    }

    if ($payload['first_name'] === '' || $payload['last_name'] === '') {
        $errors[] = 'First name and last name are required.';
    }

    if ($payload['grade_level'] === '') {
        $errors[] = 'Grade level is required.';
    }

    if ($payload['birthdate'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['birthdate'])) {
        $errors[] = 'Birthdate must use YYYY-MM-DD format.';
    }

    if ($payload['sex'] !== '' && !in_array($payload['sex'], ['male', 'female'], true)) {
        $errors[] = 'Sex must be male or female.';
    }

    if (!in_array($payload['current_status'], ['active', 'inactive', 'graduated', 'transferred'], true)) {
        $errors[] = 'Current status is invalid.';
    }

    return $errors;
}

function learner_profile_normalize_payload(array $input): array
{
    return learner_profile_form_defaults([
        'learner_id' => trim((string) ($input['learner_id'] ?? '')),
        'lrn' => preg_replace('/\D+/', '', trim((string) ($input['lrn'] ?? ''))) ?? '',
        'learner_name' => trim((string) ($input['learner_name'] ?? '')),
        'birthdate' => learner_normalize_birthdate((string) ($input['birthdate'] ?? '')),
        'mother_tongue' => trim((string) ($input['mother_tongue'] ?? '')),
        'religion' => trim((string) ($input['religion'] ?? '')),
        'address_house_number' => trim((string) ($input['address_house_number'] ?? '')),
        'address_barangay' => trim((string) ($input['address_barangay'] ?? '')),
        'address_city_municipality' => trim((string) ($input['address_city_municipality'] ?? '')) !== ''
            ? trim((string) ($input['address_city_municipality'] ?? ''))
            : learner_default_city_municipality(),
        'address_province' => trim((string) ($input['address_province'] ?? '')) !== ''
            ? trim((string) ($input['address_province'] ?? ''))
            : learner_default_province(),
    ]);
}

function learner_profile_validate_payload(array $payload): array
{
    $errors = [];

    if ($payload['learner_id'] === '' || (int) $payload['learner_id'] <= 0) {
        $errors[] = 'Choose a learner before saving the basic profile.';
    }

    if ($payload['birthdate'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['birthdate']) !== 1) {
        $errors[] = 'Birthdate must use YYYY-MM-DD format.';
    }

    return $errors;
}

function learner_profile_import_template_headers(): array
{
    return [
        'lrn',
        'birthdate',
        'mother_tongue',
        'religion',
        'address_house_number',
        'address_barangay',
        'address_city_municipality',
        'address_province',
    ];
}

function learner_profile_normalize_import_row(array $row): array
{
    $payload = learner_profile_form_defaults([
        'lrn' => preg_replace('/\D+/', '', learner_import_clean_string((string) ($row['lrn'] ?? ''))) ?? '',
        'birthdate' => learner_normalize_birthdate(
            learner_import_clean_string((string) ($row['birthdate'] ?? $row['bday'] ?? ''))
        ),
        'mother_tongue' => learner_import_clean_string((string) ($row['mother_tongue'] ?? '')),
        'religion' => learner_import_clean_string((string) ($row['religion'] ?? '')),
        'address_house_number' => learner_import_clean_string((string) ($row['address_house_number'] ?? '')),
        'address_barangay' => learner_import_clean_string((string) ($row['address_barangay'] ?? '')),
        'address_city_municipality' => learner_import_clean_string((string) ($row['address_city_municipality'] ?? '')) !== ''
            ? learner_import_clean_string((string) ($row['address_city_municipality'] ?? ''))
            : learner_default_city_municipality(),
        'address_province' => learner_import_clean_string((string) ($row['address_province'] ?? '')) !== ''
            ? learner_import_clean_string((string) ($row['address_province'] ?? ''))
            : learner_default_province(),
    ]);

    if ($payload['lrn'] === '' || strlen($payload['lrn']) !== 12) {
        throw new RuntimeException('Each learner profile row must include a valid 12-digit LRN.');
    }

    if ($payload['birthdate'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['birthdate']) !== 1) {
        throw new RuntimeException('Birthdate values in the learner profile import must use YYYY-MM-DD format.');
    }

    return $payload;
}

function learner_profile_import_rows_from_file(array $file): array
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Choose a CSV or XLS learner profile file to import.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    return match ($extension) {
        'csv' => learner_parse_csv_rows($file['tmp_name']),
        'xls' => learner_parse_excel_xml_rows($file['tmp_name']),
        default => throw new RuntimeException('Supported learner profile import files are CSV and the provided XLS template.'),
    };
}

function learner_first_friday_of_june(int $year): string
{
    $date = new DateTimeImmutable(sprintf('%04d-06-01', $year));

    while ($date->format('N') !== '5') {
        $date = $date->modify('+1 day');
    }

    return $date->format('Y-m-d');
}

function learner_age_on_reference_date(?string $birthdate, string $referenceDate): ?int
{
    if ($birthdate === null || trim($birthdate) === '') {
        return null;
    }

    $birth = date_create_immutable($birthdate);
    $reference = date_create_immutable($referenceDate);

    if ($birth === false || $reference === false) {
        return null;
    }

    return $birth->diff($reference)->invert === 1 ? null : $birth->diff($reference)->y;
}

function learner_reference_date_for_school_year(?array $schoolYear = null): string
{
    $schoolYear = $schoolYear ?? current_school_year();
    $referenceYear = $schoolYear !== null && !empty($schoolYear['start_date'])
        ? (int) substr((string) $schoolYear['start_date'], 0, 4)
        : (int) date('Y');

    return learner_first_friday_of_june($referenceYear);
}

function learner_age_for_school_year(?string $birthdate, ?array $schoolYear = null): ?int
{
    return learner_age_on_reference_date($birthdate, learner_reference_date_for_school_year($schoolYear));
}

function learner_generate_number(): string
{
    $statement = database()->query(
        "SELECT MAX(CAST(SUBSTRING(learner_number, 4) AS UNSIGNED)) AS max_number
         FROM learners
         WHERE learner_number REGEXP '^LP-[0-9]+$'"
    );
    $row = $statement->fetch();
    $nextNumber = (int) ($row['max_number'] ?? 0) + 1;

    return 'LP-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
}

function learner_save(array $payload): void
{
    learner_management_bootstrap();
    $errors = learner_validate_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $schoolYear = require_current_school_year();
    $pdo = database();
    $pdo->beginTransaction();

    try {
        $sectionId = $payload['section_id'] !== '' ? (int) $payload['section_id'] : null;

        if ($payload['id'] === null) {
            $generatedLearnerNumber = learner_generate_number();
            $statement = $pdo->prepare(
                'INSERT INTO learners (
                    learner_number,
                    lrn,
                    first_name,
                    middle_name,
                    last_name,
                    birthdate,
                    mother_tongue,
                    religion,
                    address_house_number,
                    address_barangay,
                    address_city_municipality,
                    address_province,
                    sex,
                    current_status
                 ) VALUES (
                    :learner_number,
                    :lrn,
                    :first_name,
                    :middle_name,
                    :last_name,
                    :birthdate,
                    :mother_tongue,
                    :religion,
                    :address_house_number,
                    :address_barangay,
                    :address_city_municipality,
                    :address_province,
                    :sex,
                    :current_status
                 )'
            );
            $statement->execute([
                'learner_number' => $generatedLearnerNumber,
                'lrn' => $payload['lrn'],
                'first_name' => $payload['first_name'],
                'middle_name' => $payload['middle_name'] !== '' ? $payload['middle_name'] : null,
                'last_name' => $payload['last_name'],
                'birthdate' => $payload['birthdate'] !== '' ? $payload['birthdate'] : null,
                'mother_tongue' => $payload['mother_tongue'] !== '' ? $payload['mother_tongue'] : null,
                'religion' => $payload['religion'] !== '' ? $payload['religion'] : null,
                'address_house_number' => $payload['address_house_number'] !== '' ? $payload['address_house_number'] : null,
                'address_barangay' => $payload['address_barangay'] !== '' ? $payload['address_barangay'] : null,
                'address_city_municipality' => $payload['address_city_municipality'] !== '' ? $payload['address_city_municipality'] : learner_default_city_municipality(),
                'address_province' => $payload['address_province'] !== '' ? $payload['address_province'] : learner_default_province(),
                'sex' => $payload['sex'] !== '' ? $payload['sex'] : null,
                'current_status' => $payload['current_status'],
            ]);

            $learnerId = (int) $pdo->lastInsertId();
        } else {
            $currentLearnerStatement = $pdo->prepare(
                'SELECT learner_number FROM learners WHERE id = :id LIMIT 1'
            );
            $currentLearnerStatement->execute(['id' => $payload['id']]);
            $currentLearner = $currentLearnerStatement->fetch();

            if ($currentLearner === false) {
                throw new RuntimeException('Learner record not found.');
            }

            $statement = $pdo->prepare(
                'UPDATE learners
                 SET learner_number = :learner_number,
                     lrn = :lrn,
                     first_name = :first_name,
                     middle_name = :middle_name,
                     last_name = :last_name,
                     birthdate = :birthdate,
                     mother_tongue = :mother_tongue,
                     religion = :religion,
                     address_house_number = :address_house_number,
                     address_barangay = :address_barangay,
                     address_city_municipality = :address_city_municipality,
                     address_province = :address_province,
                     sex = :sex,
                     current_status = :current_status,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $statement->execute([
                'learner_number' => $currentLearner['learner_number'],
                'lrn' => $payload['lrn'],
                'first_name' => $payload['first_name'],
                'middle_name' => $payload['middle_name'] !== '' ? $payload['middle_name'] : null,
                'last_name' => $payload['last_name'],
                'birthdate' => $payload['birthdate'] !== '' ? $payload['birthdate'] : null,
                'mother_tongue' => $payload['mother_tongue'] !== '' ? $payload['mother_tongue'] : null,
                'religion' => $payload['religion'] !== '' ? $payload['religion'] : null,
                'address_house_number' => $payload['address_house_number'] !== '' ? $payload['address_house_number'] : null,
                'address_barangay' => $payload['address_barangay'] !== '' ? $payload['address_barangay'] : null,
                'address_city_municipality' => $payload['address_city_municipality'] !== '' ? $payload['address_city_municipality'] : learner_default_city_municipality(),
                'address_province' => $payload['address_province'] !== '' ? $payload['address_province'] : learner_default_province(),
                'sex' => $payload['sex'] !== '' ? $payload['sex'] : null,
                'current_status' => $payload['current_status'],
                'id' => $payload['id'],
            ]);

            $learnerId = (int) $payload['id'];
        }

        $enrollmentStatement = $pdo->prepare(
            'SELECT id
             FROM learner_enrollments
             WHERE learner_id = :learner_id
               AND school_year_id = :school_year_id
             LIMIT 1'
        );
        $enrollmentStatement->execute([
            'learner_id' => $learnerId,
            'school_year_id' => $schoolYear['id'],
        ]);
        $existingEnrollment = $enrollmentStatement->fetch();

        if ($existingEnrollment === false) {
            $insertEnrollment = $pdo->prepare(
                'INSERT INTO learner_enrollments (
                    learner_id,
                    school_year_id,
                    grade_level,
                    section_id,
                    enrollment_status,
                    enrolled_at
                 ) VALUES (
                    :learner_id,
                    :school_year_id,
                    :grade_level,
                    :section_id,
                    :enrollment_status,
                    :enrolled_at
                 )'
            );
            $insertEnrollment->execute([
                'learner_id' => $learnerId,
                'school_year_id' => $schoolYear['id'],
                'grade_level' => $payload['grade_level'],
                'section_id' => $sectionId,
                'enrollment_status' => $payload['current_status'] === 'active' ? 'enrolled' : 'completed',
                'enrolled_at' => date('Y-m-d'),
            ]);
        } else {
            $updateEnrollment = $pdo->prepare(
                'UPDATE learner_enrollments
                 SET grade_level = :grade_level,
                     section_id = :section_id,
                     enrollment_status = :enrollment_status
                 WHERE id = :id'
            );
            $updateEnrollment->execute([
                'grade_level' => $payload['grade_level'],
                'section_id' => $sectionId,
                'enrollment_status' => $payload['current_status'] === 'active' ? 'enrolled' : 'completed',
                'id' => $existingEnrollment['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof PDOException && str_contains((string) $exception->getMessage(), 'Duplicate')) {
            throw new RuntimeException('Learner number or LRN already exists.');
        }

        throw $exception;
    }
}

function learner_delete(int $learnerId): void
{
    learner_management_bootstrap();
    $statement = database()->prepare('DELETE FROM learners WHERE id = :id');
    $statement->execute(['id' => $learnerId]);
}

function learner_import_file(array $file): int
{
    learner_management_bootstrap();
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Choose a CSV or XLS file to import.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    $rows = match ($extension) {
        'csv' => learner_parse_csv_rows($file['tmp_name']),
        'xls' => learner_parse_excel_xml_rows($file['tmp_name']),
        default => throw new RuntimeException('Supported import files are CSV and the provided XLS template.'),
    };

    if ($rows === []) {
        throw new RuntimeException('The import file does not contain learner rows.');
    }

    $schoolYear = require_current_school_year();
    $sections = learner_sections();
    $sectionMap = [];

    foreach ($sections as $section) {
        $sectionMap[strtolower($section['name']) . '|' . strtolower($section['grade_level'])] = (int) $section['id'];
    }

    $importedCount = 0;

    foreach ($rows as $row) {
        $payload = learner_normalize_payload($row);

        if (
            $payload['lrn'] === '' &&
            $payload['first_name'] === '' &&
            $payload['last_name'] === ''
        ) {
            continue;
        }

        if (isset($row['section_name']) && trim((string) $row['section_name']) !== '') {
            $sectionName = trim((string) $row['section_name']);
            $sectionKey = strtolower($sectionName) . '|' . strtolower($payload['grade_level']);

            if (!isset($sectionMap[$sectionKey])) {
                $insertSection = database()->prepare(
                    'INSERT INTO sections (name, grade_level, school_year_id, adviser_name)
                     VALUES (:name, :grade_level, :school_year_id, :adviser_name)'
                );
                $insertSection->execute([
                    'name' => $sectionName,
                    'grade_level' => $payload['grade_level'],
                    'school_year_id' => $schoolYear['id'],
                    'adviser_name' => null,
                ]);

                $sectionMap[$sectionKey] = (int) database()->lastInsertId();
            }

            $payload['section_id'] = (string) $sectionMap[$sectionKey];
        }

        $existingStatement = database()->prepare(
            'SELECT id
             FROM learners
             WHERE lrn = :lrn
             LIMIT 1'
        );
        $existingStatement->execute([
            'lrn' => $payload['lrn'],
        ]);
        $existing = $existingStatement->fetch();

        if ($existing !== false) {
            $payload['id'] = (int) $existing['id'];
        }

        learner_save($payload);
        $importedCount++;
    }

    return $importedCount;
}

function learner_parse_csv_rows(string $path): array
{
    $handle = fopen($path, 'rb');

    if ($handle === false) {
        throw new RuntimeException('Unable to read the CSV file.');
    }

    $header = fgetcsv($handle);

    if (!is_array($header)) {
        fclose($handle);
        throw new RuntimeException('CSV header row is missing.');
    }

    $normalizedHeader = array_map(
        static fn ($value): string => strtolower(learner_import_clean_string((string) $value)),
        $header
    );

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $item = [];

        foreach ($normalizedHeader as $index => $column) {
            $item[$column] = isset($row[$index]) ? learner_import_clean_string((string) $row[$index]) : '';
        }

        $rows[] = $item;
    }

    fclose($handle);

    return $rows;
}

function learner_parse_excel_xml_rows(string $path): array
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path);
    libxml_clear_errors();

    if ($xml === false) {
        throw new RuntimeException('Unsupported XLS file. Use the provided Excel template or import as CSV.');
    }

    $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
    $rowNodes = $xml->xpath('//*[local-name()="Worksheet"]/*[local-name()="Table"]/*[local-name()="Row"]');

    if (!is_array($rowNodes) || $rowNodes === []) {
        throw new RuntimeException('The XLS template does not contain rows.');
    }

    $rows = [];
    $header = [];

    foreach ($rowNodes as $rowIndex => $rowNode) {
        $cells = [];
        $position = 0;

        foreach ($rowNode->xpath('./*[local-name()="Cell"]') as $cellNode) {
            $attributes = $cellNode->attributes('ss', true);
            if (isset($attributes['Index'])) {
                $position = (int) $attributes['Index'] - 1;
            }

            $dataNodes = $cellNode->xpath('./*[local-name()="Data"]');
            $cells[$position] = isset($dataNodes[0]) ? learner_import_clean_string((string) $dataNodes[0]) : '';
            $position++;
        }

        ksort($cells);
        $values = array_values($cells);

        if ($rowIndex === 0) {
            $header = array_map(
                static fn ($value): string => strtolower(learner_import_clean_string((string) $value)),
                $values
            );
            continue;
        }

        $item = [];

        foreach ($header as $index => $column) {
            $item[$column] = $values[$index] ?? '';
        }

        $rows[] = $item;
    }

    return $rows;
}
