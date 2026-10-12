<?php
declare(strict_types=1);
namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/** Small, deliberately bounded importer for the supplied roster personnel template.
 * Only UTF-8 CSV and plain .xlsx values are supported. No formulas/macros are evaluated.
 */
final class StaffSpreadsheetReader {
    public const MAX_ROWS=200;
    private const MAX_CSV_BYTES=1048576;
    private const MAX_XML_BYTES=8388608;
    private const COLUMNS=['full_name','email','role','position','personnel_type'];

    public static function read(string $path,string $originalName): array {
        $extension=strtolower(pathinfo($originalName,PATHINFO_EXTENSION));
        if (!in_array($extension,['csv','xlsx'],true)) throw new RuntimeException('รองรับเฉพาะไฟล์ .xlsx และ .csv (UTF-8)');
        if (!is_file($path) || filesize($path)===false || filesize($path)>self::MAX_CSV_BYTES) {
            throw new RuntimeException('ไฟล์มีขนาดเกิน 1 MB หรืออ่านไม่ได้');
        }
        if ($extension==='csv') $rows=self::readCsv($path);
        else $rows=self::readXlsx($path);
        return self::normalize($rows);
    }

    private static function readCsv(string $path): array {
        $fp=fopen($path,'rb');
        if (!$fp) throw new RuntimeException('ไม่สามารถอ่านไฟล์ CSV');
        try {
            $rows=[];
            while (($line=fgetcsv($fp,0,',','"',''))!==false) {
                if (count($rows)>self::MAX_ROWS+1) throw new RuntimeException('รองรับสูงสุด 200 รายชื่อ/ไฟล์');
                foreach ($line as $field) {
                    if (!mb_check_encoding((string)$field,'UTF-8')) throw new RuntimeException('CSV ต้องเข้ารหัส UTF-8 กรุณาบันทึกเป็น CSV UTF-8');
                }
                $rows[]=$line;
            }
            return $rows;
        } finally { fclose($fp); }
    }

