<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/classes.php';
require_once dirname(__DIR__) . '/middleware/auth.php';
require_once dirname(__DIR__) . '/utils/response.php';

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

    json_response(
        array(
            'token' => issue_auth_token($user),
            'user' => $user,
        ),
        201
    );
}

function auth_login()
{
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('email', 'password'));

    $statement = $pdo->prepare('SELECT id, class_id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
    $statement->execute(array(strtolower(trim($input['email']))));
    $user = $statement->fetch();

    if (!$user || (int) $user['is_active'] !== 1 || !password_verify($input['password'], $user['password_hash'])) {
        json_response(array('error' => 'Invalid email or password.'), 401);
    }

    if ($user['role'] === 'manager' || $user['role'] === 'user') {
        $activeClass = resolve_active_class($pdo);

        if ((int) $user['class_id'] !== (int) $activeClass['id']) {
            json_response(array('error' => 'This manager account is not assigned to the selected class.'), 403);
        }
    }

    json_response(
        array(
            'token' => issue_auth_token($user),
            'user' => array(
                'id' => (int) $user['id'],
                'class_id' => $user['class_id'] !== null ? (int) $user['class_id'] : null,
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ),
        ),
        200
    );
}

function auth_me()
{
    $user = require_auth(array('admin', 'manager', 'user'));

    json_response(
        array(
            'user' => array(
                'id' => (int) $user['id'],
                'class_id' => $user['class_id'] !== null ? (int) $user['class_id'] : null,
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ),
        ),
        200
    );
}
