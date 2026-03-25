<?php

function json_response($data, $statusCode)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function read_json_body()
{
    $raw = file_get_contents('php://input');

    if (!$raw) {
        return array();
    }

    $decoded = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        json_response(array('error' => 'Invalid JSON request body.'), 400);
    }

    return is_array($decoded) ? $decoded : array();
}

function require_fields($input, $fields)
{
    $missing = array();

    foreach ($fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        json_response(
            array(
                'error' => 'Missing required fields.',
                'fields' => $missing,
            ),
            422
        );
    }
}

function normalize_slug($value)
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug === '' ? 'item-' . time() : $slug;
}
