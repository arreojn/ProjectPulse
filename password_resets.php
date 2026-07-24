<?php

declare(strict_types=1);

function password_resets_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    database()->exec(
        'CREATE TABLE IF NOT EXISTS password_reset_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\', -- pending, approved, denied
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_by_admin_id INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            new_password_snapshot VARCHAR(255) NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by_admin_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $bootstrapped = true;
}

function request_password_reset(string $identity): void
{
    $identity = trim($identity);
    if ($identity === '') {
        return;
    }

    $user = auth_find_user_by_identity($identity);

    if ($user !== null) {
        $pdo = database();
        $existingRequestStmt = $pdo->prepare(
            'SELECT id FROM password_reset_requests WHERE user_id = :user_id AND status = \'pending\''
        );
        $existingRequestStmt->execute(['user_id' => (int) $user['id']]);

        if ($existingRequestStmt->fetch() === false) {
            $stmt = $pdo->prepare(
                'INSERT INTO password_reset_requests (user_id) VALUES (:user_id)'
            );
            $stmt->execute(['user_id' => (int) $user['id']]);
        }
    }
}

function get_pending_password_requests(): array
{
    $statement = database()->query(
        'SELECT
            prr.id,
            prr.requested_at,
            u.username,
            u.email,
            u.role
         FROM password_reset_requests prr
         INNER JOIN users u ON u.id = prr.user_id
         WHERE prr.status = \'pending\'
         ORDER BY prr.requested_at ASC'
    );

    return $statement->fetchAll();
}

function approve_password_reset(int $requestId, int $adminId): string
{
    $pdo = database();
    $pdo->beginTransaction();

    try {
        $requestStmt = $pdo->prepare(
            'SELECT user_id FROM password_reset_requests WHERE id = :id AND status = \'pending\' FOR UPDATE'
        );
        $requestStmt->execute(['id' => $requestId]);
        $request = $requestStmt->fetch();

        if ($request === false) {
            throw new RuntimeException('Password reset request not found or already processed.');
        }

        $newPassword = bin2hex(random_bytes(4)); // 8-char hex password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateUserStmt = $pdo->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE id = :user_id'
        );
        $updateUserStmt->execute([
            'password_hash' => $newPasswordHash,
            'user_id' => (int) $request['user_id'],
        ]);

        $updateRequestStmt = $pdo->prepare(
            'UPDATE password_reset_requests
             SET status = \'approved\',
                 reviewed_by_admin_id = :admin_id,
                 reviewed_at = NOW(),
                 new_password_snapshot = :new_password
             WHERE id = :id'
        );
        $updateRequestStmt->execute([
            'admin_id' => $adminId,
            'new_password' => 'Approved. New password: ' . $newPassword,
            'id' => $requestId,
        ]);

        $pdo->commit();

        return $newPassword;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function deny_password_reset(int $requestId, int $adminId): void
{
    $pdo = database();
    $requestStmt = $pdo->prepare(
        'SELECT id FROM password_reset_requests WHERE id = :id AND status = \'pending\''
    );
    $requestStmt->execute(['id' => $requestId]);

    if ($requestStmt->fetch() === false) {
        throw new RuntimeException('Password reset request not found or already processed.');
    }

    $updateRequestStmt = $pdo->prepare(
        'UPDATE password_reset_requests
         SET status = \'denied\',
             reviewed_by_admin_id = :admin_id,
             reviewed_at = NOW()
         WHERE id = :id'
    );
    $updateRequestStmt->execute([
        'admin_id' => $adminId,
        'id' => $requestId,
    ]);
}