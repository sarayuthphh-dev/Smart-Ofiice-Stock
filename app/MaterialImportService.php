<?php
declare(strict_types=1);

final class MaterialImportService {
    private const HEADERS = ['code','name','category','unit','minimum_qty','location','description','active','track_assignee'];

    public function __construct(private PDO $db) {}

    public function import(string $path,int $actor): array {
        if(!is_file($path)||filesize($path)===0)throw new DomainException('ไฟล์ CSV ว่างหรืออ่านไม่ได้');
        $handle=fopen($path,'rb');if(!$handle)throw new DomainException('เปิดไฟล์ CSV ไม่ได้');
        $header=fgetcsv($handle,null,',','"','');if(!$header){fclose($handle);throw new DomainException('ไม่พบหัวตาราง CSV');}
        $header=array_map(fn($v)=>strtolower(trim((string)$v," \t\n\r\0\x0B\xEF\xBB\xBF")),$header);
        if($header!==self::HEADERS){fclose($handle);throw new DomainException('หัวตารางไม่ตรงกับไฟล์ตัวอย่าง กรุณาดาวน์โหลดแม่แบบใหม่');}
        $rows=[];$line=1;$seen=[];
        while(($data=fgetcsv($handle,null,',','"',''))!==false){$line++;if(count($data)===1&&trim((string)$data[0])==='')continue;if(count($data)!==count(self::HEADERS)){fclose($handle);throw new DomainException("แถว $line มีจำนวนคอลัมน์ไม่ถูกต้อง");}$row=array_combine(self::HEADERS,array_map('trim',$data));
            if(!mb_check_encoding(implode('',array_values($row)),'UTF-8')){fclose($handle);throw new DomainException("แถว $line ไม่ใช่ UTF-8");}
            foreach(['code'=>50,'name'=>200,'category'=>150,'unit'=>50,'location'=>150,'description'=>2000]as$key=>$max)if($row[$key]===''||mb_strlen($row[$key])>$max){if(in_array($key,['location','description'],true)&&$row[$key]==='')continue;fclose($handle);throw new DomainException("แถว $line: $key ว่างหรือยาวเกินกำหนด");}
            if(isset($seen[$row['code']])){fclose($handle);throw new DomainException("รหัส {$row['code']} ซ้ำในไฟล์");}$seen[$row['code']]=true;
            if(!is_numeric($row['minimum_qty'])||(float)$row['minimum_qty']<0){fclose($handle);throw new DomainException("แถว $line: minimum_qty ต้องเป็นเลขตั้งแต่ 0");}
            foreach(['active','track_assignee']as$key)if(!in_array(strtolower($row[$key]),['0','1','true','false','yes','no'],true)){fclose($handle);throw new DomainException("แถว $line: $key ต้องเป็น 1/0, true/false หรือ yes/no");}
            $rows[]=$row;
        }fclose($handle);if(!$rows)throw new DomainException('ไม่มีข้อมูลสำหรับนำเข้า');
        $this->db->beginTransaction();try{$category=$this->db->prepare('SELECT id FROM categories WHERE name=? FOR UPDATE');$addCategory=$this->db->prepare('INSERT INTO categories(name,active) VALUES(?,1)');$find=$this->db->prepare('SELECT id FROM materials WHERE code=? FOR UPDATE');$insert=$this->db->prepare('INSERT INTO materials(code,name,category_id,description,unit,minimum_qty,location,active,track_assignee) VALUES(?,?,?,?,?,?,?,?,?)');$update=$this->db->prepare('UPDATE materials SET name=?,category_id=?,description=?,unit=?,minimum_qty=?,location=?,active=?,track_assignee=? WHERE id=?');$created=0;$updated=0;
            foreach($rows as$row){$category->execute([$row['category']]);$categoryId=$category->fetchColumn();if(!$categoryId){$addCategory->execute([$row['category']]);$categoryId=$this->db->lastInsertId();}$active=$this->boolValue($row['active']);$track=$this->boolValue($row['track_assignee']);$find->execute([$row['code']]);$id=$find->fetchColumn();if($id){$update->execute([$row['name'],$categoryId,$row['description'],$row['unit'],round((float)$row['minimum_qty'],3),$row['location'],$active,$track,$id]);$updated++;}else{$insert->execute([$row['code'],$row['name'],$categoryId,$row['description'],$row['unit'],round((float)$row['minimum_qty'],3),$row['location'],$active,$track]);$created++;}}
            audit('material.import','material',null,null,['created'=>$created,'updated'=>$updated,'rows'=>count($rows)]);$this->db->commit();return compact('created','updated');
        }catch(Throwable$e){$this->db->rollBack();throw$e;}
    }

    private function boolValue(string $value): int { return in_array(strtolower($value),['1','true','yes'],true)?1:0; }
}
