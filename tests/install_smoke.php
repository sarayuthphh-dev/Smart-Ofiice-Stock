<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit("CLI only\n");
$dsn = (string)getenv('INSTALL_TEST_DSN');
$dbUser = (string)getenv('INSTALL_TEST_DB_USER');
$dbPassword = (string)getenv('INSTALL_TEST_DB_PASSWORD');
if (!preg_match('/dbname=([A-Za-z0-9_]+)/i', $dsn, $match) || !str_ends_with($match[1], '_install_test')) {
    exit("REFUSE: INSTALL_TEST_DSN database name must end with _install_test\n");
}
$database = $match[1];
$serverDsn = (string)preg_replace('/;?dbname=[^;]*/i', '', $dsn);
$root = dirname(__DIR__);
$pdo = new PDO($serverDsn, $dbUser, $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$command = [PHP_BINARY];
if (PHP_OS_FAMILY === 'Windows') {
    $command = array_merge($command, ['-n', '-d', 'extension_dir=' . ini_get('extension_dir'), '-d', 'extension=php_pdo_mysql.dll', '-d', 'extension=php_mbstring.dll']);
}
$command[] = $root . '/bin/install.php';
$environment = array_merge($_ENV, [
    'DB_DSN' => $dsn,
    'DB_USER' => $dbUser,
    'DB_PASSWORD' => $dbPassword,
    'SOS_CONFIG_FILE' => $root . '/config/config.example.php',
    'SOS_ADMIN_USERNAME' => 'install_admin',
    'SOS_ADMIN_FULL_NAME' => 'Install Smoke Admin',
    'SOS_ADMIN_PASSWORD' => 'Install-test-123',
]);

function runInstaller(array $command, array $environment, string $workingDirectory): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDirectory, $environment);
    if (!is_resource($process)) throw new RuntimeException('Cannot start installer process.');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}

try {
    $pdo->exec("DROP DATABASE IF EXISTS $quotedDatabase");
    $pdo->exec("CREATE DATABASE $quotedDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    [$firstCode, $firstOut, $firstError] = runInstaller($command, $environment, $root);
    if ($firstCode !== 0) throw new RuntimeException("Fresh install failed: $firstOut $firstError");
    [$secondCode, $secondOut, $secondError] = runInstaller($command, $environment, $root);
    if ($secondCode !== 0) throw new RuntimeException("Second install failed: $secondOut $secondError");

    $installed = new PDO($dsn, $dbUser, $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $adminCount = (int)$installed->query("SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.username='install_admin' AND r.code='admin'")->fetchColumn();
    $tableCount = (int)$installed->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn();
    $settingCount = (int)$installed->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    $storageReady = is_dir($root . '/storage/uploads') && is_dir($root . '/storage/branding') && is_dir($root . '/storage/logs');
    $idempotent = str_contains($secondOut, 'schema') && $adminCount === 1;
    if ($adminCount !== 1 || $tableCount < 20 || $settingCount < 20 || !$storageReady || !$idempotent) {
        throw new RuntimeException("Post-install verification failed: admin=$adminCount tables=$tableCount settings=$settingCount storage=" . ($storageReady ? 'yes' : 'no'));
    }
    echo "INSTALL_SMOKE_PASS: fresh install, first admin, storage, and repeat install verified\n";
} catch (Throwable $error) {
    fwrite(STDERR, "INSTALL_SMOKE_FAIL: {$error->getMessage()}\n");
    $exitCode = 1;
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS $quotedDatabase");
}
exit($exitCode ?? 0);
