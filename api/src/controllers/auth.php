<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/classes.php';
require_once dirname(__DIR__) . '/controllers/memberships.php';
require_once dirname(__DIR__) . '/controllers/settings.php';
require_once dirname(__DIR__) . '/middleware/auth.php';
require_once dirname(__DIR__) . '/utils/response.php';

function normalize_auth_user_response($user, $memberships = array())
{
    return array(
        'id' => (int) $user['id'],
        'class_id' => $user['class_id'] !== null ? (int) $user['class_id'] : null,
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'memberships' => $memberships,
    );
}

function mail_enabled()
{
    $value = strtolower(trim((string) env_value('MAIL_ENABLED', '1')));

    return !in_array($value, array('0', 'false', 'off', 'no'), true);
}

function reset_password_base_url()
{
    $configured = trim((string) env_value('RESET_PASSWORD_BASE_URL', ''));

    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $appUrl = trim((string) env_value('APP_URL', ''));

    if ($appUrl !== '') {
        return rtrim($appUrl, '/');
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '' ? $_SERVER['HTTP_HOST'] : 'localhost';

    return $scheme . '://' . $host;
}

function build_password_reset_link($classSlug, $token)
{
    $baseUrl = reset_password_base_url();
    $safeClassSlug = rawurlencode((string) $classSlug);
    $safeToken = rawurlencode((string) $token);

    return $baseUrl . '/' . $safeClassSlug . '/forgot-password?token=' . $safeToken;
}

function send_password_reset_email($email, $name, $class, $resetLink)
{
    if (!mail_enabled()) {
        return false;
    }

    $subject = 'Password reset request';
    $recipientName = trim((string) $name) !== '' ? trim((string) $name) : 'there';
    $className = isset($class['name']) ? $class['name'] : 'your class';
    $expiresInMinutes = 30;
    $fromAddress = trim((string) env_value('MAIL_FROM', 'no-reply@studentprojects.local'));
    $fromName = trim((string) env_value('MAIL_FROM_NAME', 'Student Projects'));

    $message = "Hi {$recipientName},\n\n";
    $message .= "We received a password reset request for {$className}.\n\n";
    $message .= "Use this link to reset your password:\n{$resetLink}\n\n";
    $message .= "This link expires in {$expiresInMinutes} minutes. If you did not request this, you can ignore this email.\n";

    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromAddress . '>',
        'Reply-To: ' . $fromAddress,
    );

    return @mail($email, $subject, $message, implode("\r\n", $headers));
}

function auth_bootstrap_status()
{
    $pdo = get_pdo();
    $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

    json_response(array('needsBootstrap' => $count === 0), 200);
}

