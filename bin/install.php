<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
if(PHP_VERSION_ID<80200)exit("ต้องใช้ PHP 8.2 ขึ้นไป\n");
$root=dirname(__DIR__);$configFile=trim((string)getenv('SOS_CONFIG_FILE'))?:$root.'/config/config.php';
if(!is_file($configFile))exit("ไม่พบ config/config.php กรุณาคัดลอกจาก config.example.php แล้วตั้งค่าก่อน\n");
$config=require$configFile;date_default_timezone_set($config['app']['timezone']??'Asia/Bangkok');
if(($config['db']['password']??'')===''||($config['db']['password']??'')==='CHANGE_ME')exit("กรุณากำหนดรหัสผ่านฐานข้อมูลจริงใน config/config.php ก่อนติดตั้ง\n");
try{$pdo=new PDO($config['db']['dsn'],$config['db']['user'],$config['db']['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);foreach(explode(';',(string)file_get_contents($root.'/database/schema.sql'))as$sql)if(trim($sql)!=='')$pdo->exec($sql);}catch(Throwable$e){exit("ติดตั้ง schema ไม่สำเร็จ: {$e->getMessage()}\n");}
require_once $root.'/app/Support.php';
foreach([$root.'/storage/uploads',$root.'/storage/branding',$root.'/storage/logs']as$directory)if(!is_dir($directory)&&!mkdir($directory,0750,true))exit("สร้างพื้นที่จัดเก็บ $directory ไม่สำเร็จ\n");
$exists=(int)$pdo->query("SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.code='admin'")->fetchColumn();if($exists){echo"schema พร้อมแล้ว และมีผู้ดูแลระบบอยู่แล้ว\n";exit(0);}
$username=trim((string)getenv('SOS_ADMIN_USERNAME'));$password=(string)getenv('SOS_ADMIN_PASSWORD');$fullName=trim((string)getenv('SOS_ADMIN_FULL_NAME'));
if($username===''){echo"ชื่อผู้ใช้ผู้ดูแล: ";$username=trim((string)fgets(STDIN));}if($fullName===''){echo"ชื่อ-สกุลผู้ดูแล: ";$fullName=trim((string)fgets(STDIN));}if($password===''){echo"รหัสผ่านผู้ดูแล (อย่างน้อย 6 ตัวอักษร แนะนำ 12 ตัวขึ้นไป; ขณะพิมพ์จะมองเห็น): ";$password=rtrim((string)fgets(STDIN));}
if(!preg_match('/^[A-Za-z0-9._-]{3,80}$/',$username)||mb_strlen($fullName)<2||!password_meets_minimum($password))exit("ข้อมูลไม่ผ่านเกณฑ์: username 3-80 ตัว, ชื่ออย่างน้อย 2 ตัว, รหัสผ่านอย่างน้อย 6 ตัว\n");
try{$pdo->beginTransaction();$pdo->prepare('INSERT INTO users(username,password_hash,full_name,active) VALUES(?,?,?,1)')->execute([$username,password_hash($password,PASSWORD_DEFAULT),$fullName]);$id=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE code='admin'")->execute([$id]);$pdo->commit();echo"ติดตั้งสำเร็จ สร้างผู้ดูแล '$username' แล้ว กรุณาล้าง environment variable ที่ใช้ทันที\n";}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();exit("สร้างผู้ดูแลไม่สำเร็จ: {$e->getMessage()}\n");}
