<?php
declare(strict_types=1);

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(): PDO { global $pdo; return $pdo; }
function cfg(string $path, mixed $default = null): mixed {
    global $config; $value = $config;
    foreach (explode('.', $path) as $key) { if (!is_array($value) || !array_key_exists($key, $value)) return $default; $value = $value[$key]; }
    return $value;
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void {
    $token = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) { http_response_code(419); exit('CSRF token ไม่ถูกต้อง กรุณาย้อนกลับและลองใหม่'); }
}
function user(): ?array {
    static $cached = false; if ($cached !== false) return $cached;
    if (empty($_SESSION['user_id'])) return $cached = null;
    $s = db()->prepare('SELECT * FROM users WHERE id=? AND active=1'); $s->execute([$_SESSION['user_id']]); $u = $s->fetch();
    if (!$u) { session_destroy(); return $cached = null; }
    $s = db()->prepare('SELECT r.code FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?'); $s->execute([$u['id']]);
    $u['roles'] = $s->fetchAll(PDO::FETCH_COLUMN); return $cached = $u;
}
function require_login(): array { $u = user(); if (!$u) redirect('?page=login'); return $u; }
function has_role(string ...$roles): bool { $u = user(); return $u && (bool)array_intersect($roles, $u['roles']); }
function require_role(string ...$roles): void { require_login(); if (!has_role(...$roles)) { http_response_code(403); exit('คุณไม่มีสิทธิ์ดำเนินการนี้'); } }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type','message'); }
function take_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function thai_date(?string $date, bool $time = true): string {
    if (!$date) return '-'; $ts = strtotime($date); $months = [1=>'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $text = date('j', $ts) . ' ' . $months[(int)date('n',$ts)] . ' ' . ((int)date('Y',$ts)+543);
    return $time ? $text . ' ' . date('H:i', $ts) . ' น.' : $text;
}
function qty(mixed $n): string { return rtrim(rtrim(number_format((float)$n, 3, '.', ','), '0'), '.'); }
function document_no(string $prefix): string { return $prefix . '-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(4))); }
function audit(string $event, string $entity, ?int $id, mixed $old = null, mixed $new = null): void {
    $s=db()->prepare('INSERT INTO audit_logs(actor_id,event_type,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?)');
    $s->execute([user()['id']??null,$event,$entity,$id,json_encode($old,JSON_UNESCAPED_UNICODE),json_encode($new,JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);
}
function ensure_positive(mixed $value, string $label='จำนวน'): float { $n=filter_var($value,FILTER_VALIDATE_FLOAT); if ($n===false||$n<=0) throw new DomainException("$label ต้องมากกว่า 0"); return round((float)$n,3); }
function post_string(string $key, int $max=500, bool $required=true): string { $v=trim((string)($_POST[$key]??'')); if ($required&&$v==='') throw new DomainException("กรุณากรอก $key"); if (mb_strlen($v)>$max) throw new DomainException("$key ยาวเกินกำหนด"); return $v; }
function setting(string $key, ?string $default=null): ?string { $s=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v; }
function password_meets_minimum(string $password): bool { return mb_strlen($password,'UTF-8')>=6; }
function status_th(string $s): string { return ['draft'=>'แบบร่าง','pending'=>'รออนุมัติ','returned'=>'ส่งกลับแก้ไข','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ','cancelled'=>'ยกเลิก','partially_issued'=>'จ่ายบางส่วน','issued'=>'จ่ายครบ · รอยืนยันรับ','closed'=>'ปิดยอด · รอยืนยันรับ','completed'=>'เสร็จสมบูรณ์'][$s]??$s; }
function pending_receipt_count(int $userId): int {$s=db()->prepare('SELECT COUNT(*) FROM issues i JOIN requisitions r ON r.id=i.requisition_id LEFT JOIN receipts_confirmation rc ON rc.issue_id=i.id WHERE r.requester_id=? AND rc.id IS NULL');$s->execute([$userId]);return(int)$s->fetchColumn();}
