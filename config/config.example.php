<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'ระบบเบิกวัสดุสำนักงานออนไลน์',
        'office_name' => 'สำนักงานตรวจบัญชีสหกรณ์อุทัยธานี',
        'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost/smart-office-stock/public',
        'timezone' => 'Asia/Bangkok',
        'session_name' => 'sos_session',
        'debug' => false,
    ],
    'db' => [
        'dsn' => getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=smart_office_stock;charset=utf8mb4',
        'user' => getenv('DB_USER') ?: 'smart_stock',
        'password' => getenv('DB_PASSWORD') ?: 'CHANGE_ME',
    ],
    'upload' => [
        'directory' => dirname(__DIR__) . '/storage/uploads',
        'max_bytes' => 2 * 1024 * 1024,
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
    'line' => [
        // สร้างจาก LINE Developers Console > Messaging API ห้าม commit token จริงลง Git
        'channel_access_token' => getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '',
        // ใช้ตรวจลายเซ็น Webhook จาก LINE Developers Console > Basic settings
        'channel_secret' => getenv('LINE_CHANNEL_SECRET') ?: '',
        'api_url' => 'https://api.line.me/v2/bot/message/push',
        'reply_api_url' => 'https://api.line.me/v2/bot/message/reply',
    ],
];
