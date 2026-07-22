<?php

declare(strict_types=1);

function parent_portal_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $pdo = database();

    $statement = $pdo->prepare(
        'INSERT IGNORE INTO users (username, email, password_hash, role, is_active)
         VALUES (:username, :email, :password_hash, :role, :is_active)'
    );
    $statement->execute([
        'username' => 'demo_parent',
        'email' => 'parent@projectpulse.local',
        'password_hash' => '$2y$10$bRKpueTjVab73zPzrBUyBe.3iRircjMowF66LfB1UuA/QZv4Vw9T.',
        'role' => 'parent',
        'is_active' => 1,
    ]);

    $userStatement = $pdo->prepare(
        'SELECT id
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $userStatement->execute(['username' => 'demo_parent']);
    $user = $userStatement->fetch();

    if ($user === false) {
        $bootstrapped = true;
        return;
    }

    $parentStatement = $pdo->prepare(
        'INSERT IGNORE INTO parents (
            user_id,
            first_name,
            middle_name,
            last_name,
            contact_number,
            address
         ) VALUES (
            :user_id,
            :first_name,
            :middle_name,
            :last_name,
            :contact_number,
            :address
         )'
    );
    $parentStatement->execute([
        'user_id' => (int) $user['id'],
        'first_name' => 'Ana',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'contact_number' => '09171234567',
        'address' => 'ProjectPulse Demo Household',
    ]);

    $parentIdStatement = $pdo->prepare(
        'SELECT id
         FROM parents
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $parentIdStatement->execute(['user_id' => (int) $user['id']]);
    $parent = $parentIdStatement->fetch();

    if ($parent === false) {
        $bootstrapped = true;
        return;
    }

    $linkLearners = [
        ['lrn' => '123456789012', 'relationship' => 'Mother', 'is_primary_contact' => 1],
        ['lrn' => '987654321098', 'relationship' => 'Mother', 'is_primary_contact' => 0],
    ];

    $linkStatement = $pdo->prepare(
        'INSERT IGNORE INTO parent_learner_links (
            parent_id,
            learner_id,
            relationship,
            is_primary_contact
         )
         SELECT
            :parent_id,
            l.id,
            :relationship,
            :is_primary_contact
         FROM learners l
         WHERE l.lrn = :lrn'
    );

    foreach ($linkLearners as $linkLearner) {
        $linkStatement->execute([
            'parent_id' => (int) $parent['id'],
            'relationship' => $linkLearner['relationship'],
            'is_primary_contact' => $linkLearner['is_primary_contact'],
            'lrn' => $linkLearner['lrn'],
        ]);
    }

    $bootstrapped = true;
}

function parent_portal_valid_month(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
        return false;
    }

    $year = (int) substr($value, 0, 4);
    $month = (int) substr($value, 5, 2);

    return checkdate($month, 1, $year);
}

function parent_portal_month_bounds(string $reportMonth): array
{
    $monthStart = $reportMonth . '-01';
    $monthStamp = strtotime($monthStart);

    if ($monthStamp === false) {
        return [
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t'),
        ];
    }

    return [
        'date_from' => date('Y-m-01', $monthStamp),
        'date_to' => date('Y-m-t', $monthStamp),
    ];
}

function parent_portal_month_label(string $reportMonth): string
{
    $monthStamp = strtotime($reportMonth . '-01');

    return $monthStamp === false ? $reportMonth : date('F Y', $monthStamp);
}

