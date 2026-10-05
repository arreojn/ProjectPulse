<?php

declare(strict_types=1);

function auth_table_exists(string $tableName): bool
{
    if (!function_exists('database') || !defined('DB_NAME')) {
        return false;
    }

    $statement = database()->prepare(
        'SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = :table_schema
           AND TABLE_NAME = :table_name'
    );
    $statement->execute([
        'table_schema' => DB_NAME,
        'table_name' => $tableName,
    ]);
    $row = $statement->fetch();

    return (int) ($row['total'] ?? 0) > 0;
}

function auth_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function auth_role_column_type(): ?string
{
    if (!auth_table_exists('users')) {
        return null;
    }

    $statement = database()->prepare(
        'SELECT COLUMN_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :table_schema
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name
         LIMIT 1'
    );
    $statement->execute([
        'table_schema' => DB_NAME,
        'table_name' => 'users',
        'column_name' => 'role',
    ]);
    $row = $statement->fetch();

    return isset($row['COLUMN_TYPE']) ? (string) $row['COLUMN_TYPE'] : null;
}

function auth_enum_values_from_column_type(?string $columnType): array
{
    $columnType = strtolower(trim((string) $columnType));

    if ($columnType === '' || !str_starts_with($columnType, 'enum(')) {
        return [];
    }

    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $columnType, $matches);
    $values = [];

    foreach ($matches[1] ?? [] as $value) {
        $values[] = stripcslashes((string) $value);
    }

    return $values;
}

function auth_ensure_user_role(string $role): void
{
    $role = trim($role);

    if ($role === '' || !auth_table_exists('users')) {
        return;
    }

    $existingValues = auth_enum_values_from_column_type(auth_role_column_type());

    if ($existingValues === [] || in_array($role, $existingValues, true)) {
        return;
    }

    $existingValues[] = $role;
    $enumSql = implode(', ', array_map(
        static fn (string $value): string => "'" . str_replace("'", "\\'", $value) . "'",
        $existingValues
    ));

    database()->exec(
        'ALTER TABLE ' . auth_quote_identifier('users') . ' MODIFY ' . auth_quote_identifier('role') .
        ' ENUM(' . $enumSql . ') NOT NULL'
    );
}

function auth_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    if (!auth_table_exists('users')) {
        $bootstrapped = true;
        return;
    }

    auth_ensure_column('users', 'first_name', 'VARCHAR(100) NULL AFTER email');
    auth_ensure_column('users', 'middle_name', 'VARCHAR(100) NULL AFTER first_name');
    auth_ensure_column('users', 'last_name', 'VARCHAR(100) NULL AFTER middle_name');
    auth_ensure_column('users', 'must_change_password', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
    auth_ensure_column('users', 'auth_version', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER must_change_password');

    database()->exec(
        'CREATE TABLE IF NOT EXISTS auth_login_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            identity_value VARCHAR(120) NOT NULL,
            username_snapshot VARCHAR(50) NULL,
            full_name_snapshot VARCHAR(255) NULL,
            role_snapshot VARCHAR(50) NULL,
            login_status ENUM(\'success\', \'failed\') NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            logged_in_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_auth_login_logs_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE SET NULL
        )'
    );

    $bootstrapped = true;
}

function auth_ensure_column(string $tableName, string $columnName, string $definition): void
{
    if (!auth_table_exists($tableName)) {
        return;
    }

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

function auth_full_name(array $user): string
{
    $parts = array_filter([
        trim((string) ($user['first_name'] ?? '')),
        trim((string) ($user['middle_name'] ?? '')),
        trim((string) ($user['last_name'] ?? '')),
    ], static fn ($value): bool => $value !== '');

    return $parts === [] ? trim((string) ($user['username'] ?? '')) : implode(' ', $parts);
}

function auth_login_attempts_bootstrap(): void
{
    auth_bootstrap();

    database()->exec(
        'CREATE TABLE IF NOT EXISTS auth_login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identity_key VARCHAR(120) NOT NULL,
            user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_auth_attempts_identity (identity_key, attempted_at),
            INDEX idx_auth_attempts_ip (ip_address, attempted_at),
            CONSTRAINT fk_auth_login_attempts_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE SET NULL
        )'
    );
}

function auth_login_attempt_limit(): int
{
    return 5;
}

function auth_login_attempt_window_seconds(): int
{
    return 900;
}

function auth_is_login_locked_out(string $identity, ?string $ipAddress = null): bool
{
    $identity = strtolower(trim($identity));
    if ($identity === '') {
        return false;
    }

    auth_login_attempts_bootstrap();

    $ipAddress = trim((string) ($ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '')));

    $statement = database()->prepare(
        'SELECT COUNT(*) AS total
         FROM auth_login_attempts
         WHERE success = 0
           AND attempted_at >= DATE_SUB(NOW(), INTERVAL :window_seconds SECOND)
           AND (identity_key = :identity_key OR ip_address = :ip_address)'
    );
    $statement->execute([
        'window_seconds' => auth_login_attempt_window_seconds(),
        'identity_key' => $identity,
        'ip_address' => $ipAddress === '' ? null : $ipAddress,
    ]);
    $row = $statement->fetch();

    return (int) ($row['total'] ?? 0) >= auth_login_attempt_limit();
}

