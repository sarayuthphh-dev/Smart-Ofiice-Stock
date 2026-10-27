<?php
declare(strict_types=1);

final class LineAccountLinkService
{
    public function __construct(private readonly PDO $db) {}

    public function createCode(int $userId): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $hash = hash('sha256', $code);

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE line_link_codes SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
            $this->db->prepare('DELETE FROM line_link_codes WHERE expires_at<NOW() OR used_at<DATE_SUB(NOW(),INTERVAL 7 DAY)')->execute();
            $this->db->prepare('INSERT INTO line_link_codes(user_id,code_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE))')->execute([$userId, $hash]);
            audit('line.link_code_create', 'user', $userId, null, ['expires_minutes' => 15]);
            $this->db->commit();
            return ['code' => $code, 'expires_minutes' => 15];
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function consumeCode(string $code, string $lineUserId): array
    {
        $code = strtoupper(trim($code));
        $lineUserId = LineNotificationService::validateUserId($lineUserId);
        if (!preg_match('/^[A-HJ-NP-Z2-9]{8}$/', $code)) throw new DomainException('รหัสลงทะเบียนไม่ถูกต้อง');

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT lc.*,u.full_name,u.active FROM line_link_codes lc JOIN users u ON u.id=lc.user_id WHERE lc.code_hash=? FOR UPDATE');
            $statement->execute([hash('sha256', $code)]);
            $link = $statement->fetch();
            if (!$link || $link['used_at'] !== null || strtotime($link['expires_at']) < time()) throw new DomainException('รหัสหมดอายุหรือถูกใช้แล้ว กรุณาสร้างรหัสใหม่ในระบบ');
            if (!(int)$link['active']) throw new DomainException('บัญชีผู้ใช้นี้ถูกปิดใช้งาน');

            $statement = $this->db->prepare('SELECT id FROM users WHERE line_user_id=? AND id<>? FOR UPDATE');
            $statement->execute([$lineUserId, $link['user_id']]);
            if ($statement->fetchColumn()) throw new DomainException('LINE บัญชีนี้ผูกกับผู้ใช้อื่นแล้ว กรุณาติดต่อผู้ดูแลระบบ');

            $this->db->prepare('UPDATE users SET line_user_id=? WHERE id=?')->execute([$lineUserId, $link['user_id']]);
            $this->db->prepare('UPDATE line_link_codes SET used_at=NOW() WHERE id=?')->execute([$link['id']]);
            audit('line.account_link', 'user', (int)$link['user_id'], null, ['line_connected' => true]);
            $this->db->commit();
            return ['user_id' => (int)$link['user_id'], 'full_name' => (string)$link['full_name']];
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function disconnect(int $userId): void
    {
        $this->db->prepare('UPDATE users SET line_user_id=NULL WHERE id=?')->execute([$userId]);
        $this->db->prepare('UPDATE line_link_codes SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
        audit('line.account_unlink', 'user', $userId, null, ['line_connected' => false]);
    }
}
