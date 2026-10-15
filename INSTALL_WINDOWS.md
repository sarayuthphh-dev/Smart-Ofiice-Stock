# คู่มือติดตั้ง Smart Office Stock บนเครื่องอื่น (Windows + XAMPP)

แพ็กเกจนี้เป็นชุดติดตั้งใหม่ที่สะอาด ไม่รวมฐานข้อมูลจริง รหัสผ่าน ไฟล์ `config/config.php` รูปวัสดุ โลโก้ หรือไฟล์ใน `.runtime` จากเครื่องต้นทาง

## 1. เตรียมเครื่องปลายทาง

1. ติดตั้ง XAMPP รุ่นที่มี **PHP 8.2 ขึ้นไป** และ MariaDB/MySQL
2. เปิด XAMPP Control Panel แล้ว Start **Apache** และ **MySQL**
3. แตก ZIP ไปที่ `C:\xampp\htdocs\smart-office-stock` โดยให้ไฟล์ `install-xampp.ps1` อยู่ตรงตำแหน่งนี้
4. ตรวจว่า Windows Firewall อนุญาต Apache เฉพาะเครือข่าย Private หากต้องใช้ผ่าน LAN

ส่วนขยาย PHP ที่ต้องเปิดคือ `pdo_mysql`, `mbstring`, `fileinfo`, `json` และ `openssl` ส่วน `allow_url_fopen` ต้องเปิดเมื่อใช้แจ้งเตือน LINE

## 2. ติดตั้งแบบช่วยตั้งค่าอัตโนมัติ

เปิด PowerShell แล้วรัน:

```powershell
cd C:\xampp\htdocs\smart-office-stock
powershell -ExecutionPolicy Bypass -File .\install-xampp.ps1
```

ตัวช่วยติดตั้งจะ:

- ตรวจ PHP และส่วนขยายที่จำเป็น
- ขอชื่อผู้ดูแล MySQL (XAMPP ใหม่มักใช้ `root`) และขอรหัสผ่านแบบไม่แสดงบนจอ
- สร้างฐานข้อมูล `smart_office_stock` และบัญชีฐานข้อมูลเฉพาะระบบ
- สุ่มรหัสผ่านฐานข้อมูลและเขียน `config/config.php` ให้โดยอัตโนมัติ
- สร้างตารางและขอชื่อบัญชีผู้ดูแลระบบครั้งแรก โดยไม่มีรหัสผ่านตายตัว
- สร้างโฟลเดอร์เก็บรูป โลโก้ และ log ที่จำเป็น

ถ้า MySQL `root` ของ XAMPP ยังไม่มีรหัสผ่าน ให้กด Enter ที่ช่องรหัสผ่านได้ แต่ควรตั้งรหัสผ่าน MySQL ก่อนนำไปใช้งานจริง

เมื่อติดตั้งเสร็จ เปิด:

```text
http://localhost/smart-office-stock/public
```

ถ้าจะใช้ผ่าน IP ในสำนักงาน ให้กำหนด URL ตั้งแต่ติดตั้ง เช่น:

```powershell
powershell -ExecutionPolicy Bypass -File .\install-xampp.ps1 -BaseUrl "http://192.168.1.20/smart-office-stock/public"
```

การรันซ้ำจะเก็บ `config/config.php` เดิมและอัปเดต schema แบบ idempotent ไม่สร้างผู้ดูแลซ้ำ ใช้ `-ForceConfig` เฉพาะเมื่อตั้งใจสร้างการเชื่อมต่อใหม่ สคริปต์จะสำรอง config เดิมเป็น `config.php.bak-*`; หลังตรวจระบบแล้วให้ย้ายหรือลบไฟล์สำรองนั้น เพราะมีค่าลับฐานข้อมูล

## 3. ตรวจสอบก่อนเปิดใช้

1. เข้าระบบด้วยผู้ดูแลที่สร้างระหว่างติดตั้ง
2. เปิด **ตั้งค่าระบบ → ชื่อและโลโก้** ตั้งชื่อสำนักงาน โลโก้ และ Base URL ตามคู่มือ
3. สร้างกลุ่มงานและบัญชีผู้ใช้ พร้อมกำหนดบทบาท
4. นำเข้าทะเบียนวัสดุ หรือเพิ่มวัสดุ แล้วบันทึกยอดตั้งต้นจากการนับจริง
5. ทดสอบลำดับ สร้างคำขอ → อนุมัติ → จ่าย → ยืนยันรับ
6. เปิดใบเบิกและ Print Preview A4 รวมถึง CSV ใน Excel
7. ถ้าใช้ LINE ให้ตั้ง `LINE_CHANNEL_ACCESS_TOKEN` ใน Apache/VirtualHost แล้ว Restart Apache ก่อนทดสอบ

