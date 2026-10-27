<?php
declare(strict_types=1);

final class LineNotificationService
{
    private readonly ?Closure $transport;
    private array $options;

    public function __construct(private readonly PDO $db, array $options = [], ?callable $transport = null)
    {
        $this->options = array_replace([
            'channel_access_token' => (string)cfg('line.channel_access_token', ''),
            'api_url' => (string)cfg('line.api_url', 'https://api.line.me/v2/bot/message/push'),
            'base_url' => (string)cfg('app.base_url', ''),
            'office_name' => (string)setting('office_name', (string)cfg('app.office_name', '')),
            'timeout_seconds' => 5,
        ], $options);
        $this->transport = $transport ? Closure::fromCallable($transport) : null;
    }

    public static function parseRecipients(string $value): array
    {
        $parts = preg_split('/[\s,;]+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $recipients = [];
        foreach ($parts as $id) {
            if (!preg_match('/^U[0-9a-f]{32}$/i', $id)) {
                throw new DomainException('LINE User ID ไม่ถูกต้อง: ' . mb_substr($id, 0, 40));
            }
            $recipients[$id] = true;
        }
        if (count($recipients) > 20) throw new DomainException('กำหนดผู้รับ LINE ได้ไม่เกิน 20 บัญชี');
        return array_keys($recipients);
    }

    public static function validateUserId(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && !preg_match('/^U[0-9a-f]{32}$/i', $value)) throw new DomainException('LINE User ID ต้องขึ้นต้นด้วย U และตามด้วยอักขระ 32 ตัว');
        return $value;
    }

    public function configurationStatus(): array
    {
        $raw = $this->setting('line_recipient_user_ids', '');
        try { $recipients = self::parseRecipients($raw); } catch (DomainException) { $recipients = []; }
        return [
            'enabled' => $this->setting('line_notifications_enabled', '0') === '1',
            'token_configured' => trim((string)$this->options['channel_access_token']) !== '',
            'webhook_configured' => trim((string)cfg('line.channel_secret', '')) !== '',
            'recipient_count' => count($recipients),
        ];
    }

    public function notifyRequisitionSubmitted(int $requisitionId): array
    {
        if ($this->setting('line_notifications_enabled', '0') !== '1') return $this->result(false, 0, 0, 1, 'ปิดการแจ้งเตือน LINE อยู่');
        $token = trim((string)$this->options['channel_access_token']);
        if ($token === '') return $this->result(true, 0, 0, 1, 'ยังไม่ได้ตั้งค่า Channel Access Token');
        try { $recipients = self::parseRecipients($this->setting('line_recipient_user_ids', '')); }
        catch (DomainException $e) { return $this->result(true, 0, 0, 1, $e->getMessage()); }
        if (!$recipients) return $this->result(true, 0, 0, 1, 'ยังไม่ได้กำหนด LINE User ID ผู้รับ');

        $request = $this->loadRequisition($requisitionId);
        if (!$request || $request['status'] !== 'pending') return $this->result(true, 0, 0, 1, 'ไม่พบคำขอที่อยู่ในสถานะรออนุมัติ');
        $message = $this->buildRequisitionMessage($request);
        $actionId = (int)$this->db->query('SELECT MAX(id) FROM requisition_actions WHERE requisition_id=' . (int)$requisitionId . " AND action='submit'")->fetchColumn();
        $eventKey = 'requisition.submitted:' . $requisitionId . ':' . $actionId;
        return $this->sendToRecipients($recipients, $message, $eventKey, $requisitionId);
    }

