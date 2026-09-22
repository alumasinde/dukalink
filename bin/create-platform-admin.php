<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Database\Database;
new App();
function ask(string $label, bool $hidden=false): string {
    if ($hidden && DIRECTORY_SEPARATOR === '/') {
        $cmd = '/usr/bin/env bash -c ' . escapeshellarg('read -s -p ' . escapeshellarg($label . ': ') . ' value; echo; printf "%s" "$value"');
        $value = shell_exec($cmd);
        return trim((string)$value);
    }
    echo $label . ': ';
    return trim((string)fgets(STDIN));
}
$first=ask('First name'); $last=ask('Last name'); $phone=ask('Phone'); $email=ask('Email'); $password=ask('Password', true);
if($first===''||$phone===''||$password===''){fwrite(STDERR,"First name, phone and password are required.\n");exit(1);} if(strlen($password)<12){fwrite(STDERR,"Password must be at least 12 characters.\n");exit(1);}
$db=Database::connection(); $stmt=$db->prepare('INSERT INTO platform_users (first_name,last_name,phone,email,password_hash,role,status) VALUES (:first,:last,:phone,:email,:hash,\'super_admin\',\'active\')');
$stmt->execute(['first'=>$first,'last'=>$last?:null,'phone'=>$phone,'email'=>$email?:null,'hash'=>password_hash($password,PASSWORD_DEFAULT)]);
echo "Platform super admin created.\n";
