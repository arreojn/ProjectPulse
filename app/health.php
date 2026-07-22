<?php

declare(strict_types=1);

function health_portal_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    auth_bootstrap();

    if (!auth_table_exists('users')) {
        $bootstrapped = true;
        return;
    }

    auth_ensure_user_role('health');

    $pdo = database();
    $userStatement = $pdo->prepare(
        'INSERT IGNORE INTO users (username, email, first_name, middle_name, last_name, password_hash, role, is_active)
         VALUES (:username, :email, :first_name, :middle_name, :last_name, :password_hash, :role, :is_active)'
    );
    $userStatement->execute([
        'username' => 'health_coordinator',
        'email' => 'health@projectpulse.local',
        'first_name' => 'Health',
        'middle_name' => null,
        'last_name' => 'Coordinator',
        'password_hash' => password_hash('health123', PASSWORD_DEFAULT),
        'role' => 'health',
        'is_active' => 1,
    ]);

    $pdo->prepare(
        'UPDATE users
         SET first_name = :first_name,
             middle_name = :middle_name,
             last_name = :last_name,
             updated_at = CURRENT_TIMESTAMP
         WHERE username = :username
           AND role = :role'
    )->execute([
        'first_name' => 'Health',
        'middle_name' => null,
        'last_name' => 'Coordinator',
        'username' => 'health_coordinator',
        'role' => 'health',
    ]);

    if (!auth_table_exists('learner_enrollments') || !auth_table_exists('school_years')) {
        $bootstrapped = true;
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS learner_health_measurements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            learner_enrollment_id INT UNSIGNED NOT NULL UNIQUE,
            height_cm DECIMAL(5,2) NULL,
            weight_kg DECIMAL(5,2) NULL,
            recorded_on DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_health_measurement_enrollment
                FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
                ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS learner_deworming_records (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            learner_enrollment_id INT UNSIGNED NOT NULL,
            dose_number TINYINT UNSIGNED NOT NULL,
            administered_on DATE NOT NULL,
            created_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_deworming_enrollment_dose (learner_enrollment_id, dose_number),
            CONSTRAINT fk_deworming_enrollment
                FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_deworming_created_by
                FOREIGN KEY (created_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS feeding_program_recipients (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            learner_enrollment_id INT UNSIGNED NOT NULL UNIQUE,
            school_year_id INT UNSIGNED NOT NULL,
            enrolled_on DATE NOT NULL,
            created_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_feeding_recipient_enrollment
                FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_feeding_recipient_school_year
                FOREIGN KEY (school_year_id) REFERENCES school_years(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_feeding_recipient_created_by
                FOREIGN KEY (created_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
        )'
    );

    $bootstrapped = true;
}

function health_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function health_bmi_remark_options(): array
{
    return [
        'Underweight',
        'Normal',
        'Overweight',
        'Obese',
        'Not measured',
    ];
}

function health_filter_defaults(array $source = []): array
{
    $gradeSectionFilter = trim((string) ($source['grade_section_filter'] ?? 'all|all'));
    $parts = explode('|', $gradeSectionFilter);
    $gradeLevel = $parts[0] ?? 'all';
    $sectionId = $parts[1] ?? 'all';

    if ($gradeLevel !== 'all' && !in_array($gradeLevel, learner_grade_level_options(), true)) {
        $gradeLevel = 'all';
        $sectionId = 'all';
    }

    if ($sectionId !== 'all') {
        $sectionOptions = health_filter_section_options_for_dropdown(); // Use the comprehensive list
        $found = false;
        foreach ($sectionOptions as $option) {
            if ($option['value'] === $gradeLevel . '|' . $sectionId) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $sectionId = 'all';
        }
    }

    $bmiRemarks = trim((string) ($source['bmi_remarks'] ?? ''));

    if ($bmiRemarks !== '' && !in_array($bmiRemarks, health_bmi_remark_options(), true)) {
        $bmiRemarks = '';
    }

    return [
        'grade_section_filter' => $gradeLevel . '|' . $sectionId,
        'grade_level' => $gradeLevel === 'all' ? '' : $gradeLevel,
        'section_id' => $sectionId === 'all' ? '' : $sectionId,
        'keyword' => trim((string) ($source['keyword'] ?? '')),
        'bmi_remarks' => $bmiRemarks,
    ];
}

function health_filter_section_options_for_dropdown(): array
{
    $schoolYear = require_current_school_year();
    $pdo = database();

    $options = [];
    $options[] = ['value' => 'all|all', 'label' => 'All Grade Levels and Sections'];

    foreach (learner_grade_level_options() as $gradeLevel) {
        $options[] = ['value' => $gradeLevel . '|all', 'label' => $gradeLevel . ' - All Sections'];
        $sectionsStatement = $pdo->prepare('SELECT id, name FROM sections WHERE school_year_id = :school_year_id AND grade_level = :grade_level ORDER BY name ASC');
        $sectionsStatement->execute(['school_year_id' => (int) $schoolYear['id'], 'grade_level' => $gradeLevel]);
        foreach ($sectionsStatement->fetchAll() as $section) {
            $options[] = ['value' => $gradeLevel . '|' . $section['id'], 'label' => $gradeLevel . ' - ' . $section['name']];
        }
    }

    return $options;
}

function health_filter_section_options(string $gradeLevel = ''): array
{
    $schoolYear = require_current_school_year();
    $params = ['school_year_id' => (int) $schoolYear['id']];
    $conditions = ['school_year_id = :school_year_id'];

    if ($gradeLevel !== '') {
        $conditions[] = 'grade_level = :grade_level';
        $params['grade_level'] = $gradeLevel;
    }

    $statement = database()->prepare(
        'SELECT id, name, grade_level
         FROM sections
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY grade_level ASC, name ASC'
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function health_enrollment_filter_conditions(array $filters, array &$params, string $alias = 'le', ?string $learnerAlias = 'l'): array
{
    $conditions = [];

    if (($filters['grade_level'] ?? '') !== '') {
        $conditions[] = $alias . '.grade_level = :grade_level';
        $params['grade_level'] = $filters['grade_level'];
    }

    if (($filters['section_id'] ?? '') !== '') {
        $conditions[] = 'COALESCE(' . $alias . '.section_id, 0) = :section_id';
        $params['section_id'] = (int) $filters['section_id'];
    }

    if ($learnerAlias !== null && trim((string) ($filters['keyword'] ?? '')) !== '') {
        $conditions[] = '(' . $learnerAlias . '.lrn LIKE :keyword OR ' .
            $learnerAlias . '.learner_number LIKE :keyword OR ' .
            $learnerAlias . '.first_name LIKE :keyword OR ' .
            $learnerAlias . '.middle_name LIKE :keyword OR ' .
            $learnerAlias . '.last_name LIKE :keyword OR ' .
            'CONCAT(' . $learnerAlias . '.first_name, \' \', COALESCE(' . $learnerAlias . '.middle_name, \'\'), \' \', ' . $learnerAlias . '.last_name) LIKE :keyword OR ' .
            'CONCAT(' . $learnerAlias . '.last_name, \', \', ' . $learnerAlias . '.first_name) LIKE :keyword)';
        $params['keyword'] = '%' . trim((string) $filters['keyword']) . '%';
    }

    return $conditions;
}

function health_full_learner_name(array $learner): string
{
    $name = trim((string) ($learner['last_name'] ?? ''));

    if (trim((string) ($learner['first_name'] ?? '')) !== '') {
        $name .= ($name !== '' ? ', ' : '') . trim((string) $learner['first_name']);
    }

    if (trim((string) ($learner['middle_name'] ?? '')) !== '') {
        $name .= ' ' . trim((string) $learner['middle_name']);
    }

    return trim($name);
}

function health_parse_measurement(?string $value, string $label, float $min, float $max): ?float
{
    $clean = trim((string) $value);

    if ($clean === '') {
        return null;
    }

    if (!is_numeric($clean)) {
        throw new RuntimeException($label . ' must be numeric.');
    }

    $numeric = round((float) $clean, 2);

    if ($numeric < $min || $numeric > $max) {
        throw new RuntimeException($label . ' must be between ' . $min . ' and ' . $max . '.');
    }

    return $numeric;
}

function health_calculate_bmi($heightCm, $weightKg): ?float
{
    if ($heightCm === null || $weightKg === null || $heightCm === '' || $weightKg === '') {
        return null;
    }

    $height = (float) $heightCm;
    $weight = (float) $weightKg;

    if ($height <= 0 || $weight <= 0) {
        return null;
    }

    $heightMeters = $height / 100;
    $bmi = $weight / ($heightMeters * $heightMeters);

    return round($bmi, 2);
}

function health_bmi_remarks(?float $bmi): string
{
    if ($bmi === null) {
        return '-';
    }

    return match (true) {
        $bmi < 18.5 => 'Underweight',
        $bmi < 25 => 'Normal',
        $bmi < 30 => 'Overweight',
        default => 'Obese',
    };
}

function health_dashboard_stats(): array
{
    $schoolYear = require_current_school_year();
    $statement = database()->prepare(
        'SELECT
            (SELECT COUNT(*)
             FROM learner_enrollments le
             WHERE le.school_year_id = :school_year_id) AS total_learners,
            (SELECT COUNT(*)
             FROM learner_health_measurements hm
             INNER JOIN learner_enrollments le ON le.id = hm.learner_enrollment_id
             WHERE le.school_year_id = :school_year_id
               AND hm.height_cm IS NOT NULL
               AND hm.weight_kg IS NOT NULL) AS measured_learners,
            (SELECT COUNT(*)
             FROM learner_deworming_records dr
             INNER JOIN learner_enrollments le ON le.id = dr.learner_enrollment_id
             WHERE le.school_year_id = :school_year_id
               AND dr.dose_number = 1) AS first_dose_count,
            (SELECT COUNT(*)
             FROM learner_deworming_records dr
             INNER JOIN learner_enrollments le ON le.id = dr.learner_enrollment_id
             WHERE le.school_year_id = :school_year_id
               AND dr.dose_number = 2) AS second_dose_count,
            (SELECT COUNT(*)
             FROM feeding_program_recipients fpr
             WHERE fpr.school_year_id = :school_year_id) AS feeding_count'
    );
    $statement->execute(['school_year_id' => (int) $schoolYear['id']]);

    return $statement->fetch() ?: [
        'total_learners' => 0,
        'measured_learners' => 0,
        'first_dose_count' => 0,
        'second_dose_count' => 0,
        'feeding_count' => 0,
    ];
}

function health_learner_rows(array $filters): array
{
    $schoolYear = require_current_school_year();
    $params = ['school_year_id' => (int) $schoolYear['id']];
    $conditions = health_enrollment_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT
            le.id AS learner_enrollment_id,
            l.id AS learner_id,
            l.learner_number,
            l.lrn,
            l.first_name,
            l.middle_name,
            l.last_name,
            l.sex,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            hm.height_cm,
            hm.weight_kg,
            hm.recorded_on
         FROM learner_enrollments le
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN learner_health_measurements hm ON hm.learner_enrollment_id = le.id
         WHERE le.school_year_id = :school_year_id' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         ORDER BY le.grade_level ASC, section_name ASC, l.last_name ASC, l.first_name ASC, l.id ASC'
    );
    $statement->execute($params);
    $rows = $statement->fetchAll();

    foreach ($rows as &$row) {
        $row['complete_name'] = health_full_learner_name($row);
        $row['bmi'] = health_calculate_bmi($row['height_cm'] ?? null, $row['weight_kg'] ?? null);
        $row['bmi_remarks'] = health_bmi_remarks($row['bmi']);
    }
    unset($row);

    if (($filters['bmi_remarks'] ?? '') !== '') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            $remarks = $row['bmi'] === null ? 'Not measured' : (string) $row['bmi_remarks'];

            return $remarks === $filters['bmi_remarks'];
        }));
    }

    return $rows;
}

