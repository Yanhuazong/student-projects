<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/classes.php';
require_once dirname(__DIR__) . '/middleware/auth.php';
require_once dirname(__DIR__) . '/utils/response.php';

function list_users()
{
    require_auth(array('admin'));
    $pdo = get_pdo();
    $class = resolve_active_class($pdo, array('allow_inactive' => true));
    $statement = $pdo->prepare(
           "SELECT id, class_id, name, email, role, is_active, created_at
            FROM users
            WHERE role = 'admin' OR (role IN ('manager', 'user') AND class_id = ?)
            ORDER BY role DESC, name ASC"
    );
    $statement->execute(array($class['id']));
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

    $statement = $pdo->prepare('INSERT INTO users (class_id, name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $statement->execute(
        array(
            $classId,
            trim($input['name']),
            strtolower(trim($input['email'])),
            password_hash($input['password'], PASSWORD_DEFAULT),
            $role,
        )
    );

    json_response(array('message' => 'User created.'), 201);
}
