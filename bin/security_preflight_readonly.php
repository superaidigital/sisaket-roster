<?php
declare(strict_types=1);
// READ-ONLY production preflight: never stores secrets or changes the database.
require dirname(__DIR__).'/app/Core/bootstrap.php';
use App\Core\Database;
use App\Models\RosterModel;

if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
if(count($argv)!==4){fwrite(STDERR,"Usage: php bin/security_preflight_readonly.php HOSPITAL_CODE YYYY MM\n");exit(2);}
[$script,$code,$yearIn,$monthIn]=$argv;
if(!preg_match('/^[A-Za-z0-9_-]{3,30}$/D',$code)||!preg_match('/^20[0-9]{2}$/D',$yearIn)||
    !ctype_digit($monthIn)||(int)$monthIn<1||(int)$monthIn>12){fwrite(STDERR,"Invalid arguments\n");exit(2);}
$year=(int)$yearIn;$month=(int)$monthIn;
try {
    $db=Database::pdo();
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $q=$db->prepare('SELECT id,hospital_code,name FROM hospitals WHERE hospital_code=?');$q->execute([$code]);$hospital=$q->fetch();
    if(!$hospital){fwrite(STDERR,"Hospital not found\n");exit(2);}
    $id=(int)$hospital['id'];
    $q=$db->prepare('SELECT role, COUNT(*) AS qty FROM users WHERE hospital_id=? AND is_active=1 GROUP BY role ORDER BY role');
    $q->execute([$id]);$roles=$q->fetchAll();
    $q=$db->prepare("SELECT id,version,status,lock_version FROM rosters WHERE hospital_id=? AND `year`=? AND `month`=? ORDER BY version");
    $q->execute([$id,$year,$month]);$versions=$q->fetchAll();
    $public=RosterModel::latest($id,$year,$month,true);
    $latest=RosterModel::latest($id,$year,$month,false);
    $versionIDs=array_column($versions,'id');
    $mismatch=0;
    if($versionIDs){
        $placeholders=implode(',',array_fill(0,count($versionIDs),'?'));
        $q=$db->prepare("SELECT COUNT(*) FROM roster_entries e JOIN rosters r ON r.id=e.roster_id JOIN users u ON u.id=e.user_id WHERE r.id IN ($placeholders) AND u.hospital_id<>r.hospital_id");
        $q->execute($versionIDs);$mismatch=(int)$q->fetchColumn();
    }
    echo "=== Sisaket Roster V11 Security Preflight (READ ONLY) ===\n";
    echo 'Hospital: '.$hospital['name'].' ('.$code.")\n";
    echo sprintf("Month: %04d-%02d\n",$year,$month);
    echo "Active role counts:\n";
    foreach($roles as $r)echo '  '.$r['role'].': '.$r['qty']."\n";
    echo "Roster Versions:\n";
    foreach($versions as $v)echo '  V'.$v['version'].' | '.$v['status'].' | Revision '.$v['lock_version']."\n";
    echo 'Visible to STAFF: '.($public?'Version '.$public['version'].' '.$public['status']:'none')."\n";
    echo 'Latest management version: '.($latest?'Version '.$latest['version'].' '.$latest['status']:'none')."\n";
    if($public && !in_array($public['status'],['PUBLISHED','LOCKED'],true))throw new RuntimeException('ERROR: staff visibility leaked draft');
    echo "Cross-hospital historical entries: $mismatch (verify transfers manually; not necessarily an error)\n";
    $app=config('app');
    if(!(bool)($app['cookie_secure']??false))echo "LOCAL-HTTP NOTE: cookie_secure=false. Require HTTPS and true for production.\n";
    $secret=(string)($app['ip_hash_secret']??'');
    if(strlen($secret)<32||str_contains($secret,'REPLACE_'))echo "WARNING: replace ip_hash_secret with >=32 random characters.\n";
    echo "ZERO SQL WRITES issued. This is not an API penetration test.\n";
} catch(Throwable $ex){fwrite(STDERR,"Preflight failed: ".$ex->getMessage()."\n");exit(1);}
