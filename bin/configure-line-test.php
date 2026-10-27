<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$userId = trim((string)($argv[1] ?? ''));
require __DIR__ . '/../app/bootstrap.php';

try {
    $userId = LineNotificationService::validateUserId($userId);
    if ($userId === '') throw new DomainException('กรุณาระบุ LINE User ID');

    $db = db();
    $db->beginTransaction();
    $statement = $db->prepare(
        'INSERT INTO settings(setting_key,setting_value) VALUES(?,?) '
        . 'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    $statement->execute(['line_recipient_user_ids', $userId]);
    $statement->execute(['line_notifications_enabled', '1']);
    $db->commit();

    $result = (new LineNotificationService($db))->sendTest('ผู้ดูแลระบบ');
    if (($result['sent'] ?? 0) < 1 || ($result['failed'] ?? 0) > 0) {
        throw new RuntimeException((string)($result['message'] ?? 'ส่งข้อความไม่สำเร็จ'));
    }

    echo "SUCCESS: ส่งข้อความทดสอบสำเร็จ {$result['sent']} บัญชี\n";
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