function auth_bootstrap_admin()
{
    $pdo = get_pdo();
    $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

    if ($count > 0) {
        json_response(array('error' => 'Initial admin has already been created.'), 409);
    }

    $input = read_json_body();
    require_fields($input, array('name', 'email', 'password'));

    $statement = $pdo->prepare('INSERT INTO users (class_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
    $statement->execute(
        array(
            null,
            trim($input['name']),
            strtolower(trim($input['email'])),
            password_hash($input['password'], PASSWORD_DEFAULT),
            'admin',
        )
    );

    $userId = (int) $pdo->lastInsertId();
    $statement = $pdo->prepare('SELECT id, class_id, name, email, role FROM users WHERE id = ? LIMIT 1');
    $statement->execute(array($userId));
    $user = $statement->fetch();

    $memberships = get_user_memberships($pdo, $userId);

    json_response(
        array(
            'token' => issue_auth_token($user),
            'user' => normalize_auth_user_response($user, $memberships),
        ),
        201
    );
}

function auth_register()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $input = read_json_body();
    require_fields($input, array('name', 'email', 'password'));

    $name = trim($input['name']);
    $email = strtolower(trim($input['email']));
    $password = (string) $input['password'];
    $requestedRole = isset($input['role']) ? strtolower(trim((string) $input['role'])) : 'user';
    $registrationRole = $requestedRole === 'manager' ? 'manager' : 'user';
    $managerInviteCode = trim((string) (isset($input['manager_invite_code']) ? $input['manager_invite_code'] : ''));

    if ($name === '' || $email === '' || $password === '') {
        json_response(array('error' => 'Name, email, and password are required.'), 422);
    }

    if (strlen($password) < 8) {
        json_response(array('error' => 'Password must be at least 8 characters long.'), 422);
    }

    if ($registrationRole === 'manager') {
        $expectedManagerCode = trim((string) fetch_class_setting_value($pdo, (int) $class['id'], 'manager_registration_code', ''));

        if ($expectedManagerCode === '') {
            json_response(array('error' => 'Manager registration is not enabled for this class.'), 403);
        }

        if (!hash_equals($expectedManagerCode, $managerInviteCode)) {
            json_response(array('error' => 'Manager invite code is invalid.'), 403);
        }
    }

    $userStatement = $pdo->prepare('SELECT id, class_id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
    $userStatement->execute(array($email));
    $existingUser = $userStatement->fetch();

    if ($existingUser) {
        if ((int) $existingUser['is_active'] !== 1) {
            json_response(array('error' => 'This account is inactive. Please contact an administrator.'), 403);
        }

        if (!password_verify($password, $existingUser['password_hash'])) {
            json_response(array('error' => 'An account with this email already exists. Use your existing password to add class access.'), 409);
        }

        if (trim((string) $existingUser['name']) === '' && $name !== '') {
            $updateNameStatement = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
            $updateNameStatement->execute(array($name, (int) $existingUser['id']));
            $existingUser['name'] = $name;
        }

        if ($registrationRole === 'manager' && $existingUser['role'] !== 'admin' && $existingUser['role'] !== 'manager') {
            $promoteUserStatement = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
            $promoteUserStatement->execute(array('manager', (int) $existingUser['id']));
            $existingUser['role'] = 'manager';
        }

        if ($existingUser['role'] !== 'admin') {
            $existingMembership = get_user_class_membership($pdo, (int) $existingUser['id'], (int) $class['id']);
            $membershipRole = $registrationRole === 'manager'
                ? 'manager'
                : ($existingMembership ? $existingMembership['class_role'] : 'user');
            upsert_user_class_membership($pdo, (int) $existingUser['id'], (int) $class['id'], $membershipRole, 1);
        }

        $effectiveRole = resolve_effective_role_for_class($pdo, $existingUser, (int) $class['id']);

        if ($effectiveRole === null) {
            json_response(array('error' => 'Unable to grant access for this class.'), 422);
        }

        $authUser = array(
            'id' => (int) $existingUser['id'],
            'class_id' => (int) $class['id'],
            'name' => $existingUser['name'],
            'email' => $existingUser['email'],
            'role' => $effectiveRole,
        );
        $memberships = get_user_memberships($pdo, (int) $existingUser['id']);

        json_response(
            array(
                'token' => issue_auth_token($authUser),
                'user' => normalize_auth_user_response($authUser, $memberships),
            ),
            200
        );
    }

    $insertStatement = $pdo->prepare('INSERT INTO users (class_id, name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $insertStatement->execute(
        array(
            (int) $class['id'],
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $registrationRole,
        )
    );

    $userId = (int) $pdo->lastInsertId();
    upsert_user_class_membership($pdo, $userId, (int) $class['id'], $registrationRole, 1);

    $authUser = array(
        'id' => $userId,
        'class_id' => (int) $class['id'],
        'name' => $name,
        'email' => $email,
        'role' => $registrationRole,
    );
    $memberships = get_user_memberships($pdo, $userId);

    json_response(
        array(
            'token' => issue_auth_token($authUser),
            'user' => normalize_auth_user_response($authUser, $memberships),
        ),
        201
    );
}

function auth_login()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $input = read_json_body();
    require_fields($input, array('email', 'password'));

    $statement = $pdo->prepare('SELECT id, class_id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
    $statement->execute(array(strtolower(trim($input['email']))));
    $user = $statement->fetch();

    if (!$user || (int) $user['is_active'] !== 1 || !password_verify($input['password'], $user['password_hash'])) {
        json_response(array('error' => 'Invalid email or password.'), 401);
    }

    $effectiveRole = resolve_effective_role_for_class($pdo, $user, (int) $class['id']);

    if ($effectiveRole === null) {
        json_response(array('error' => 'This account is not assigned to the selected class.'), 403);
    }

    $authUser = array(
        'id' => (int) $user['id'],
        'class_id' => (int) $class['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $effectiveRole,
    );
    $memberships = get_user_memberships($pdo, (int) $user['id']);

    json_response(
        array(
            'token' => issue_auth_token($authUser),
            'user' => normalize_auth_user_response($authUser, $memberships),
        ),
        200
    );
}

function auth_change_password()
{
    $user = require_auth(array('admin', 'manager', 'user'));
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('current_password', 'new_password'));

    $currentPassword = (string) $input['current_password'];
    $newPassword = (string) $input['new_password'];

    if (strlen($newPassword) < 8) {
        json_response(array('error' => 'New password must be at least 8 characters long.'), 422);
    }

    if ($currentPassword === $newPassword) {
        json_response(array('error' => 'New password must be different from your current password.'), 422);
    }

    $statement = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $statement->execute(array((int) $user['id']));
    $passwordHash = $statement->fetchColumn();

    if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
        json_response(array('error' => 'Current password is incorrect.'), 401);
    }

    $updateStatement = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $updateStatement->execute(array(password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']));

    json_response(array('message' => 'Password updated successfully.'), 200);
}

function auth_forgot_password()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $input = read_json_body();
    require_fields($input, array('email'));

    $email = strtolower(trim((string) $input['email']));

    if ($email === '') {
        json_response(array('error' => 'Email is required.'), 422);
    }

    // Always return a generic message to avoid leaking whether an account exists.
    $genericResponse = array(
        'message' => 'If an account exists for this email, a password reset token has been created.',
    );

    $statement = $pdo->prepare('SELECT id, class_id, name, email, role, is_active FROM users WHERE email = ? LIMIT 1');
    $statement->execute(array($email));
    $user = $statement->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        json_response($genericResponse, 200);
    }

    $effectiveRole = resolve_effective_role_for_class($pdo, $user, (int) $class['id']);

    if ($effectiveRole === null) {
        json_response($genericResponse, 200);
    }

    if (function_exists('random_bytes')) {
        $rawToken = bin2hex(random_bytes(24));
    } else {
        $rawToken = hash('sha256', uniqid(mt_rand(), true) . microtime(true));
    }
    $tokenHash = hash('sha256', $rawToken);
    $resetLink = build_password_reset_link($class['slug'], $rawToken);

    $expireStatement = $pdo->prepare(
        'UPDATE password_reset_tokens
         SET used_at = NOW()
         WHERE user_id = ? AND class_id = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $expireStatement->execute(array((int) $user['id'], (int) $class['id']));

    $insertStatement = $pdo->prepare(
        'INSERT INTO password_reset_tokens (user_id, class_id, token_hash, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
    );
    $insertStatement->execute(array((int) $user['id'], (int) $class['id'], $tokenHash));

    $mailSent = send_password_reset_email($user['email'], $user['name'], $class, $resetLink);

    if (!$mailSent) {
        error_log('Password reset email not sent for user_id=' . (int) $user['id']);
    }

    if (env_value('APP_ENV', 'development') === 'development') {
        $genericResponse['reset_token'] = $rawToken;
        $genericResponse['reset_link'] = $resetLink;
        $genericResponse['expires_in_minutes'] = 30;
    }

    json_response($genericResponse, 200);
}

function auth_reset_password()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $input = read_json_body();
    require_fields($input, array('new_password'));

    $token = trim((string) $input['token']);
    $token = preg_replace('/\s+/', '', $token);
    $email = strtolower(trim((string) (isset($input['email']) ? $input['email'] : '')));
    $resetCode = trim((string) (isset($input['reset_code']) ? $input['reset_code'] : ''));
    $newPassword = (string) $input['new_password'];

    if (strlen($newPassword) < 8) {
        json_response(array('error' => 'New password must be at least 8 characters long.'), 422);
    }

    if ($resetCode !== '') {
        $expectedResetCode = trim((string) fetch_class_setting_value($pdo, (int) $class['id'], 'password_reset_code', ''));

        if ($expectedResetCode === '') {
            json_response(array('error' => 'Class reset code is not enabled for this class.'), 403);
        }

        if (!hash_equals($expectedResetCode, $resetCode)) {
            json_response(array('error' => 'Class reset code is invalid.'), 403);
        }

        if ($email === '') {
            json_response(array('error' => 'Email is required when using class reset code.'), 422);
        }

        $userStatement = $pdo->prepare('SELECT id, class_id, name, email, role, is_active FROM users WHERE email = ? LIMIT 1');
        $userStatement->execute(array($email));
        $user = $userStatement->fetch();

        if (!$user || (int) $user['is_active'] !== 1) {
            json_response(array('error' => 'Account not available for password reset.'), 403);
        }

        $effectiveRole = resolve_effective_role_for_class($pdo, $user, (int) $class['id']);

        if ($effectiveRole === null) {
            json_response(array('error' => 'This account is not assigned to the selected class.'), 403);
        }

        $updateUserStatement = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updateUserStatement->execute(array(password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']));

        $expireTokensStatement = $pdo->prepare(
            'UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE user_id = ? AND class_id = ? AND used_at IS NULL'
        );
        $expireTokensStatement->execute(array((int) $user['id'], (int) $class['id']));

        json_response(array('message' => 'Password has been reset. You can now sign in.'), 200);
    }

    if ($token === '') {
        json_response(array('error' => 'Reset token is required when class reset code is not provided.'), 422);
    }

    if (!ctype_xdigit($token)) {
        json_response(array('error' => 'Reset token format is invalid.'), 422);
    }

    $tokenHash = hash('sha256', $token);

    $tokenStatement = $pdo->prepare(
        'SELECT id, user_id
         FROM password_reset_tokens
         WHERE class_id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC
         LIMIT 1'
    );
    $tokenStatement->execute(array((int) $class['id'], $tokenHash));
    $tokenRow = $tokenStatement->fetch();

    if (!$tokenRow) {
        $response = array('error' => 'Reset token is invalid or expired.');

        if (env_value('APP_ENV', 'development') === 'development') {
            $debugStatement = $pdo->prepare(
                'SELECT class_id, used_at, expires_at
                 FROM password_reset_tokens
                 WHERE token_hash = ?
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $debugStatement->execute(array($tokenHash));
            $debugRow = $debugStatement->fetch();

            if (!$debugRow) {
                $response['debug_reason'] = 'token_not_found';
            } elseif ((int) $debugRow['class_id'] !== (int) $class['id']) {
                $response['debug_reason'] = 'class_mismatch';
                $response['debug_class_id'] = (int) $debugRow['class_id'];
                $response['active_class_id'] = (int) $class['id'];
            } elseif (!empty($debugRow['used_at'])) {
                $response['debug_reason'] = 'token_already_used';
            } elseif (strtotime((string) $debugRow['expires_at']) <= time()) {
                $response['debug_reason'] = 'token_expired';
                $response['debug_expires_at'] = $debugRow['expires_at'];
            }
        }

        json_response($response, 400);
    }

    $userStatement = $pdo->prepare('SELECT id, is_active FROM users WHERE id = ? LIMIT 1');
    $userStatement->execute(array((int) $tokenRow['user_id']));
    $userRow = $userStatement->fetch();

    if (!$userRow || (int) $userRow['is_active'] !== 1) {
        json_response(array('error' => 'Account not available for password reset.'), 403);
    }

    $pdo->beginTransaction();

    try {
        $updateUserStatement = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updateUserStatement->execute(array(password_hash($newPassword, PASSWORD_DEFAULT), (int) $tokenRow['user_id']));

        $markUsedStatement = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
        $markUsedStatement->execute(array((int) $tokenRow['id']));

        $expireOthersStatement = $pdo->prepare(
            'UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE user_id = ? AND class_id = ? AND used_at IS NULL AND id <> ?'
        );
        $expireOthersStatement->execute(array((int) $tokenRow['user_id'], (int) $class['id'], (int) $tokenRow['id']));

        $pdo->commit();
    } catch (Exception $exception) {
        $pdo->rollBack();
        json_response(array('error' => 'Unable to reset password right now.'), 500);
    }

    json_response(array('message' => 'Password has been reset. You can now sign in.'), 200);
}

function auth_me()
{
    $user = require_auth(array('admin', 'manager', 'user'));
    $pdo = get_pdo();
    $memberships = get_user_memberships($pdo, (int) $user['id']);

    json_response(
        array(
            'user' => normalize_auth_user_response($user, $memberships),
        ),
        200
    );
}
