# ระบบเบิกวัสดุสำนักงานออนไลน์

เว็บแอป PHP/MySQL สำหรับสำนักงานตรวจบัญชีสหกรณ์อุทัยธานี รองรับคำขอหลายรายการ การอนุมัติ การจ่ายบางส่วน การรับเข้า คืน ตรวจนับ Stock Movement Ledger และ Audit Log ข้อมูลทั้งหมดเก็บในฐานข้อมูลจริง

ส่วนติดต่อผู้ใช้ใช้ **AdminLTE 4.10.0 / Bootstrap 5.3** แบบ responsive โดยเก็บไฟล์ CSS/JavaScript ไว้ใน `public/vendor/adminlte` เพื่อใช้งานในเครือข่ายสำนักงานโดยไม่พึ่ง CDN สัญญาอนุญาต MIT อยู่ในโฟลเดอร์เดียวกัน

ผู้ดูแลระบบปรับชื่อระบบ ชื่อสำนักงาน โลโก้ โทนสี และคำนำหน้าเลขคำขอได้จากเมนู **ตั้งค่าระบบ → ชื่อและโลโก้** โลโก้ถูกตรวจ MIME/ขนาดและเก็บใน `storage/branding` นอก Document Root

เมนูตั้งค่าถูกแยกเป็น 6 หน้า ได้แก่ **ชื่อและโลโก้**, **เมนูและรายงาน**, **รูปแบบใบเบิก**, **บัญชีผู้ใช้**, **กลุ่มงาน** และ **แจ้งเตือน LINE** เพื่อให้ค้นหาและใช้งานง่าย หน้าเมนูและรายงานใช้เปลี่ยนชื่อเมนู ซ่อน/แสดง จัดลำดับ และกำหนดรูปแบบรายงาน ส่วนหน้ารูปแบบใบเบิกใช้ปรับหัวเอกสาร ข้อความ ป้ายคอลัมน์ ช่องลงนาม โลโก้ และสถานะของใบเบิก A4 การซ่อนเมนูเป็นการปรับหน้าจอเท่านั้น การตรวจบทบาทฝั่งเซิร์ฟเวอร์ยังคงทำงานเสมอ

## แพ็กเกจสำหรับติดตั้งเครื่องอื่น

สร้าง ZIP แบบสะอาดสำหรับ Windows/XAMPP ด้วย:

```powershell
powershell -ExecutionPolicy Bypass -File .\bin\build-release.ps1
```

ไฟล์จะอยู่ใน `dist/` พร้อม SHA-256 โดยมีตัวช่วย `install-xampp.ps1` อยู่ใน ZIP สคริปต์ build ใช้ allowlist และตรวจ ZIP หลังแตกจริง จึงไม่รวม `config/config.php`, `.env`, `.runtime`, ฐานข้อมูลเดิม รูป โลโก้ หรือ log อ่านขั้นตอนปลายทางใน [INSTALL_WINDOWS.md](INSTALL_WINDOWS.md)

## ความต้องการระบบ

