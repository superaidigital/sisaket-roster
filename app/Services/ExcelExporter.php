<?php
declare(strict_types=1);
namespace App\Services;
use ZipArchive;

final class ExcelExporter {
    public static function download(array $roster,array $hospital,array $staff,array $entries): never {
        $filename=sprintf('roster_%s_%04d-%02d_v%d',preg_replace('/[^A-Za-z0-9_-]/','_',$hospital['hospital_code']),$roster['year'],$roster['month'],$roster['version']);
        $days=(int)date('t',strtotime(sprintf('%04d-%02d-01',$roster['year'],$roster['month'])));
        $cells=[]; foreach($entries as $entry) $cells[$entry['user_id'].'|'.(int)substr($entry['work_date'],8,2)]=['label'=>$entry['short_name'],'working'=>(int)$entry['is_working']];
        $rows=[['ตารางปฏิบัติงาน - '.$hospital['name'].' - เดือน '.(int)$roster['month'].' / พ.ศ. '.((int)$roster['year']+543).' - Version '.$roster['version']]];
        $rows[]=array_merge(['บุคลากร'],array_map('strval',range(1,$days)),['จำนวนเวร','หมายเหตุ']);
        foreach($staff as $person) {
            $row=[$person['full_name']]; $number=0;
            for($d=1;$d<=$days;$d++) { $shift=$cells[$person['id'].'|'.$d] ?? null; $row[]=$shift['label'] ?? ''; if($shift && $shift['working']) $number++; }
            $row[]=(string)$number; $row[]=''; $rows[]=$row;
        }
        $rows[]=['ผู้จัดทำ ........................................................ ผู้ตรวจสอบ ........................................................ ผู้อนุมัติ ........................................................'];
        if(!class_exists(ZipArchive::class)) self::csv($rows,$filename);
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane xSplit="1" ySplit="2" topLeftCell="B3" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="1" width="35" customWidth="1"/><col min="2" max="34" width="8" customWidth="1"/></cols><sheetData>';
        foreach($rows as $index=>$row) {
            $rowNo=$index+1; $xml.='<row r="'.$rowNo.'">';
            foreach($row as $column=>$value) {
                $address=self::column($column+1).$rowNo;
                $escaped=htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8');
                $xml.='<c r="'.$address.'" t="inlineStr"><is><t xml:space="preserve">'.$escaped.'</t></is></c>';
            }
            $xml.='</row>';
        }
        $xml.='</sheetData><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.3" footer="0.3"/></worksheet>';
        $file=tempnam(sys_get_temp_dir(),'roster_xlsx_');
        if($file===false) jsonError('สร้างไฟล์ชั่วคราวไม่สำเร็จ',500);
        $zip=new ZipArchive(); if($zip->open($file,ZipArchive::OVERWRITE)!==true) jsonError('ไม่สามารถสร้างไฟล์ Excel ได้',500);
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="ตารางเวร" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml',$xml); $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'.xlsx"');
        header('Content-Length: '.filesize($file)); header('Cache-Control: private, no-store');
        readfile($file); unlink($file); exit;
    }
    private static function column(int $number): string {
        $s=''; while($number>0) { $number--; $s=chr(65+$number%26).$s; $number=intdiv($number,26); } return $s;
    }
    private static function csv(array $rows,string $name): never {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$name.'.csv"');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
        foreach($rows as $r) {
            $safe=array_map(static function ($value): string {
                $s=(string)$value;
                // CSV spreadsheet programs may interpret user-controlled text as formulas.
                return preg_match('/^\\s*[=+\\-@]/u',$s) ? "'".$s : $s;
            },$r);
            fputcsv($out,$safe);
        }
        fclose($out); exit;
    }
}
