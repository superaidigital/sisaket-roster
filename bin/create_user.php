<?php
declare(strict_types=1);
// CLI only. Provision roster roles; this is not an HR management module.
if(PHP_SAPI!=='cli') { http_response_code(403); exit('CLI only'); }
require dirname(__DIR__).'/app/Core/bootstrap.php';
use App\Core\Database;

if($argc<5){
    fwrite(STDERR,"Usage: php bin/create_user.php HOSPITAL_CODE ROLE EMAIL FULL_NAME [POSITION] [PERSONNEL_TYPE]\n");
    fwrite(STDERR,"Roles: SUPERADMIN ADMIN SCHEDULER DIRECTOR STAFF HR\n"); exit(2);
}
[$script,$code,$role,$email,$name]=$argv;
$position=$argv[5] ?? ''; $type=$argv[6] ?? '';
$roles=['SUPERADMIN','ADMIN','SCHEDULER','DIRECTOR','STAFF','HR'];
if(!in_array($role,$roles,true)||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($name)<2) {
    fwrite(STDERR,"Invalid role, email or full name\n");exit(2);
}
$password=getenv('ROSTER_NEW_USER_PASSWORD');
if(!$password){
    fwrite(STDERR,"Temporary password (input visible in terminal; use ROSTER_NEW_USER_PASSWORD for privacy): ");
    $password=trim((string)fgets(STDIN));
}
if(strlen($password)<12){fwrite(STDERR,"Password must have at least 12 characters\n");exit(2);}
$db=Database::pdo();
$q=$db->prepare('SELECT id FROM hospitals WHERE hospital_code=? AND is_active=1');$q->execute([$code]);$hid=$q->fetchColumn();
if(!$hid){fwrite(STDERR,"Hospital code not found or inactive\n");exit(2);}
$q=$db->prepare('INSERT INTO users (hospital_id,full_name,email,password_hash,role,position,personnel_type) VALUES (?,?,?,?,?,?,?)');
$q->execute([(int)$hid,$name,mb_strtolower($email),password_hash($password,PASSWORD_DEFAULT),$role,$position,$type]);
fwrite(STDOUT,"Created {$role}: user ID ".$db->lastInsertId()." | hospital {$code}\n");
