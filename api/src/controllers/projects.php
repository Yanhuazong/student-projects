<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/classes.php';
require_once dirname(__DIR__) . '/middleware/auth.php';
require_once dirname(__DIR__) . '/utils/response.php';

function project_select_sql()
{
    return "
        SELECT
            p.id,
            p.semester_id,
            p.manager_user_id,
            p.title,
            p.slug,
            p.student_name,
            p.summary,
            p.description_html,
            p.image_url,
            p.external_url,
            p.sort_order,
            p.is_published,
            s.name AS semester_name,
            s.slug AS semester_slug,
            s.is_current,
            c.id AS class_id,
            c.name AS class_name,
            c.slug AS class_slug,
            u.name AS manager_name,
            COALESCE(fc.like_count, 0) AS like_count
        FROM projects p
        INNER JOIN semesters s ON s.id = p.semester_id
        INNER JOIN classes c ON c.id = s.class_id
        INNER JOIN users u ON u.id = p.manager_user_id
        LEFT JOIN (
            SELECT project_id, COUNT(*) AS like_count
            FROM favorites
            GROUP BY project_id
        ) fc ON fc.project_id = p.id
    ";
}

function normalize_project_row($row)
{
    $row['id'] = (int) $row['id'];
    $row['class_id'] = (int) $row['class_id'];
    $row['semester_id'] = (int) $row['semester_id'];
    $row['manager_user_id'] = (int) $row['manager_user_id'];
    $row['sort_order'] = (int) $row['sort_order'];
    $row['is_published'] = (int) $row['is_published'];
    $row['is_current'] = (int) $row['is_current'];
    $row['like_count'] = (int) $row['like_count'];

    return $row;
}

function semester_class_id($pdo, $semesterId)
{
    $statement = $pdo->prepare('SELECT class_id FROM semesters WHERE id = ? LIMIT 1');
    $statement->execute(array((int) $semesterId));
    $classId = $statement->fetchColumn();

    if ($classId === false) {
        json_response(array('error' => 'Semester not found.'), 404);
    }

    return (int) $classId;
}

function assert_manager_within_class($user, $targetClassId)
{
    if ($user['role'] !== 'manager') {
        return;
    }

    if ((int) $user['class_id'] !== (int) $targetClassId) {
        json_response(array('error' => 'Managers can only manage projects in their assigned class.'), 403);
    }
}

function assert_manager_owner_within_class($pdo, $managerUserId, $targetClassId)
{
    $managerStatement = $pdo->prepare('SELECT id, role, class_id, is_active FROM users WHERE id = ? LIMIT 1');
    $managerStatement->execute(array((int) $managerUserId));
    $manager = $managerStatement->fetch();

    if (!$manager || (int) $manager['is_active'] !== 1) {
        json_response(array('error' => 'Manager user not found or inactive.'), 422);
    }

    if ($manager['role'] !== 'manager') {
        json_response(array('error' => 'Assigned project owner must be a manager.'), 422);
    }

    if ((int) $manager['class_id'] !== (int) $targetClassId) {
        json_response(array('error' => 'Manager must belong to the same class as the selected semester.'), 422);
    }
}

