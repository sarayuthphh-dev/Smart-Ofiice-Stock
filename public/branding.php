<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$name=setting('logo_path','');
if($name===''||!preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/',$name)){http_response_code(404);exit;}
$base=dirname((string)cfg('upload.directory')).DIRECTORY_SEPARATOR.'branding';
$path=$base.DIRECTORY_SEPARATOR.$name;
if(!is_file($path)){http_response_code(404);exit;}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(404);exit;}
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Cache-Control: public, max-age=86400, immutable');
header('X-Content-Type-Options: nosniff');
readfile($path);