- PHP 8.2 ขึ้นไป พร้อม `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `openssl` และเปิด `allow_url_fopen` สำหรับเชื่อม LINE Messaging API
- MySQL 8.0+ หรือ MariaDB 10.6+ (InnoDB, utf8mb4)
- Apache 2.4/Nginx โดยตั้ง Document Root มาที่โฟลเดอร์ `public`
- แนะนำ HTTPS เมื่อใช้นอกเครื่องเดียวกัน

ตรวจ prerequisites ด้วย `php bin/check.php`

## ติดตั้งบน XAMPP

1. ใช้ XAMPP ที่มี PHP 8.2 ขึ้นไป แล้ววางโปรเจกต์ไว้นอก public web root ถ้าทำได้
2. สร้างฐานข้อมูลและผู้ใช้เฉพาะระบบ:

```sql
CREATE DATABASE smart_office_stock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smart_stock'@'localhost' IDENTIFIED BY 'รหัสผ่านที่สุ่มและยาว';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON smart_office_stock.* TO 'smart_stock'@'localhost';
```

3. คัดลอก `config/config.example.php` เป็น `config/config.php` แล้วแก้ DSN, user, password และ `base_url` ห้าม commit ไฟล์นี้
4. แนะนำสร้าง Apache VirtualHost ให้ `DocumentRoot "D:/Smart Office Stock/public"` และอนุญาต `AllowOverride All` หากวางทั้งโฟลเดอร์ใต้ `htdocs` มี `.htaccess` ชั้นรากช่วยปิดโฟลเดอร์ภายใน แต่ VirtualHost ปลอดภัยกว่า
5. รัน `php bin/install.php` จาก Command Prompt/PowerShell ด้วย PHP 8.2 โปรแกรมจะสร้างตารางและถามข้อมูลผู้ดูแลครั้งแรก ไม่มีรหัสผ่านตั้งต้นตายตัว
6. เปิด URL ตาม `base_url` แล้วเข้าสู่ระบบ เพิ่มกลุ่มงาน/ผู้ใช้/บทบาทจากเมนูตั้งค่า
7. ถ้าต้องการทะเบียนตัวอย่าง ให้ import `database/sample_catalog.sql` เอง ไฟล์นี้ไม่มีสต๊อกสมมติและตัวติดตั้งจะไม่โหลดอัตโนมัติ
8. นับของจริงแล้วใช้เมนู “คลังวัสดุและรับเข้า” เลือก “ยอดตั้งต้นจากการนับจริง” จำนวนต้องเป็นหน่วยพื้นฐาน เช่น ซื้อ 5 แพ็ก แพ็กละ 10 ด้าม ให้กรอก 50 ด้าม

## แจ้งเตือนคำขอใหม่ทาง LINE

LINE Notify ยุติบริการแล้ว ระบบจึงใช้ **LINE Official Account + Messaging API** ส่งข้อความส่วนตัวแบบ Push message:

1. สร้าง LINE Official Account และเปิด Messaging API channel ใน LINE Developers Console จากนั้นให้เจ้าหน้าที่พัสดุเพิ่มบัญชีทางการเป็นเพื่อน
2. ออก Channel Access Token สำหรับ Messaging API แล้วกำหนดผ่าน environment variable `LINE_CHANNEL_ACCESS_TOKEN` ของ PHP/Apache (แนะนำ) หรือใส่ค่าใน `config/config.php` ซึ่งอยู่นอก Document Root และถูก ignore จาก Git
3. สำหรับ XAMPP สามารถเพิ่ม `SetEnv LINE_CHANNEL_ACCESS_TOKEN "token-จริง"` ใน VirtualHost แล้ว Restart Apache หรือแก้ `channel_access_token` ใน `config/config.php` โดยตรง ห้ามใส่ token จริงในไฟล์ตัวอย่างหรือ commit ลง Git
4. หา LINE User ID ของผู้รับ ซึ่งมีรูปแบบ `U` ตามด้วยเลข/อักษรฐานสิบหก 32 ตัว ค่า User ID ไม่ใช่ LINE ID ที่ใช้ค้นหาเพื่อน ผู้พัฒนาช่องดู User ID ของตนได้จาก Basic settings; บัญชีอื่นต้องรับจาก webhook/ขั้นตอนเชื่อมบัญชีของ LINE
5. เข้า **ตั้งค่าระบบ → แจ้งเตือน LINE** กรอก User ID หนึ่งบัญชีต่อบรรทัด บันทึก กด **ส่งข้อความทดสอบ** และเมื่อทดสอบสำเร็จจึงเปิดการแจ้งเตือน

### ผูก LINE ของผู้เบิกอัตโนมัติด้วย Webhook

ระบบมีหน้า **เชื่อมต่อ LINE** ให้ผู้เบิกสร้างรหัสใช้ครั้งเดียวอายุ 15 นาที แล้วส่งข้อความ `ลงทะเบียน XXXXXXXX` ไปยัง LINE Official Account ระบบจะผูก LINE User ID กับบัญชีผู้เบิกโดยอัตโนมัติ การเปิดใช้ต้องมี URL HTTPS สาธารณะที่ LINE เข้าถึงได้:

1. กำหนด `LINE_CHANNEL_SECRET` และ `LINE_CHANNEL_ACCESS_TOKEN` ใน environment ของ PHP
2. ตั้ง `base_url` เป็น URL HTTPS สาธารณะของระบบ
3. ตั้ง Webhook URL ใน LINE Developers Console เป็น `{base_url}/line-webhook.php` แล้วเปิด **Use webhook**
4. รัน `php bin/migrate-line-account-linking.php` หนึ่งครั้งเมื่ออัปเกรดจากระบบเดิม
5. ให้ผู้เบิกเข้าสู่ระบบ เปิดเมนู **เชื่อมต่อ LINE** สร้างรหัส และส่งข้อความตามที่หน้าจอแสดง

Webhook ตรวจ `X-Line-Signature` ด้วย Channel Secret ทุกครั้ง รหัสถูกเก็บเป็น SHA-256 ใช้ได้ครั้งเดียว และ LINE หนึ่งบัญชีผูกกับผู้ใช้ระบบได้เพียงหนึ่งบัญชี ห้ามเปิด Webhook ผ่าน HTTP ธรรมดาหรือเผย Channel Secret

เซิร์ฟเวอร์ต้องเชื่อมต่อ `https://api.line.me` ทาง HTTPS ขาออกได้ ข้อความมีเลขคำขอ ผู้เบิก กลุ่มงาน วัตถุประสงค์ รายการ และลิงก์เปิดคำขอ การเปิดลิงก์ยังต้องเข้าสู่ระบบตามสิทธิ์ ระบบเก็บประวัติผลส่งและใช้ retry key/กุญแจเหตุการณ์เพื่อป้องกันการส่งเหตุการณ์เดิมซ้ำ ความล้มเหลวของ LINE จะไม่ย้อนกลับคำขอเบิกที่บันทึกสำเร็จแล้ว