function list_public_projects()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $semesterId = isset($_GET['semester_id']) ? (int) $_GET['semester_id'] : 0;
    $deviceToken = isset($_GET['device_token']) ? trim($_GET['device_token']) : '';
    $allSemesters = isset($_GET['all_semesters']) && (int) $_GET['all_semesters'] === 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;

    if ($limit < 0) {
        $limit = 0;
    }

    if ($limit > 50) {
        $limit = 50;
    }

    if ($allSemesters) {
        $limitClause = $limit > 0 ? " LIMIT $limit" : '';

        $statement = $pdo->prepare(
            project_select_sql() . "
            WHERE p.is_published = 1 AND s.class_id = ? AND COALESCE(fc.like_count, 0) > 0
            ORDER BY like_count DESC, p.created_at DESC$limitClause"
        );
        $statement->execute(array($class['id']));
        $projects = $statement->fetchAll();

        foreach ($projects as &$project) {
            $project = normalize_project_row($project);
        }

        json_response(
            array(
                'class' => $class,
                'projects' => $projects,
                'likes' => null,
            ),
            200
        );
    }

    if ($semesterId <= 0) {
        $currentSemesterStatement = $pdo->prepare('SELECT id FROM semesters WHERE class_id = ? AND is_current = 1 ORDER BY id DESC LIMIT 1');
        $currentSemesterStatement->execute(array($class['id']));
        $semesterId = (int) $currentSemesterStatement->fetchColumn();
    }

    $statement = $pdo->prepare(
        project_select_sql() . '
        WHERE p.semester_id = ? AND s.class_id = ? AND p.is_published = 1
        ORDER BY p.title ASC, p.created_at ASC'
    );
    $statement->execute(array($semesterId, $class['id']));
    $projects = $statement->fetchAll();

    foreach ($projects as &$project) {
        $project = normalize_project_row($project);
    }

    $likedProjectIds = array();
    $likeLimit = 3;

    if ($semesterId > 0 && $deviceToken !== '') {
        $likesStatement = $pdo->prepare('SELECT project_id FROM favorites WHERE semester_id = ? AND device_token = ? ORDER BY id ASC');
        $likesStatement->execute(array($semesterId, $deviceToken));
        $likeRows = $likesStatement->fetchAll();

        foreach ($likeRows as $likeRow) {
            $likedProjectIds[] = (int) $likeRow['project_id'];
        }
    }

    json_response(
        array(
            'class' => $class,
            'projects' => $projects,
            'likes' => array(
                'project_ids' => $likedProjectIds,
                'limit' => $likeLimit,
            ),
        ),
        200
    );
}

function get_project_by_slug($slug)
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $statement = $pdo->prepare(
        project_select_sql() . '
        WHERE p.slug = ? AND s.class_id = ? AND p.is_published = 1
        LIMIT 1'
    );
    $statement->execute(array($slug, $class['id']));
    $project = $statement->fetch();

    if (!$project) {
        json_response(array('error' => 'Project not found.'), 404);
    }

    json_response(array('project' => normalize_project_row($project)), 200);
}

function dashboard_projects()
{
    $user = require_auth(array('admin', 'manager'));
    $pdo = get_pdo();
    $class = resolve_active_class($pdo, array('allow_inactive' => true));
    $params = array();
    $where = 'WHERE s.class_id = ?';
    $params[] = (int) $class['id'];

    if ($user['role'] === 'manager') {
        $where .= ' AND p.manager_user_id = ?';
        $params[] = (int) $user['id'];
    }

    $statement = $pdo->prepare(
        project_select_sql() . "
        $where
        ORDER BY s.is_current DESC, s.starts_on DESC, p.title ASC, p.created_at ASC"
    );
    $statement->execute($params);
    $projects = $statement->fetchAll();

    foreach ($projects as &$project) {
        $project = normalize_project_row($project);
    }

    json_response(array('projects' => $projects), 200);
}

function assert_project_permission($user, $projectRow)
{
    if ($user['role'] === 'admin') {
        return;
    }

    if ((int) $projectRow['manager_user_id'] !== (int) $user['id']) {
        json_response(array('error' => 'You can only manage your own projects.'), 403);
    }
}

function create_project()
{
    $user = require_auth(array('admin', 'manager'));
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('semester_id', 'title', 'student_name', 'summary', 'description_html'));

    $targetClassId = semester_class_id($pdo, (int) $input['semester_id']);
    assert_manager_within_class($user, $targetClassId);

    $managerUserId = $user['role'] === 'admin' && !empty($input['manager_user_id'])
        ? (int) $input['manager_user_id']
        : (int) $user['id'];

    if ($user['role'] === 'admin') {
        assert_manager_owner_within_class($pdo, $managerUserId, $targetClassId);
    }

    $title = trim($input['title']);
    $slug = isset($input['slug']) && $input['slug'] !== '' ? normalize_slug($input['slug']) : normalize_slug($title);

    $statement = $pdo->prepare(
        'INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->execute(
        array(
            (int) $input['semester_id'],
            $managerUserId,
            $title,
            $slug,
            trim($input['student_name']),
            trim($input['summary']),
            $input['description_html'],
            empty($input['image_url']) ? null : trim($input['image_url']),
            empty($input['external_url']) ? null : trim($input['external_url']),
            isset($input['sort_order']) ? (int) $input['sort_order'] : 0,
            isset($input['is_published']) && !$input['is_published'] ? 0 : 1,
        )
    );

    json_response(array('message' => 'Project created.'), 201);
}

