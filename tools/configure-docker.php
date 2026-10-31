<?php
declare(strict_types=1);
$path=dirname(__DIR__).'/.env.docker';
if(file_exists($path)){fwrite(STDOUT,".env.docker already exists; unchanged.\n");exit(0);}
$contents="APP_PORT=8080\nAPP_SECRET=".bin2hex(random_bytes(32))."\nPOSTGRES_PASSWORD=".bin2hex(random_bytes(24))."\nAPP_DATABASE_PASSWORD=".bin2hex(random_bytes(24))."\n";
$handle=fopen($path,'x');if(!$handle)throw new RuntimeException('Cannot create local Docker configuration.');
fwrite($handle,$contents);fclose($handle);chmod($path,0600);
fwrite(STDOUT,"Created private .env.docker with generated values. Do not commit it.\n");