## เพิ่ม แก้ไข และนำเข้าทะเบียนวัสดุ

- ผู้มีบทบาทเจ้าหน้าที่พัสดุหรือผู้ดูแลระบบเข้าเมนู **คลังวัสดุและรับเข้า** แล้วกด **เพิ่มวัสดุ** หรือคลิกชื่อวัสดุเพื่อแก้ไข
- เมื่อต้องเพิ่มหรือปรับปรุงหลายรายการ กด **นำเข้า CSV** ดาวน์โหลดแม่แบบ แล้วบันทึกไฟล์เป็น CSV UTF-8 ขนาดไม่เกิน 2 MB ระบบเทียบรายการด้วย `code` และนำเข้าทั้งไฟล์ภายใต้ transaction
- การนำเข้าแก้เฉพาะทะเบียนวัสดุ ไม่แก้ `stock_qty` และไม่สร้างยอดสมมติ การเพิ่มยอดจริงต้องทำผ่านรับเข้า/ยอดตั้งต้นเพื่อให้มี Stock Movement Ledger

## รายงาน PDF

เข้าเมนู **รายงาน** ตั้งตัวกรองช่วงเวลา วัสดุ ผู้เบิก หรือกลุ่มงาน แล้วเลือก **PDF การเพิ่มวัสดุ** หรือ **PDF การเบิก/จ่าย** หน้ารายงานจัดรูปแบบ A4 แนวนอน ให้ใช้คำสั่ง Print ของ Chrome/Edge แล้วเลือก **Save as PDF** ระบบไม่ได้ติดตั้งโปรแกรมสร้าง PDF ฝั่งเซิร์ฟเวอร์

ผู้ดูแลปรับรูปแบบใบเบิกแต่ละคำขอได้ที่ **ตั้งค่าระบบ → รูปแบบใบเบิก** ทั้งชื่อเอกสาร ข้อความหัว/ท้าย ป้ายข้อมูลและคอลัมน์ ช่องลงนาม การแสดงโลโก้ และสถานะ พร้อมปุ่มเปิดตัวอย่างจากคำขอล่าสุด

## ทดลองแบบ Docker (ทางเลือก)

คัดลอก `.env.example` เป็น `.env` และเปลี่ยนรหัสผ่านทั้งสองค่า จากนั้นรัน:

```text
docker compose up -d --build
docker compose exec web php bin/install.php
```

เปิด `http://localhost:8080` ฐานข้อมูลและรูปอัปโหลดอยู่ใน named volume จึงยังอยู่หลังปิด container ห้ามนำค่าใน `.env.example` ไปใช้จริง

## ใช้ผ่านมือถือในเครือข่ายสำนักงาน

ตั้ง `base_url` เป็น IP/LAN hostname ของเครื่องเซิร์ฟเวอร์ เช่น `http://192.168.1.20:8080` อนุญาตพอร์ตใน Windows Firewall เฉพาะโปรไฟล์ Private/LAN แล้วเปิด “ตั้งค่าระบบ → QR Code เข้าใช้งาน” มือถือต้องอยู่เครือข่ายเดียวกัน การสแกนจะเปิดหน้าเบิก แต่ต้องล็อกอินก่อนเสมอ

## แจ้งเตือนงานค้างและตรวจสอบ Ledger

ผู้ดูแลกำหนดจำนวนวันที่เมนู **ตั้งค่าระบบ → แจ้งเตือน LINE** แล้วทดสอบงานประจำวันด้วย:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\run-maintenance.ps1"
```

ติดตั้ง Windows Task Scheduler ให้รันทุกวันเวลา 08:00 ด้วย PowerShell (อาจต้องเปิดด้วยสิทธิ์ผู้ดูแลระบบ):

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\install-maintenance-task.ps1" -DailyAt "08:00"
```

หากอัปเกรดจากระบบเดิม ให้เพิ่มฟิลด์ LINE User ID รายบัญชีหนึ่งครั้ง:

```powershell
.\.runtime\php\php.exe -n -d "extension_dir=.runtime/php/ext" -d extension=php_pdo_mysql.dll .\bin\migrate-requester-line.php
```

