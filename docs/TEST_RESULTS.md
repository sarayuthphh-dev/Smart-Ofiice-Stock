# ผลการทดสอบที่รันจริง

วันที่ทดสอบ: 12 ตุลาคม 2569 (Asia/Bangkok)

สภาพแวดล้อม isolated: PHP CLI 8.5.11 NTS, MariaDB 11.4.0, Windows x64 ใช้ฐานข้อมูลที่ชื่อลงท้าย `_test` ไม่แตะ MySQL 5.0 เดิมของเครื่อง

## Automated integration

คำสั่ง `php tests/integration.php` (เปิด extensions ตาม README) ให้ผล **PASS 19, FAIL 0**:

- PASS อนุมัติแล้วสต๊อกยังไม่ลด
- PASS เริ่ม 50 รีม จ่าย 3 รีม เหลือ 47 รีม
- PASS จ่ายบางส่วน/จ่ายเพิ่ม และปฏิเสธเมื่อยอดสะสมเกินอนุมัติ
- PASS idempotency key เดิมไม่ตัดสต๊อกซ้ำ
- PASS จ่ายเกินยอดคงเหลือไม่ได้
- PASS คืนสภาพดีเพิ่มยอด ชำรุดไม่เพิ่ม และคืนเกินยอดจ่ายไม่ได้
- PASS ผู้เบิกแก้คำขอของผู้อื่นไม่ได้
- PASS ตรวจนับไม่ยืนยันเมื่อมี movement แทรก
- PASS import CSV เพิ่มรายการใหม่และปรับปรุงรหัสเดิมโดยไม่เปลี่ยนยอดสต๊อก
- PASS วัสดุหมดสต๊อกแสดงข้อความ ปิดตัวเลือก และไม่สามารถสร้างหรือส่งคำขอได้แม้ส่งข้อมูลตรงมายังเซิร์ฟเวอร์
- PASS ค่าภาพลักษณ์และโลโก้มีค่าเริ่มต้น ตรวจ MIME/ขนาดภาพฝั่งเซิร์ฟเวอร์ ส่งไฟล์ด้วย `nosniff` และบันทึก Audit Log
- PASS รหัสผ่าน 5 ตัวถูกปฏิเสธและ 6 ตัวผ่าน ทั้งภาษาไทยและอังกฤษ
- PASS ตั้งค่าชื่อ/การแสดงเมนู แนวกระดาษ ป้ายผู้ลงนาม และ Audit Log ของการตั้งค่าเมนู/รายงานได้
- PASS ส่ง LINE ส่วนตัวให้ผู้รับที่กำหนดเมื่อส่งคำขอ เนื้อหามีรายการและลิงก์ และไม่ส่งเหตุการณ์เดิมซ้ำ (ใช้ transport จำลอง ไม่เรียก LINE จริง)
- PASS เมื่อ LINE API ตอบล้มเหลว คำขอยังคงเป็นรออนุมัติและบันทึกประวัติส่งไม่สำเร็จครบทุกผู้รับ
- PASS ผู้ดูแลปรับหัวเอกสาร ข้อความ โลโก้ สถานะ ป้ายคอลัมน์ ช่องลงนาม และส่วนท้ายของใบเบิกได้ พร้อม Audit Log
- PASS CSV มี UTF-8 BOM และป้องกัน formula injection
- PASS CSS ใบเบิกกำหนด A4 และห้ามตัดแถวตาราง
- PASS สอง process จ่ายพร้อมกันรายการละ 4 จากยอด 5: สำเร็จหนึ่งรายการ เหลือ 1 และไม่มีสต๊อกติดลบ

PHP syntax check ผ่านทุกไฟล์ PHP และ `bin/check.php` ผ่าน PHP/PDO MySQL/mbstring/fileinfo/JSON/OpenSSL

โครงหน้า AdminLTE 4.10.0 ผ่าน HTTP smoke test: หน้า login/dashboard และไฟล์ CSS/JavaScript ตอบ 200, dashboard มี app wrapper, responsive sidebar และ active menu ตามหน้าปัจจุบัน

Interface refresh ผ่าน PHP syntax check และ HTTP smoke test: หน้าเข้าสู่ระบบและ CSS ตอบ 200 พร้อมโครง login แบบ responsive, เมนู SVG, เมนูลัดตามบทบาท, การ์ดสถิติ และแท็บรายงานที่แสดงสถานะ active

## Installer และ HTTP smoke test

- `bin/install.php` สร้าง schema 21 ตารางและผู้ดูแลครั้งแรกในฐานใหม่สำเร็จ ไม่มีบัญชี/รหัสผ่านตั้งต้นตายตัว
- `tests/install_smoke.php` ผ่านกับฐานข้อมูลชั่วคราวที่ลงท้าย `_install_test`: ติดตั้งใหม่ สร้างผู้ดูแลครั้งแรก สร้างโฟลเดอร์ storage และรันติดตั้งซ้ำโดยไม่สร้างผู้ดูแลซ้ำ จากนั้นลบฐานทดสอบแล้ว
- PHP built-in HTTP server: หน้า login ตอบ 200, อ่าน CSRF token ได้, POST login สำเร็จ, ตั้ง session cookie และ redirect ไป dashboard ภาษาไทย
- หลัง login หน้า import, ไฟล์แม่แบบ CSV, รายงาน PDF การเพิ่มวัสดุ และรายงาน PDF การเบิก/จ่ายตอบ HTTP 200; แม่แบบตอบ `text/csv` พร้อมชื่อไฟล์ดาวน์โหลด
- หน้า Branding ของผู้ดูแลและการบันทึกชื่อสำนักงาน/ชื่อระบบ/โทนสีผ่าน HTTP สำเร็จ; ยังไม่ได้อัปโหลดโลโก้จริงจากเบราว์เซอร์ในการทดสอบอัตโนมัติ

## แพ็กเกจติดตั้งเครื่องอื่น

- `bin/build-release.ps1` สร้าง ZIP สำหรับ XAMPP และไฟล์ SHA-256 สำเร็จ
- สคริปต์แตก ZIP ไปยังโฟลเดอร์ตรวจสอบชั่วคราว แล้วตรวจไฟล์บังคับและ PHP syntax จากไฟล์ที่แพ็กจริงผ่าน
- ตรวจยืนยันว่า ZIP ไม่มี `config/config.php`, `.env`, `.runtime`, `.git`, `.vscode`, ฐานข้อมูลเดิม รูป โลโก้ หรือ log จริง
- PowerShell parser ตรวจ `install-xampp.ps1` และ `bin/build-release.ps1` ผ่าน
- ไม่ได้รัน Docker build/compose จริง เพราะสภาพแวดล้อมนี้ไม่มีคำสั่ง Docker; ตรวจได้เฉพาะโครงไฟล์ Docker และ release เท่านั้น

## สิ่งที่ยังต้องตรวจบนเครื่องใช้งานจริง

ยังไม่ได้ทำ visual browser QA ด้วย Chrome/Edge จริงหรือทดสอบเครื่องพิมพ์จริง เนื่องจากสภาพแวดล้อมนี้ไม่มี browser automation/เครื่องพิมพ์ ให้ทำ manual acceptance ตาม `docs/TESTING.md` โดยเฉพาะ Print Preview A4, Save as PDF, Excel รุ่นที่สำนักงานใช้, QR ผ่านมือถือ และ firewall/LAN
