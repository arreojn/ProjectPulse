<?php

declare(strict_types=1);

function teacher_management_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    auth_bootstrap();
    $pdo = database();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS teacher_section_assignments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            teacher_user_id INT UNSIGNED NOT NULL,
            section_id INT UNSIGNED NOT NULL UNIQUE,
            school_year_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_teacher_assignment_user
                FOREIGN KEY (teacher_user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_teacher_assignment_section
                FOREIGN KEY (section_id) REFERENCES sections(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_teacher_assignment_school_year
                FOREIGN KEY (school_year_id) REFERENCES school_years(id)
                ON DELETE CASCADE
        )'
    );
    teacher_ensure_non_unique_index_for_column(
        'teacher_section_assignments',
        'teacher_user_id',
        'idx_teacher_assignment_teacher_user_id'
    );
    teacher_drop_unique_indexes_for_column(
        'teacher_section_assignments',
        'teacher_user_id',
        ['idx_teacher_assignment_teacher_user_id']
    );

    $userStatement = $pdo->prepare(
        'INSERT IGNORE INTO users (username, email, first_name, middle_name, last_name, password_hash, role, is_active)
         VALUES (:username, :email, :first_name, :middle_name, :last_name, :password_hash, :role, :is_active)'
    );
    $userStatement->execute([
        'username' => 'teacher_mabini',
        'email' => 'teacher.mabini@projectpulse.local',
        'first_name' => 'Mabini',
        'middle_name' => 'Demo',
        'last_name' => 'Teacher',
        'password_hash' => '$2y$10$MowOCypAlH70pG7wAMix3.cddt8d.B66dIBvCfhptP958vYLiu5bi',
        'role' => 'teacher',
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
        'first_name' => 'Mabini',
        'middle_name' => 'Demo',
        'last_name' => 'Teacher',
        'username' => 'teacher_mabini',
        'role' => 'teacher',
    ]);

    $assignmentSeedStatement = $pdo->prepare(
        'INSERT IGNORE INTO teacher_section_assignments (
            teacher_user_id,
            section_id,
            school_year_id
         )
         SELECT
            u.id,
            s.id,
            sy.id
         FROM users u
         INNER JOIN school_years sy ON sy.is_current = 1
         INNER JOIN sections s ON s.school_year_id = sy.id
         WHERE u.username = :username
           AND s.name = :section_name
         LIMIT 1'
    );
    $assignmentSeedStatement->execute([
        'username' => 'teacher_mabini',
        'section_name' => 'Mabini',
    ]);

    $bootstrapped = true;
}

