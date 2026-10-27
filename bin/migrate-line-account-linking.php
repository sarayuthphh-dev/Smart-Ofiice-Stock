<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("CLI only\n");
$config = require __DIR__ . '/../config/config.php';
$pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = (string)file_get_contents(__DIR__ . '/../database/migrate_line_account_linking.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $pdo->exec($statement);
$index = $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='users' AND index_name='uq_users_line_user_id'")->fetchColumn();
if (!(int)$index) $pdo->exec('CREATE UNIQUE INDEX uq_users_line_user_id ON users(line_user_id)');
echo "ติดตั้งระบบผูกบัญชี LINE เรียบร้อย\n";
