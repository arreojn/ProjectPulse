<?php

declare(strict_types=1);

function announcements_bootstrap(): void
{
    $pdo = database();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS announcements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_by_user_id INT UNSIGNED NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            published_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_announcement_user
                FOREIGN KEY (created_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
        )'
    );
}

function announcement_list(array $filters = []): array
{
    $pdo = database();
    $sql = 'SELECT a.*, u.username, u.role
            FROM announcements a
            LEFT JOIN users u ON u.id = a.created_by_user_id';

    $where = [];
    $params = [];

    if (!empty($filters['role'])) {
        $where[] = 'u.role = :role';
        $params['role'] = $filters['role'];
    }

    if (!empty($filters['user_id'])) {
        $where[] = 'a.created_by_user_id = :user_id';
        $params['user_id'] = (int) $filters['user_id'];
    }

    if (isset($filters['is_published'])) {
        $where[] = 'a.is_published = :is_published';
        $params['is_published'] = (int) $filters['is_published'];
    }

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY a.published_at DESC, a.created_at DESC';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function announcement_find(int $id): ?array
{
    $statement = database()->prepare(
        'SELECT * FROM announcements WHERE id = :id'
    );
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function announcement_save(array $data, int $userId): int
{
    $id = isset($data['id']) ? (int) $data['id'] : 0;
    $title = trim((string) ($data['title'] ?? ''));
    $content = trim((string) ($data['content'] ?? ''));
    $isPublished = isset($data['is_published']) ? 1 : 0;

    if ($title === '' || $content === '') {
        throw new RuntimeException('Title and content are required.');
    }

    $pdo = database();

    if ($id > 0) {
        $statement = $pdo->prepare(
            'UPDATE announcements
             SET title = :title,
                 content = :content,
                 is_published = :is_published,
                 published_at = CASE WHEN :is_published = 1 AND published_at IS NULL THEN CURRENT_TIMESTAMP ELSE published_at END
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'title' => $title,
            'content' => $content,
            'is_published' => $isPublished,
        ]);

        return $id;
    }

    $statement = $pdo->prepare(
        'INSERT INTO announcements (title, content, is_published, created_by_user_id, published_at)
         VALUES (:title, :content, :is_published, :created_by_user_id, CASE WHEN :is_published = 1 THEN CURRENT_TIMESTAMP ELSE NULL END)'
    );
    $statement->execute([
        'title' => $title,
        'content' => $content,
        'is_published' => $isPublished,
        'created_by_user_id' => $userId,
    ]);

    return (int) $pdo->lastInsertId();
}

function announcement_delete(int $id): void
{
    $statement = database()->prepare(
        'DELETE FROM announcements WHERE id = :id'
    );
    $statement->execute(['id' => $id]);
}