    private static function boundedEntry(ZipArchive $zip,string $name,int $limit): ?string {
        $index=$zip->locateName($name);
        if ($index===false) return null;
        $info=$zip->statIndex($index);
        if (!$info || $info['size']>$limit) throw new RuntimeException('ไฟล์ Excel มีข้อมูลภายในใหญ่เกินกำหนด');
        $data=$zip->getFromIndex($index);
        if ($data===false || strlen($data)>$limit) throw new RuntimeException('ไม่สามารถอ่านข้อมูล Excel');
        return $data;
    }
    private static function dom(string $xml): DOMDocument {
        if (stripos($xml,'<!DOCTYPE')!==false || stripos($xml,'<!ENTITY')!==false) throw new RuntimeException('Excel มี XML declaration ที่ไม่อนุญาต');
        if (!class_exists(DOMDocument::class)) throw new RuntimeException('ต้องเปิด PHP extension DOM/XML ใน XAMPP');
        $previous=libxml_use_internal_errors(true);
        try {
            $dom=new DOMDocument('1.0','UTF-8');
            if (!$dom->loadXML($xml,LIBXML_NONET|LIBXML_NOBLANKS)) throw new RuntimeException('โครงสร้าง XML ของ Excel ไม่ถูกต้อง');
            return $dom;
        } finally { libxml_clear_errors();libxml_use_internal_errors($previous); }
    }
    private static function readXlsx(string $path): array {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('ต้องเปิด PHP extension zip ใน XAMPP');
        $zip=new ZipArchive();
        if ($zip->open($path)!==true) throw new RuntimeException('ไฟล์ .xlsx ไม่ถูกต้อง');
        try {
            if ($zip->numFiles>300) throw new RuntimeException('ไฟล์ Excel มีส่วนประกอบมากเกินกำหนด');
            if ($zip->locateName('xl/vbaProject.bin')!==false) throw new RuntimeException('ไม่อนุญาตไฟล์ Excel ที่มี Macro');
            $shared=[];
            $sharedXml=self::boundedEntry($zip,'xl/sharedStrings.xml',self::MAX_XML_BYTES);
            if ($sharedXml!==null) {
                $dom=self::dom($sharedXml); $xp=new DOMXPath($dom);
                foreach ($xp->query('//*[local-name()="sst"]/*[local-name()="si"]') as $si) {
                    if (count($shared)>20000) throw new RuntimeException('ตารางสตริง Excel ใหญ่เกินกำหนด');
                    $value='';
                    foreach ($xp->query('.//*[local-name()="t"]',$si) as $t) $value.=$t->textContent;
                    $shared[]=$value;
                }
            }
            $sheetXml=self::boundedEntry($zip,'xl/worksheets/sheet1.xml',self::MAX_XML_BYTES);
            if ($sheetXml===null) throw new RuntimeException('ไม่พบ worksheet แรก (sheet1.xml) กรุณาใช้แม่แบบ Excel ที่ให้มา');
            $dom=self::dom($sheetXml); $xp=new DOMXPath($dom); $rows=[];
            foreach ($xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $rowNumber=(int)$row->getAttribute('r');
                if ($rowNumber<1) continue;
                if ($rowNumber>self::MAX_ROWS+1) throw new RuntimeException('Excel รองรับสูงสุด 200 รายชื่อ/ไฟล์ (ไม่รวมแถวหัวตาราง)');
                $values=array_fill(0,5,'');
                foreach ($xp->query('./*[local-name()="c"]',$row) as $cell) {
                    if (!$cell instanceof DOMElement) continue;
                    $ref=$cell->getAttribute('r');
                    if (!preg_match('/^([A-Z]+)\d+$/',$ref,$matches)) continue;
                    $col=self::colIndex($matches[1]);
                    if ($col>4) continue;
                    if ($xp->query('./*[local-name()="f"]',$cell)->length>0) {
                        throw new RuntimeException('ไม่อนุญาตสูตร Excel ในช่องข้อมูล กรุณาใช้ค่าข้อความเท่านั้น');
                    }
                    $type=$cell->getAttribute('t');
                    if ($type==='inlineStr') {
                        $content='';
                        foreach ($xp->query('.//*[local-name()="t"]',$cell) as $t) $content.=$t->textContent;
                    } else {
                        $nodes=$xp->query('./*[local-name()="v"]',$cell);
                        $content=$nodes->length ? $nodes->item(0)->textContent : '';
                        if ($type==='s') {
                            $index=filter_var($content,FILTER_VALIDATE_INT);
                            if ($index===false || !isset($shared[$index])) throw new RuntimeException('พบข้อความอ้างอิง Excel ที่ไม่ถูกต้อง');
                            $content=$shared[$index];
                        }
                        if ($type==='e') throw new RuntimeException('Excel มีช่องที่เป็นข้อผิดพลาด');
                    }
                    $values[$col]=$content;
                }
                $rows[$rowNumber]=$values;
            }
            if (!$rows) throw new RuntimeException('ไม่พบข้อมูลใน Excel');
            // Preserve Excel source row numbers for diagnostics; do not shift empty lines.
            $result=[];
            foreach (range(1,max(array_keys($rows))) as $i) $result[$i]=$rows[$i]??array_fill(0,5,'');
            return $result;
        } finally { $zip->close(); }
    }
    private static function colIndex(string $ref): int {
        $n=0;
        foreach(str_split($ref) as $char) $n=$n*26+(ord($char)-64);
        return $n-1;
    }
    private static function normalize(array $raw): array {
        $headers=array_shift($raw);
        if (!is_array($headers)) throw new RuntimeException('ไม่มีแถวหัวตาราง');
        $headers=array_map(static fn($v):string=>mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$v))),$headers);
        foreach(self::COLUMNS as $column) if(!in_array($column,$headers,true)) {
            throw new RuntimeException('ไม่พบหัวคอลัมน์ '.$column.' กรุณาใช้ไฟล์แม่แบบ');
        }
        $indices=[];
        foreach(self::COLUMNS as $column) {
            if(count(array_keys($headers,$column,true))!==1) throw new RuntimeException('พบหัวคอลัมน์ซ้ำ: '.$column);
            $indices[$column]=array_search($column,$headers,true);
        }
        $out=[];
        foreach ($raw as $index=>$data) {
            if (!is_array($data)) continue;
            $row=[];
            foreach($indices as $name=>$position) $row[$name]=trim((string)($data[$position]??''));
            if (implode('',$row)==='') continue;
            $out[]=['line'=>$index+2]+$row;
            if (count($out)>self::MAX_ROWS) throw new RuntimeException('รองรับสูงสุด 200 รายชื่อ/ไฟล์');
        }
        if (!$out) throw new RuntimeException('ไฟล์ไม่มีข้อมูลบุคลากร');
        return $out;
    }
}
