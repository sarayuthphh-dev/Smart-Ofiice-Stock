<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("CLI only\n");
$config = require __DIR__.'/../config/config.php';
$pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = (string)file_get_contents(__DIR__.'/../database/migrate_requester_line_notifications.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $pdo->exec($statement);
echo "ติดตั้งฟิลด์ LINE User ID รายบัญชีเรียบร้อย\n";