    public function notifyRequesterStatus(int $requisitionId, string $event, ?int $issueId = null): array
    {
        if ($this->setting('line_notifications_enabled', '0') !== '1') return $this->result(false, 0, 0, 1, 'ปิดการแจ้งเตือน LINE อยู่');
        if (trim((string)$this->options['channel_access_token']) === '') return $this->result(true, 0, 0, 1, 'ยังไม่ได้ตั้งค่า Channel Access Token');
        $request = $this->loadRequisition($requisitionId);
        if (!$request) return $this->result(true, 0, 0, 1, 'ไม่พบคำขอ');
        $recipient = self::validateUserId((string)($request['line_user_id'] ?? ''));
        if ($recipient === '') return $this->result(true, 0, 0, 1, 'ผู้เบิกยังไม่ได้ผูก LINE User ID');
        $titles = [
            'approved' => '✅ คำขอได้รับอนุมัติแล้ว',
            'returned' => '↩️ คำขอถูกส่งกลับให้แก้ไข',
            'rejected' => '❌ คำขอไม่ได้รับอนุมัติ',
            'ready' => '📦 พัสดุจัดเตรียมแล้ว พร้อมรับ',
            'issued' => '📋 บันทึกการจ่ายวัสดุแล้ว',
        ];
        if (!isset($titles[$event])) throw new DomainException('ประเภทการแจ้งเตือนไม่ถูกต้อง');
        $lines = [$titles[$event], (string)$this->options['office_name'], '', 'เลขคำขอ: '.$request['request_no']];
        if (in_array($event, ['returned','rejected'], true)) {
            $s=$this->db->prepare("SELECT reason FROM requisition_actions WHERE requisition_id=? AND action=? ORDER BY id DESC LIMIT 1");$s->execute([$requisitionId,$event==='returned'?'return':'reject']);$lines[]='เหตุผล: '.((string)($s->fetchColumn() ?: '-'));
        } elseif ($event === 'approved') {
            $lines[]='เจ้าหน้าที่พัสดุจะดำเนินการจัดเตรียมวัสดุต่อไป';
        } elseif ($event === 'ready') {
            $lines[]='กรุณาติดต่อเจ้าหน้าที่พัสดุเพื่อรับวัสดุ';
        } else {
            $lines[]='กรุณาตรวจรับวัสดุและกดยืนยันรับของในระบบ';
        }
        $baseUrl=rtrim((string)$this->options['base_url'],'/');if($baseUrl!==''){$lines[]='';$lines[]='เปิดคำขอ: '.$baseUrl.'/index.php?page=request_view&id='.$requisitionId;}
        $suffix=$issueId ?: (int)$this->db->query('SELECT MAX(id) FROM requisition_actions WHERE requisition_id='.(int)$requisitionId)->fetchColumn();
        return $this->sendToRecipients([$recipient],mb_substr(implode("\n",$lines),0,4900),'requester.'.$event.':'.$requisitionId.':'.$suffix,$requisitionId);
    }

    public function sendLowStockDigest(?DateTimeImmutable $now = null): array
    {
        if ($this->setting('line_notifications_enabled', '0') !== '1') return $this->result(false, 0, 0, 1, 'ปิดการแจ้งเตือน LINE อยู่');
        if (trim((string)$this->options['channel_access_token']) === '') return $this->result(true, 0, 0, 1, 'ยังไม่ได้ตั้งค่า Channel Access Token');
        try { $recipients=self::parseRecipients($this->setting('line_recipient_user_ids','')); } catch(DomainException $e) { return $this->result(true,0,0,1,$e->getMessage()); }
        if(!$recipients)return $this->result(true,0,0,1,'ยังไม่ได้กำหนด LINE User ID ผู้รับ');
        $rows=$this->db->query('SELECT code,name,unit,stock_qty,minimum_qty FROM materials WHERE active=1 AND stock_qty<=minimum_qty ORDER BY stock_qty, name')->fetchAll();
        if(!$rows)return $this->result(true,0,0,1,'ไม่มีวัสดุต่ำกว่ายอดขั้นต่ำ');
        $lines=['⚠️ วัสดุต่ำกว่ายอดขั้นต่ำ',(string)$this->options['office_name'],''];foreach(array_slice($rows,0,15)as$row)$lines[]='• '.$row['code'].' '.$row['name'].' เหลือ '.qty($row['stock_qty']).' '.$row['unit'].' (ขั้นต่ำ '.qty($row['minimum_qty']).')';if(count($rows)>15)$lines[]='• และอีก '.(count($rows)-15).' รายการ';
        $now??=new DateTimeImmutable('now',new DateTimeZone((string)cfg('app.timezone','Asia/Bangkok')));$baseUrl=rtrim((string)$this->options['base_url'],'/');if($baseUrl!==''){$lines[]='';$lines[]='ดูรายงาน: '.$baseUrl.'/index.php?page=reports&type=low';}
        return $this->sendToRecipients($recipients,mb_substr(implode("\n",$lines),0,4900),'stock.low:'.$now->format('Y-m-d'),null);
    }