function auth_log_login_attempt(string $identity, ?array $user, bool $isSuccess): void
{
    auth_bootstrap();
    auth_login_attempts_bootstrap();

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
        ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
        : null;

    $statement = database()->prepare(
        'INSERT INTO auth_login_logs (
            user_id,
            identity_value,
            username_snapshot,
            full_name_snapshot,
            role_snapshot,
            login_status,
            ip_address,
            user_agent,
            logged_in_at
         ) VALUES (
            :user_id,
            :identity_value,
            :username_snapshot,
            :full_name_snapshot,
            :role_snapshot,
            :login_status,
            :ip_address,
            :user_agent,
            :logged_in_at
         )'
    );
    $statement->execute([
        'user_id' => $user !== null ? (int) $user['id'] : null,
        'identity_value' => $identity,
        'username_snapshot' => $user['username'] ?? null,
        'full_name_snapshot' => $user !== null ? auth_full_name($user) : null,
        'role_snapshot' => $user['role'] ?? null,
        'login_status' => $isSuccess ? 'success' : 'failed',
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'logged_in_at' => date('Y-m-d H:i:s'),
    ]);

    $attemptStatement = database()->prepare(
        'INSERT INTO auth_login_attempts (
            identity_key,
            user_id,
            ip_address,
            user_agent,
            success,
            attempted_at
         ) VALUES (
            :identity_key,
            :user_id,
            :ip_address,
            :user_agent,
            :success,
            :attempted_at
         )'
    );
    $attemptStatement->execute([
        'identity_key' => strtolower(trim($identity)),
        'user_id' => $user !== null ? (int) $user['id'] : null,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'success' => $isSuccess ? 1 : 0,
        'attempted_at' => date('Y-m-d H:i:s'),
    ]);
}

function auth_recent_login_logs(int $limit = 20): array
{
    auth_bootstrap();

    $statement = database()->prepare(
        'SELECT
            id,
            identity_value,
            username_snapshot,
            full_name_snapshot,
            role_snapshot,
            login_status,
            ip_address,
            user_agent,
            logged_in_at
         FROM auth_login_logs
         ORDER BY logged_in_at DESC, id DESC
         LIMIT :limit_value'
    );
    $statement->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function auth_find_user_by_identity(string $identity): ?array
{
    $identity = trim($identity);

    if ($identity === '') {
        return null;
    }

    $statement = database()->prepare(
        'SELECT id, username, email, first_name, middle_name, last_name, password_hash, role, is_active
         FROM users
         WHERE (username = :identity OR email = :identity)
         LIMIT 1'
    );
    $statement->execute(['identity' => $identity]);
    $user = $statement->fetch();

    return is_array($user) ? $user : null;
}

function attempt_login(string $identity, string $password): bool
{
    auth_bootstrap();
    $identity = trim($identity);

    if ($identity === '' || $password === '') {
        return false;
    }

    if (auth_is_login_locked_out($identity, $_SERVER['REMOTE_ADDR'] ?? null)) {
        auth_log_login_attempt($identity, null, false);
        return false;
    }

    $statement = database()->prepare(
        'SELECT id, username, email, first_name, middle_name, last_name, password_hash, role, must_change_password, auth_version
         FROM users
         WHERE is_active = 1
           AND (username = :identity OR email = :identity)
         LIMIT 1'
    );
    $statement->execute(['identity' => $identity]);
    $user = $statement->fetch();

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        auth_log_login_attempt($identity, $user === false ? null : $user, false);
        return false;
    }

    start_session();
    session_regenerate_id(true);

    $_SESSION['auth_user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'middle_name' => $user['middle_name'],
        'last_name' => $user['last_name'],
        'role' => $user['role'],
        'must_change_password' => (int) ($user['must_change_password'] ?? 0),
        'auth_version' => (int) ($user['auth_version'] ?? 1),
    ];
    auth_log_login_attempt($identity, $user, true);

    return true;
}