function health_dashboard_bmi_remarks_rows(): array
{
    $counts = array_fill_keys(health_bmi_remark_options(), 0);

    foreach (health_learner_rows([]) as $row) {
        $remarks = $row['bmi'] === null ? 'Not measured' : (string) $row['bmi_remarks'];

        if (!array_key_exists($remarks, $counts)) {
            $counts[$remarks] = 0;
        }

        $counts[$remarks]++;
    }

    $colors = [
        'Underweight' => '#dd6b20',
        'Normal' => '#17663a',
        'Overweight' => '#b45309',
        'Obese' => '#a12b2b',
        'Not measured' => '#52606d',
    ];
    $rows = [];

    foreach ($counts as $label => $total) {
        $rows[] = [
            'label' => $label,
            'total' => (int) $total,
            'color' => $colors[$label] ?? '#b45309',
        ];
    }

    return $rows;
}

function health_deworming_status_counts(array $filters = []): array
{
    $schoolYear = require_current_school_year();
    $params = ['school_year_id' => (int) $schoolYear['id']];
    $conditions = health_enrollment_filter_conditions($filters, $params);

    $sql = 'SELECT
                COUNT(DISTINCT le.id) AS total_learners,
                COUNT(DISTINCT CASE WHEN dr1.dose_number = 1 THEN le.id END) AS first_dose_count,
                COUNT(DISTINCT CASE WHEN dr2.dose_number = 2 THEN le.id END) AS second_dose_count
            FROM learner_enrollments le
            INNER JOIN learners l ON l.id = le.learner_id
            LEFT JOIN learner_deworming_records dr1 ON dr1.learner_enrollment_id = le.id AND dr1.dose_number = 1
            LEFT JOIN learner_deworming_records dr2 ON dr2.learner_enrollment_id = le.id AND dr2.dose_number = 2
            WHERE le.school_year_id = :school_year_id' .
            ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '');

    $statement = database()->prepare($sql);
    $statement->execute($params);
    $counts = $statement->fetch();

    $totalLearners = (int) ($counts['total_learners'] ?? 0);
    $firstDoseCount = (int) ($counts['first_dose_count'] ?? 0);
    $secondDoseCount = (int) ($counts['second_dose_count'] ?? 0);
    $noDoseCount = $totalLearners - $firstDoseCount;

    return [
        'total_learners' => $totalLearners,
        'first_dose_count' => $firstDoseCount,
        'second_dose_count' => $secondDoseCount,
        'no_dose_count' => $noDoseCount,
    ];
}

