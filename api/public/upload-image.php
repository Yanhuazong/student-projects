<?php

header('Access-Control-Allow-Origin: https://va.tech.purdue.edu');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'Method not allowed.'));
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();

    if (!$error) {
        return;
    }

    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: https://va.tech.purdue.edu');
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }

    echo json_encode(
        array(
            'error' => 'Uploader fatal error.',
            'details' => $error['message'],
            'line' => isset($error['line']) ? (int) $error['line'] : null,
        )
    );
});

function upload_json_response($data, $statusCode)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function upload_load_env_file($filePath)
{
    static $loadedFiles = array();

    if (isset($loadedFiles[$filePath]) || !file_exists($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    $loadedFiles[$filePath] = true;
}

function upload_env_value($key, $defaultValue)
{
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    $value = getenv($key);

    return $value !== false && $value !== '' ? $value : $defaultValue;
}

function upload_base64url_decode($value)
{
    $padding = strlen($value) % 4;

    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'));
}

function upload_hash_equals($knownString, $userString)
{
    if (function_exists('hash_equals')) {
        return hash_equals($knownString, $userString);
    }

    $knownString = (string) $knownString;
    $userString = (string) $userString;
    $knownLength = strlen($knownString);
    $userLength = strlen($userString);

    if ($knownLength !== $userLength) {
        return false;
    }

    $result = 0;

    for ($i = 0; $i < $knownLength; $i++) {
        $result |= ord($knownString[$i]) ^ ord($userString[$i]);
    }

    return $result === 0;
}

function upload_decode_auth_token($token, $secret)
{
    if (!$token || strpos($token, '.') === false) {
        return null;
    }

    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }

    $encodedPayload = $parts[0];
    $signature = $parts[1];
    $expected = hash_hmac('sha256', $encodedPayload, $secret);

    if (!upload_hash_equals($expected, $signature)) {
        return null;
    }

    $decodedPayload = json_decode(upload_base64url_decode($encodedPayload), true);

    if (!is_array($decodedPayload)) {
        return null;
    }

    if (!isset($decodedPayload['exp']) || (int) $decodedPayload['exp'] < time()) {
        return null;
    }

    return $decodedPayload;
}

upload_load_env_file(dirname(__DIR__) . '/.env');
upload_load_env_file(dirname(__DIR__) . '/.env.production');

$secret = upload_env_value('APP_SECRET', 'student-projects-secret');
$token = isset($_POST['auth_token']) ? trim((string) $_POST['auth_token']) : '';
$payload = upload_decode_auth_token($token, $secret);

if (!$payload) {
    upload_json_response(array('error' => 'Authentication required.'), 401);
}

$role = isset($payload['role']) ? strtolower(trim((string) $payload['role'])) : '';
if ($role !== 'admin' && $role !== 'manager') {
    upload_json_response(array('error' => 'You do not have permission to upload images.'), 403);
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    upload_json_response(array('error' => 'Image file is required.'), 400);
}

$file = $_FILES['image'];
if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
    upload_json_response(array('error' => 'Image upload failed.'), 400);
}

$maxBytes = 5 * 1024 * 1024;
if ((int) $file['size'] > $maxBytes) {
    upload_json_response(array('error' => 'Image must be 5MB or smaller.'), 400);
}

$tmpName = isset($file['tmp_name']) ? $file['tmp_name'] : '';
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    upload_json_response(array('error' => 'Invalid upload payload.'), 400);
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
    upload_json_response(array('error' => 'Only JPG, PNG, WEBP, and GIF images are allowed.'), 400);
}

$configuredUploadDir = trim((string) upload_env_value('PROJECT_UPLOAD_DIR', ''));
$uploadDir = $configuredUploadDir !== '' ? rtrim(str_replace('\\', '/', $configuredUploadDir), '/') : dirname(dirname(__DIR__)) . '/uploads';

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    upload_json_response(array('error' => 'Upload directory is not writable.'), 500);
}

if (!is_writable($uploadDir)) {
    upload_json_response(array('error' => 'Upload directory is not writable.'), 500);
}

if (function_exists('random_bytes')) {
    try {
        $randomSuffix = bin2hex(random_bytes(6));
    } catch (Exception $exception) {
        $randomSuffix = dechex(mt_rand(0, 0xffffff)) . dechex(mt_rand(0, 0xffffff));
    }
} else {
    $randomSuffix = dechex(mt_rand(0, 0xffffff)) . dechex(mt_rand(0, 0xffffff));
}

$filename = 'project-upload-' . time() . '-' . $randomSuffix . '.' . $allowedMimeTypes[$mimeType];
$destination = $uploadDir . '/' . $filename;

if (!move_uploaded_file($tmpName, $destination)) {
    upload_json_response(array('error' => 'Unable to store uploaded file.'), 500);
}

$publicUrlRoot = trim((string) upload_env_value('PROJECT_UPLOAD_PUBLIC_URL', ''));
if ($publicUrlRoot === '') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '' ? $_SERVER['HTTP_HOST'] : 'localhost';
    $publicUrlRoot = $scheme . '://' . $host . '/uploads';
}

$absoluteUrl = rtrim($publicUrlRoot, '/') . '/' . $filename;

upload_json_response(
    array(
        'image_url' => $absoluteUrl,
        'image_url_absolute' => $absoluteUrl,
        'message' => 'Image uploaded.',
    ),
    201
);
