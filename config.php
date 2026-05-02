<?php
// ============================================================
// config.php — читает настройки из data/settings.json
// Менять настройки нужно через /admin/, а не здесь
// ============================================================

define('MS_API_BASE', 'https://api.moysklad.ru/api/remap/1.2');

$settingsFile = __DIR__ . '/data/settings.json';

if (!file_exists($settingsFile)) {
    define('MS_TOKEN',           '');
    define('MS_PRICELIST_ID',    '');
    define('MS_ORGANIZATION_ID', '');
    define('NOTIFY_EMAIL',       '');
    define('ALLOWED_ORIGIN',     '');
} else {
    $s = json_decode(file_get_contents($settingsFile), true) ?? [];
    define('MS_TOKEN',           $s['ms_token']           ?? '');
    define('MS_PRICELIST_ID',    $s['ms_pricelist_id']    ?? '');
    define('MS_ORGANIZATION_ID', $s['ms_organization_id'] ?? '');
    define('NOTIFY_EMAIL',       $s['notify_email']       ?? '');
    define('ALLOWED_ORIGIN',     $s['allowed_origin']     ?? '');
}

error_reporting(0);
ini_set('display_errors', 0);