function parent_linked_learners(int $userId): array
{
    $statement = database()->prepare(
        'SELECT
            l.id,
            l.learner_number,
            l.lrn,
            l.first_name,
            l.middle_name,
            l.last_name,
            pll.relationship,
            pll.is_primary_contact,
            COALESCE(le.grade_level, \'Unassigned\') AS grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name,
            COALESCE(sy.label, \'No school year\') AS school_year_label,
            latest_ar.attendance_date AS latest_attendance_date,
            COALESCE(latest_al.label, \'No attendance yet\') AS latest_attendance_status,
            lhm.height_cm,
            lhm.weight_kg
         FROM parents p
         INNER JOIN parent_learner_links pll ON pll.parent_id = p.id
         INNER JOIN learners l ON l.id = pll.learner_id
         LEFT JOIN learner_enrollments le
            ON le.id = (
                SELECT le2.id
                FROM learner_enrollments le2
                INNER JOIN school_years sy2 ON sy2.id = le2.school_year_id
                WHERE le2.learner_id = l.id
                ORDER BY sy2.is_current DESC, sy2.start_date DESC, le2.id DESC
                LIMIT 1
            )
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN school_years sy ON sy.id = le.school_year_id
         LEFT JOIN attendance_records latest_ar
            ON latest_ar.id = (
                SELECT ar2.id
                FROM attendance_records ar2
                WHERE ar2.learner_enrollment_id = le.id
                ORDER BY ar2.attendance_date DESC, ar2.id DESC
                LIMIT 1
            )
         LEFT JOIN attendance_legends latest_al ON latest_al.id = latest_ar.legend_id
         LEFT JOIN learner_health_measurements lhm ON lhm.learner_enrollment_id = le.id
         WHERE p.user_id = :user_id
         ORDER BY l.last_name ASC, l.first_name ASC, l.id ASC'
    );
    $statement->execute(['user_id' => $userId]);

    $rows = $statement->fetchAll();

    foreach ($rows as &$row) {
        $bmi = _parent_portal_calculate_bmi($row['height_cm'] ?? null, $row['weight_kg'] ?? null);
        $row['bmi'] = $bmi;
        $row['bmi_remarks'] = _parent_portal_bmi_remarks($bmi);
    }
    unset($row);

    return $rows;
}

function parent_portal_filters(array $learners): array
{
    $currentMonth = date('Y-m');
    $learnerIds = array_map(
        static fn (array $learner): string => (string) $learner['id'],
        $learners
    );

    $defaultLearnerId = $learnerIds[0] ?? '';
    $childId = trim((string) ($_GET['child_id'] ?? $defaultLearnerId));

    if (!in_array($childId, $learnerIds, true)) {
        $childId = $defaultLearnerId;
    }

    $reportMonth = trim((string) ($_GET['report_month'] ?? $currentMonth));

    if (!parent_portal_valid_month($reportMonth)) {
        $reportMonth = $currentMonth;
    }

    return [
        'child_id' => $childId,
        'report_month' => $reportMonth,
    ];
}

function parent_portal_selected_child(array $learners, string $childId): ?array
{
    foreach ($learners as $learner) {
        if ((string) $learner['id'] === $childId) {
            return $learner;
        }
    }

    return null;
}

function parent_child_month_attendance(int $userId, int $learnerId, string $reportMonth): array
{
    $bounds = parent_portal_month_bounds($reportMonth);

    $statement = database()->prepare(
        'SELECT
            ar.attendance_date,
            COALESCE(al.code, \'\') AS attendance_code,
            COALESCE(al.label, \'No record\') AS attendance_status,
            COALESCE(al.counts_as_present, 0) AS counts_as_present,
            ar.am_time_in,
            ar.am_time_out,
            ar.pm_time_in,
            ar.pm_time_out,
            ar.remarks,
            le.grade_level,
            COALESCE(s.name, \'Unassigned\') AS section_name
         FROM parents p
         INNER JOIN parent_learner_links pll ON pll.parent_id = p.id
         INNER JOIN learners l ON l.id = pll.learner_id
         INNER JOIN learner_enrollments le ON le.learner_id = l.id
         INNER JOIN attendance_records ar
            ON ar.learner_enrollment_id = le.id
           AND ar.attendance_date BETWEEN :date_from AND :date_to
         LEFT JOIN sections s ON s.id = le.section_id
         LEFT JOIN attendance_legends al ON al.id = ar.legend_id
         WHERE p.user_id = :user_id
           AND l.id = :learner_id
         ORDER BY ar.attendance_date DESC, ar.id DESC'
    );
    $statement->execute([
        'user_id' => $userId,
        'learner_id' => $learnerId,
        'date_from' => $bounds['date_from'],
        'date_to' => $bounds['date_to'],
    ]);

    return $statement->fetchAll();
}

function parent_child_month_summary(array $rows): array
{
    $summary = [
        'days_with_records' => 0,
        'attended_days' => 0,
        'present_count' => 0,
        'late_count' => 0,
        'absent_count' => 0,
        'excused_count' => 0,
    ];

    foreach ($rows as $row) {
        $summary['days_with_records']++;

        if ((int) ($row['counts_as_present'] ?? 0) === 1) {
            $summary['attended_days']++;
        }

        switch ((string) ($row['attendance_code'] ?? '')) {
            case 'P':
                $summary['present_count']++;
                break;
            case 'L':
                $summary['late_count']++;
                break;
            case 'A':
                $summary['absent_count']++;
                break;
            case 'E':
                $summary['excused_count']++;
                break;
        }
    }

    return $summary;
}

function parent_account_form_defaults(array $overrides = []): array
{
    return array_merge([
        'username' => '',
        'email' => '',
        'password' => '',
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'contact_number' => '',
        'address' => '',
        'relationship' => '',
        'is_primary_contact' => '0',
        'learner_id' => '',
        'identity' => '',
    ], $overrides);
}

function parent_account_normalize_payload(array $input): array
{
    return parent_account_form_defaults([
        'username' => trim((string) ($input['username'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'password' => (string) ($input['password'] ?? ''),
        'first_name' => trim((string) ($input['first_name'] ?? '')),
        'middle_name' => trim((string) ($input['middle_name'] ?? '')),
        'last_name' => trim((string) ($input['last_name'] ?? '')),
        'contact_number' => trim((string) ($input['contact_number'] ?? '')),
        'address' => trim((string) ($input['address'] ?? '')),
        'relationship' => trim((string) ($input['relationship'] ?? '')),
        'is_primary_contact' => !empty($input['is_primary_contact']) ? '1' : '0',
        'learner_id' => trim((string) ($input['learner_id'] ?? '')),
        'identity' => trim((string) ($input['identity'] ?? '')),
    ]);
}

function parent_account_validate_payload(array $payload, bool $requirePassword = true): array
{
    $errors = [];

    if ($payload['username'] === '') {
        $errors[] = 'Parent username is required.';
    }

    if ($payload['email'] === '' || filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'A valid parent email is required.';
    }

    if ($requirePassword && strlen($payload['password']) < 6) {
        $errors[] = 'Parent password must be at least 6 characters.';
    }

    if ($payload['first_name'] === '' || $payload['last_name'] === '') {
        $errors[] = 'Parent first name and last name are required.';
    }

    return $errors;
}

function parent_create_account(array $payload): int
{
    $errors = parent_account_validate_payload($payload, true);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $pdo = database();
    $pdo->beginTransaction();

    try {
        $userStatement = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role, is_active)
             VALUES (:username, :email, :password_hash, :role, :is_active)'
        );
        $userStatement->execute([
            'username' => $payload['username'],
            'email' => $payload['email'],
            'password_hash' => password_hash($payload['password'], PASSWORD_DEFAULT),
            'role' => 'parent',
            'is_active' => 1,
        ]);

        $userId = (int) $pdo->lastInsertId();

        $parentStatement = $pdo->prepare(
            'INSERT INTO parents (
                user_id,
                first_name,
                middle_name,
                last_name,
                contact_number,
                address
             ) VALUES (
                :user_id,
                :first_name,
                :middle_name,
                :last_name,
                :contact_number,
                :address
             )'
        );
        $parentStatement->execute([
            'user_id' => $userId,
            'first_name' => $payload['first_name'],
            'middle_name' => $payload['middle_name'] !== '' ? $payload['middle_name'] : null,
            'last_name' => $payload['last_name'],
            'contact_number' => $payload['contact_number'] !== '' ? $payload['contact_number'] : null,
            'address' => $payload['address'] !== '' ? $payload['address'] : null,
        ]);

        $parentId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return $parentId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof PDOException && str_contains((string) $exception->getMessage(), 'Duplicate')) {
            throw new RuntimeException('Parent username or email already exists.');
        }

        throw $exception;
    }
}

function parent_create_account_and_link(array $payload, int $learnerId): int
{
    $errors = parent_account_validate_payload($payload, true);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    if ($payload['relationship'] === '') {
        throw new RuntimeException('Relationship is required when linking a parent account.');
    }

    $pdo = database();
    $pdo->beginTransaction();

    try {
        $userStatement = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role, is_active)
             VALUES (:username, :email, :password_hash, :role, :is_active)'
        );
        $userStatement->execute([
            'username' => $payload['username'],
            'email' => $payload['email'],
            'password_hash' => password_hash($payload['password'], PASSWORD_DEFAULT),
            'role' => 'parent',
            'is_active' => 1,
        ]);

        $userId = (int) $pdo->lastInsertId();

        $parentStatement = $pdo->prepare(
            'INSERT INTO parents (
                user_id,
                first_name,
                middle_name,
                last_name,
                contact_number,
                address
             ) VALUES (
                :user_id,
                :first_name,
                :middle_name,
                :last_name,
                :contact_number,
                :address
             )'
        );
        $parentStatement->execute([
            'user_id' => $userId,
            'first_name' => $payload['first_name'],
            'middle_name' => $payload['middle_name'] !== '' ? $payload['middle_name'] : null,
            'last_name' => $payload['last_name'],
            'contact_number' => $payload['contact_number'] !== '' ? $payload['contact_number'] : null,
            'address' => $payload['address'] !== '' ? $payload['address'] : null,
        ]);

        $parentId = (int) $pdo->lastInsertId();

        $linkStatement = $pdo->prepare(
            'INSERT INTO parent_learner_links (
                parent_id,
                learner_id,
                relationship,
                is_primary_contact
             ) VALUES (
                :parent_id,
                :learner_id,
                :relationship,
                :is_primary_contact
             )'
        );
        $linkStatement->execute([
            'parent_id' => $parentId,
            'learner_id' => $learnerId,
            'relationship' => $payload['relationship'],
            'is_primary_contact' => $payload['is_primary_contact'] === '1' ? 1 : 0,
        ]);

        $pdo->commit();

        return $parentId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof PDOException && str_contains((string) $exception->getMessage(), 'Duplicate')) {
            throw new RuntimeException('Parent username, email, or learner link already exists.');
        }

        throw $exception;
    }
}

