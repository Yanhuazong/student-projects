<?php

require_once dirname(__DIR__) . '/config/database.php';

function normalize_membership_role($value)
{
    $role = strtolower(trim((string) $value));

    if ($role !== 'manager') {
        return 'user';
    }

    return 'manager';
}

function get_user_class_membership($pdo, $userId, $classId)
{
    $statement = $pdo->prepare(
        'SELECT user_id, class_id, class_role, is_active
         FROM user_class_memberships
         WHERE user_id = ? AND class_id = ?
         LIMIT 1'
    );
    $statement->execute(array((int) $userId, (int) $classId));
    $membership = $statement->fetch();

    return $membership ?: null;
}

function upsert_user_class_membership($pdo, $userId, $classId, $classRole, $isActive = 1)
{
    $statement = $pdo->prepare(
        'INSERT INTO user_class_memberships (user_id, class_id, class_role, is_active)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE class_role = VALUES(class_role), is_active = VALUES(is_active)'
    );
    $statement->execute(
        array(
            (int) $userId,
            (int) $classId,
            normalize_membership_role($classRole),
            (int) $isActive === 1 ? 1 : 0,
        )
    );
}

function ensure_legacy_class_membership($pdo, $user)
{
    if (!isset($user['id']) || !isset($user['class_id']) || $user['class_id'] === null) {
        return null;
    }

    if (!isset($user['role']) || ($user['role'] !== 'manager' && $user['role'] !== 'user')) {
        return null;
    }

    upsert_user_class_membership($pdo, (int) $user['id'], (int) $user['class_id'], $user['role'], (int) $user['is_active'] === 1 ? 1 : 0);

    return get_user_class_membership($pdo, (int) $user['id'], (int) $user['class_id']);
}

function resolve_membership_for_class($pdo, $user, $classId)
{
    if (!$user || !isset($user['id'])) {
        return null;
    }

    if (isset($user['role']) && $user['role'] === 'admin') {
        return array(
            'user_id' => (int) $user['id'],
            'class_id' => (int) $classId,
            'class_role' => 'admin',
            'is_active' => 1,
        );
    }

    $membership = get_user_class_membership($pdo, (int) $user['id'], (int) $classId);

    if ($membership && (int) $membership['is_active'] === 1) {
        return $membership;
    }

    if (
        isset($user['class_id'])
        && $user['class_id'] !== null
        && (int) $user['class_id'] === (int) $classId
        && isset($user['role'])
        && ($user['role'] === 'manager' || $user['role'] === 'user')
    ) {
        $legacyMembership = ensure_legacy_class_membership($pdo, $user);

        if ($legacyMembership && (int) $legacyMembership['is_active'] === 1) {
            return $legacyMembership;
        }
    }

    return null;
}

function resolve_effective_role_for_class($pdo, $user, $classId)
{
    if (!$user || !isset($user['id'])) {
        return null;
    }

    if (isset($user['role']) && $user['role'] === 'admin') {
        return 'admin';
    }

    $membership = resolve_membership_for_class($pdo, $user, $classId);

    if (!$membership || (int) $membership['is_active'] !== 1) {
        return null;
    }

    return normalize_membership_role($membership['class_role']);
}

function get_user_memberships($pdo, $userId)
{
    $statement = $pdo->prepare(
        'SELECT m.class_id, c.slug AS class_slug, c.name AS class_name, m.class_role, m.is_active
         FROM user_class_memberships m
         INNER JOIN classes c ON c.id = m.class_id
         WHERE m.user_id = ?
         ORDER BY c.display_order ASC, c.id ASC'
    );
    $statement->execute(array((int) $userId));

    $rows = $statement->fetchAll();
    $memberships = array();

    foreach ($rows as $row) {
        $memberships[] = array(
            'class_id' => (int) $row['class_id'],
            'class_slug' => $row['class_slug'],
            'class_name' => $row['class_name'],
            'class_role' => normalize_membership_role($row['class_role']),
            'is_active' => (int) $row['is_active'],
        );
    }

    return $memberships;
}
