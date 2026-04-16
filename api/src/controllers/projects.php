<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/classes.php';
require_once dirname(__DIR__) . '/controllers/memberships.php';
require_once dirname(__DIR__) . '/controllers/vote_categories.php';
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
            FROM project_votes
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

function build_vote_counts_for_projects($pdo, $semesterId, $projectIds)
{
    if ((int) $semesterId <= 0 || count($projectIds) === 0) {
        return array();
    }

    $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
    $params = array_merge(array((int) $semesterId), $projectIds);
    $statement = $pdo->prepare(
        'SELECT project_id, category_id, COUNT(*) AS vote_count
         FROM project_votes
         WHERE semester_id = ? AND project_id IN (' . $placeholders . ')
         GROUP BY project_id, category_id'
    );
    $statement->execute($params);
    $rows = $statement->fetchAll();
    $countsByProject = array();

    foreach ($rows as $row) {
        $projectId = (int) $row['project_id'];
        $categoryId = (int) $row['category_id'];
        $voteCount = (int) $row['vote_count'];

        if (!isset($countsByProject[$projectId])) {
            $countsByProject[$projectId] = array();
        }

        $countsByProject[$projectId][$categoryId] = $voteCount;
    }

    return $countsByProject;
}

function build_user_votes_map($pdo, $semesterId, $userId)
{
    if ((int) $semesterId <= 0 || (int) $userId <= 0) {
        return array();
    }

    $statement = $pdo->prepare(
        'SELECT category_id, project_id
         FROM project_votes
         WHERE semester_id = ? AND user_id = ?'
    );
    $statement->execute(array((int) $semesterId, (int) $userId));
    $rows = $statement->fetchAll();
    $result = array();

    foreach ($rows as $row) {
        $result[(string) ((int) $row['category_id'])] = (int) $row['project_id'];
    }

    return $result;
}

function attach_vote_metrics_to_projects(&$projects, $categories, $countsByProject)
{
    foreach ($projects as &$project) {
        $projectCounts = isset($countsByProject[$project['id']]) ? $countsByProject[$project['id']] : array();
        $voteCounts = array();
        $totalVotes = 0;

        foreach ($categories as $category) {
            $categoryId = (int) $category['id'];
            $count = isset($projectCounts[$categoryId]) ? (int) $projectCounts[$categoryId] : 0;
            $voteCounts[(string) $categoryId] = $count;
            $totalVotes += $count;
        }

        $project['vote_counts'] = $voteCounts;
        $project['total_vote_count'] = $totalVotes;
        $project['like_count'] = $totalVotes;
    }
    unset($project);
}

function top_rated_project_limit($totalProjects, $isPrimary)
{
    if ($isPrimary) {
        return 1;
    }

    if ($totalProjects > 20) {
        return 3;
    }

    if ($totalProjects >= 10) {
        return 2;
    }

    return 1;
}

function build_top_rated_sections($projects, $categories)
{
    $sections = array();
    $totalProjects = count($projects);

    foreach ($categories as $category) {
        $categoryId = (int) $category['id'];
        $limit = top_rated_project_limit($totalProjects, (int) $category['is_primary'] === 1);
        $ranked = array();

        foreach ($projects as $project) {
            $count = isset($project['vote_counts'][(string) $categoryId]) ? (int) $project['vote_counts'][(string) $categoryId] : 0;
            if ($count <= 0) {
                continue;
            }

            $ranked[] = array(
                'project' => $project,
                'count' => $count,
            );
        }

        usort(
            $ranked,
            function ($left, $right) {
                if ($left['count'] === $right['count']) {
                    return strcmp($left['project']['title'], $right['project']['title']);
                }

                return $right['count'] - $left['count'];
            }
        );

        $projectsForSection = array();
        $slice = array_slice($ranked, 0, $limit);
        foreach ($slice as $entry) {
            $item = $entry['project'];
            $item['category_vote_count'] = (int) $entry['count'];
            $projectsForSection[] = $item;
        }

        $section = $category;
        $section['projects'] = $projectsForSection;
        $sections[] = $section;
    }

    return $sections;
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

    $membership = resolve_membership_for_class($pdo, $manager, (int) $targetClassId);

    if (!$membership || (int) $membership['is_active'] !== 1 || normalize_membership_role($membership['class_role']) !== 'manager') {
        json_response(array('error' => 'Manager must belong to the same class as the selected semester.'), 422);
    }
}

