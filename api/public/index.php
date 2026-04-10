<?php

require_once dirname(__DIR__) . '/src/config/database.php';
require_once dirname(__DIR__) . '/src/utils/response.php';
require_once dirname(__DIR__) . '/src/controllers/auth.php';

// Load env early so APP_SECRET is available before any DB call.
load_env_file(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/src/controllers/classes.php';
require_once dirname(__DIR__) . '/src/controllers/semesters.php';
require_once dirname(__DIR__) . '/src/controllers/users.php';
require_once dirname(__DIR__) . '/src/controllers/projects.php';
require_once dirname(__DIR__) . '/src/controllers/settings.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function send_uploaded_file($absolutePath)
{
    if (!is_file($absolutePath)) {
        json_response(array('error' => 'File not found.'), 404);
    }

    $mimeType = 'application/octet-stream';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detectedType = finfo_file($finfo, $absolutePath);
            finfo_close($finfo);

            if ($detectedType) {
                $mimeType = $detectedType;
            }
        }
    } elseif (function_exists('mime_content_type')) {
        $detectedType = mime_content_type($absolutePath);
        if ($detectedType) {
            $mimeType = $detectedType;
        }
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($absolutePath));
    readfile($absolutePath);
    exit;
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$path = $requestUri;

if ($scriptName !== '' && strpos($requestUri, $scriptName) === 0) {
    $path = substr($requestUri, strlen($scriptName));
}

if ($path === '' || $path === false) {
    $path = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && preg_match('#^/uploads/projects/([a-zA-Z0-9._-]+)$#', $path, $matches)) {
    $uploadFileName = $matches[1];
    $uploadsRoots = array(
        dirname(__DIR__) . '/public/uploads/projects',
        dirname(__DIR__) . '/uploads/projects',
    );

    foreach ($uploadsRoots as $uploadsRoot) {
        $candidate = $uploadsRoot . '/' . $uploadFileName;
        if (is_file($candidate)) {
            send_uploaded_file($candidate);
        }
    }

    json_response(array('error' => 'File not found.'), 404);
}

if ($method === 'GET' && $path === '/') {
    json_response(array('message' => 'Student Projects API', 'status' => 'ok'), 200);
}

if ($method === 'GET' && $path === '/auth/bootstrap-status') {
    auth_bootstrap_status();
}

if ($method === 'POST' && $path === '/auth/bootstrap-admin') {
    auth_bootstrap_admin();
}

if ($method === 'POST' && $path === '/auth/login') {
    auth_login();
}

if ($method === 'GET' && $path === '/auth/me') {
    auth_me();
}

if ($method === 'GET' && $path === '/classes') {
    list_classes();
}

if ($method === 'GET' && $path === '/semesters') {
    list_semesters();
}

if ($method === 'GET' && $path === '/semesters/current') {
    current_semester();
}

if ($method === 'GET' && $path === '/settings/public') {
    public_settings();
}

if ($method === 'POST' && $path === '/admin/semesters') {
    create_semester();
}

if ($method === 'PUT' && preg_match('#^/admin/semesters/(\d+)$#', $path, $matches)) {
    update_semester((int) $matches[1]);
}

if ($method === 'GET' && $path === '/admin/users') {
    list_users();
}

if ($method === 'POST' && $path === '/admin/users') {
    create_user();
}

if ($method === 'GET' && $path === '/admin/settings') {
    admin_settings();
}

if ($method === 'PUT' && $path === '/admin/settings') {
    update_admin_settings();
}

if ($method === 'POST' && $path === '/admin/settings') {
    update_admin_settings();
}

if ($method === 'GET' && $path === '/projects') {
    list_public_projects();
}

if ($method === 'GET' && preg_match('#^/projects/([a-z0-9\-]+)$#', $path, $matches)) {
    get_project_by_slug($matches[1]);
}

if ($method === 'POST' && preg_match('#^/projects/(\d+)/like$#', $path, $matches)) {
    toggle_project_like((int) $matches[1]);
}

if ($method === 'POST' && preg_match('#^/projects/(\d+)/favorite$#', $path, $matches)) {
    toggle_project_like((int) $matches[1]);
}

if ($method === 'POST' && preg_match('#^/projects/(\d+)/vote$#', $path, $matches)) {
    toggle_project_like((int) $matches[1]);
}

if ($method === 'GET' && $path === '/dashboard/projects') {
    dashboard_projects();
}

if ($method === 'POST' && $path === '/dashboard/projects') {
    create_project();
}

if ($method === 'POST' && $path === '/dashboard/uploads/image') {
    upload_project_image();
}

if ($method === 'GET' && $path === '/dashboard/uploads/health') {
    upload_project_health();
}

if ($method === 'PUT' && preg_match('#^/dashboard/projects/(\d+)$#', $path, $matches)) {
    update_project((int) $matches[1]);
}

if ($method === 'POST' && preg_match('#^/dashboard/projects/(\d+)/update$#', $path, $matches)) {
    update_project((int) $matches[1]);
}

if ($method === 'DELETE' && preg_match('#^/dashboard/projects/(\d+)$#', $path, $matches)) {
    delete_project((int) $matches[1]);
}

if ($method === 'POST' && preg_match('#^/dashboard/projects/(\d+)/delete$#', $path, $matches)) {
    delete_project((int) $matches[1]);
}

json_response(array('error' => 'Endpoint not found.'), 404);