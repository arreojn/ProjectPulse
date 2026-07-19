<?php

declare(strict_types=1);

function grade_book_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    database()->exec(
        'CREATE TABLE IF NOT EXISTS learner_subject_grades (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            learner_enrollment_id INT UNSIGNED NOT NULL,
            subject_name VARCHAR(160) NOT NULL,
            quarter_1_grade DECIMAL(5,2) NULL,
            quarter_2_grade DECIMAL(5,2) NULL,
            quarter_3_grade DECIMAL(5,2) NULL,
            quarter_4_grade DECIMAL(5,2) NULL,
            first_semester_average DECIMAL(5,2) NULL,
            second_semester_average DECIMAL(5,2) NULL,
            final_average DECIMAL(5,2) NULL,
            remarks VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_enrollment_subject (learner_enrollment_id, subject_name),
            CONSTRAINT fk_subject_grades_enrollment
                FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
                ON DELETE CASCADE
        )'
    );

    $bootstrapped = true;
}

function grade_template_headers(): array
{
    return [
        'lrn',
        'school_year',
        'grade_level',
        'subject',
        'quarter_1_grade',
        'quarter_2_grade',
        'quarter_3_grade',
        'quarter_4_grade',
        'first_semester_average',
        'second_semester_average',
        'final_average',
        'remarks',
    ];
}

function grade_import_clean_string(?string $value): string
{
    $clean = trim((string) $value);

    return preg_replace('/^\xEF\xBB\xBF/', '', $clean) ?? $clean;
}

function grade_parse_numeric(?string $value): ?float
{
    $clean = trim((string) $value);

    if ($clean === '' || $clean === '#') {
        return null;
    }

    if (!is_numeric($clean)) {
        throw new RuntimeException('Grade values must be numeric, blank, or #.');
    }

    $grade = (float) $clean;

    if ($grade < 0 || $grade > 100) {
        throw new RuntimeException('Grade values must be between 0 and 100.');
    }

    return round($grade, 2);
}

function grade_average(array $values): ?float
{
    $grades = array_values(array_filter(
        $values,
        static fn ($value): bool => $value !== null
    ));

    if ($grades === []) {
        return null;
    }

    return round(array_sum($grades) / count($grades), 2);
}

function grade_level_normalize(string $gradeLevel): string
{
    return strtolower(preg_replace('/\s+/', ' ', trim($gradeLevel)) ?? trim($gradeLevel));
}

function grade_level_matches(string $expected, string $actual): bool
{
    return grade_level_normalize($expected) === grade_level_normalize($actual);
}

function grade_school_year_matches(string $expected, string $actual): bool
{
    return strtolower(trim($expected)) === strtolower(trim($actual));
}

function grade_level_sort_value(string $gradeLevel): int
{
    if (preg_match('/grade\s+(\d+)/i', $gradeLevel, $matches) === 1) {
        return (int) $matches[1];
    }

    return 0;
}

function grade_is_senior_high(string $gradeLevel): bool
{
    return in_array(grade_level_normalize($gradeLevel), ['grade 11', 'grade 12'], true);
}

function grade_quarter_average(array $row): ?float
{
    return grade_average([
        $row['quarter_1_grade'] ?? null,
        $row['quarter_2_grade'] ?? null,
        $row['quarter_3_grade'] ?? null,
        $row['quarter_4_grade'] ?? null,
    ]);
}