function update_project($projectId)
{
    $user = require_auth(array('admin', 'manager'));
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('semester_id', 'title', 'student_name', 'summary', 'description_html'));

    $projectStatement = $pdo->prepare(
        'SELECT p.id, p.manager_user_id, s.class_id
         FROM projects p
         INNER JOIN semesters s ON s.id = p.semester_id
         WHERE p.id = ?
         LIMIT 1'
    );
    $projectStatement->execute(array((int) $projectId));
    $projectRow = $projectStatement->fetch();

    if (!$projectRow) {
        json_response(array('error' => 'Project not found.'), 404);
    }

    assert_project_permission($user, $projectRow);

    $targetClassId = semester_class_id($pdo, (int) $input['semester_id']);
    assert_manager_within_class($user, $targetClassId);

    $managerUserId = $user['role'] === 'admin' && !empty($input['manager_user_id'])
        ? (int) $input['manager_user_id']
        : (int) $projectRow['manager_user_id'];

    if ($user['role'] === 'admin') {
        assert_manager_owner_within_class($pdo, $managerUserId, $targetClassId);
    }

    $statement = $pdo->prepare(
        'UPDATE projects
         SET semester_id = ?, manager_user_id = ?, title = ?, slug = ?, student_name = ?, summary = ?, description_html = ?, image_url = ?, external_url = ?, sort_order = ?, is_published = ?
         WHERE id = ?'
    );
    $statement->execute(
        array(
            (int) $input['semester_id'],
            $managerUserId,
            trim($input['title']),
            normalize_slug(isset($input['slug']) && $input['slug'] !== '' ? $input['slug'] : $input['title']),
            trim($input['student_name']),
            trim($input['summary']),
            $input['description_html'],
            empty($input['image_url']) ? null : trim($input['image_url']),
            empty($input['external_url']) ? null : trim($input['external_url']),
            isset($input['sort_order']) ? (int) $input['sort_order'] : 0,
            isset($input['is_published']) && !$input['is_published'] ? 0 : 1,
            (int) $projectId,
        )
    );

    json_response(array('message' => 'Project updated.'), 200);
}

function delete_project($projectId)
{
    $user = require_auth(array('admin', 'manager'));
    $pdo = get_pdo();

    $projectStatement = $pdo->prepare('SELECT id, manager_user_id FROM projects WHERE id = ? LIMIT 1');
    $projectStatement->execute(array((int) $projectId));
    $projectRow = $projectStatement->fetch();

    if (!$projectRow) {
        json_response(array('error' => 'Project not found.'), 404);
    }

    assert_project_permission($user, $projectRow);

    $statement = $pdo->prepare('DELETE FROM projects WHERE id = ?');
    $statement->execute(array((int) $projectId));

    json_response(array('message' => 'Project deleted.'), 200);
}

