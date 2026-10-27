<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
$secret = trim((string)($config['line']['channel_secret'] ?? ''));
$body = (string)file_get_contents('php://input');
$signature = (string)($_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '');

if ($secret === '') {
    http_response_code(503);
    exit('LINE webhook is not configured');
}
$expected = base64_encode(hash_hmac('sha256', $body, $secret, true));
if ($signature === '' || !hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

require __DIR__ . '/../app/bootstrap.php';

function reply_line(string $replyToken, string $message): void
{
    $token = trim((string)cfg('line.channel_access_token', ''));
    if ($token === '' || $replyToken === '') return;
    $payload = json_encode(['replyToken'=>$replyToken,'messages'=>[['type'=>'text','text'=>$message]]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $context = stream_context_create(['http'=>[
        'method'=>'POST','timeout'=>5,'ignore_errors'=>true,
        'header'=>"Content-Type: application/json\r\nAuthorization: Bearer $token\r\n",
        'content'=>$payload,
    ],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    @file_get_contents((string)cfg('line.reply_api_url','https://api.line.me/v2/bot/message/reply'),false,$context);
}

try {
    $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    foreach (($payload['events'] ?? []) as $event) {
        $replyToken = (string)($event['replyToken'] ?? '');
        $lineUserId = (string)($event['source']['userId'] ?? '');
        if (($event['type'] ?? '') === 'follow') {
            reply_line($replyToken, "เพิ่มเพื่อนสำเร็จ\nกรุณาเข้าสู่ระบบ Smart Office Stock เลือกเมนู เชื่อมต่อ LINE แล้วส่งข้อความ ลงทะเบียน ตามด้วยรหัส");
            continue;
        }
        if (($event['type'] ?? '') !== 'message' || ($event['message']['type'] ?? '') !== 'text') continue;
        $text = trim((string)($event['message']['text'] ?? ''));
        if (!preg_match('/^ลงทะเบียน\s+([A-HJ-NP-Z2-9]{8})$/iu', $text, $match)) continue;
        try {
            $linked = (new LineAccountLinkService(db()))->consumeCode($match[1], $lineUserId);
            reply_line($replyToken, "เชื่อมต่อสำเร็จ\nบัญชีระบบ: {$linked['full_name']}\nจากนี้คุณจะได้รับผลคำขอผ่าน LINE บัญชีนี้");
        } catch (DomainException $error) {
            reply_line($replyToken, 'เชื่อมต่อไม่สำเร็จ: '.$error->getMessage());
        }
    }
} catch (Throwable $error) {
    error_log('LINE webhook error: '.$error->getMessage());
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo '{"ok":true}';
