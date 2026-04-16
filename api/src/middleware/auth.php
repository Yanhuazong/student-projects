<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/memberships.php';
require_once dirname(__DIR__) . '/utils/response.php';

function app_secret()
{
    return env_value('APP_SECRET', 'student-projects-secret');
}

function get_bearer_token()
{
    $header = '';

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $header = $headers['Authorization'];
        }
    }

    if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return trim($matches[1]);
    }

    // Fallback for cross-origin multipart uploads where Authorization headers
    // can trigger preflight failures on constrained hosts.
    if (isset($_POST['auth_token'])) {
        $token = trim((string) $_POST['auth_token']);
        if ($token !== '') {
            return $token;
        }
    }

    if (isset($_GET['auth_token'])) {
        $token = trim((string) $_GET['auth_token']);
        if ($token !== '') {
            return $token;
        }
    }

    return null;
}

function base64url_encode_value($value)
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64url_decode_value($value)
{
    $padding = strlen($value) % 4;

    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'));
}

function issue_auth_token($user)
{
    $payload = array(
        'user_id' => (int) $user['id'],
        'class_id' => isset($user['class_id']) && $user['class_id'] !== null ? (int) $user['class_id'] : null,
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'exp' => time() + (7 * 24 * 60 * 60),
    );

    $encodedPayload = base64url_encode_value(json_encode($payload));
    $signature = hash_hmac('sha256', $encodedPayload, app_secret());

    return $encodedPayload . '.' . $signature;
}

function decode_auth_token($token)
{
    if (!$token || strpos($token, '.') === false) {
        return null;
    }

    list($encodedPayload, $signature) = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $encodedPayload, app_secret());

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $decodedPayload = json_decode(base64url_decode_value($encodedPayload), true);

    if (!is_array($decodedPayload) || !isset($decodedPayload['exp']) || $decodedPayload['exp'] < time()) {
        return null;
    }

    return $decodedPayload;
}

function current_user()
{
    static $cachedUser = false;

    if ($cachedUser !== false) {
        return $cachedUser;
    }

    $token = get_bearer_token();
    $payload = decode_auth_token($token);

    if (!$payload) {
        $cachedUser = null;
        return null;
    }

    $pdo = get_pdo();
    $statement = $pdo->prepare('SELECT id, class_id, name, email, role, is_active FROM users WHERE id = ? LIMIT 1');
    $statement->execute(array((int) $payload['user_id']));
    $user = $statement->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        $cachedUser = null;
        return null;
    }

    $tokenRole = isset($payload['role']) ? strtolower(trim((string) $payload['role'])) : '';
    $tokenClassId = isset($payload['class_id']) && $payload['class_id'] !== null ? (int) $payload['class_id'] : null;

    if ($tokenRole !== 'admin') {
        if ($tokenClassId === null || $tokenClassId <= 0) {
            $cachedUser = null;
            return null;
        }

        $membership = resolve_membership_for_class($pdo, $user, $tokenClassId);

        if (!$membership || (int) $membership['is_active'] !== 1) {
            $cachedUser = null;
            return null;
        }
    }

    // Use class-aware role and class context from the signed token.
    $user['role'] = $tokenRole !== '' ? $tokenRole : $user['role'];
    $user['class_id'] = $tokenClassId;

    $cachedUser = $user;
    return $cachedUser;
}

function require_auth($allowedRoles)
{
    $user = current_user();

    if (!$user) {
        json_response(array('error' => 'Authentication required.'), 401);
    }

    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles, true)) {
        json_response(array('error' => 'You do not have permission to access this resource.'), 403);
    }

    return $user;
}