ตรวจเฉพาะความพร้อมโดยไม่เปลี่ยนไฟล์หรือฐานข้อมูล:

```powershell
powershell -ExecutionPolicy Bypass -File .\install-xampp.ps1 -CheckOnly
```

## 4. วิธีติดตั้งด้วยตนเอง (กรณีตัวช่วยทำงานไม่ได้)

ตรวจ PHP ก่อน:

```powershell
C:\xampp\php\php.exe bin\check.php
```

จากนั้นสร้างฐานข้อมูลและบัญชีผ่าน phpMyAdmin:

```sql
CREATE DATABASE smart_office_stock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smart_stock'@'localhost' IDENTIFIED BY 'รหัสผ่านที่สุ่มและยาว';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
ON smart_office_stock.* TO 'smart_stock'@'localhost';
```

คัดลอก `config/config.example.php` เป็น `config/config.php` แก้ DSN, user, password และ `base_url` แล้วรัน:

```powershell
C:\xampp\php\php.exe bin\install.php
```

## 5. VirtualHost ที่แนะนำ

การชี้ Document Root ไปที่ `public` ปลอดภัยกว่าการเปิดผ่านโฟลเดอร์ย่อย เพิ่มใน Apache VirtualHost ตามตำแหน่งจริง:

```apache
<VirtualHost *:80>
    ServerName smart-stock.local
    DocumentRoot "C:/xampp/htdocs/smart-office-stock/public"
    <Directory "C:/xampp/htdocs/smart-office-stock/public">
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

เพิ่ม `127.0.0.1 smart-stock.local` ในไฟล์ hosts, Restart Apache แล้วตั้ง Base URL เป็น `http://smart-stock.local` หากใช้ผ่าน LAN ให้ใช้ชื่อ DNS หรือ IP จริงแทน

## 6. ย้ายข้อมูลเดิมจากอีกเครื่อง

อย่าคัดลอก `.runtime/mariadb-data` หรือโฟลเดอร์ข้อมูลดิบของ MySQL ให้ย้ายด้วย SQL dump เท่านั้น และหยุดการทำรายการระหว่างสำรอง:

```powershell
C:\xampp\mysql\bin\mysqldump.exe --single-transaction --routines --triggers -u smart_stock -p smart_office_stock > smart_stock_backup.sql
```

บนเครื่องใหม่ ให้สร้างฐานข้อมูล/บัญชีฐานข้อมูล นำเข้า dump แล้วคัดลอกทั้งสองโฟลเดอร์นี้จากเครื่องเดิม:

- `storage/uploads` — รูปวัสดุและไฟล์แนบ
- `storage/branding` — โลโก้สำนักงาน

หลังนำเข้าให้ตรวจ `config/config.php`, รัน `C:\xampp\php\php.exe bin\install.php` เพื่อเติมค่าที่ขาดแบบไม่ลบข้อมูลเดิม แล้วทดสอบยอดสต๊อก ประวัติ movement ผู้ใช้ รูป และโลโก้ก่อนเปิดใช้

## 7. ความปลอดภัยและการสำรอง

- ห้ามส่งต่อ `config/config.php`, `.env`, SQL dump หรือไฟล์สำรอง config ผ่านช่องทางสาธารณะ
- จำกัดระบบไว้ใน LAN/VPN และใช้ HTTPS เมื่อมีการเข้าถึงข้ามเครื่อง
- ให้ Apache เขียนได้เฉพาะ `storage/uploads`, `storage/branding` และ `storage/logs`
- สำรองฐานข้อมูลพร้อม `storage/uploads` และ `storage/branding` เสมอ
- เก็บ backup แยกจากเครื่องเซิร์ฟเวอร์และทดลองกู้คืนเป็นระยะ
- ตรวจ SHA-256 ของ ZIP กับไฟล์ `.sha256` ก่อนนำไปติดตั้ง
