<?php
declare(strict_types=1);
$dsn=(string)getenv('TEST_DSN');$dbUser=(string)getenv('TEST_DB_USER');$dbPass=(string)getenv('TEST_DB_PASSWORD');
$pdo=new PDO($dsn,$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$config=['app'=>['timezone'=>'Asia/Bangkok','debug'=>true],'db'=>[]];date_default_timezone_set('Asia/Bangkok');session_start();$_SESSION['user_id']=(int)$argv[2];
require __DIR__.'/../app/Support.php';require __DIR__.'/../app/RequestService.php';
$deadline=microtime(true)+10;while(!is_file($argv[1])&&microtime(true)<$deadline)usleep(10000);
try{(new RequestService($pdo))->issue((int)$argv[3],[(int)$argv[4]=>4],'ผู้รับทดสอบ',$argv[5],'concurrency test',(int)$argv[2]);exit(0);}catch(Throwable$e){fwrite(STDERR,$e->getMessage());exit(2);}

