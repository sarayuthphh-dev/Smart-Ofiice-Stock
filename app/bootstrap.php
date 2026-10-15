<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('ยังไม่ได้ตั้งค่าระบบ: คัดลอก config/config.example.php เป็น config/config.php');
}
$config = require $configFile;
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

if (PHP_VERSION_ID < 80200) {
    http_response_code(503);
    exit('ระบบต้องใช้ PHP 8.2 ขึ้นไป รุ่นปัจจุบันคือ ' . PHP_VERSION);
}

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_name($config['app']['session_name'] ?? 'sos_session');
session_start();

try {
    $pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (Throwable $e) {
    http_response_code(503);
    exit(($config['app']['debug'] ?? false) ? 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'เชื่อมต่อฐานข้อมูลไม่ได้ กรุณาตรวจ configuration');
}

require_once __DIR__ . '/Support.php';
require_once __DIR__ . '/StockService.php';
require_once __DIR__ . '/RequestService.php';
require_once __DIR__ . '/MaterialImportService.php';
require_once __DIR__ . '/LineNotificationService.php';
require_once __DIR__ . '/StockIntegrityService.php';
require_once __DIR__ . '/ExecutiveReportService.php';
