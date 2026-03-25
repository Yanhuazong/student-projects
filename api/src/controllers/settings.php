<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/classes.php';
require_once dirname(__DIR__) . '/middleware/auth.php';
require_once dirname(__DIR__) . '/utils/response.php';

function default_site_settings()
{
    return array(
        'site_logo_text' => 'Student Projects',
        'home_heading' => 'Top-rated project stories across every semester.',
    );
}

function upsert_class_setting($pdo, $classId, $key, $value)
{
    $statement = $pdo->prepare(
        'INSERT INTO class_settings (class_id, setting_key, setting_value) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $statement->execute(array((int) $classId, $key, $value));
}

function fetch_site_settings($pdo, $classId = null)
{
    $defaults = default_site_settings();
    $statement = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
    $rows = $statement->fetchAll();

    foreach ($rows as $row) {
        $key = $row['setting_key'];
        if (array_key_exists($key, $defaults)) {
            $defaults[$key] = $row['setting_value'];
        }
    }

    if ($classId !== null) {
        $classStatement = $pdo->prepare('SELECT setting_key, setting_value FROM class_settings WHERE class_id = ?');
        $classStatement->execute(array((int) $classId));
        $classRows = $classStatement->fetchAll();

        foreach ($classRows as $row) {
            $key = $row['setting_key'];
            if (array_key_exists($key, $defaults)) {
                $defaults[$key] = $row['setting_value'];
            }
        }
    }

    return $defaults;
}

function public_settings()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $settings = fetch_site_settings($pdo, $class['id']);

    json_response(array('class' => $class, 'settings' => $settings), 200);
}

function admin_settings()
{
    require_auth(array('admin'));
    $pdo = get_pdo();
    $class = resolve_active_class($pdo, array('allow_inactive' => true));
    $settings = fetch_site_settings($pdo, $class['id']);

    json_response(array('class' => $class, 'settings' => $settings), 200);
}

function update_admin_settings()
{
    require_auth(array('admin'));
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('site_logo_text', 'home_heading'));

    $logoText = trim($input['site_logo_text']);
    $homeHeading = trim($input['home_heading']);
    $class = resolve_active_class($pdo, array('allow_inactive' => true));

    if ($logoText === '' || $homeHeading === '') {
        json_response(array('error' => 'Site logo text and home heading are required.'), 422);
    }

    upsert_class_setting($pdo, $class['id'], 'site_logo_text', $logoText);
    upsert_class_setting($pdo, $class['id'], 'home_heading', $homeHeading);

    json_response(
        array(
            'message' => 'Site settings updated.',
            'class' => $class,
            'settings' => fetch_site_settings($pdo, $class['id']),
        ),
        200
    );
}