function parent_find_account_by_identity(string $identity): ?array
{
    $identity = trim($identity);

    if ($identity === '') {
        return null;
    }

    $statement = database()->prepare(
        'SELECT
            p.id,
            p.first_name,
            p.middle_name,
            p.last_name,
            u.username,
            u.email
         FROM parents p
         INNER JOIN users u ON u.id = p.user_id
         WHERE u.role = :role
           AND (u.username = :identity OR u.email = :identity)
         LIMIT 1'
    );
    $statement->execute([
        'role' => 'parent',
        'identity' => $identity,
    ]);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function parent_link_learner(int $parentId, int $learnerId, string $relationship, bool $isPrimaryContact = false): void
{
    if ($relationship === '') {
        throw new RuntimeException('Relationship is required when linking a parent account.');
    }

    $statement = database()->prepare(
        'INSERT INTO parent_learner_links (
            parent_id,
            learner_id,
            relationship,
            is_primary_contact
         ) VALUES (
            :parent_id,
            :learner_id,
            :relationship,
            :is_primary_contact
         )'
    );

    try {
        $statement->execute([
            'parent_id' => $parentId,
            'learner_id' => $learnerId,
            'relationship' => $relationship,
            'is_primary_contact' => $isPrimaryContact ? 1 : 0,
        ]);
    } catch (Throwable $exception) {
        if ($exception instanceof PDOException && str_contains((string) $exception->getMessage(), 'Duplicate')) {
            throw new RuntimeException('This parent is already linked to the selected learner.');
        }

        throw $exception;
    }
}