function health_feeding_program_status_counts(array $filters = []): array
{
    $schoolYear = require_current_school_year();
    $params = ['school_year_id' => (int) $schoolYear['id']];
    $conditions = health_enrollment_filter_conditions($filters, $params);

    $sql = 'SELECT
                COUNT(DISTINCT le.id) AS total_learners,
                COUNT(DISTINCT fpr.learner_enrollment_id) AS recipient_count
            FROM learner_enrollments le
            INNER JOIN learners l ON l.id = le.learner_id
            LEFT JOIN feeding_program_recipients fpr ON fpr.learner_enrollment_id = le.id
            WHERE le.school_year_id = :school_year_id' .
            ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '');

    $statement = database()->prepare($sql);
    $statement->execute($params);
    $counts = $statement->fetch();

    $totalLearners = (int) ($counts['total_learners'] ?? 0);
    $recipientCount = (int) ($counts['recipient_count'] ?? 0);
    $nonRecipientCount = $totalLearners - $recipientCount;

    return [
        'total_learners' => $totalLearners,
        'recipient_count' => $recipientCount,
        'non_recipient_count' => $nonRecipientCount,
    ];
}

function health_measurement_template_headers(): array
{
    return ['lrn', 'height_cm', 'weight_kg'];
}

function health_measurement_export_rows(array $filters): array
{
    return array_map(static function (array $row): array {
        return [
            'lrn' => $row['lrn'],
            'complete_name' => $row['complete_name'],
            'grade_level' => $row['grade_level'],
            'section' => $row['section_name'],
            'height_cm' => $row['height_cm'] !== null ? (string) $row['height_cm'] : '',
            'weight_kg' => $row['weight_kg'] !== null ? (string) $row['weight_kg'] : '',
            'bmi' => $row['bmi'] !== null ? number_format((float) $row['bmi'], 2, '.', '') : '',
            'bmi_remarks' => $row['bmi'] === null ? 'Not measured' : $row['bmi_remarks'],
        ];
    }, health_learner_rows($filters));
}

