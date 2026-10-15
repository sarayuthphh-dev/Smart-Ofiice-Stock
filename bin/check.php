<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("CLI only\n");
$checks = [
    'PHP >= 8.2' => PHP_VERSION_ID >= 80200,
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'fileinfo' => extension_loaded('fileinfo'),
    'JSON' => extension_loaded('json'),
    'OpenSSL' => extension_loaded('openssl'),
    'allow_url_fopen (LINE API)' => filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN),
];
$ok = true;
foreach ($checks as $name => $passed) { echo ($passed ? '[ผ่าน] ' : '[ไม่ผ่าน] ') . $name . "\n"; $ok = $ok && $passed; }
exit($ok ? 0 : 1);
