<?php

declare(strict_types=1);

function section_form_defaults(array $overrides = []): array
{
    return array_merge([
        'id' => null,
        'name' => '',
        'grade_level' => '',
        'adviser_name' => '',
    ], $overrides);
}

function section_normalize_payload(array $input): array
{
    return section_form_defaults([
        'id' => isset($input['id']) && $input['id'] !== '' ? (int) $input['id'] : null,
        'name' => trim((string) ($input['name'] ?? '')),
        'grade_level' => trim((string) ($input['grade_level'] ?? '')),
        'adviser_name' => trim((string) ($input['adviser_name'] ?? '')),
    ]);
}

function section_validate_payload(array $payload): array
{
    $errors = [];

    if ($payload['name'] === '') {
        $errors[] = 'Section name is required.';
    }

    if ($payload['grade_level'] === '') {
        $errors[] = 'Grade level is required.';
    }

    return $errors;
}

function section_adviser_options(?string $selectedAdviserName = null): array
{
    auth_bootstrap();

    $statement = database()->query(
        'SELECT
            username,
            COALESCE(first_name, \'\') AS first_name,
            COALESCE(middle_name, \'\') AS middle_name,
            COALESCE(last_name, \'\') AS last_name
         FROM users
         WHERE role = \'teacher\'
           AND is_active = 1
         ORDER BY last_name ASC, first_name ASC, middle_name ASC, username ASC'
    );
    $options = [];
    $seenValues = [];

    foreach ($statement->fetchAll() as $teacherAccount) {
        $fullName = trim(implode(' ', array_filter([
            trim((string) ($teacherAccount['first_name'] ?? '')),
            trim((string) ($teacherAccount['middle_name'] ?? '')),
            trim((string) ($teacherAccount['last_name'] ?? '')),
        ], static fn ($value): bool => $value !== '')));
        $username = trim((string) ($teacherAccount['username'] ?? ''));
        $value = $fullName !== '' ? $fullName : $username;

        if ($value === '') {
            continue;
        }

        $optionKey = strtolower($value);

        if (isset($seenValues[$optionKey])) {
            continue;
        }

        $label = $fullName !== '' ? $fullName : $username;

        if ($username !== '' && $username !== $label) {
            $label .= ' (' . $username . ')';
        }

        $options[] = [
            'value' => $value,
            'label' => $label,
        ];
        $seenValues[$optionKey] = true;
    }

    $selectedAdviserName = trim((string) $selectedAdviserName);

    if ($selectedAdviserName !== '' && !isset($seenValues[strtolower($selectedAdviserName)])) {
        array_unshift($options, [
            'value' => $selectedAdviserName,
            'label' => $selectedAdviserName . ' (Saved adviser)',
        ]);
    }

    return $options;
}

function section_list(): array
{
    $schoolYear = require_current_school_year();

    $statement = database()->prepare(
        'SELECT
            s.id,
            s.name,
            s.grade_level,
            s.adviser_name,
            sy.label AS school_year_label,
            COUNT(DISTINCT tsa.id) AS assigned_teacher_count,
            COUNT(DISTINCT le.id) AS learner_count
         FROM sections s
         INNER JOIN school_years sy ON sy.id = s.school_year_id
         LEFT JOIN teacher_section_assignments tsa ON tsa.section_id = s.id
         LEFT JOIN learner_enrollments le
            ON le.section_id = s.id
           AND le.school_year_id = s.school_year_id
         WHERE s.school_year_id = :school_year_id
         GROUP BY s.id, s.name, s.grade_level, s.adviser_name, sy.label
         ORDER BY s.grade_level ASC, s.name ASC, s.id ASC'
    );
    $statement->execute(['school_year_id' => (int) $schoolYear['id']]);

    return $statement->fetchAll();
}

function section_find(int $sectionId): ?array
{
    $schoolYear = require_current_school_year();

    $statement = database()->prepare(
        'SELECT id, name, grade_level, COALESCE(adviser_name, \'\') AS adviser_name
         FROM sections
         WHERE id = :section_id
           AND school_year_id = :school_year_id
         LIMIT 1'
    );
    $statement->execute([
        'section_id' => $sectionId,
        'school_year_id' => (int) $schoolYear['id'],
    ]);
    $row = $statement->fetch();

    return $row === false ? null : section_form_defaults($row);
}

function section_save(array $payload): void
{
    $errors = section_validate_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $schoolYear = require_current_school_year();

    try {
        if ($payload['id'] === null) {
            $statement = database()->prepare(
                'INSERT INTO sections (name, grade_level, school_year_id, adviser_name)
                 VALUES (:name, :grade_level, :school_year_id, :adviser_name)'
            );
            $statement->execute([
                'name' => $payload['name'],
                'grade_level' => $payload['grade_level'],
                'school_year_id' => (int) $schoolYear['id'],
                'adviser_name' => $payload['adviser_name'] !== '' ? $payload['adviser_name'] : null,
            ]);

            return;
        }

        $statement = database()->prepare(
            'UPDATE sections
             SET name = :name,
                 grade_level = :grade_level,
                 adviser_name = :adviser_name
             WHERE id = :section_id
               AND school_year_id = :school_year_id'
        );
        $statement->execute([
            'name' => $payload['name'],
            'grade_level' => $payload['grade_level'],
            'adviser_name' => $payload['adviser_name'] !== '' ? $payload['adviser_name'] : null,
            'section_id' => (int) $payload['id'],
            'school_year_id' => (int) $schoolYear['id'],
        ]);
    } catch (Throwable $exception) {
        if ($exception instanceof PDOException && str_contains((string) $exception->getMessage(), 'Duplicate')) {
            throw new RuntimeException('That grade level and section name already exist for the current school year.');
        }

        throw $exception;
    }
}

function section_delete(int $sectionId): void
{
    $schoolYear = require_current_school_year();

    $usageStatement = database()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM teacher_section_assignments WHERE section_id = :section_id) AS teacher_count,
            (SELECT COUNT(*) FROM learner_enrollments WHERE section_id = :section_id AND school_year_id = :school_year_id) AS learner_count'
    );
    $usageStatement->execute([
        'section_id' => $sectionId,
        'school_year_id' => (int) $schoolYear['id'],
    ]);
    $usage = $usageStatement->fetch() ?: [];

    if ((int) ($usage['teacher_count'] ?? 0) > 0 || (int) ($usage['learner_count'] ?? 0) > 0) {
        throw new RuntimeException('This section cannot be deleted while teachers or learners are still assigned to it.');
    }

    $statement = database()->prepare(
        'DELETE FROM sections
         WHERE id = :section_id
           AND school_year_id = :school_year_id'
    );
    $statement->execute([
        'section_id' => $sectionId,
        'school_year_id' => (int) $schoolYear['id'],
    ]);
}