function health_import_clean_string(?string $value): string
{
    $clean = trim((string) $value);

    return preg_replace('/^\xEF\xBB\xBF/', '', $clean) ?? $clean;
}

function health_import_measurement_file(array $file): int
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a CSV height and weight file to import.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    if ($extension !== 'csv') {
        throw new RuntimeException('Height and weight import supports CSV files.');
    }

    $handle = fopen((string) $file['tmp_name'], 'rb');

    if ($handle === false) {
        throw new RuntimeException('Unable to read the uploaded height and weight file.');
    }

    $header = fgetcsv($handle);

    if (!is_array($header)) {
        fclose($handle);
        throw new RuntimeException('CSV header row is missing.');
    }

    $normalizedHeader = array_map(
        static fn ($value): string => strtolower(health_import_clean_string((string) $value)),
        $header
    );
    $requiredHeaders = health_measurement_template_headers();
    $missingHeaders = array_values(array_diff($requiredHeaders, $normalizedHeader));

    if ($missingHeaders !== []) {
        fclose($handle);
        throw new RuntimeException('Missing required column(s): ' . implode(', ', $missingHeaders) . '.');
    }

    $schoolYear = require_current_school_year();
    $pdo = database();
    $pdo->beginTransaction();
    $updatedCount = 0;
    $lineNumber = 1;

    try {
        $lookupStatement = $pdo->prepare(
            'SELECT le.id
             FROM learner_enrollments le
             INNER JOIN learners l ON l.id = le.learner_id
             WHERE le.school_year_id = :school_year_id
               AND l.lrn = :lrn
             LIMIT 1'
        );

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($row === [null] || array_filter($row, static fn ($value): bool => trim((string) $value) !== '') === []) {
                continue;
            }

            $item = [];

            foreach ($normalizedHeader as $index => $column) {
                $item[$column] = isset($row[$index]) ? health_import_clean_string((string) $row[$index]) : '';
            }

            $lrn = preg_replace('/\D+/', '', $item['lrn'] ?? '') ?? '';

            if ($lrn === '') {
                throw new RuntimeException('Row ' . $lineNumber . ' is missing an LRN.');
            }

            $lookupStatement->execute([
                'school_year_id' => (int) $schoolYear['id'],
                'lrn' => $lrn,
            ]);
            $enrollment = $lookupStatement->fetch();

            if ($enrollment === false) {
                throw new RuntimeException('Row ' . $lineNumber . ' LRN ' . $lrn . ' is not enrolled in the current school year.');
            }

            health_save_measurement(
                (int) $enrollment['id'],
                $item['height_cm'] ?? '',
                $item['weight_kg'] ?? ''
            );
            $updatedCount++;
        }

        fclose($handle);
        $pdo->commit();
    } catch (Throwable $exception) {
        fclose($handle);

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $updatedCount;
}