function auth_change_password(int $userId, string $currentPassword, string $newPassword): void
{
    auth_bootstrap();

    if ($userId <= 0) {
        throw new RuntimeException('Your login session is invalid. Please sign in again.');
    }

    if (strlen($newPassword) < 12) {
        throw new RuntimeException('New password must be at least 12 characters long.');
    }

    $statement = database()->prepare(
        'SELECT id, password_hash
         FROM users
         WHERE id = :id
           AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    if ($user === false || !password_verify($currentPassword, (string) $user['password_hash'])) {
        throw new RuntimeException('Current password is incorrect.');
    }

    if (password_verify($newPassword, (string) $user['password_hash'])) {
        throw new RuntimeException('Choose a new password that is different from your current password.');
    }

    $updateStatement = database()->prepare(
        'UPDATE users
         SET password_hash = :password_hash,
             must_change_password = 0,
             auth_version = auth_version + 1
         WHERE id = :id'
    );
    $updateStatement->execute([
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    if (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
        $_SESSION['auth_user']['must_change_password'] = 0;
        $_SESSION['auth_user']['auth_version'] = (int) ($_SESSION['auth_user']['auth_version'] ?? 1) + 1;
    }
}

function current_user(): ?array
{
    auth_bootstrap();
    start_session();

    if (!isset($_SESSION['auth_user']['id']) || !is_numeric($_SESSION['auth_user']['id'])) {
        return null;
    }

    $statement = database()->prepare(
        'SELECT id, username, email, first_name, middle_name, last_name, role, is_active, must_change_password, auth_version
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => (int) $_SESSION['auth_user']['id']]);
    $user = $statement->fetch();

    if (
        $user === false
        || (int) $user['is_active'] !== 1
        || (int) ($user['auth_version'] ?? 1) !== (int) ($_SESSION['auth_user']['auth_version'] ?? 1)
    ) {
        logout_user();
        return null;
    }

    $_SESSION['auth_user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'middle_name' => $user['middle_name'],
        'last_name' => $user['last_name'],
        'role' => $user['role'],
        'must_change_password' => (int) $user['must_change_password'],
        'auth_version' => (int) $user['auth_version'],
    ];

    return $_SESSION['auth_user'];
}

function dashboard_path_for_role(?string $role): string
{
    return match ($role) {
        'admin' => 'admin.php',
        'attendance' => 'attendance.php',
        'health' => 'health.php',
        'guidance' => 'guidance.php',
        'teacher' => 'teacher.php',
        'parent' => 'parent.php',
        default => 'attendance.php',
    };
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        redirect('index.php');
    }

    $currentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ((int) ($user['must_change_password'] ?? 0) === 1 && $currentScript !== 'change_password.php') {
        redirect('change_password.php');
    }

    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        redirect(dashboard_path_for_role($user['role'] ?? null));
    }

    return $user;
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => APP_BASE_PATH === '' ? '/' : APP_BASE_PATH,
                'domain' => $params['domain'] ?? '',
                'secure' => projectpulse_detect_scheme() === 'https',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    session_destroy();
}
