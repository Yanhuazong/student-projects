<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/classes.php';
require_once dirname(__DIR__) . '/middleware/auth.php';
require_once dirname(__DIR__) . '/utils/response.php';

function normalize_semester_row($semester)
{
    $semester['id'] = (int) $semester['id'];
    $semester['class_id'] = (int) $semester['class_id'];
    $semester['is_current'] = (int) $semester['is_current'];

    return $semester;
}

function list_semesters()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $statement = $pdo->prepare(
        'SELECT s.id, s.class_id, s.name, s.slug, s.starts_on, s.ends_on, s.is_current, c.name AS class_name, c.slug AS class_slug
         FROM semesters s
         INNER JOIN classes c ON c.id = s.class_id
         WHERE s.class_id = ?
         ORDER BY s.is_current DESC, s.starts_on DESC, s.id DESC'
    );
    $statement->execute(array($class['id']));
    $semesters = $statement->fetchAll();

    foreach ($semesters as &$semester) {
        $semester = normalize_semester_row($semester);
    }

    json_response(array('class' => $class, 'semesters' => $semesters), 200);
}

function current_semester()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $statement = $pdo->prepare(
        'SELECT s.id, s.class_id, s.name, s.slug, s.starts_on, s.ends_on, s.is_current, c.name AS class_name, c.slug AS class_slug
         FROM semesters s
         INNER JOIN classes c ON c.id = s.class_id
         WHERE s.class_id = ? AND s.is_current = 1
         ORDER BY s.id DESC LIMIT 1'
    );
    $statement->execute(array($class['id']));
    $semester = $statement->fetch();

    if (!$semester) {
        json_response(array('class' => $class, 'semester' => null), 200);
    }

    $semester = normalize_semester_row($semester);

    json_response(array('class' => $class, 'semester' => $semester), 200);
}

function create_semester()
{
    require_auth(array('admin'));
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('name'));

    if (!empty($input['class_id'])) {
        $_GET['class_id'] = (int) $input['class_id'];
    }

    $class = resolve_active_class($pdo, array('allow_inactive' => true));

    $name = trim($input['name']);
    $slug = isset($input['slug']) && $input['slug'] !== '' ? normalize_slug($input['slug']) : normalize_slug($name);
    $isCurrent = !empty($input['is_current']) ? 1 : 0;

    if ($isCurrent === 1) {
        $resetStatement = $pdo->prepare('UPDATE semesters SET is_current = 0 WHERE class_id = ?');
        $resetStatement->execute(array($class['id']));
    }

    $statement = $pdo->prepare('INSERT INTO semesters (class_id, name, slug, starts_on, ends_on, is_current) VALUES (?, ?, ?, ?, ?, ?)');
    $statement->execute(
        array(
            $class['id'],
            $name,
            $slug,
            empty($input['starts_on']) ? null : $input['starts_on'],
            empty($input['ends_on']) ? null : $input['ends_on'],
            $isCurrent,
        )
    );

    json_response(array('message' => 'Semester created.'), 201);
}

function update_semester($semesterId)
{
    require_auth(array('admin'));
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('name'));

    $existingStatement = $pdo->prepare('SELECT class_id FROM semesters WHERE id = ? LIMIT 1');
    $existingStatement->execute(array((int) $semesterId));
    $existingSemester = $existingStatement->fetch();

    if (!$existingSemester) {
        json_response(array('error' => 'Semester not found.'), 404);
    }

    $classId = !empty($input['class_id']) ? (int) $input['class_id'] : (int) $existingSemester['class_id'];

    $isCurrent = !empty($input['is_current']) ? 1 : 0;

    if ($isCurrent === 1) {
        $resetStatement = $pdo->prepare('UPDATE semesters SET is_current = 0 WHERE class_id = ?');
        $resetStatement->execute(array($classId));
    }

    $statement = $pdo->prepare('UPDATE semesters SET class_id = ?, name = ?, slug = ?, starts_on = ?, ends_on = ?, is_current = ? WHERE id = ?');
    $statement->execute(
        array(
            $classId,
            trim($input['name']),
            normalize_slug(isset($input['slug']) && $input['slug'] !== '' ? $input['slug'] : $input['name']),
            empty($input['starts_on']) ? null : $input['starts_on'],
            empty($input['ends_on']) ? null : $input['ends_on'],
            $isCurrent,
            (int) $semesterId,
        )
    );

    json_response(array('message' => 'Semester updated.'), 200);
}