    public function overdueSummary(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone((string)cfg('app.timezone', 'Asia/Bangkok')));
        $pendingDays = max(1, min(90, (int)$this->setting('reminder_pending_days', '1')));
        $issueDays = max(1, min(90, (int)$this->setting('reminder_issue_days', '1')));
        $receiptDays = max(1, min(90, (int)$this->setting('reminder_receipt_days', '2')));
        $fetch = function(string $sql, string $cutoff): array {$s=$this->db->prepare($sql);$s->execute([$cutoff]);return$s->fetchAll();};
        $pending = $fetch("SELECT id,request_no,submitted_at FROM requisitions WHERE status='pending' AND submitted_at<=? ORDER BY submitted_at", $now->modify("-$pendingDays days")->format('Y-m-d H:i:s'));
        $issues = $fetch("SELECT id,request_no,approved_at FROM requisitions WHERE status IN('approved','partially_issued') AND approved_at<=? ORDER BY approved_at", $now->modify("-$issueDays days")->format('Y-m-d H:i:s'));
        $receipts = $fetch("SELECT i.id,i.issue_no,r.request_no,i.issued_at FROM issues i JOIN requisitions r ON r.id=i.requisition_id LEFT JOIN receipts_confirmation rc ON rc.issue_id=i.id WHERE rc.id IS NULL AND i.issued_at<=? ORDER BY i.issued_at", $now->modify("-$receiptDays days")->format('Y-m-d H:i:s'));
        return compact('pendingDays','issueDays','receiptDays','pending','issues','receipts');
    }

    public function sendOverdueDigest(?DateTimeImmutable $now = null): array
    {
        if ($this->setting('line_notifications_enabled', '0') !== '1' || $this->setting('reminder_notifications_enabled', '0') !== '1') return $this->result(false, 0, 0, 1, 'ปิดการแจ้งเตือนงานค้างอยู่');
        $token = trim((string)$this->options['channel_access_token']);
        if ($token === '') return $this->result(true, 0, 0, 1, 'ยังไม่ได้ตั้งค่า Channel Access Token');
        try { $recipients = self::parseRecipients($this->setting('line_recipient_user_ids', '')); }
        catch (DomainException $e) { return $this->result(true, 0, 0, 1, $e->getMessage()); }
        if (!$recipients) return $this->result(true, 0, 0, 1, 'ยังไม่ได้กำหนด LINE User ID ผู้รับ');
        $now ??= new DateTimeImmutable('now', new DateTimeZone((string)cfg('app.timezone', 'Asia/Bangkok')));
        $summary = $this->overdueSummary($now);$total=count($summary['pending'])+count($summary['issues'])+count($summary['receipts']);
        if ($total===0) return $this->result(true, 0, 0, 1, 'ไม่มีงานค้างเกินกำหนด');
        $lines=['🔔 สรุปงานวัสดุค้างดำเนินการ',(string)$this->options['office_name'],'','รออนุมัติเกิน '.$summary['pendingDays'].' วัน: '.count($summary['pending']).' คำขอ','รอจ่ายเกิน '.$summary['issueDays'].' วัน: '.count($summary['issues']).' คำขอ','รอยืนยันรับเกิน '.$summary['receiptDays'].' วัน: '.count($summary['receipts']).' ใบจ่าย'];
        foreach(array_slice(array_merge(array_column($summary['pending'],'request_no'),array_column($summary['issues'],'request_no'),array_column($summary['receipts'],'issue_no')),0,10)as$no)$lines[]='• '.$no;
        if($total>10)$lines[]='• และอีก '.($total-10).' รายการ';$baseUrl=rtrim((string)$this->options['base_url'],'/');if($baseUrl!==''){$lines[]='';$lines[]='เปิดระบบ: '.$baseUrl.'/index.php';}
        return $this->sendToRecipients($recipients,mb_substr(implode("\n",$lines),0,4900),'overdue.digest:'.$now->format('Y-m-d'),null);
    }

    public function sendTest(string $actorName): array
    {
        $token = trim((string)$this->options['channel_access_token']);
        if ($token === '') return $this->result(true, 0, 0, 1, 'ยังไม่ได้ตั้งค่า Channel Access Token ใน config/config.php');
        try { $recipients = self::parseRecipients($this->setting('line_recipient_user_ids', '')); }
        catch (DomainException $e) { return $this->result(true, 0, 0, 1, $e->getMessage()); }
        if (!$recipients) return $this->result(true, 0, 0, 1, 'ยังไม่ได้กำหนด LINE User ID ผู้รับ');
        $message = "ทดสอบการแจ้งเตือน Smart Office Stock\n" . (string)$this->options['office_name'] . "\nส่งโดย: " . $actorName . "\nเวลา: " . thai_date(date('Y-m-d H:i:s'));
        return $this->sendToRecipients($recipients, $message, 'line.test:' . bin2hex(random_bytes(12)), null);
    }

    private function loadRequisition(int $id): ?array
    {
        $s = $this->db->prepare('SELECT r.*,u.full_name,u.line_user_id,g.name group_name FROM requisitions r JOIN users u ON u.id=r.requester_id LEFT JOIN work_groups g ON g.id=r.work_group_id WHERE r.id=?');
        $s->execute([$id]); $request = $s->fetch();
        if (!$request) return null;
        $s = $this->db->prepare('SELECT m.code,m.name,m.unit,ri.requested_qty FROM requisition_items ri JOIN materials m ON m.id=ri.material_id WHERE ri.requisition_id=? ORDER BY ri.id');
        $s->execute([$id]); $request['items'] = $s->fetchAll();
        return $request;
    }

    private function buildRequisitionMessage(array $request): string
    {
        $lines = [
            '📦 มีคำขอเบิกวัสดุใหม่',
            (string)$this->options['office_name'],
            '',
            'เลขคำขอ: ' . ($request['request_no'] ?: '#' . $request['id']),
            'ผู้เบิก: ' . $request['full_name'],
            'กลุ่มงาน: ' . ($request['group_name'] ?: '-'),
            'วัตถุประสงค์: ' . mb_substr(trim((string)$request['purpose']), 0, 300),
            'รายการ:',
        ];
        foreach (array_slice($request['items'], 0, 12) as $item) {
            $lines[] = '• ' . $item['name'] . ' ' . qty($item['requested_qty']) . ' ' . $item['unit'];
        }
        if (count($request['items']) > 12) $lines[] = '• และอีก ' . (count($request['items']) - 12) . ' รายการ';
        $baseUrl = rtrim((string)$this->options['base_url'], '/');
        if ($baseUrl !== '') {
            $lines[] = '';
            $lines[] = 'ตรวจสอบคำขอ: ' . $baseUrl . '/index.php?page=request_view&id=' . (int)$request['id'];
            $lines[] = 'กรุณาเข้าสู่ระบบก่อนเปิดดูข้อมูล';
        }
        return mb_substr(implode("\n", $lines), 0, 4900);
    }

    private function sendToRecipients(array $recipients, string $message, string $eventKey, ?int $requisitionId): array
    {
        $sent = 0; $failed = 0; $skipped = 0; $errors = [];
        foreach ($recipients as $recipient) {
            $deliveryId = $this->reserveDelivery($eventKey, $requisitionId, $recipient);
            if ($deliveryId === null) { $skipped++; continue; }
            $retryKey = $this->retryKey($eventKey . ':' . $recipient);
            try {
                $response = $this->post($recipient, $message, $retryKey);
                $httpStatus = (int)($response['status'] ?? 0);
                $body = mb_substr((string)($response['body'] ?? ''), 0, 1000);
                if ($httpStatus >= 200 && $httpStatus < 300) {
                    $this->finishDelivery($deliveryId, 'sent', $httpStatus, null); $sent++;
                } else {
                    $error = $this->responseError($body, $httpStatus); $this->finishDelivery($deliveryId, 'failed', $httpStatus, $error); $errors[] = $error; $failed++;
                }
            } catch (Throwable $e) {
                $error = mb_substr($e->getMessage(), 0, 1000); $this->finishDelivery($deliveryId, 'failed', null, $error); $errors[] = $error; $failed++;
            }
        }
        $messageText = $failed ? implode(' | ', array_unique($errors)) : ($sent ? 'ส่งสำเร็จ' : 'ไม่มีรายการที่ต้องส่งซ้ำ');
        return $this->result(true, $sent, $failed, $skipped, $messageText);
    }

    private function reserveDelivery(string $eventKey, ?int $requisitionId, string $recipient): ?int
    {
        try {
            $s = $this->db->prepare("INSERT INTO notification_deliveries(event_key,requisition_id,channel,recipient,status) VALUES(?,?,'line',?,'sending')");
            $s->execute([$eventKey, $requisitionId, $recipient]);
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') return null;
            throw $e;
        }
    }

    private function finishDelivery(int $id, string $status, ?int $httpStatus, ?string $error): void
    {
        $s = $this->db->prepare('UPDATE notification_deliveries SET status=?,http_status=?,error_message=?,sent_at=IF(?="sent",NOW(),NULL) WHERE id=?');
        $s->execute([$status, $httpStatus, $error, $status, $id]);
    }

    private function post(string $recipient, string $message, string $retryKey): array
    {
        $payload = ['to' => $recipient, 'messages' => [['type' => 'text', 'text' => $message]], 'notificationDisabled' => false];
        if ($this->transport) return ($this->transport)((string)$this->options['api_url'], (string)$this->options['channel_access_token'], $payload, $retryKey);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $context = stream_context_create(['http' => [
            'method' => 'POST', 'timeout' => max(2, (int)$this->options['timeout_seconds']), 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . (string)$this->options['channel_access_token'] . "\r\nX-Line-Retry-Key: $retryKey\r\n",
            'content' => $json,
        ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $body = @file_get_contents((string)$this->options['api_url'], false, $context);
        $headers = $http_response_header ?? [];
        $status = 0;
        if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $m)) $status = (int)$m[1];
        if ($body === false && $status === 0) {
            $last = error_get_last(); throw new RuntimeException('เชื่อมต่อ LINE Messaging API ไม่สำเร็จ' . (!empty($last['message']) ? ': ' . $last['message'] : ''));
        }
        return ['status' => $status, 'body' => $body === false ? '' : $body];
    }

    private function responseError(string $body, int $status): string
    {
        $decoded = json_decode($body, true); $detail = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
        return 'LINE API HTTP ' . $status . ($detail !== '' ? ': ' . $detail : '');
    }

    private function setting(string $key, string $default): string
    {
        $s = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key=?'); $s->execute([$key]); $value = $s->fetchColumn();
        return $value === false ? $default : (string)$value;
    }

    private function retryKey(string $value): string
    {
        $hex = substr(hash('sha256', $value), 0, 32);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
    }

    private function result(bool $enabled, int $sent, int $failed, int $skipped, string $message): array
    {
        return compact('enabled', 'sent', 'failed', 'skipped', 'message');
    }
}
