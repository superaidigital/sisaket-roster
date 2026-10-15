<?php
declare(strict_types=1);
/**
 * Integrated, MUTATING acceptance smoke test on a disposable CI DB ONLY.
 * Never run this script on a real sisaket_roster database.
 */
if(PHP_SAPI!=='cli')exit(2);
$dbName=(string)getenv('TEST_DB_NAME');
$dbHost=(string)getenv('TEST_DB_HOST');
$dbUser=(string)getenv('TEST_DB_USER');
$dbPass=(string)getenv('TEST_DB_PASSWORD');
if($dbName!=='sisaket_roster_ci'||$dbHost!=='127.0.0.1'||$dbUser===''){
    fwrite(STDERR,"SAFETY STOP: Integration tests require a disposable sisaket_roster_ci on 127.0.0.1.\n");exit(2);
}
$root=dirname(__DIR__);$configPath=$root.'/config.php';
if(is_file($configPath)){fwrite(STDERR,"SAFETY STOP: refusing to overwrite an existing config.php\n");exit(2);}
$key=bin2hex(random_bytes(32));
$config=['db'=>['host'=>$dbHost,'port'=>3306,'name'=>$dbName,'user'=>$dbUser,'password'=>$dbPass],
    'app'=>['timezone'=>'Asia/Bangkok','session_name'=>'SSK_CI_ONLY_SESSION',
        'ip_hash_secret'=>bin2hex(random_bytes(32)),'sensitive_data_key'=>$key]];