function grade_normalize_row(array $row): array
{
    $payload = [
        'lrn' => preg_replace('/\D+/', '', grade_import_clean_string($row['lrn'] ?? '')) ?? '',
        'school_year' => grade_import_clean_string($row['school_year'] ?? ''),
        'grade_level' => grade_import_clean_string($row['grade_level'] ?? ''),
        'subject_name' => grade_import_clean_string($row['subject'] ?? ''),
        'quarter_1_grade' => grade_parse_numeric($row['quarter_1_grade'] ?? null),
        'quarter_2_grade' => grade_parse_numeric($row['quarter_2_grade'] ?? null),
        'quarter_3_grade' => grade_parse_numeric($row['quarter_3_grade'] ?? null),
        'quarter_4_grade' => grade_parse_numeric($row['quarter_4_grade'] ?? null),
        'first_semester_average' => grade_parse_numeric($row['first_semester_average'] ?? null),
        'second_semester_average' => grade_parse_numeric($row['second_semester_average'] ?? null),
        'final_average' => grade_parse_numeric($row['final_average'] ?? null),
        'remarks' => grade_import_clean_string($row['remarks'] ?? ''),
    ];

    if ($payload['lrn'] === '' || strlen($payload['lrn']) !== 12) {
        throw new RuntimeException('Each grade row must include a valid 12-digit LRN.');
    }

    if ($payload['school_year'] === '') {
        throw new RuntimeException('Each grade row must include a school year.');
    }

    if ($payload['grade_level'] === '') {
        throw new RuntimeException('Each grade row must include a grade level.');
    }

    if ($payload['subject_name'] === '') {
        throw new RuntimeException('Each grade row must include a subject.');
    }

    if ($payload['first_semester_average'] === null) {
        $payload['first_semester_average'] = grade_average([
            $payload['quarter_1_grade'],
            $payload['quarter_2_grade'],
        ]);
    }

    if ($payload['second_semester_average'] === null) {
        $payload['second_semester_average'] = grade_average([
            $payload['quarter_3_grade'],
            $payload['quarter_4_grade'],
        ]);
    }

    if ($payload['final_average'] === null) {
        $semesterAverage = grade_average([
            $payload['first_semester_average'],
            $payload['second_semester_average'],
        ]);
        $quarterAverage = grade_average([
            $payload['quarter_1_grade'],
            $payload['quarter_2_grade'],
            $payload['quarter_3_grade'],
            $payload['quarter_4_grade'],
        ]);

        $payload['final_average'] = $semesterAverage ?? $quarterAverage;
    }

    return $payload;
}

function grade_parse_csv_rows(string $path): array
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
        static fn ($value): string => strtolower(grade_import_clean_string((string) $value)),
        $header
    );

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $item = [];

        foreach ($normalizedHeader as $index => $column) {
            $item[$column] = isset($row[$index]) ? grade_import_clean_string((string) $row[$index]) : '';
        }

        $rows[] = $item;
    }

    fclose($handle);

    return $rows;
}

function grade_parse_excel_xml_rows(string $path): array
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
            $cells[$position] = isset($dataNodes[0]) ? grade_import_clean_string((string) $dataNodes[0]) : '';
            $position++;
        }

        ksort($cells);
        $values = array_values($cells);

        if ($rowIndex === 0) {
            $header = array_map(
                static fn ($value): string => strtolower(grade_import_clean_string((string) $value)),
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

function grade_import_rows_from_file(array $file): array
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Choose a CSV or XLS grade file to import.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    return match ($extension) {
        'csv' => grade_parse_csv_rows($file['tmp_name']),
        'xls' => grade_parse_excel_xml_rows($file['tmp_name']),
        default => throw new RuntimeException('Supported grade import files are CSV and the provided XLS template.'),
    };
}

function grade_teacher_enrollment_for_lrn(int $teacherUserId, string $lrn, string $schoolYear): ?array
{
    $statement = database()->prepare(
        'SELECT le.id AS learner_enrollment_id
              , le.grade_level
              , sy.label AS school_year_label
         FROM teacher_section_assignments tsa
         INNER JOIN learner_enrollments le
            ON le.section_id = tsa.section_id
           AND le.school_year_id = tsa.school_year_id
         INNER JOIN school_years sy ON sy.id = le.school_year_id
         INNER JOIN learners l ON l.id = le.learner_id
         WHERE tsa.teacher_user_id = :teacher_user_id
           AND l.lrn = :lrn
           AND sy.label = :school_year
         LIMIT 1'
    );
    $statement->execute([
        'teacher_user_id' => $teacherUserId,
        'lrn' => $lrn,
        'school_year' => $schoolYear,
    ]);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function grade_save_subject_grade(int $enrollmentId, array $payload): void
{
    $statement = database()->prepare(
        'INSERT INTO learner_subject_grades (
            learner_enrollment_id,
            subject_name,
            quarter_1_grade,
            quarter_2_grade,
            quarter_3_grade,
            quarter_4_grade,
            first_semester_average,
            second_semester_average,
            final_average,
            remarks
         ) VALUES (
            :learner_enrollment_id,
            :subject_name,
            :quarter_1_grade,
            :quarter_2_grade,
            :quarter_3_grade,
            :quarter_4_grade,
            :first_semester_average,
            :second_semester_average,
            :final_average,
            :remarks
         )
         ON DUPLICATE KEY UPDATE
            quarter_1_grade = VALUES(quarter_1_grade),
            quarter_2_grade = VALUES(quarter_2_grade),
            quarter_3_grade = VALUES(quarter_3_grade),
            quarter_4_grade = VALUES(quarter_4_grade),
            first_semester_average = VALUES(first_semester_average),
            second_semester_average = VALUES(second_semester_average),
            final_average = VALUES(final_average),
            remarks = VALUES(remarks),
            updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'learner_enrollment_id' => $enrollmentId,
        'subject_name' => $payload['subject_name'],
        'quarter_1_grade' => $payload['quarter_1_grade'],
        'quarter_2_grade' => $payload['quarter_2_grade'],
        'quarter_3_grade' => $payload['quarter_3_grade'],
        'quarter_4_grade' => $payload['quarter_4_grade'],
        'first_semester_average' => $payload['first_semester_average'],
        'second_semester_average' => $payload['second_semester_average'],
        'final_average' => $payload['final_average'],
        'remarks' => $payload['remarks'] !== '' ? $payload['remarks'] : null,
    ]);
}

