<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/controllers/classes.php';
require_once dirname(__DIR__) . '/controllers/vote_categories.php';
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

function fetch_class_setting_value($pdo, $classId, $key, $defaultValue = '')
{
    $statement = $pdo->prepare(
        'SELECT setting_value
         FROM class_settings
         WHERE class_id = ? AND setting_key = ?
         LIMIT 1'
    );
    $statement->execute(array((int) $classId, $key));
    $value = $statement->fetchColumn();

    return $value !== false ? $value : $defaultValue;
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

function normalize_admin_vote_categories_input($categoriesInput)
{
    if (!is_array($categoriesInput) || count($categoriesInput) !== 4) {
        json_response(array('error' => 'Exactly four vote categories are required.'), 422);
    }

    $normalized = array();

    foreach ($categoriesInput as $index => $entry) {
        if (!is_array($entry)) {
            json_response(array('error' => 'Each vote category must be an object.'), 422);
        }

        $name = isset($entry['name']) ? trim($entry['name']) : '';
        $icon = isset($entry['icon']) ? trim($entry['icon']) : '';
        $id = isset($entry['id']) ? (int) $entry['id'] : 0;

        if ($name === '' || $icon === '') {
            json_response(array('error' => 'Each vote category requires a name and icon.'), 422);
        }

        $normalized[] = array(
            'id' => $id,
            'name' => $name,
            'slug' => normalize_slug($name),
            'icon' => $icon,
            'display_order' => $index + 1,
            'is_primary' => $index === 0 ? 1 : 0,
            'is_active' => 1,
        );
    }

    return $normalized;
}

function save_vote_categories($pdo, $classId, $categories)
{
    $pdo->beginTransaction();

    try {
        $existingById = array();
        $existingRows = get_vote_categories_for_class($pdo, $classId, true);

        foreach ($existingRows as $existingRow) {
            $existingById[(int) $existingRow['id']] = $existingRow;
        }

        $keptIds = array();
        $updateStatement = $pdo->prepare(
            'UPDATE vote_categories
             SET name = ?, slug = ?, icon = ?, display_order = ?, is_primary = ?, is_active = 1
             WHERE id = ? AND class_id = ?'
        );
        $insertStatement = $pdo->prepare(
            'INSERT INTO vote_categories (class_id, name, slug, icon, display_order, is_primary, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );

        foreach ($categories as $category) {
            if ($category['id'] > 0 && isset($existingById[$category['id']])) {
                $updateStatement->execute(
                    array(
                        $category['name'],
                        $category['slug'],
                        $category['icon'],
                        (int) $category['display_order'],
                        (int) $category['is_primary'],
                        (int) $category['id'],
                        (int) $classId,
                    )
                );
                $keptIds[] = (int) $category['id'];
                continue;
            }

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
            $keptIds[] = (int) $pdo->lastInsertId();
        }

        if (count($keptIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($keptIds), '?'));
            $disableStatement = $pdo->prepare(
                'UPDATE vote_categories
                 SET is_active = 0, is_primary = 0
                 WHERE class_id = ? AND id NOT IN (' . $placeholders . ')'
            );
            $disableStatement->execute(array_merge(array((int) $classId), $keptIds));
        }

        $pdo->commit();
    } catch (Exception $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function public_settings()
{
    $pdo = get_pdo();
    $class = resolve_active_class($pdo);
    $settings = fetch_site_settings($pdo, $class['id']);
    $voteCategories = get_vote_categories_for_class($pdo, $class['id']);

    json_response(array('class' => $class, 'settings' => $settings, 'vote_categories' => $voteCategories), 200);
}

function admin_settings()
{
    require_auth(array('admin'));
    $pdo = get_pdo();
    $class = resolve_active_class($pdo, array('allow_inactive' => true));
    $settings = array_merge(
        fetch_site_settings($pdo, $class['id']),
        array(
            'manager_registration_code' => fetch_class_setting_value($pdo, $class['id'], 'manager_registration_code', ''),
            'password_reset_code' => fetch_class_setting_value($pdo, $class['id'], 'password_reset_code', ''),
        )
    );
    $voteCategories = get_vote_categories_for_class($pdo, $class['id'], true);

    json_response(array('class' => $class, 'settings' => $settings, 'vote_categories' => $voteCategories), 200);
}

function update_admin_settings()
{
    require_auth(array('admin'));
    $pdo = get_pdo();
    $input = read_json_body();
    require_fields($input, array('site_logo_text', 'home_heading', 'vote_categories'));

    $logoText = trim($input['site_logo_text']);
    $homeHeading = trim($input['home_heading']);
    $class = resolve_active_class($pdo, array('allow_inactive' => true));

    if ($logoText === '' || $homeHeading === '') {
        json_response(array('error' => 'Site logo text and home heading are required.'), 422);
    }

    $normalizedCategories = normalize_admin_vote_categories_input($input['vote_categories']);

    upsert_class_setting($pdo, $class['id'], 'site_logo_text', $logoText);
    upsert_class_setting($pdo, $class['id'], 'home_heading', $homeHeading);
    if (array_key_exists('manager_registration_code', $input)) {
        upsert_class_setting($pdo, $class['id'], 'manager_registration_code', trim((string) $input['manager_registration_code']));
    }
    if (array_key_exists('password_reset_code', $input)) {
        upsert_class_setting($pdo, $class['id'], 'password_reset_code', trim((string) $input['password_reset_code']));
    }
    save_vote_categories($pdo, $class['id'], $normalizedCategories);

    json_response(
        array(
            'message' => 'Site settings updated.',
            'class' => $class,
            'settings' => array_merge(
                fetch_site_settings($pdo, $class['id']),
                array(
                    'manager_registration_code' => fetch_class_setting_value($pdo, $class['id'], 'manager_registration_code', ''),
                    'password_reset_code' => fetch_class_setting_value($pdo, $class['id'], 'password_reset_code', ''),
                )
            ),
            'vote_categories' => get_vote_categories_for_class($pdo, $class['id'], true),
        ),
        200
    );
}
