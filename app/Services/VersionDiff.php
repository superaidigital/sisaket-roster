<?php
 declare(strict_types=1);
 namespace App\Services;

 /** Pure comparison: keyed by user + work date, independent of row ordering and entry IDs. */
 final class VersionDiff {
     public static function compare(array $before,array $after,array $names=[]): array {
         $index=static function(array $entries): array {
             $map=[];
             foreach($entries as $entry) {
                 $key=(int)$entry['user_id'].'|'.(string)$entry['work_date'];
                 $map[$key]=[
                     'user_id'=>(int)$entry['user_id'],
                     'date'=>(string)$entry['work_date'],
                     'shift_code'=>(string)($entry['code']??''),
                     'shift_name'=>(string)($entry['shift_name']??''),
                     'note'=>(string)($entry['note']??''),
                 ];
             }
             return $map;
         };
         $left=$index($before);$right=$index($after);
         $keys=array_unique(array_merge(array_keys($left),array_keys($right)));
         sort($keys,SORT_STRING);$changes=[];$totals=['added'=>0,'removed'=>0,'changed'=>0];
         foreach($keys as $key) {
             $old=$left[$key]??null;$new=$right[$key]??null;
             if(($old['shift_code']??null)===($new['shift_code']??null) && ($old['note']??null)===($new['note']??null)) continue;
             $type=$old===null?'added':($new===null?'removed':'changed');$totals[$type]++;
             $userId=(int)($new['user_id']??$old['user_id']);
             $changes[]=['type'=>$type,'user_id'=>$userId,'staff_name'=>(string)($names[$userId]??('ID '.$userId)),
                 'date'=>(string)($new['date']??$old['date']),'before'=>$old,'after'=>$new];
         }
         return ['summary'=>$totals,'total'=>count($changes),'changes'=>$changes];
     }
 }