function grade_import_file_for_teacher(int $teacherUserId, array $file): int
{
    grade_book_bootstrap();

    $rows = grade_import_rows_from_file($file);

    if ($rows === []) {
        throw new RuntimeException('The grade import file does not contain grade rows.');
    }

    $importedCount = 0;

    foreach ($rows as $row) {
        if (implode('', array_map('strval', $row)) === '') {
            continue;
        }

        $payload = grade_normalize_row($row);
        $enrollment = grade_teacher_enrollment_for_lrn($teacherUserId, $payload['lrn'], $payload['school_year']);

        if ($enrollment === null) {
            throw new RuntimeException('LRN ' . $payload['lrn'] . ' is not part of your assigned section for school year ' . $payload['school_year'] . '.');
        }

        if (!grade_school_year_matches((string) $enrollment['school_year_label'], $payload['school_year'])) {
            throw new RuntimeException('LRN ' . $payload['lrn'] . ' is enrolled in school year ' . $enrollment['school_year_label'] . ', not ' . $payload['school_year'] . '.');
        }

        if (!grade_level_matches((string) $enrollment['grade_level'], $payload['grade_level'])) {
            throw new RuntimeException('LRN ' . $payload['lrn'] . ' is enrolled as ' . $enrollment['grade_level'] . ', not ' . $payload['grade_level'] . '.');
        }

        grade_save_subject_grade((int) $enrollment['learner_enrollment_id'], $payload);
        $importedCount++;
    }

    return $importedCount;
}

