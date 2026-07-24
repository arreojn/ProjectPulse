<?php

declare(strict_types=1);

function issue_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    database()->exec(
        'CREATE TABLE IF NOT EXISTS reported_issues (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            subject VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            status ENUM(\'open\', \'in_progress\', \'resolved\', \'closed\') DEFAULT \'open\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_reported_issues_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        )'
    );

    $bootstrapped = true;
}

function issue_form_defaults(array $overrides = []): array
{
    return array_merge([
        'id' => null,
        'subject' => '',
        'description' => '',
        'status' => 'open',
    ], $overrides);
}

function issue_normalize_payload(array $input): array
{
    return issue_form_defaults([
        'id' => isset($input['id']) && $input['id'] !== '' ? (int) $input['id'] : null,
        'subject' => trim((string) ($input['subject'] ?? '')),
        'description' => trim((string) ($input['description'] ?? '')),
        'status' => trim((string) ($input['status'] ?? 'open')),
    ]);
}

function issue_validate_payload(array $payload): array
{
    $errors = [];

    if ($payload['subject'] === '') {
        $errors[] = 'Issue subject is required.';
    }

    if (strlen($payload['subject']) > 255) {
        $errors[] = 'Issue subject cannot exceed 255 characters.';
    }

    if ($payload['description'] === '') {
        $errors[] = 'Issue description is required.';
    }

    if (!in_array($payload['status'], ['open', 'in_progress', 'resolved', 'closed'], true)) {
        $errors[] = 'Invalid issue status.';
    }

    return $errors;
}

function issue_report_for_teacher(int $userId, array $payload): void
{
    issue_bootstrap();

    $errors = issue_validate_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $statement = database()->prepare(
        'INSERT INTO reported_issues (user_id, subject, description, status)
         VALUES (:user_id, :subject, :description, :status)'
    );
    $statement->execute([
        'user_id' => $userId,
        'subject' => $payload['subject'],
        'description' => $payload['description'],
        'status' => $payload['status'],
    ]);
}

function issue_list_for_admin(): array
{
    issue_bootstrap();

    $statement = database()->query(
        'SELECT
            ri.id,
            ri.subject,
            ri.description,
            ri.status,
            ri.created_at,
            u.username,
            u.email,
            CONCAT(u.first_name, \' \', u.last_name) AS reporter_name
         FROM reported_issues ri
         INNER JOIN users u ON u.id = ri.user_id
         ORDER BY ri.status ASC, ri.created_at DESC'
    );

    return $statement->fetchAll();
}

function issue_find(int $issueId): ?array
{
    issue_bootstrap();

    $statement = database()->prepare(
        'SELECT
            ri.id,
            ri.subject,
            ri.description,
            ri.status,
            ri.created_at,
            u.username,
            u.email,
            CONCAT(u.first_name, \' \', u.last_name) AS reporter_name
         FROM reported_issues ri
         INNER JOIN users u ON u.id = ri.user_id
         WHERE ri.id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $issueId]);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function issue_update_status(int $issueId, string $status): void
{
    issue_bootstrap();

    if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
        throw new RuntimeException('Invalid issue status.');
    }

    $statement = database()->prepare(
        'UPDATE reported_issues SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
    );
    $statement->execute([
        'status' => $status,
        'id' => $issueId,
    ]);
}