function upload_project_image()
{
    require_auth(array('admin', 'manager'));

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        json_response(array('error' => 'Image file is required.'), 400);
    }

    $file = $_FILES['image'];

    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        json_response(array('error' => 'Image upload failed.'), 400);
    }

    $maxBytes = 5 * 1024 * 1024;
    if ((int) $file['size'] > $maxBytes) {
        json_response(array('error' => 'Image must be 5MB or smaller.'), 400);
    }

    $tmpName = isset($file['tmp_name']) ? $file['tmp_name'] : '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        json_response(array('error' => 'Invalid upload payload.'), 400);
    }

    $mimeType = false;

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }
    }

    if (!$mimeType && function_exists('mime_content_type')) {
        $mimeType = mime_content_type($tmpName);
    }

    if (!$mimeType && function_exists('getimagesize')) {
        $imageInfo = getimagesize($tmpName);
        if (is_array($imageInfo) && isset($imageInfo['mime'])) {
            $mimeType = $imageInfo['mime'];
        }
    }

    $allowedMimeTypes = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    );

    if (!$mimeType || !isset($allowedMimeTypes[$mimeType])) {
        json_response(array('error' => 'Only JPG, PNG, WEBP, and GIF images are allowed.'), 400);
    }

    $uploadDir = dirname(__DIR__, 2) . '/public/uploads/projects';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        json_response(array('error' => 'Unable to create upload directory.'), 500);
    }

    $filename = 'project-' . time() . '-' . bin2hex(random_bytes(6)) . '.' . $allowedMimeTypes[$mimeType];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        json_response(array('error' => 'Unable to store uploaded file.'), 500);
    }

    $relativePath = '/uploads/projects/' . $filename;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '' ? $_SERVER['HTTP_HOST'] : 'localhost';
    $absolutePath = $scheme . '://' . $host . $relativePath;

    json_response(
        array(
            'image_url' => $relativePath,
            'image_url_absolute' => $absolutePath,
            'message' => 'Image uploaded.',
        ),
        201
    );
}

function toggle_project_like($projectId)
{
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('device_token'));
    $deviceToken = trim($input['device_token']);
    $likeLimit = 3;

    $projectStatement = $pdo->prepare(
        'SELECT p.id, p.semester_id, s.is_current
         FROM projects p
         INNER JOIN semesters s ON s.id = p.semester_id
         WHERE p.id = ? AND p.is_published = 1
         LIMIT 1'
    );
    $projectStatement->execute(array((int) $projectId));
    $project = $projectStatement->fetch();

    if (!$project) {
        json_response(array('error' => 'Project not found.'), 404);
    }

    if ((int) $project['is_current'] !== 1) {
        json_response(array('error' => 'Likes are only available for the current semester.'), 409);
    }

    $existingStatement = $pdo->prepare(
        'SELECT id FROM favorites WHERE semester_id = ? AND project_id = ? AND device_token = ? LIMIT 1'
    );
    $existingStatement->execute(array((int) $project['semester_id'], (int) $projectId, $deviceToken));
    $existing = $existingStatement->fetch();

    if ($existing) {
        $deleteStatement = $pdo->prepare('DELETE FROM favorites WHERE id = ?');
        $deleteStatement->execute(array((int) $existing['id']));

        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE project_id = ?');
        $countStatement->execute(array((int) $projectId));
        $likeCount = (int) $countStatement->fetchColumn();

        json_response(
            array(
                'message' => 'Like removed.',
                'liked' => false,
                'like_count' => $likeCount,
                'limit' => $likeLimit,
            ),
            200
        );
    }

    $deviceLikesStatement = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE semester_id = ? AND device_token = ?');
    $deviceLikesStatement->execute(array((int) $project['semester_id'], $deviceToken));
    $deviceLikeCount = (int) $deviceLikesStatement->fetchColumn();

    if ($deviceLikeCount >= $likeLimit) {
        json_response(
            array(
                'error' => 'You can like up to 3 projects this semester.',
                'limit' => $likeLimit,
            ),
            409
        );
    }

    $insertStatement = $pdo->prepare('INSERT INTO favorites (semester_id, project_id, device_token) VALUES (?, ?, ?)');
    $insertStatement->execute(array((int) $project['semester_id'], (int) $projectId, $deviceToken));

    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE project_id = ?');
    $countStatement->execute(array((int) $projectId));
    $likeCount = (int) $countStatement->fetchColumn();

    json_response(
        array(
            'message' => 'Like saved.',
            'liked' => true,
            'like_count' => $likeCount,
            'limit' => $likeLimit,
        ),
        200
    );
}