function list_public_projects()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $categories = get_vote_categories_for_class($pdo, $class['id']);
    $user = current_user();
    $semesterId = isset($_GET['semester_id']) ? (int) $_GET['semester_id'] : 0;
    $allSemesters = isset($_GET['all_semesters']) && (int) $_GET['all_semesters'] === 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;

    if ($limit < 0) {
        $limit = 0;
    }

    if ($limit > 50) {
        $limit = 50;
    }

    if ($allSemesters) {
        $currentSemesterStatement = $pdo->prepare('SELECT id, name, slug, starts_on, ends_on, is_current FROM semesters WHERE class_id = ? AND is_current = 1 ORDER BY id DESC LIMIT 1');
        $currentSemesterStatement->execute(array($class['id']));
        $currentSemester = $currentSemesterStatement->fetch();

        if (!$currentSemester) {
            json_response(
                array(
                    'class' => $class,
                    'projects' => array(),
                    'vote_categories' => $categories,
                    'top_rated' => array(
                        'semester' => null,
                        'sections' => array(),
                    ),
                    'votes' => array(
                        'user_votes' => array(),
                    ),
                ),
                200
            );
        }

        $statement = $pdo->prepare(
            project_select_sql() . '
            WHERE p.is_published = 1 AND s.class_id = ? AND p.semester_id = ?
            ORDER BY p.title ASC, p.created_at ASC'
        );
        $statement->execute(array($class['id'], (int) $currentSemester['id']));
        $projects = $statement->fetchAll();

        foreach ($projects as &$project) {
            $project = normalize_project_row($project);
        }
        unset($project);

        $projectIds = array();
        foreach ($projects as $project) {
            $projectIds[] = (int) $project['id'];
        }

        $countsByProject = build_vote_counts_for_projects($pdo, (int) $currentSemester['id'], $projectIds);
        attach_vote_metrics_to_projects($projects, $categories, $countsByProject);
        $userVotes = $user ? build_user_votes_map($pdo, (int) $currentSemester['id'], (int) $user['id']) : array();
        $sections = build_top_rated_sections($projects, $categories);

        json_response(
            array(
                'class' => $class,
                'projects' => $projects,
                'vote_categories' => $categories,
                'top_rated' => array(
                    'semester' => array(
                        'id' => (int) $currentSemester['id'],
                        'name' => $currentSemester['name'],
                        'slug' => $currentSemester['slug'],
                        'starts_on' => $currentSemester['starts_on'],
                        'ends_on' => $currentSemester['ends_on'],
                        'is_current' => (int) $currentSemester['is_current'],
                    ),
                    'sections' => $sections,
                ),
                'votes' => array(
                    'user_votes' => $userVotes,
                ),
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
    unset($project);

    $projectIds = array();
    foreach ($projects as $project) {
        $projectIds[] = (int) $project['id'];
    }

    $countsByProject = build_vote_counts_for_projects($pdo, $semesterId, $projectIds);
    attach_vote_metrics_to_projects($projects, $categories, $countsByProject);

    $userVotes = array();
    if ($user) {
        $userVotes = build_user_votes_map($pdo, $semesterId, (int) $user['id']);
    }

    json_response(
        array(
            'class' => $class,
            'projects' => $projects,
            'vote_categories' => $categories,
            'votes' => array(
                'user_votes' => $userVotes,
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
    unset($project);

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

function project_upload_directories()
{
    $configuredDirectory = trim((string) env_value('PROJECT_UPLOAD_DIR', ''));
    $configuredDirectory = str_replace('\\', '/', $configuredDirectory);

    if ($configuredDirectory !== '') {
        return array(rtrim($configuredDirectory, '/'));
    }

    return array(
        dirname(__DIR__, 3) . '/uploads',
    );
}

function project_upload_base_path()
{
    $configuredPublicPath = trim((string) env_value('PROJECT_UPLOAD_PUBLIC_PATH', ''));
    if ($configuredPublicPath !== '') {
        $normalizedConfiguredPath = '/' . ltrim(str_replace('\\', '/', $configuredPublicPath), '/');
        return rtrim($normalizedConfiguredPath, '/');
    }

    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    $scriptName = str_replace('\\', '/', $scriptName);

    if ($scriptName === '' || $scriptName[0] !== '/') {
        return '';
    }

    $publicIndexSuffix = '/api/public/index.php';
    $suffixPosition = strpos($scriptName, $publicIndexSuffix);

    if ($suffixPosition !== false) {
        return rtrim(substr($scriptName, 0, $suffixPosition), '/') . '/uploads';
    }

    return rtrim(dirname($scriptName), '/') . '/uploads';
}

function resolve_writable_project_upload_dir()
{
    $directories = project_upload_directories();

    foreach ($directories as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
            error_log('Project upload directory create failed: ' . $directory);
            continue;
        }

        if (!is_writable($directory)) {
            error_log('Project upload directory not writable: ' . $directory);
            continue;
        }

        return $directory;
    }

    return null;
}

function upload_error_message($errorCode)
{
    $messages = array(
        UPLOAD_ERR_INI_SIZE => 'Uploaded file exceeds upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds MAX_FILE_SIZE from form.',
        UPLOAD_ERR_PARTIAL => 'Uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload folder on server.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write uploaded file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    );

    if (isset($messages[$errorCode])) {
        return $messages[$errorCode];
    }

    return 'Image upload failed.';
}

function upload_runtime_diagnostics()
{
    $tmpDir = ini_get('upload_tmp_dir');
    if ($tmpDir === '' || $tmpDir === false) {
        $tmpDir = sys_get_temp_dir();
    }

    $directories = project_upload_directories();
    $directoryChecks = array();

    foreach ($directories as $directory) {
        $directoryChecks[] = array(
            'path' => $directory,
            'exists' => is_dir($directory),
            'writable' => is_writable($directory),
        );
    }

    $processUser = function_exists('posix_geteuid')
        ? posix_getpwuid(posix_geteuid())
        : array();

    $diagnostics = array(
        'php_process_uid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
        'php_process_user' => isset($processUser['name']) ? $processUser['name'] : get_current_user(),
        'php_process_gid' => function_exists('posix_getegid') ? posix_getegid() : null,
        'php_process_group' => (function_exists('posix_getegid') && function_exists('posix_getgrgid'))
            ? (function () {
                $groupInfo = posix_getgrgid(posix_getegid());

                return (is_array($groupInfo) && isset($groupInfo['name'])) ? $groupInfo['name'] : null;
            })()
            : null,
        'file_uploads' => ini_get('file_uploads'),
        'upload_tmp_dir' => $tmpDir,
        'upload_tmp_dir_exists' => is_dir($tmpDir),
        'upload_tmp_dir_writable' => is_writable($tmpDir),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
        'open_basedir' => ini_get('open_basedir'),
        'target_directories' => $directoryChecks,
    );

    return $diagnostics;
}

function upload_project_health()
{
    require_auth(array('admin', 'manager'));

    json_response(
        array(
            'status' => 'ok',
            'diagnostics' => upload_runtime_diagnostics(),
        ),
        200
    );
}

function upload_project_image()
{
    require_auth(array('admin', 'manager'));

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        json_response(array('error' => 'Image file is required.'), 400);
    }

    $file = $_FILES['image'];

    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        $errorCode = isset($file['error']) ? (int) $file['error'] : -1;
        json_response(
            array(
                'error' => upload_error_message($errorCode),
                'code' => $errorCode,
            ),
            400
        );
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

    $uploadDir = resolve_writable_project_upload_dir();
    if ($uploadDir === null) {
        json_response(
            array(
                'error' => 'Upload storage is not writable. Please check folder permissions for the uploads directory.',
                'diagnostics' => upload_runtime_diagnostics(),
            ),
            500
        );
    }

    if (function_exists('random_bytes')) {
        try {
            $randomSuffix = bin2hex(random_bytes(6));
        } catch (Exception $exception) {
            error_log('Project upload random_bytes failed: ' . $exception->getMessage());
            $randomSuffix = dechex(mt_rand(0, 0xffffff)) . dechex(mt_rand(0, 0xffffff));
        }
    } else {
        $randomSuffix = dechex(mt_rand(0, 0xffffff)) . dechex(mt_rand(0, 0xffffff));
    }

    $filename = 'project-upload-' . time() . '-' . $randomSuffix . '.' . $allowedMimeTypes[$mimeType];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        error_log('Project upload move failed: ' . $destination);
        json_response(
            array(
                'error' => 'Unable to store uploaded file.',
                'diagnostics' => upload_runtime_diagnostics(),
            ),
            500
        );
    }

    $basePath = project_upload_base_path();
    $relativePath = ($basePath === '' ? '' : $basePath) . '/' . $filename;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '' ? $_SERVER['HTTP_HOST'] : 'localhost';
    $absolutePath = $scheme . '://' . $host . $relativePath;

    $configuredPublicUrl = trim((string) env_value('PROJECT_UPLOAD_PUBLIC_URL', ''));
    if ($configuredPublicUrl !== '') {
        $absolutePath = rtrim($configuredPublicUrl, '/') . '/' . $filename;
        $relativePath = $absolutePath;
    }

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
    $user = require_auth(array('admin', 'manager', 'user'));
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('category_id'));
    $categoryId = (int) $input['category_id'];

    if ($categoryId <= 0) {
        json_response(array('error' => 'A valid vote category is required.'), 422);
    }

    $projectStatement = $pdo->prepare(
        'SELECT p.id, p.semester_id, s.class_id, s.is_current
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
        json_response(array('error' => 'Voting is only available for the current semester.'), 409);
    }

    $categoryStatement = $pdo->prepare(
        'SELECT id
         FROM vote_categories
         WHERE id = ? AND class_id = ? AND is_active = 1
         LIMIT 1'
    );
    $categoryStatement->execute(array($categoryId, (int) $project['class_id']));
    $category = $categoryStatement->fetch();

    if (!$category) {
        json_response(array('error' => 'Vote category not found for this class.'), 404);
    }

    $existingStatement = $pdo->prepare(
        'SELECT id, project_id
         FROM project_votes
         WHERE semester_id = ? AND user_id = ? AND category_id = ?
         LIMIT 1'
    );
    $existingStatement->execute(array((int) $project['semester_id'], (int) $user['id'], $categoryId));
    $existing = $existingStatement->fetch();

    if ($existing && (int) $existing['project_id'] === (int) $projectId) {
        $deleteStatement = $pdo->prepare('DELETE FROM project_votes WHERE id = ?');
        $deleteStatement->execute(array((int) $existing['id']));

        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM project_votes WHERE project_id = ? AND category_id = ?');
        $countStatement->execute(array((int) $projectId, $categoryId));
        $voteCount = (int) $countStatement->fetchColumn();
        $userVotes = build_user_votes_map($pdo, (int) $project['semester_id'], (int) $user['id']);

        json_response(
            array(
                'message' => 'Vote removed.',
                'voted' => false,
                'category_id' => $categoryId,
                'project_id' => (int) $projectId,
                'vote_count' => $voteCount,
                'user_votes' => $userVotes,
            ),
            200
        );
    }

    if ($existing) {
        $updateStatement = $pdo->prepare('UPDATE project_votes SET project_id = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?');
        $updateStatement->execute(array((int) $projectId, (int) $existing['id']));
    } else {
        $insertStatement = $pdo->prepare(
            'INSERT INTO project_votes (class_id, semester_id, project_id, category_id, user_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insertStatement->execute(
            array(
                (int) $project['class_id'],
                (int) $project['semester_id'],
                (int) $projectId,
                $categoryId,
                (int) $user['id'],
            )
        );
    }

    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM project_votes WHERE project_id = ? AND category_id = ?');
    $countStatement->execute(array((int) $projectId, $categoryId));
    $voteCount = (int) $countStatement->fetchColumn();
    $userVotes = build_user_votes_map($pdo, (int) $project['semester_id'], (int) $user['id']);

    json_response(
        array(
            'message' => 'Vote saved.',
            'voted' => true,
            'category_id' => $categoryId,
            'project_id' => (int) $projectId,
            'vote_count' => $voteCount,
            'user_votes' => $userVotes,
        ),
        200
    );
}