function health_learner_enrollment_exists(int $learnerEnrollmentId): bool
{
    $schoolYear = require_current_school_year();
    $statement = database()->prepare(
        'SELECT id
         FROM learner_enrollments
         WHERE id = :learner_enrollment_id
           AND school_year_id = :school_year_id
         LIMIT 1'
    );
    $statement->execute([
        'learner_enrollment_id' => $learnerEnrollmentId,
        'school_year_id' => (int) $schoolYear['id'],
    ]);

    return $statement->fetch() !== false;
}

function health_save_measurement(int $learnerEnrollmentId, ?string $heightCm, ?string $weightKg): void
{
    if ($learnerEnrollmentId <= 0 || !health_learner_enrollment_exists($learnerEnrollmentId)) {
        throw new RuntimeException('The selected learner enrollment could not be found for the current school year.');
    }

    $height = health_parse_measurement($heightCm, 'Height', 30, 250);
    $weight = health_parse_measurement($weightKg, 'Weight', 1, 300);

    if ($height === null && $weight === null) {
        $statement = database()->prepare(
            'DELETE FROM learner_health_measurements
             WHERE learner_enrollment_id = :learner_enrollment_id'
        );
        $statement->execute(['learner_enrollment_id' => $learnerEnrollmentId]);
        return;
    }

    $statement = database()->prepare(
        'INSERT INTO learner_health_measurements (
            learner_enrollment_id,
            height_cm,
            weight_kg,
            recorded_on
         ) VALUES (
            :learner_enrollment_id,
            :height_cm,
            :weight_kg,
            :recorded_on
         )
         ON DUPLICATE KEY UPDATE
            height_cm = VALUES(height_cm),
            weight_kg = VALUES(weight_kg),
            recorded_on = VALUES(recorded_on),
            updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'learner_enrollment_id' => $learnerEnrollmentId,
        'height_cm' => $height,
        'weight_kg' => $weight,
        'recorded_on' => date('Y-m-d'),
    ]);
}

