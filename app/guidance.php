<?php

declare(strict_types=1);

function guidance_portal_bootstrap(): void
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

    auth_ensure_user_role('guidance');

    $pdo = database();
    $pdo->prepare(
        'INSERT IGNORE INTO users (username, email, first_name, middle_name, last_name, password_hash, role, is_active)
         VALUES (:username, :email, :first_name, :middle_name, :last_name, :password_hash, :role, :is_active)'
    )->execute([
        'username' => 'guidance_counselor',
        'email' => 'guidance@projectpulse.local',
        'first_name' => 'Guidance',
        'middle_name' => null,
        'last_name' => 'Counselor',
        'password_hash' => password_hash('guidance123', PASSWORD_DEFAULT),
        'role' => 'guidance',
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
        'first_name' => 'Guidance',
        'middle_name' => null,
        'last_name' => 'Counselor',
        'username' => 'guidance_counselor',
        'role' => 'guidance',
    ]);

    if (!auth_table_exists('learners')) {
        $bootstrapped = true;
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS guidance_cases (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            learner_id INT UNSIGNED NOT NULL,
            case_number VARCHAR(40) NOT NULL,
            date_opened DATE NOT NULL,
            referral_source VARCHAR(120) NULL,
            referral_reason TEXT NULL,
            counseling_type VARCHAR(80) NULL,
            counseling_date DATE NULL,
            presenting_concern TEXT NULL,
            intervention_plan TEXT NULL,
            follow_up_schedule DATE NULL,
            parent_conference VARCHAR(20) NULL,
            remarks TEXT NULL,
            case_status VARCHAR(40) NOT NULL DEFAULT "Open",
            date_closed DATE NULL,
            created_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_guidance_case_number (case_number),
            CONSTRAINT fk_guidance_case_learner
                FOREIGN KEY (learner_id) REFERENCES learners(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_guidance_case_user
                FOREIGN KEY (created_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS guidance_counseling_sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            guidance_case_id INT UNSIGNED NOT NULL,
            session_date DATE NOT NULL,
            session_type VARCHAR(80) NULL,
            notes TEXT NULL,
            follow_up_required TINYINT(1) NOT NULL DEFAULT 0,
            created_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_guidance_session_case
                FOREIGN KEY (guidance_case_id) REFERENCES guidance_cases(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_guidance_session_user
                FOREIGN KEY (created_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS guidance_referrals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            guidance_case_id INT UNSIGNED NOT NULL,
            source_role VARCHAR(80) NULL,
            referral_reason TEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT "Pending",
            action_taken TEXT NULL,
            outcome_recommendation TEXT NULL,
            referred_on DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_guidance_referral_case
                FOREIGN KEY (guidance_case_id) REFERENCES guidance_cases(id)
                ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS guidance_interventions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            guidance_case_id INT UNSIGNED NOT NULL,
            intervention_title VARCHAR(160) NOT NULL,
            intervention_type VARCHAR(80) NULL,
            scheduled_on DATE NULL,
            status VARCHAR(40) NOT NULL DEFAULT "Planned",
            completion_date DATE NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_guidance_intervention_case
                FOREIGN KEY (guidance_case_id) REFERENCES guidance_cases(id)
                ON DELETE CASCADE
        )'
    );

    $bootstrapped = true;
}

function guidance_case_status_options(): array
{
    return [
        'Open' => 'Open',
        'Monitoring' => 'Monitoring',
        'Closed' => 'Closed',
    ];
}

function guidance_counseling_type_options(): array
{
    return [
        'Individual Counseling' => 'Individual Counseling',
        'Group Counseling' => 'Group Counseling',
        'Parent Conference' => 'Parent Conference',
        'Classroom Observation' => 'Classroom Observation',
    ];
}

function guidance_referral_status_options(): array
{
    return [
        'Pending' => 'Pending',
        'In Progress' => 'In Progress',
        'Completed' => 'Completed',
    ];
}

function guidance_intervention_status_options(): array
{
    return [
        'Planned' => 'Planned',
        'In Progress' => 'In Progress',
        'Completed' => 'Completed',
    ];
}

function guidance_learner_options(): array
{
    $statement = database()->prepare(
        'SELECT id, learner_number, first_name, middle_name, last_name
         FROM learners
         ORDER BY first_name, last_name, id'
    );
    $statement->execute();

    return $statement->fetchAll();
}

function guidance_full_name(array $learner): string
{
    $parts = array_filter([
        trim((string) ($learner['first_name'] ?? '')),
        trim((string) ($learner['middle_name'] ?? '')),
        trim((string) ($learner['last_name'] ?? '')),
    ], static fn ($value): bool => $value !== '');

    return $parts === [] ? (string) ($learner['learner_number'] ?? '') : implode(' ', $parts);
}

function guidance_dashboard_stats(): array
{
    $pdo = database();

    return [
        'active_cases' => (int) $pdo->query('SELECT COUNT(*) FROM guidance_cases WHERE case_status != "Closed"')->fetchColumn(),
        'pending_referrals' => (int) $pdo->query('SELECT COUNT(*) FROM guidance_referrals WHERE status = "Pending"')->fetchColumn(),
        'completed_interventions' => (int) $pdo->query('SELECT COUNT(*) FROM guidance_interventions WHERE status = "Completed"')->fetchColumn(),
        'scheduled_sessions' => (int) $pdo->query('SELECT COUNT(*) FROM guidance_counseling_sessions')->fetchColumn(),
        'follow_up_cases' => (int) $pdo->query('SELECT COUNT(*) FROM guidance_cases WHERE follow_up_schedule IS NOT NULL AND follow_up_schedule >= CURDATE()')->fetchColumn(),
    ];
}

function guidance_case_rows(array $filters = []): array
{
    $pdo = database();
    $sql = 'SELECT gc.*, l.learner_number,
                l.first_name, l.middle_name, l.last_name
         FROM guidance_cases gc
         LEFT JOIN learners l ON l.id = gc.learner_id';

    $where = [];
    $params = [];

    if (!empty($filters['keyword'])) {
        $where[] = '(gc.case_number LIKE :keyword OR l.first_name LIKE :keyword OR l.last_name LIKE :keyword)';
        $params['keyword'] = '%' . $filters['keyword'] . '%';
    }

    if (!empty($filters['case_status'])) {
        $where[] = 'gc.case_status = :case_status';
        $params['case_status'] = $filters['case_status'];
    }

    if (!empty($filters['date_from'])) {
        $where[] = 'gc.date_opened >= :date_from';
        $params['date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'gc.date_opened <= :date_to';
        $params['date_to'] = $filters['date_to'];
    }

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY gc.date_opened DESC, gc.id DESC';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function guidance_case_by_id(int $id): ?array
{
    $schoolYear = current_school_year();
    $sql = 'SELECT
                gc.*,
                l.learner_number,
                l.first_name,
                l.middle_name,
                l.last_name,
                le.grade_level,
                s.name AS section_name
            FROM guidance_cases gc
            INNER JOIN learners l ON l.id = gc.learner_id
            LEFT JOIN learner_enrollments le ON le.learner_id = l.id AND le.school_year_id = :school_year_id
            LEFT JOIN sections s ON s.id = le.section_id
            WHERE gc.id = :id
            LIMIT 1';

    $statement = database()->prepare($sql);
    $statement->execute([
        'id' => $id,
        'school_year_id' => $schoolYear['id'] ?? 0,
    ]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function guidance_session_rows(int $guidanceCaseId): array
{
    $statement = database()->prepare(
        'SELECT * FROM guidance_counseling_sessions
         WHERE guidance_case_id = :guidance_case_id
         ORDER BY session_date DESC, id DESC'
    );
    $statement->execute(['guidance_case_id' => $guidanceCaseId]);

    return $statement->fetchAll();
}

function guidance_referral_rows(int $guidanceCaseId): array
{
    $statement = database()->prepare(
        'SELECT * FROM guidance_referrals
         WHERE guidance_case_id = :guidance_case_id
         ORDER BY referred_on DESC, id DESC'
    );
    $statement->execute(['guidance_case_id' => $guidanceCaseId]);

    return $statement->fetchAll();
}

function guidance_intervention_rows(int $guidanceCaseId): array
{
    $statement = database()->prepare(
        'SELECT * FROM guidance_interventions
         WHERE guidance_case_id = :guidance_case_id
         ORDER BY scheduled_on DESC, id DESC'
    );
    $statement->execute(['guidance_case_id' => $guidanceCaseId]);

    return $statement->fetchAll();
}

function guidance_upcoming_followups(int $limit = 5): array
{
    $statement = database()->prepare(
        'SELECT gc.id, gc.case_number, gc.follow_up_schedule,
                l.first_name, l.middle_name, l.last_name
         FROM guidance_cases gc
         INNER JOIN learners l ON l.id = gc.learner_id
         WHERE gc.follow_up_schedule IS NOT NULL
           AND gc.follow_up_schedule >= CURDATE()
         ORDER BY gc.follow_up_schedule ASC
         LIMIT :limit'
    );
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function guidance_save_case(array $data, ?int $userId = null): int
{
    $pdo = database();
    $learnerId = isset($data['learner_id']) ? (int) $data['learner_id'] : 0;
    $caseId = isset($data['id']) ? (int) $data['id'] : 0;
    $caseNumber = trim((string) ($data['case_number'] ?? ''));
    $dateOpened = trim((string) ($data['date_opened'] ?? ''));

    if ($learnerId <= 0 || $caseNumber === '' || $dateOpened === '') {
        throw new RuntimeException('Please provide the learner, case number, and open date.');
    }

    $caseNumber = $caseNumber !== '' ? $caseNumber : 'GC-' . date('Ymd');

    $values = [
        'learner_id' => $learnerId,
        'case_number' => $caseNumber,
        'date_opened' => $dateOpened,
        'referral_source' => trim((string) ($data['referral_source'] ?? '')) !== '' ? trim((string) ($data['referral_source'] ?? '')) : null,
        'referral_reason' => trim((string) ($data['referral_reason'] ?? '')) !== '' ? trim((string) ($data['referral_reason'] ?? '')) : null,
        'counseling_type' => trim((string) ($data['counseling_type'] ?? '')) !== '' ? trim((string) ($data['counseling_type'] ?? '')) : null,
        'counseling_date' => trim((string) ($data['counseling_date'] ?? '')) !== '' ? trim((string) ($data['counseling_date'] ?? '')) : null,
        'presenting_concern' => trim((string) ($data['presenting_concern'] ?? '')) !== '' ? trim((string) ($data['presenting_concern'] ?? '')) : null,
        'intervention_plan' => trim((string) ($data['intervention_plan'] ?? '')) !== '' ? trim((string) ($data['intervention_plan'] ?? '')) : null,
        'follow_up_schedule' => trim((string) ($data['follow_up_schedule'] ?? '')) !== '' ? trim((string) ($data['follow_up_schedule'] ?? '')) : null,
        'parent_conference' => trim((string) ($data['parent_conference'] ?? '')) !== '' ? trim((string) ($data['parent_conference'] ?? '')) : null,
        'remarks' => trim((string) ($data['remarks'] ?? '')) !== '' ? trim((string) ($data['remarks'] ?? '')) : null,
        'case_status' => trim((string) ($data['case_status'] ?? 'Open')) !== '' ? trim((string) ($data['case_status'] ?? 'Open')) : 'Open',
        'date_closed' => trim((string) ($data['date_closed'] ?? '')) !== '' ? trim((string) ($data['date_closed'] ?? '')) : null,
        'created_by_user_id' => $userId ?? null,
    ];

    if ($caseId > 0) {
        $values['id'] = $caseId;
        $statement = $pdo->prepare(
            'UPDATE guidance_cases
             SET learner_id = :learner_id,
                 case_number = :case_number,
                 date_opened = :date_opened,
                 referral_source = :referral_source,
                 referral_reason = :referral_reason,
                 counseling_type = :counseling_type,
                 counseling_date = :counseling_date,
                 presenting_concern = :presenting_concern,
                 intervention_plan = :intervention_plan,
                 follow_up_schedule = :follow_up_schedule,
                 parent_conference = :parent_conference,
                 remarks = :remarks,
                 case_status = :case_status,
                 date_closed = :date_closed,
                 created_by_user_id = COALESCE(:created_by_user_id, created_by_user_id)
             WHERE id = :id'
        );
        $statement->execute($values);

        return $caseId;
    }

    $statement = $pdo->prepare(
        'INSERT INTO guidance_cases (
            learner_id,
            case_number,
            date_opened,
            referral_source,
            referral_reason,
            counseling_type,
            counseling_date,
            presenting_concern,
            intervention_plan,
            follow_up_schedule,
            parent_conference,
            remarks,
            case_status,
            date_closed,
            created_by_user_id
         ) VALUES (
            :learner_id,
            :case_number,
            :date_opened,
            :referral_source,
            :referral_reason,
            :counseling_type,
            :counseling_date,
            :presenting_concern,
            :intervention_plan,
            :follow_up_schedule,
            :parent_conference,
            :remarks,
            :case_status,
            :date_closed,
            :created_by_user_id
         )'
    );
    $statement->execute($values);

    return (int) $pdo->lastInsertId();
}

function guidance_save_counseling_session(int $guidanceCaseId, array $data, ?int $userId = null): void
{
    $pdo = database();
    $statement = $pdo->prepare(
        'INSERT INTO guidance_counseling_sessions (
            guidance_case_id,
            session_date,
            session_type,
            notes,
            follow_up_required,
            created_by_user_id
         ) VALUES (
            :guidance_case_id,
            :session_date,
            :session_type,
            :notes,
            :follow_up_required,
            :created_by_user_id
         )'
    );
    $statement->execute([
        'guidance_case_id' => $guidanceCaseId,
        'session_date' => trim((string) ($data['session_date'] ?? '')) !== '' ? trim((string) ($data['session_date'] ?? '')) : null,
        'session_type' => trim((string) ($data['session_type'] ?? '')) !== '' ? trim((string) ($data['session_type'] ?? '')) : null,
        'notes' => trim((string) ($data['notes'] ?? '')) !== '' ? trim((string) ($data['notes'] ?? '')) : null,
        'follow_up_required' => isset($data['follow_up_required']) ? 1 : 0,
        'created_by_user_id' => $userId ?? null,
    ]);
}

function guidance_save_referral(int $guidanceCaseId, array $data, ?int $userId = null): void
{
    $pdo = database();
    $statement = $pdo->prepare(
        'INSERT INTO guidance_referrals (
            guidance_case_id,
            source_role,
            referral_reason,
            status,
            action_taken,
            outcome_recommendation,
            referred_on
         ) VALUES (
            :guidance_case_id,
            :source_role,
            :referral_reason,
            :status,
            :action_taken,
            :outcome_recommendation,
            :referred_on
         )'
    );
    $statement->execute([
        'guidance_case_id' => $guidanceCaseId,
        'source_role' => trim((string) ($data['source_role'] ?? '')) !== '' ? trim((string) ($data['source_role'] ?? '')) : null,
        'referral_reason' => trim((string) ($data['referral_reason'] ?? '')) !== '' ? trim((string) ($data['referral_reason'] ?? '')) : null,
        'status' => trim((string) ($data['status'] ?? 'Pending')) !== '' ? trim((string) ($data['status'] ?? 'Pending')) : 'Pending',
        'action_taken' => trim((string) ($data['action_taken'] ?? '')) !== '' ? trim((string) ($data['action_taken'] ?? '')) : null,
        'outcome_recommendation' => trim((string) ($data['outcome_recommendation'] ?? '')) !== '' ? trim((string) ($data['outcome_recommendation'] ?? '')) : null,
        'referred_on' => trim((string) ($data['referred_on'] ?? '')) !== '' ? trim((string) ($data['referred_on'] ?? '')) : date('Y-m-d'),
    ]);
}

function guidance_save_intervention(int $guidanceCaseId, array $data, ?int $userId = null): void
{
    $pdo = database();
    $statement = $pdo->prepare(
        'INSERT INTO guidance_interventions (
            guidance_case_id,
            intervention_title,
            intervention_type,
            scheduled_on,
            status,
            completion_date,
            notes
         ) VALUES (
            :guidance_case_id,
            :intervention_title,
            :intervention_type,
            :scheduled_on,
            :status,
            :completion_date,
            :notes
         )'
    );
    $statement->execute([
        'guidance_case_id' => $guidanceCaseId,
        'intervention_title' => trim((string) ($data['intervention_title'] ?? '')) !== '' ? trim((string) ($data['intervention_title'] ?? '')) : 'Intervention',
        'intervention_type' => trim((string) ($data['intervention_type'] ?? '')) !== '' ? trim((string) ($data['intervention_type'] ?? '')) : null,
        'scheduled_on' => trim((string) ($data['scheduled_on'] ?? '')) !== '' ? trim((string) ($data['scheduled_on'] ?? '')) : null,
        'status' => trim((string) ($data['status'] ?? 'Planned')) !== '' ? trim((string) ($data['status'] ?? 'Planned')) : 'Planned',
        'completion_date' => trim((string) ($data['completion_date'] ?? '')) !== '' ? trim((string) ($data['completion_date'] ?? '')) : null,
        'notes' => trim((string) ($data['notes'] ?? '')) !== '' ? trim((string) ($data['notes'] ?? '')) : null,
    ]);
}