จากนั้นผู้ดูแลเข้า **ตั้งค่าระบบ → บัญชีผู้ใช้** เพื่อกรอก LINE User ID ของผู้เบิกแต่ละคน ระบบจะแจ้งผลอนุมัติ ส่งกลับ ไม่อนุมัติ และการจ่ายวัสดุไปยังผู้เบิกโดยตรง ส่วนรายงานวัสดุต่ำกว่ายอดขั้นต่ำจะส่งให้เจ้าหน้าที่ที่ตั้งไว้ทุกวันโดยงาน maintenance

งานนี้ส่งสรุปคำขอรออนุมัติ รอจ่าย และรอยืนยันรับที่เกินกำหนดผ่าน LINE วันละไม่เกินหนึ่งครั้ง พร้อมตรวจสอบยอดวัสดุกับ Stock Movement Ledger หากคำสั่งคืน exit code `2` แสดงว่าพบยอดไม่ตรง ให้เปิดเมนู **ตรวจสอบความถูกต้องสต๊อก** และแก้ผ่านการกลับรายการหรือตรวจนับ ห้ามแก้ยอดในฐานข้อมูลโดยตรง

## สำรองและกู้คืน

สำรองฐานข้อมูลและไฟล์อัปโหลดพร้อมกัน:

```text
mysqldump --single-transaction --routines --triggers -u smart_stock -p smart_office_stock > smart_stock_backup.sql
```

คัดลอก `storage/uploads` และ `storage/branding` เก็บคู่กับ SQL โดยจำกัดสิทธิ์เข้าถึง กู้คืนด้วย `mysql -u smart_stock -p smart_office_stock < smart_stock_backup.sql` แล้วคืนทั้งสองโฟลเดอร์ที่ตำแหน่งเดิม ทดสอบการกู้คืนเป็นระยะและเก็บ backup แยกเครื่อง

## ทดสอบ

ชุด integration test จะลบและสร้างตารางใหม่ จึงบังคับให้ชื่อฐานข้อมูลลงท้าย `_test` เท่านั้น:

```powershell
$env:TEST_DSN='mysql:host=127.0.0.1;dbname=smart_office_stock_test;charset=utf8mb4'
$env:TEST_DB_USER='smart_stock_test'
$env:TEST_DB_PASSWORD='รหัสผ่านทดสอบ'
php tests/integration.php
```

ครอบคลุมยอด 50→47, อนุมัติไม่ตัดสต๊อก, จ่ายบางส่วน/เกินอนุมัติ, สต๊อกไม่ติดลบ, idempotency, สอง process จ่ายพร้อมกัน, คืน, ownership, optimistic check ของการตรวจนับ, import CSV, export CSV, การแจ้ง LINE แบบไม่ส่งซ้ำ และ CSS พิมพ์ A4 รายละเอียดการตรวจรับแบบ manual อยู่ใน `docs/TESTING.md`

การตรวจ fresh install ใช้ `tests/install_smoke.php` กับ DSN ของฐานชั่วคราวที่ชื่อลงท้าย `_install_test` เท่านั้น สคริปต์จะสร้างและลบฐานนั้นเพื่อตรวจการสร้างผู้ดูแลครั้งแรก โฟลเดอร์ storage และการติดตั้งซ้ำโดยไม่สร้างข้อมูลซ้ำ ดูผลที่รันจริงใน `docs/TEST_RESULTS.md`

## หลักความปลอดภัยและการดูแล

- ทุก action ตรวจ session, role และ CSRF ที่ server; คำขอของผู้เบิกกรองด้วย owner ID
- PDO native prepared statements, escape HTML, `password_hash/password_verify`, session strict/HttpOnly/SameSite
- จ่าย/คืน/ปรับยอดอยู่ใน transaction และ lock แถววัสดุ ห้ามแก้ ledger ที่ยืนยันแล้ว ใช้รายการกลับรายการ
- รูปอยู่ใต้ `storage` นอก Document Root ตรวจ MIME/ขนาด และดาวน์โหลดผ่าน endpoint ที่ตรวจ login
- บัญชี/วัสดุที่มีประวัติให้ปิดสถานะแทนลบ
- ก่อนใช้จริงควรเปิด HTTPS, จำกัด LAN/VPN, ตั้ง backup, ตรวจ audit log และอัปเดต PHP/MariaDB สม่ำเสมอ

คู่มือผู้ใช้: [docs/USER_GUIDE.md](docs/USER_GUIDE.md) · ขั้นตอนทดสอบ: [docs/TESTING.md](docs/TESTING.md) · ผลที่รันจริง: [docs/TEST_RESULTS.md](docs/TEST_RESULTS.md)