function health_filtered_enrollment_ids(array $filters): array
{
    $schoolYear = require_current_school_year();
    $params = ['school_year_id' => (int) $schoolYear['id']];
    $conditions = health_enrollment_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT le.id
         FROM learner_enrollments le
         INNER JOIN learners l ON l.id = le.learner_id
         WHERE le.school_year_id = :school_year_id' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         ORDER BY le.id ASC'
    );
    $statement->execute($params);

    return array_map(
        static fn (array $row): int => (int) $row['id'],
        $statement->fetchAll()
    );
}

function health_deworming_dose_options(): array
{
    return [
        1 => 'First Dose',
        2 => 'Second Dose',
    ];
}

function health_validate_dose_number(int $doseNumber): void
{
    if (!array_key_exists($doseNumber, health_deworming_dose_options())) {
        throw new RuntimeException('Choose a valid deworming dose.');
    }
}

function health_assign_deworming_records(array $learnerEnrollmentIds, int $doseNumber, string $administeredOn, ?int $userId = null): int
{
    health_validate_dose_number($doseNumber);

    if (!health_valid_date($administeredOn)) {
        throw new RuntimeException('Deworming date must use YYYY-MM-DD format.');
    }

    $learnerEnrollmentIds = array_values(array_unique(array_filter(
        array_map('intval', $learnerEnrollmentIds),
        static fn (int $value): bool => $value > 0
    )));

    if ($learnerEnrollmentIds === []) {
        throw new RuntimeException('No learners matched the selected grade and section.');
    }

    $pdo = database();
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            'INSERT INTO learner_deworming_records (
                learner_enrollment_id,
                dose_number,
                administered_on,
                created_by_user_id
             ) VALUES (
                :learner_enrollment_id,
                :dose_number,
                :administered_on,
                :created_by_user_id
             )
             ON DUPLICATE KEY UPDATE
                administered_on = VALUES(administered_on),
                created_by_user_id = VALUES(created_by_user_id),
                updated_at = CURRENT_TIMESTAMP'
        );

        foreach ($learnerEnrollmentIds as $learnerEnrollmentId) {
            if (!health_learner_enrollment_exists($learnerEnrollmentId)) {
                continue;
            }

            $statement->execute([
                'learner_enrollment_id' => $learnerEnrollmentId,
                'dose_number' => $doseNumber,
                'administered_on' => $administeredOn,
                'created_by_user_id' => $userId,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return count($learnerEnrollmentIds);
}

function health_assign_deworming_class(array $filters, int $doseNumber, string $administeredOn, ?int $userId = null): int
{
    return health_assign_deworming_records(
        health_filtered_enrollment_ids($filters),
        $doseNumber,
        $administeredOn,
        $userId
    );
}

function health_assign_deworming_individual(int $learnerEnrollmentId, int $doseNumber, string $administeredOn, ?int $userId = null): void
{
    health_assign_deworming_records([$learnerEnrollmentId], $doseNumber, $administeredOn, $userId);
}

function health_clear_deworming_records(array $learnerEnrollmentIds, int $doseNumber): int
{
    health_validate_dose_number($doseNumber);

    $learnerEnrollmentIds = array_values(array_unique(array_filter(
        array_map('intval', $learnerEnrollmentIds),
        static fn (int $value): bool => $value > 0
    )));

    if ($learnerEnrollmentIds === []) {
        throw new RuntimeException('Select at least one learner to clear.');
    }

    $schoolYear = require_current_school_year();
    $placeholders = implode(', ', array_fill(0, count($learnerEnrollmentIds), '?'));
    $statement = database()->prepare(
        'DELETE dr
         FROM learner_deworming_records dr
         INNER JOIN learner_enrollments le ON le.id = dr.learner_enrollment_id
         WHERE le.school_year_id = ?
           AND dr.dose_number = ?
           AND dr.learner_enrollment_id IN (' . $placeholders . ')'
    );
    $statement->execute(array_merge([(int) $schoolYear['id'], $doseNumber], $learnerEnrollmentIds));

    return $statement->rowCount();
}

function health_deworming_rows(array $filters): array
{
    $schoolYear = require_current_school_year();
    $params = ['school_year_id' => (int) $schoolYear['id']];
    $conditions = health_enrollment_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT
            le.id AS learner_enrollment_id,
            l.id AS learner_id,
            l.lrn,
            l.first_name,
            l.middle_name,
            l.last_name,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            MAX(CASE WHEN dr.dose_number = 1 THEN dr.administered_on END) AS first_dose_date,
            MAX(CASE WHEN dr.dose_number = 2 THEN dr.administered_on END) AS second_dose_date
         FROM learner_enrollments le
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN learner_deworming_records dr ON dr.learner_enrollment_id = le.id
         WHERE le.school_year_id = :school_year_id' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         GROUP BY
            le.id,
            l.id,
            l.lrn,
            l.first_name,
            l.middle_name,
            l.last_name,
            le.grade_level,
            s.name
         ORDER BY le.grade_level ASC, section_name ASC, l.last_name ASC, l.first_name ASC, l.id ASC'
    );
    $statement->execute($params);
    $rows = $statement->fetchAll();

    foreach ($rows as &$row) {
        $row['complete_name'] = health_full_learner_name($row);
    }
    unset($row);

    return $rows;
}

function health_feeding_candidate_rows(array $filters): array
{
    $schoolYear = require_current_school_year();
    $params = ['school_year_id' => (int) $schoolYear['id']];
    $conditions = health_enrollment_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT
            le.id AS learner_enrollment_id,
            l.id AS learner_id,
            l.lrn,
            l.first_name,
            l.middle_name,
            l.last_name,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            fpr.id AS feeding_recipient_id,
            fpr.enrolled_on
         FROM learner_enrollments le
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN feeding_program_recipients fpr ON fpr.learner_enrollment_id = le.id
         WHERE fpr.id IS NULL AND le.school_year_id = :school_year_id' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         ORDER BY le.grade_level ASC, section_name ASC, l.last_name ASC, l.first_name ASC, l.id ASC'
    );
    $statement->execute($params);
    $rows = $statement->fetchAll();

    foreach ($rows as &$row) {
        $row['complete_name'] = health_full_learner_name($row); // This will be used for display
        $row['is_recipient'] = false; // Always false for candidates now
    }
    unset($row);

    return $rows;
}

