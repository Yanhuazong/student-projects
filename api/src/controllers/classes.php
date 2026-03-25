<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/utils/response.php';

function normalize_class_row($row)
{
    $row['id'] = (int) $row['id'];
    $row['is_active'] = (int) $row['is_active'];
    $row['display_order'] = (int) $row['display_order'];

    return $row;
}

function resolve_active_class($pdo, $options = array())
{
    $allowInactive = !empty($options['allow_inactive']);
    $activeClause = $allowInactive ? '' : ' AND is_active = 1';

    if (isset($_GET['class_id']) && (int) $_GET['class_id'] > 0) {
        $statement = $pdo->prepare(
            'SELECT id, name, slug, description, is_active, display_order
             FROM classes
             WHERE id = ?' . $activeClause . '
             LIMIT 1'
        );
        $statement->execute(array((int) $_GET['class_id']));
        $class = $statement->fetch();

        if (!$class) {
            json_response(array('error' => 'Class not found.'), 404);
        }

        return normalize_class_row($class);
    }

    if (isset($_GET['class_slug']) && trim($_GET['class_slug']) !== '') {
        $statement = $pdo->prepare(
            'SELECT id, name, slug, description, is_active, display_order
             FROM classes
             WHERE slug = ?' . $activeClause . '
             LIMIT 1'
        );
        $statement->execute(array(strtolower(trim($_GET['class_slug']))));
        $class = $statement->fetch();

        if (!$class) {
            json_response(array('error' => 'Class not found.'), 404);
        }

        return normalize_class_row($class);
    }

    $statement = $pdo->query(
        'SELECT id, name, slug, description, is_active, display_order
         FROM classes
         WHERE is_active = 1
         ORDER BY display_order ASC, id ASC
         LIMIT 1'
    );
    $class = $statement->fetch();

    if (!$class) {
        json_response(array('error' => 'No classes are configured.'), 500);
    }

    return normalize_class_row($class);
}

function list_classes()
{
    $pdo = get_pdo();
    $statement = $pdo->query(
        'SELECT id, name, slug, description, is_active, display_order
         FROM classes
         WHERE is_active = 1
         ORDER BY display_order ASC, id ASC'
    );
    $classes = $statement->fetchAll();

    foreach ($classes as &$class) {
        $class = normalize_class_row($class);
    }

    json_response(array('classes' => $classes), 200);
}