function teacher_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function teacher_ensure_non_unique_index_for_column(string $tableName, string $columnName, string $indexName): void
{
    $statement = database()->prepare(
        'SELECT COUNT(*) AS total
         FROM (
            SELECT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = :table_schema
              AND TABLE_NAME = :table_name
              AND INDEX_NAME <> \'PRIMARY\'
              AND COLUMN_NAME = :column_name
            GROUP BY INDEX_NAME
            HAVING MAX(NON_UNIQUE) = 1
               AND COUNT(*) = 1
         ) AS matching_indexes'
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

    $indexNameStatement = database()->prepare(
        'SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = :table_schema
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name'
    );
    $indexNameStatement->execute([
        'table_schema' => DB_NAME,
        'table_name' => $tableName,
        'index_name' => $indexName,
    ]);
    $indexNameRow = $indexNameStatement->fetch();
    $effectiveIndexName = (int) ($indexNameRow['total'] ?? 0) > 0
        ? $indexName . '_lookup'
        : $indexName;

    database()->exec(sprintf(
        'ALTER TABLE %s ADD INDEX %s (%s)',
        teacher_quote_identifier($tableName),
        teacher_quote_identifier($effectiveIndexName),
        teacher_quote_identifier($columnName)
    ));
}

function teacher_drop_unique_indexes_for_column(string $tableName, string $columnName, array $preserveIndexNames = []): void
{
    $statement = database()->prepare(
        'SELECT INDEX_NAME
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = :table_schema
           AND TABLE_NAME = :table_name
           AND INDEX_NAME <> \'PRIMARY\'
         GROUP BY INDEX_NAME
         HAVING MAX(NON_UNIQUE) = 0
            AND COUNT(*) = 1
            AND MAX(CASE WHEN COLUMN_NAME = :column_name THEN 1 ELSE 0 END) = 1'
    );
    $statement->execute([
        'table_schema' => DB_NAME,
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);
    $indexes = $statement->fetchAll();

    foreach ($indexes as $index) {
        $indexName = isset($index['INDEX_NAME']) ? (string) $index['INDEX_NAME'] : '';

        if ($indexName === '' || in_array($indexName, $preserveIndexNames, true)) {
            continue;
        }

        database()->exec(sprintf(
            'ALTER TABLE %s DROP INDEX %s',
            teacher_quote_identifier($tableName),
            teacher_quote_identifier($indexName)
        ));
    }
}

function teacher_form_defaults(array $overrides = []): array
{
    return array_merge([
        'id' => null,
        'username' => '',
        'email' => '',
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'password' => '',
        'section_id' => '',
        'section_ids' => [],
    ], $overrides);
}

function teacher_normalize_payload(array $input): array
{
    $sectionIds = $input['section_ids'] ?? [];

    if (!is_array($sectionIds)) {
        $sectionIds = [];
    }

    if ($sectionIds === [] && isset($input['section_id']) && trim((string) $input['section_id']) !== '') {
        $sectionIds = [trim((string) $input['section_id'])];
    }

    $normalizedSectionIds = [];

    foreach ($sectionIds as $sectionId) {
        $clean = trim((string) $sectionId);

        if ($clean !== '' && !in_array($clean, $normalizedSectionIds, true)) {
            $normalizedSectionIds[] = $clean;
        }
    }

    return teacher_form_defaults([
        'id' => isset($input['id']) && $input['id'] !== '' ? (int) $input['id'] : null,
        'username' => trim((string) ($input['username'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'first_name' => trim((string) ($input['first_name'] ?? '')),
        'middle_name' => trim((string) ($input['middle_name'] ?? '')),
        'last_name' => trim((string) ($input['last_name'] ?? '')),
        'password' => (string) ($input['password'] ?? ''),
        'section_id' => $normalizedSectionIds[0] ?? '',
        'section_ids' => $normalizedSectionIds,
    ]);
}

function teacher_validate_payload(array $payload): array
{
    $errors = [];

    if ($payload['username'] === '') {
        $errors[] = 'Teacher username is required.';
    }

    if ($payload['email'] === '' || filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'A valid teacher email is required.';
    }

    if ($payload['first_name'] === '' || $payload['last_name'] === '') {
        $errors[] = 'Teacher first name and last name are required.';
    }

    if ($payload['id'] === null && strlen($payload['password']) < 6) {
        $errors[] = 'Teacher password must be at least 6 characters.';
    }

    if (($payload['section_ids'] ?? []) === []) {
        $errors[] = 'Assign at least one grade and section to this teacher.';
    }

    return $errors;
}

function teacher_section_options(): array
{
    teacher_management_bootstrap();

    $statement = database()->query(
        'SELECT
            s.id,
            s.name,
            s.grade_level,
            sy.label AS school_year_label,
            assigned_user.username AS assigned_teacher_username,
            TRIM(CONCAT(
                COALESCE(assigned_user.first_name, \'\'),
                IF(COALESCE(assigned_user.middle_name, \'\') = \'\', \'\', CONCAT(\' \', assigned_user.middle_name)),
                IF(COALESCE(assigned_user.last_name, \'\') = \'\', \'\', CONCAT(\' \', assigned_user.last_name))
            )) AS assigned_teacher_name
         FROM sections s
         INNER JOIN school_years sy ON sy.id = s.school_year_id
         LEFT JOIN teacher_section_assignments tsa ON tsa.section_id = s.id
         LEFT JOIN users assigned_user ON assigned_user.id = tsa.teacher_user_id
         ORDER BY sy.is_current DESC, sy.start_date DESC, s.grade_level ASC, s.name ASC'
    );

    return $statement->fetchAll();
}

function teacher_list(): array
{
    teacher_management_bootstrap();

    $statement = database()->query(
        'SELECT
            u.id,
            u.username,
            u.email,
            COALESCE(u.first_name, \'\') AS first_name,
            COALESCE(u.middle_name, \'\') AS middle_name,
            COALESCE(u.last_name, \'\') AS last_name,
            COALESCE(GROUP_CONCAT(DISTINCT s.grade_level ORDER BY s.grade_level SEPARATOR \', \'), \'Unassigned\') AS grade_level,
            COALESCE(GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR \', \'), \'Unassigned\') AS section_name,
            COALESCE(GROUP_CONCAT(DISTINCT sy.label ORDER BY sy.start_date DESC SEPARATOR \', \'), \'Not assigned\') AS school_year_label
         FROM users u
         LEFT JOIN teacher_section_assignments tsa ON tsa.teacher_user_id = u.id
         LEFT JOIN sections s ON s.id = tsa.section_id
         LEFT JOIN school_years sy ON sy.id = tsa.school_year_id
         WHERE u.role = \'teacher\'
         GROUP BY u.id, u.username, u.email, u.first_name, u.middle_name, u.last_name
         ORDER BY u.username ASC, u.id ASC'
    );

    return $statement->fetchAll();
}

function teacher_find(int $userId): ?array
{
    teacher_management_bootstrap();

    $statement = database()->prepare(
        'SELECT
            u.id,
            u.username,
            u.email,
            COALESCE(u.first_name, \'\') AS first_name,
            COALESCE(u.middle_name, \'\') AS middle_name,
            COALESCE(u.last_name, \'\') AS last_name,
            COALESCE(GROUP_CONCAT(tsa.section_id ORDER BY tsa.section_id SEPARATOR \',\'), \'\') AS section_ids_csv
         FROM users u
         LEFT JOIN teacher_section_assignments tsa ON tsa.teacher_user_id = u.id
         WHERE u.id = :id
           AND u.role = :role
         GROUP BY u.id, u.username, u.email, u.first_name, u.middle_name, u.last_name
         LIMIT 1'
    );
    $statement->execute([
        'id' => $userId,
        'role' => 'teacher',
    ]);
    $row = $statement->fetch();

    if ($row === false) {
        return null;
    }

    $sectionIds = trim((string) ($row['section_ids_csv'] ?? '')) !== ''
        ? array_values(array_filter(explode(',', (string) $row['section_ids_csv']), static fn ($value): bool => $value !== ''))
        : [];

    unset($row['section_ids_csv']);

    return teacher_form_defaults(array_merge($row, [
        'section_id' => $sectionIds[0] ?? '',
        'section_ids' => $sectionIds,
    ]));
}

function teacher_save(array $payload): void
{
    teacher_management_bootstrap();

    $errors = teacher_validate_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $sectionIds = array_values(array_unique(array_map('intval', $payload['section_ids'] ?? [])));

    if ($sectionIds === []) {
        throw new RuntimeException('Assign at least one section to this teacher.');
    }

    $placeholders = implode(', ', array_fill(0, count($sectionIds), '?'));
    $sectionStatement = database()->prepare(
        'SELECT s.id, s.school_year_id
         FROM sections s
         WHERE s.id IN (' . $placeholders . ')'
    );
    $sectionStatement->execute($sectionIds);
    $sections = $sectionStatement->fetchAll();

    if (count($sections) !== count($sectionIds)) {
        throw new RuntimeException('One or more selected sections could not be found.');
    }

    $pdo = database();
    $pdo->beginTransaction();

    try {
        if ($payload['id'] === null) {
            $userStatement = $pdo->prepare(
                'INSERT INTO users (username, email, first_name, middle_name, last_name, password_hash, role, is_active)
                 VALUES (:username, :email, :first_name, :middle_name, :last_name, :password_hash, :role, :is_active)'
            );
            $userStatement->execute([
                'username' => $payload['username'],
                'email' => $payload['email'],
                'first_name' => $payload['first_name'],
                'middle_name' => $payload['middle_name'] !== '' ? $payload['middle_name'] : null,
                'last_name' => $payload['last_name'],
                'password_hash' => password_hash($payload['password'], PASSWORD_DEFAULT),
                'role' => 'teacher',
                'is_active' => 1,
            ]);

            $teacherUserId = (int) $pdo->lastInsertId();
        } else {
            $teacherUserId = (int) $payload['id'];

            $existingStatement = $pdo->prepare(
                'SELECT id
                 FROM users
                 WHERE id = :id
                   AND role = :role
                 LIMIT 1'
            );
            $existingStatement->execute([
                'id' => $teacherUserId,
                'role' => 'teacher',
            ]);

            if ($existingStatement->fetch() === false) {
                throw new RuntimeException('Teacher account not found.');
            }

            if ($payload['password'] !== '') {
                $userStatement = $pdo->prepare(
                    'UPDATE users
                     SET username = :username,
                         email = :email,
                         first_name = :first_name,
                         middle_name = :middle_name,
                         last_name = :last_name,
                         password_hash = :password_hash,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id
                       AND role = :role'
                );
                $userStatement->execute([
                    'username' => $payload['username'],
                    'email' => $payload['email'],
                    'first_name' => $payload['first_name'],
                    'middle_name' => $payload['middle_name'] !== '' ? $payload['middle_name'] : null,
                    'last_name' => $payload['last_name'],
                    'password_hash' => password_hash($payload['password'], PASSWORD_DEFAULT),
                    'id' => $teacherUserId,
                    'role' => 'teacher',
                ]);
            } else {
                $userStatement = $pdo->prepare(
                    'UPDATE users
                     SET username = :username,
                         email = :email,
                         first_name = :first_name,
                         middle_name = :middle_name,
                         last_name = :last_name,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id
                       AND role = :role'
                );
                $userStatement->execute([
                    'username' => $payload['username'],
                    'email' => $payload['email'],
                    'first_name' => $payload['first_name'],
                    'middle_name' => $payload['middle_name'] !== '' ? $payload['middle_name'] : null,
                    'last_name' => $payload['last_name'],
                    'id' => $teacherUserId,
                    'role' => 'teacher',
                ]);
            }

            $deleteAssignmentStatement = $pdo->prepare(
                'DELETE FROM teacher_section_assignments
                 WHERE teacher_user_id = :teacher_user_id'
            );
            $deleteAssignmentStatement->execute(['teacher_user_id' => $teacherUserId]);
        }

        $assignmentStatement = $pdo->prepare(
            'INSERT INTO teacher_section_assignments (
                teacher_user_id,
                section_id,
                school_year_id
             ) VALUES (
                :teacher_user_id,
                :section_id,
                :school_year_id
             )'
        );
        foreach ($sections as $section) {
            $assignmentStatement->execute([
                'teacher_user_id' => $teacherUserId,
                'section_id' => (int) $section['id'],
                'school_year_id' => (int) $section['school_year_id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof PDOException && str_contains((string) $exception->getMessage(), 'Duplicate')) {
            throw new RuntimeException('Teacher username, email, or section assignment already exists.');
        }

        throw $exception;
    }
}

function teacher_delete(int $userId): void
{
    teacher_management_bootstrap();

    $statement = database()->prepare(
        'DELETE FROM users
         WHERE id = :id
           AND role = :role'
    );
    $statement->execute([
        'id' => $userId,
        'role' => 'teacher',
    ]);
}

function teacher_assigned_sections(int $userId): array
{
    teacher_management_bootstrap();

    $statement = database()->prepare(
        'SELECT
            s.id,
            s.name,
            s.grade_level,
            sy.label AS school_year_label,
            sy.start_date AS school_year_start_date,
            sy.end_date AS school_year_end_date,
            u.username,
            u.email
         FROM teacher_section_assignments tsa
         INNER JOIN sections s ON s.id = tsa.section_id
         INNER JOIN school_years sy ON sy.id = tsa.school_year_id
         INNER JOIN users u ON u.id = tsa.teacher_user_id
         WHERE tsa.teacher_user_id = :teacher_user_id
         ORDER BY sy.is_current DESC, sy.start_date DESC, s.grade_level ASC, s.name ASC'
    );
    $statement->execute(['teacher_user_id' => $userId]);

    return $statement->fetchAll();
}

function teacher_assigned_section(int $userId): ?array
{
    $sections = teacher_assigned_sections($userId);

    return $sections[0] ?? null;
}

function teacher_section_learners(int $userId): array
{
    teacher_management_bootstrap();
    learner_management_bootstrap();

    $statement = database()->prepare(
        'SELECT
            l.id,
            l.learner_number,
            l.lrn,
            CONCAT(
                l.last_name,
                \', \',
                l.first_name,
                IF(l.middle_name IS NULL OR l.middle_name = \'\', \'\', CONCAT(\' \', l.middle_name))
            ) AS learner_name,
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
            COALESCE(s.name, \'Unassigned\') AS section_name,
            hm.height_cm,
            hm.weight_kg,
            hm.recorded_on AS health_recorded_on,
            COUNT(pll.id) AS linked_parent_count
         FROM teacher_section_assignments tsa
         INNER JOIN learner_enrollments le
            ON le.section_id = tsa.section_id
           AND le.school_year_id = tsa.school_year_id
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN learner_health_measurements hm ON hm.learner_enrollment_id = le.id
         LEFT JOIN parent_learner_links pll ON pll.learner_id = l.id
         WHERE tsa.teacher_user_id = :teacher_user_id
         GROUP BY
            l.id,
            l.learner_number,
            l.lrn,
            l.last_name,
            l.first_name,
            l.middle_name,
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
            s.name,
            hm.height_cm,
            hm.weight_kg,
            hm.recorded_on
         ORDER BY l.last_name ASC, l.first_name ASC, l.id ASC'
    );
    $statement->execute(['teacher_user_id' => $userId]);

    return $statement->fetchAll();
}

function teacher_section_parent_links(int $userId): array
{
    teacher_management_bootstrap();

    $statement = database()->prepare(
        'SELECT
            l.id AS learner_id,
            CONCAT(
                l.last_name,
                \', \',
                l.first_name,
                IF(l.middle_name IS NULL OR l.middle_name = \'\', \'\', CONCAT(\' \', l.middle_name))
            ) AS learner_name,
            l.lrn,
            CONCAT(
                p.last_name,
                \', \',
                p.first_name,
                IF(p.middle_name IS NULL OR p.middle_name = \'\', \'\', CONCAT(\' \', p.middle_name))
            ) AS parent_name,
            u.username AS parent_username,
            u.email AS parent_email,
            pll.relationship,
            pll.is_primary_contact
         FROM teacher_section_assignments tsa
         INNER JOIN learner_enrollments le
            ON le.section_id = tsa.section_id
           AND le.school_year_id = tsa.school_year_id
         INNER JOIN learners l ON l.id = le.learner_id
         INNER JOIN parent_learner_links pll ON pll.learner_id = l.id
         INNER JOIN parents p ON p.id = pll.parent_id
         INNER JOIN users u ON u.id = p.user_id
         WHERE tsa.teacher_user_id = :teacher_user_id
         ORDER BY l.last_name ASC, l.first_name ASC, p.last_name ASC, p.first_name ASC'
    );
    $statement->execute(['teacher_user_id' => $userId]);

    return $statement->fetchAll();
}

function teacher_accessible_learner(int $userId, int $learnerId): ?array
{
    teacher_management_bootstrap();
    learner_management_bootstrap();

    $statement = database()->prepare(
        'SELECT
            l.id,
            l.lrn,
            CONCAT(
                l.last_name,
                \', \',
                l.first_name,
                IF(l.middle_name IS NULL OR l.middle_name = \'\', \'\', CONCAT(\' \', l.middle_name))
            ) AS learner_name,
            l.birthdate,
            l.mother_tongue,
            l.religion,
            l.address_house_number,
            l.address_barangay,
            l.address_city_municipality,
            l.address_province,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name
         FROM teacher_section_assignments tsa
         INNER JOIN learner_enrollments le
            ON le.section_id = tsa.section_id
           AND le.school_year_id = tsa.school_year_id
         INNER JOIN learners l ON l.id = le.learner_id
         LEFT JOIN sections s ON s.id = le.section_id
         WHERE tsa.teacher_user_id = :teacher_user_id
           AND l.id = :learner_id
         LIMIT 1'
    );
    $statement->execute([
        'teacher_user_id' => $userId,
        'learner_id' => $learnerId,
    ]);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function teacher_accessible_learner_by_lrn(int $userId, string $lrn): ?array
{
    teacher_management_bootstrap();
    learner_management_bootstrap();

    $statement = database()->prepare(
        'SELECT
            l.id,
            l.lrn,
            CONCAT(
                l.last_name,
                \', \',
                l.first_name,
                IF(l.middle_name IS NULL OR l.middle_name = \'\', \'\', CONCAT(\' \', l.middle_name))
            ) AS learner_name
         FROM teacher_section_assignments tsa
         INNER JOIN learner_enrollments le
            ON le.section_id = tsa.section_id
           AND le.school_year_id = tsa.school_year_id
         INNER JOIN learners l ON l.id = le.learner_id
         WHERE tsa.teacher_user_id = :teacher_user_id
           AND l.lrn = :lrn
         LIMIT 1'
    );
    $statement->execute([
        'teacher_user_id' => $userId,
        'lrn' => $lrn,
    ]);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function teacher_update_learner_profile(int $teacherUserId, array $payload): void
{
    learner_management_bootstrap();

    $errors = learner_profile_validate_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $learner = teacher_accessible_learner($teacherUserId, (int) $payload['learner_id']);

    if ($learner === null) {
        throw new RuntimeException('The selected learner is not part of your assigned section.');
    }

    $statement = database()->prepare(
        'UPDATE learners
         SET birthdate = :birthdate,
             mother_tongue = :mother_tongue,
             religion = :religion,
             address_house_number = :address_house_number,
             address_barangay = :address_barangay,
             address_city_municipality = :address_city_municipality,
             address_province = :address_province,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :learner_id'
    );
    $statement->execute([
        'birthdate' => $payload['birthdate'] !== '' ? $payload['birthdate'] : null,
        'mother_tongue' => $payload['mother_tongue'] !== '' ? $payload['mother_tongue'] : null,
        'religion' => $payload['religion'] !== '' ? $payload['religion'] : null,
        'address_house_number' => $payload['address_house_number'] !== '' ? $payload['address_house_number'] : null,
        'address_barangay' => $payload['address_barangay'] !== '' ? $payload['address_barangay'] : null,
        'address_city_municipality' => $payload['address_city_municipality'] !== '' ? $payload['address_city_municipality'] : learner_default_city_municipality(),
        'address_province' => $payload['address_province'] !== '' ? $payload['address_province'] : learner_default_province(),
        'learner_id' => (int) $payload['learner_id'],
    ]);
}

function teacher_import_learner_profiles(int $teacherUserId, array $file): int
{
    learner_management_bootstrap();

    $rows = learner_profile_import_rows_from_file($file);

    if ($rows === []) {
        throw new RuntimeException('The learner profile import file does not contain rows.');
    }

    $updatedCount = 0;

    foreach ($rows as $row) {
        if (implode('', array_map('strval', $row)) === '') {
            continue;
        }

        $payload = learner_profile_normalize_import_row($row);
        $learner = teacher_accessible_learner_by_lrn($teacherUserId, $payload['lrn']);

        if ($learner === null) {
            throw new RuntimeException('LRN ' . $payload['lrn'] . ' is not part of your assigned section.');
        }

        $payload['learner_id'] = (string) $learner['id'];
        teacher_update_learner_profile($teacherUserId, $payload);
        $updatedCount++;
    }

    return $updatedCount;
}

function teacher_import_parent_accounts(int $teacherUserId, array $file): int
{
    $rows = parent_import_rows_from_file($file);

    if ($rows === []) {
        throw new RuntimeException('The parent account import file does not contain rows.');
    }

    $importedCount = 0;

    foreach ($rows as $row) {
        if (implode('', array_map('strval', $row)) === '') {
            continue;
        }

        $payload = parent_import_normalize_row($row);
        $learner = teacher_accessible_learner_by_lrn($teacherUserId, $payload['lrn']);

        if ($learner === null) {
            throw new RuntimeException('LRN ' . $payload['lrn'] . ' is not part of your assigned section.');
        }

        $existingParent = parent_find_account_by_identity($payload['username']);
        if ($existingParent === null) {
            $existingParent = parent_find_account_by_identity($payload['email']);
        }

        if ($existingParent !== null) {
            parent_link_learner(
                (int) $existingParent['id'],
                (int) $learner['id'],
                $payload['relationship'],
                $payload['is_primary_contact'] === '1'
            );
        } else {
            parent_create_account_and_link($payload, (int) $learner['id']);
        }

        $importedCount++;
    }

    return $importedCount;
}