function health_feeding_recipient_rows(array $filters = []): array
{
    $schoolYear = require_current_school_year();
    $params = ['school_year_id' => (int) $schoolYear['id']];
    $conditions = health_enrollment_filter_conditions($filters, $params);

    $statement = database()->prepare(
        'SELECT
            le.id AS learner_enrollment_id,
            l.id AS learner_id,
            l.lrn,
            l.first_name,
            l.middle_name,
            l.last_name,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            fpr.enrolled_on
         FROM feeding_program_recipients fpr
         INNER JOIN learner_enrollments le ON le.id = fpr.learner_enrollment_id
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         WHERE fpr.school_year_id = :school_year_id' .
         ($conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '') . '
         ORDER BY le.grade_level ASC, section_name ASC, l.last_name ASC, l.first_name ASC, l.id ASC'
    );
    $statement->execute($params);
    $rows = $statement->fetchAll();

    foreach ($rows as &$row) {
        $row['complete_name'] = health_full_learner_name($row);
    }
    unset($row);

    return $rows;
}

function health_add_feeding_recipients(array $learnerEnrollmentIds, ?int $userId = null): int
{
    $learnerEnrollmentIds = array_values(array_unique(array_filter(
        array_map('intval', $learnerEnrollmentIds),
        static fn (int $value): bool => $value > 0
    )));

    if ($learnerEnrollmentIds === []) {
        throw new RuntimeException('Select at least one learner for the feeding program.');
    }

    $schoolYear = require_current_school_year();
    $placeholders = implode(', ', array_fill(0, count($learnerEnrollmentIds), '?'));

    $enrollmentStatement = database()->prepare(
        'SELECT id
         FROM learner_enrollments
         WHERE school_year_id = ?
           AND id IN (' . $placeholders . ')'
    );
    $enrollmentStatement->execute(array_merge([(int) $schoolYear['id']], $learnerEnrollmentIds));
    $validEnrollmentIds = array_map(
        static fn (array $row): int => (int) $row['id'],
        $enrollmentStatement->fetchAll()
    );

    if ($validEnrollmentIds === []) {
        throw new RuntimeException('The selected learners are not active in the current school year.');
    }

    $existingStatement = database()->prepare(
        'SELECT learner_enrollment_id
         FROM feeding_program_recipients
         WHERE learner_enrollment_id IN (' . implode(', ', array_fill(0, count($validEnrollmentIds), '?')) . ')'
    );
    $existingStatement->execute($validEnrollmentIds);
    $existingIds = array_map(
        static fn (array $row): int => (int) $row['learner_enrollment_id'],
        $existingStatement->fetchAll()
    );
    $newIds = array_values(array_diff($validEnrollmentIds, $existingIds));

    if ($newIds === []) {
        return 0;
    }

    $statement = database()->prepare(
        'INSERT INTO feeding_program_recipients (
            learner_enrollment_id,
            school_year_id,
            enrolled_on,
            created_by_user_id
         ) VALUES (
            :learner_enrollment_id,
            :school_year_id,
            :enrolled_on,
            :created_by_user_id
         )'
    );

    foreach ($newIds as $learnerEnrollmentId) {
        $statement->execute([
            'learner_enrollment_id' => $learnerEnrollmentId,
            'school_year_id' => (int) $schoolYear['id'],
            'enrolled_on' => date('Y-m-d'),
            'created_by_user_id' => $userId,
        ]);
    }

    return count($newIds);
}

function health_remove_feeding_recipient(int $learnerEnrollmentId): void
{
    $statement = database()->prepare(
        'DELETE FROM feeding_program_recipients
         WHERE learner_enrollment_id = :learner_enrollment_id'
    );
    $statement->execute(['learner_enrollment_id' => $learnerEnrollmentId]);
}