if(file_put_contents($configPath,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX)===false){exit(2);}
register_shutdown_function(static fn()=>@unlink($configPath));
require $root.'/app/Core/bootstrap.php';
use App\Core\Database;
use App\Services\DutyPolicy;
use App\Services\DutyRosterValidationService as Validator;
use App\Services\DutyReviewService as Review;
use App\Services\LeaveRequestService as Leave;
use App\Services\DutySwapService as Swap;
use App\Services\LeaveReasonCipher as Cipher;
$db=Database::pdo();
$n=0;function chk(bool $value,string $name):void{global $n;$n++;if(!$value){fwrite(STDERR,"FAIL: ".$name."\n");exit(1);}}
$hospitalCode='CI-ONLY-'.substr(bin2hex(random_bytes(4)),0,8);
$db->prepare("INSERT INTO hospitals(hospital_code,name)VALUES(?,?)")->execute([$hospitalCode,'CI disposable hospital']);
$hospital=(int)$db->lastInsertId();
function user(PDO $db,int $h,string $role,string $label):array{
 $mail=strtolower($label).bin2hex(random_bytes(4)).'@example.test';
 $db->prepare("INSERT INTO users(hospital_id,full_name,email,password_hash,role,position)VALUES(?,?,?,?,?,?)")
    ->execute([$h,$label,$mail,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$role,'CI role']);
 return ['id'=>(int)$db->lastInsertId(),'hospital_id'=>$h,'role'=>$role];
}
$scheduler=user($db,$hospital,'SCHEDULER','scheduler');
$director=user($db,$hospital,'DIRECTOR','director');
$staff1=user($db,$hospital,'STAFF','staff_one');
$staff2=user($db,$hospital,'STAFF','staff_two');
$admin=user($db,$hospital,'ADMIN','admin');
$today=date('Y-m-d');
foreach(['DUTY_APPROVAL','LEAVE_APPROVAL','SHIFT_SWAP_REVIEW'] as $scope) {
 $q=$db->prepare("INSERT INTO approval_delegations
    (hospital_id,delegate_user_id,approval_scope,order_reference,effective_from,evidence_uri,verified_by)
    VALUES (?,?,?,?,?,?,?)");
 $q->execute([$hospital,$director['id'],$scope,'CI TEST ONLY - NO LEGAL POWER',
    date('Y-m-d',strtotime('-1 day')),'ci-test://non-legal-document',$admin['id']]);
}
$day=(new DateTimeImmutable('+7 days'))->format('Y-m-d');
$nextDay=(new DateTimeImmutable('+8 days'))->format('Y-m-d');
$leaveDay=(new DateTimeImmutable('+11 days'))->format('Y-m-d');
$year=(int)substr($day,0,4);$month=(int)substr($day,5,2);
$insertRoster=$db->prepare("INSERT INTO duty_rosters(hospital_id,roster_year,roster_month,version,status,created_by)
    VALUES(?,?,?,?,?,?)");
$insertRoster->execute([$hospital,$year,$month,1,'PUBLISHED',$scheduler['id']]);
$roster1=(int)$db->lastInsertId();
$insertRoster->execute([$hospital,$year,$month,2,'DRAFT',$scheduler['id']]);
$roster2=(int)$db->lastInsertId();
$type=(int)$db->query("SELECT id FROM duty_types WHERE code='บ' LIMIT 1")->fetchColumn();
chk($type>0,'duty types seeded');
$db->prepare("INSERT INTO duty_coverage_requirements
 (hospital_id,duty_type_id,skill_code,min_count,evidence_reference,verified_by)
 VALUES (?,?,?,?,?,?)")
 ->execute([$hospital,$type,'__TOTAL__',1,'CI TEST CRITERIA - NOT A LEGAL POLICY',$director['id']]);

$insertAssignment=$db->prepare("INSERT INTO duty_assignments(duty_roster_id,user_id,duty_type_id,work_date,start_at,end_at,created_by,updated_by)
    VALUES(?,?,?,?,?,?,?,?)");
[$start,$end]=DutyPolicy::interval($day,'บ');
$insertAssignment->execute([$roster1,$staff1['id'],$type,$day,$start,$end,$scheduler['id'],$scheduler['id']]);
$source=(int)$db->lastInsertId();
[$s2,$e2]=DutyPolicy::interval($nextDay,'บ');
$insertAssignment->execute([$roster2,$staff1['id'],$type,$nextDay,$s2,$e2,$scheduler['id'],$scheduler['id']]);

$draft=$db->query('SELECT * FROM duty_rosters WHERE id='.$roster2)->fetch(PDO::FETCH_ASSOC);
$report=Validator::report($draft);
chk($report['critical']===0 && $report['assignment_count']===1,'Draft validation with MySQL');
$submitted=Validator::submit($draft,$scheduler['id'],0);
chk($submitted['status']==='SUBMITTED' && $submitted['revision']===1,'Draft submitted');
$refused=false;
try{Review::transition($scheduler,$hospital,$roster2,1,'approve');}catch(RuntimeException $e){$refused=true;}
chk($refused,'scheduler not allowed to approve');
$approved=Review::transition($director,$hospital,$roster2,1,'approve');
chk($approved['status']==='APPROVED','director approval with explicit CI-only mandate');
$published=Review::transition($scheduler,$hospital,$roster2,2,'publish');
chk($published['status']==='PUBLISHED','publish');
$locked=Review::transition($admin,$hospital,$roster2,3,'lock');
chk($locked['status']==='LOCKED','lock');

$leaveStart=$leaveDay.' 09:00:00';$leaveEnd=$leaveDay.' 17:00:00';
$leaveId=Leave::submit($staff2,(int)$db->query("SELECT id FROM leave_types WHERE code='SICK'")->fetchColumn(),
    $leaveStart,$leaveEnd,'CI confidential reason');
$encrypted=$db->query('SELECT encrypted_reason FROM leave_requests WHERE id='.$leaveId)->fetchColumn();
chk(is_string($encrypted)&&!str_contains($encrypted,'confidential'),'leave at-rest encryption');
chk(Cipher::decrypt($encrypted)==='CI confidential reason','leave decrypt roundtrip');
$reason=Leave::detail($staff2,$leaveId);
chk($reason['reason']==='CI confidential reason','requester reads reason');
Leave::decision($director,$leaveId,'APPROVED');
chk($db->query('SELECT status FROM leave_requests WHERE id='.$leaveId)->fetchColumn()==='APPROVED','delegated leave approval');

$swapId=Swap::request($staff1,$source,$staff2['id'],'CI private shift exchange');
chk($db->query('SELECT status FROM shift_swaps WHERE id='.$swapId)->fetchColumn()==='PENDING_CONSENT','swap request');
Swap::consent($staff2,$swapId,true);
chk($db->query('SELECT status FROM shift_swaps WHERE id='.$swapId)->fetchColumn()==='PENDING_DIRECTOR','receiver consent');
$revealed=Swap::reason($director,$swapId);
chk($revealed==='CI private shift exchange','delegated director sees reason');
Swap::directorReview($director,$swapId,true);
chk($db->query('SELECT status FROM shift_swaps WHERE id='.$swapId)->fetchColumn()==='PENDING_AUTHORITY','director endorsed, not final');
chk((int)$db->query('SELECT user_id FROM duty_assignments WHERE id='.$source)->fetchColumn()===$staff1['id'],'issued roster remains immutable');
chk((int)$db->query("SELECT COUNT(*) FROM payroll_logs")->fetchColumn()===0,'no payroll artifacts written');
chk((int)$db->query("SELECT COUNT(*) FROM duty_audit_events WHERE hospital_id=".$hospital)->fetchColumn()>=9,'audit entries');
echo "PASS {$n} MySQL8 integrated V13 workflow assertions; CI disposable data only.\n";
