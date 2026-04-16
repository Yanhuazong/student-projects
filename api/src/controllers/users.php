<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/classes.php';
require_once dirname(__DIR__) . '/controllers/memberships.php';
require_once dirname(__DIR__) . '/middleware/auth.php';
require_once dirname(__DIR__) . '/utils/response.php';

function list_users()
{
    require_auth(array('admin'));
    $pdo = get_pdo();
    $class = resolve_active_class($pdo, array('allow_inactive' => true));
    $statement = $pdo->prepare(
           "SELECT DISTINCT u.id, u.class_id, u.name, u.email, u.role, u.is_active, u.created_at
            FROM users u
            LEFT JOIN user_class_memberships m ON m.user_id = u.id AND m.class_id = ? AND m.is_active = 1
            WHERE u.role = 'admin'
               OR m.user_id IS NOT NULL
               OR (u.role IN ('manager', 'user') AND u.class_id = ?)
            ORDER BY u.role DESC, u.name ASC"
    );
    $statement->execute(array($class['id'], $class['id']));
    $users = $statement->fetchAll();

    foreach ($users as &$user) {
        $user['id'] = (int) $user['id'];
        $user['class_id'] = $user['class_id'] !== null ? (int) $user['class_id'] : null;
        $user['is_active'] = (int) $user['is_active'];
    }

    json_response(array('class' => $class, 'users' => $users), 200);
}

function create_user()
{
    require_auth(array('admin'));
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('name', 'email', 'password', 'role'));

    $allowedRoles = array('admin', 'manager', 'user');
    $requestedRole = isset($input['role']) ? strtolower(trim($input['role'])) : 'manager';
    $role = in_array($requestedRole, $allowedRoles, true) ? $requestedRole : 'manager';
    $classId = null;

    if ($role === 'manager' || $role === 'user') {
        if (!empty($input['class_id'])) {
            $_GET['class_id'] = (int) $input['class_id'];
        }

        $class = resolve_active_class($pdo, array('allow_inactive' => true));
        $classId = (int) $class['id'];
    }

    $name = trim($input['name']);
    $email = strtolower(trim($input['email']));
    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
    $existingStatement = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
    $existingStatement->execute(array($email));
    $existingUser = $existingStatement->fetch();

    if ($existingUser) {
        $userId = (int) $existingUser['id'];

        if ($role === 'admin') {
            $promoteStatement = $pdo->prepare('UPDATE users SET role = ?, is_active = 1 WHERE id = ?');
            $promoteStatement->execute(array('admin', $userId));
        }
    } else {
        $statement = $pdo->prepare('INSERT INTO users (class_id, name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
        $statement->execute(
            array(
                $classId,
                $name,
                $email,
                $passwordHash,
                $role,
            )
        );
        $userId = (int) $pdo->lastInsertId();
    }

    if ($role === 'manager' || $role === 'user') {
        upsert_user_class_membership($pdo, $userId, (int) $classId, $role, 1);
    }

    json_response(array('message' => 'User created.'), 201);
}
