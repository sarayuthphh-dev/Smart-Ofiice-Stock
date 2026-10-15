<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';require __DIR__ . '/../app/Layout.php';require_role('admin','supply');
$url=rtrim((string)cfg('app.base_url'),'/').'/index.php?page=request_form';render_header('QR Code เข้าใช้งาน',true);?>
<main class="container"><div class="page-head"><div><h1>QR Code สำหรับเข้าเบิกวัสดุ</h1><p>เมื่อสแกนแล้ว ผู้ใช้ต้องเข้าสู่ระบบก่อนส่งคำขอ</p></div></div><section class="card" style="text-align:center"><div id="qrcode" style="display:inline-block;padding:18px;background:#fff"></div><p><a href="<?=h($url)?>"><?=h($url)?></a></p><button class="btn primary" onclick="print()">พิมพ์ QR Code</button><noscript><p class="alert error">ต้องเปิด JavaScript เพื่อสร้าง QR Code</p></noscript></section></main>
<script src="assets/qrcode.min.js"></script><script>new QRCode(document.getElementById('qrcode'),{text:<?=json_encode($url,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,width:256,height:256,correctLevel:QRCode.CorrectLevel.M});</script><?php render_footer();

