<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit("CLI only\n");
require __DIR__.'/../app/bootstrap.php';

$integrity=(new StockIntegrityService(db()))->inspect();
echo '[STOCK] ตรวจสอบ '.$integrity['total'].' รายการ: ถูกต้อง '.$integrity['ok'].', ไม่ตรง '.$integrity['mismatch_count'].PHP_EOL;
foreach($integrity['mismatches']as$row)echo '  - '.$row['code'].' '.$row['name'].' ผลต่าง '.qty($row['difference']).' '.$row['unit'].PHP_EOL;

$line=(new LineNotificationService(db()))->sendOverdueDigest();
echo '[REMINDER] '.$line['message'].' (ส่ง '.$line['sent'].', ล้มเหลว '.$line['failed'].', ข้าม '.$line['skipped'].')'.PHP_EOL;
$low=(new LineNotificationService(db()))->sendLowStockDigest();
echo '[LOW STOCK] '.$low['message'].' (ส่ง '.$low['sent'].', ล้มเหลว '.$low['failed'].', ข้าม '.$low['skipped'].')'.PHP_EOL;
exit(($line['failed']+$low['failed'])>0?1:($integrity['mismatch_count']>0?2:0));
