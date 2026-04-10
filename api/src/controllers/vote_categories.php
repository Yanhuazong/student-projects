<?php

require_once dirname(__DIR__) . '/config/database.php';

function default_vote_categories()
{
    return array(
        array('name' => 'Best Overall', 'slug' => 'best-overall', 'icon' => 'trophy', 'display_order' => 1, 'is_primary' => 1),
        array('name' => 'Most Creative', 'slug' => 'most-creative', 'icon' => 'palette', 'display_order' => 2, 'is_primary' => 0),
        array('name' => 'Best Technical Execution', 'slug' => 'best-technical-execution', 'icon' => 'gear', 'display_order' => 3, 'is_primary' => 0),
        array('name' => 'Audience Choice', 'slug' => 'audience-choice', 'icon' => 'spark', 'display_order' => 4, 'is_primary' => 0),
    );
}

function normalize_vote_category_row($row)
{
    return array(
        'id' => (int) $row['id'],
        'class_id' => (int) $row['class_id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
        'icon' => $row['icon'],
        'display_order' => (int) $row['display_order'],
        'is_primary' => (int) $row['is_primary'],
        'is_active' => (int) $row['is_active'],
    );
}

function ensure_vote_categories($pdo, $classId)
{
    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM vote_categories WHERE class_id = ?');
    $countStatement->execute(array((int) $classId));
    $count = (int) $countStatement->fetchColumn();

    if ($count > 0) {
        return;
    }

    $insertStatement = $pdo->prepare(
        'INSERT INTO vote_categories (class_id, name, slug, icon, display_order, is_primary, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    foreach (default_vote_categories() as $category) {
        $insertStatement->execute(
            array(
                (int) $classId,
                $category['name'],
                $category['slug'],
                $category['icon'],
                (int) $category['display_order'],
                (int) $category['is_primary'],
            )
        );
    }
}

function get_vote_categories_for_class($pdo, $classId, $includeInactive = false)
{
    ensure_vote_categories($pdo, $classId);

    $whereClause = $includeInactive ? '' : ' AND is_active = 1';

    $statement = $pdo->prepare(
        'SELECT id, class_id, name, slug, icon, display_order, is_primary, is_active
         FROM vote_categories
         WHERE class_id = ?' . $whereClause . '
         ORDER BY is_primary DESC, display_order ASC, id ASC'
    );
    $statement->execute(array((int) $classId));
    $rows = $statement->fetchAll();

    foreach ($rows as &$row) {
        $row = normalize_vote_category_row($row);
    }

    return $rows;
}
