<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_role('supply','admin');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="material-import-template.csv"');
echo "\xEF\xBB\xBF";
$out=fopen('php://output','wb');
fputcsv($out,['code','name','category','unit','minimum_qty','location','description','active','track_assignee'],',','"','');
fputcsv($out,['PEN-BLUE-05','ปากกาลูกลื่น สีน้ำเงิน 0.5 มม.','เครื่องเขียน','ด้าม','10','ชั้น B1','ตัวอย่างสำหรับแก้ไขก่อนนำเข้า','1','0'],',','"','');
fclose($out);
