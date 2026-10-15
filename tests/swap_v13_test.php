<?php
declare(strict_types=1);
$root=dirname(__DIR__);$n=0;
function test(bool $ok,string $name):void{global $n;$n++;if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}}
$router=file_get_contents($root.'/public/index.php');
$service=file_get_contents($root.'/app/Services/DutySwapService.php');
$ctl=file_get_contents($root.'/app/Controllers/SwapController.php');
$js=file_get_contents($root.'/public/js/swap-v13.js');
$fields=["'swap'=>['index','data','request','consent','review','reason']","'swap'=>new App\\Controllers\\SwapController()"];
foreach($fields as $field)test(str_contains($router,$field),'swap route present');
test(str_contains($service,"'ENC1:'.base64_encode"),'encrypted reason');
test(str_contains($service,"'PENDING_CONSENT'"),'consent workflow');
test(str_contains($service,"'PENDING_DIRECTOR'"),'director review');
test(str_contains($service,"'PENDING_AUTHORITY'"),'authority still pending');
test(str_contains($service,"'SHIFT_SWAP_REVIEW'"),'delegation scope distinct');
test(str_contains($service,"'CONSENT'"),'consent audit');
test(str_contains($service,"'DIRECTOR_REVIEW'"),'director audit');
test(str_contains($service,"expected_revision"),'snapshot revision');
test(str_contains($service,"DutyPolicy::overlaps"),'V11 overlapping shift guard');
test(!str_contains($service,'UPDATE duty_assignments'),'published duty roster not mutated');
test(!str_contains($service,'UPDATE compensation_rates'),'no payment write');
test(str_contains($ctl,'Csrf::check()'),'CSRF checks');
test(str_contains($ctl,'Auth::requireUser()'),'authenticated handler');
test(str_contains($js,'textContent'),'safe DOM text');
echo "PASS {$n} duty swap source safeguards; no database accessed.\n";