function grade_teacher_section_rows(int $teacherUserId): array
{
    grade_book_bootstrap();

    $statement = database()->prepare(
        'SELECT
            l.lrn,
            l.id AS learner_id,
            CONCAT(
                l.last_name,
                \', \',
                l.first_name,
                IF(l.middle_name IS NULL OR l.middle_name = \'\', \'\', CONCAT(\' \', l.middle_name))
            ) AS learner_name,
            sy.label AS school_year_label,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            lsg.subject_name,
            lsg.quarter_1_grade,
            lsg.quarter_2_grade,
            lsg.quarter_3_grade,
            lsg.quarter_4_grade,
            lsg.first_semester_average,
            lsg.second_semester_average,
            lsg.final_average,
            lsg.remarks
         FROM teacher_section_assignments tsa
         INNER JOIN learner_enrollments le
            ON le.section_id = tsa.section_id
           AND le.school_year_id = tsa.school_year_id
         INNER JOIN learners l ON l.id = le.learner_id
         INNER JOIN school_years sy ON sy.id = le.school_year_id
         LEFT JOIN sections s ON s.id = le.section_id
         INNER JOIN learner_subject_grades lsg ON lsg.learner_enrollment_id = le.id
         WHERE tsa.teacher_user_id = :teacher_user_id
         ORDER BY l.last_name ASC, l.first_name ASC, lsg.subject_name ASC'
    );
    $statement->execute(['teacher_user_id' => $teacherUserId]);

    return $statement->fetchAll();
}

function grade_teacher_learner_rows(int $teacherUserId, int $learnerId): array
{
    grade_book_bootstrap();

    $statement = database()->prepare(
        'SELECT
            l.id AS learner_id,
            l.lrn,
            CONCAT(
                l.last_name,
                \', \',
                l.first_name,
                IF(l.middle_name IS NULL OR l.middle_name = \'\', \'\', CONCAT(\' \', l.middle_name))
            ) AS learner_name,
            sy.label AS school_year_label,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            lsg.subject_name,
            lsg.quarter_1_grade,
            lsg.quarter_2_grade,
            lsg.quarter_3_grade,
            lsg.quarter_4_grade,
            lsg.first_semester_average,
            lsg.second_semester_average,
            lsg.final_average,
            lsg.remarks
         FROM teacher_section_assignments tsa
         INNER JOIN learner_enrollments le
            ON le.section_id = tsa.section_id
           AND le.school_year_id = tsa.school_year_id
         INNER JOIN school_years sy ON sy.id = le.school_year_id
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         INNER JOIN learner_subject_grades lsg ON lsg.learner_enrollment_id = le.id
         WHERE tsa.teacher_user_id = :teacher_user_id
           AND l.id = :learner_id
         ORDER BY lsg.subject_name ASC'
    );
    $statement->execute([
        'teacher_user_id' => $teacherUserId,
        'learner_id' => $learnerId,
    ]);

    return $statement->fetchAll();
}

function grade_parent_child_history(int $parentUserId, int $learnerId): array
{
    grade_book_bootstrap();

    $statement = database()->prepare(
        'SELECT
            sy.label AS school_year_label,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            lsg.subject_name,
            lsg.quarter_1_grade,
            lsg.quarter_2_grade,
            lsg.quarter_3_grade,
            lsg.quarter_4_grade,
            lsg.first_semester_average,
            lsg.second_semester_average,
            lsg.final_average,
            lsg.remarks
         FROM parents p
         INNER JOIN parent_learner_links pll ON pll.parent_id = p.id
         INNER JOIN learners l ON l.id = pll.learner_id
         INNER JOIN learner_enrollments le ON le.learner_id = l.id
         INNER JOIN school_years sy ON sy.id = le.school_year_id
         LEFT JOIN sections s ON s.id = le.section_id
         INNER JOIN learner_subject_grades lsg ON lsg.learner_enrollment_id = le.id
         WHERE p.user_id = :parent_user_id
           AND l.id = :learner_id
         ORDER BY sy.start_date ASC, le.grade_level ASC, lsg.subject_name ASC'
    );
    $statement->execute([
        'parent_user_id' => $parentUserId,
        'learner_id' => $learnerId,
    ]);

    return $statement->fetchAll();
}

function grade_group_history_by_level(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $key = $row['school_year_label'] . '|' . $row['grade_level'] . '|' . $row['section_name'];

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'school_year_label' => $row['school_year_label'],
                'grade_level' => $row['grade_level'],
                'section_name' => $row['section_name'],
                'rows' => [],
                'grand_average' => null,
            ];
        }

        $grouped[$key]['rows'][] = $row;
    }

    foreach ($grouped as $key => $group) {
        $grouped[$key]['grand_average'] = grade_average(array_column($group['rows'], 'final_average'));
    }

    $values = array_values($grouped);
    usort(
        $values,
        static fn (array $a, array $b): int => grade_level_sort_value((string) $a['grade_level']) <=> grade_level_sort_value((string) $b['grade_level'])
            ?: strcmp((string) $a['school_year_label'], (string) $b['school_year_label'])
    );

    return $values;
}