function parent_import_template_headers(): array
{
    return [
        'lrn',
        'parent_username',
        'parent_email',
        'parent_password',
        'parent_first_name',
        'parent_middle_name',
        'parent_last_name',
        'contact_number',
        'address',
        'relationship',
        'is_primary_contact',
    ];
}

function parent_import_clean_string(?string $value): string
{
    $clean = trim((string) $value);

    return preg_replace('/^\xEF\xBB\xBF/', '', $clean) ?? $clean;
}

function parent_import_parse_csv_rows(string $path): array
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
        static fn ($value): string => strtolower(parent_import_clean_string((string) $value)),
        $header
    );

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $item = [];

        foreach ($normalizedHeader as $index => $column) {
            $item[$column] = isset($row[$index]) ? parent_import_clean_string((string) $row[$index]) : '';
        }

        $rows[] = $item;
    }

    fclose($handle);

    return $rows;
}

function parent_import_parse_excel_xml_rows(string $path): array
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
            $cells[$position] = isset($dataNodes[0]) ? parent_import_clean_string((string) $dataNodes[0]) : '';
            $position++;
        }

        ksort($cells);
        $values = array_values($cells);

        if ($rowIndex === 0) {
            $header = array_map(
                static fn ($value): string => strtolower(parent_import_clean_string((string) $value)),
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

function parent_import_rows_from_file(array $file): array
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Choose a CSV or XLS parent account file to import.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    return match ($extension) {
        'csv' => parent_import_parse_csv_rows($file['tmp_name']),
        'xls' => parent_import_parse_excel_xml_rows($file['tmp_name']),
        default => throw new RuntimeException('Supported parent account import files are CSV and the provided XLS template.'),
    };
}

function parent_import_normalize_row(array $row): array
{
    $relationship = parent_import_clean_string($row['relationship'] ?? '');

    $payload = parent_account_form_defaults([
        'username' => parent_import_clean_string($row['parent_username'] ?? ''),
        'email' => parent_import_clean_string($row['parent_email'] ?? ''),
        'password' => (string) ($row['parent_password'] ?? ''),
        'first_name' => parent_import_clean_string($row['parent_first_name'] ?? ''),
        'middle_name' => parent_import_clean_string($row['parent_middle_name'] ?? ''),
        'last_name' => parent_import_clean_string($row['parent_last_name'] ?? ''),
        'contact_number' => parent_import_clean_string($row['contact_number'] ?? ''),
        'address' => parent_import_clean_string($row['address'] ?? ''),
        'relationship' => $relationship !== '' ? $relationship : 'Parent',
        'is_primary_contact' => in_array(strtolower(parent_import_clean_string($row['is_primary_contact'] ?? '')), ['1', 'yes', 'true', 'y'], true) ? '1' : '0',
    ]);
    $payload['lrn'] = preg_replace('/\D+/', '', parent_import_clean_string($row['lrn'] ?? '')) ?? '';

    if ($payload['lrn'] === '' || strlen($payload['lrn']) !== 12) {
        throw new RuntimeException('Each parent account row must include a valid 12-digit learner LRN.');
    }

    return $payload;
}

function _parent_portal_calculate_bmi($heightCm, $weightKg): ?float
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

function _parent_portal_bmi_remarks(?float $bmi): string
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

function parent_account_options(): array
{
    $statement = database()->query(
        'SELECT
            u.id,
            u.username,
            u.email,
            p.first_name,
            p.last_name
         FROM users u
         INNER JOIN parents p ON p.user_id = u.id
         WHERE u.role = \'parent\' AND u.is_active = 1
         ORDER BY p.last_name, p.first_name, u.username'
    );

    return $statement->fetchAll();
}
