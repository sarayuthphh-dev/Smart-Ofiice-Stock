<?php
declare(strict_types=1);

final class StockService {
    public function __construct(private PDO $db) {}

    private function move(int $materialId,string $type,float $delta,string $refType,int $refId,string $refNo,int $actor,?string $reason=null,?int $reversalOf=null): int {
        $s=$this->db->prepare('SELECT stock_qty FROM materials WHERE id=? FOR UPDATE'); $s->execute([$materialId]); $m=$s->fetch();
        if(!$m) throw new DomainException('ไม่พบวัสดุ');
        $balance=round((float)$m['stock_qty']+$delta,3); if($balance<0) throw new DomainException('ยอดคงเหลือไม่เพียงพอ');
        $this->db->prepare('UPDATE materials SET stock_qty=?,version=version+1 WHERE id=?')->execute([$balance,$materialId]);
        $this->db->prepare('INSERT INTO stock_movements(material_id,movement_type,quantity_delta,balance_after,reference_type,reference_id,reference_no,reversal_of_id,reason,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$materialId,$type,$delta,$balance,$refType,$refId,$refNo,$reversalOf,$reason,$actor]);
        return (int)$this->db->lastInsertId();
    }

    public function receive(int $materialId,float $quantity,string $date,string $source,?string $reference,int $actor,bool $opening=false): int {
        $this->db->beginTransaction();
        try { $no=document_no($opening?'OPEN':'RCV');
            $this->db->prepare('INSERT INTO receipts(receipt_no,received_date,source,reference_no,created_by) VALUES(?,?,?,?,?)')->execute([$no,$date,$source,$reference,$actor]); $id=(int)$this->db->lastInsertId();
            $this->move($materialId,$opening?'opening':'receipt',$quantity,'receipt',$id,$no,$actor,$source);
            audit($opening?'stock.opening':'stock.receive','receipt',$id,null,['material_id'=>$materialId,'qty'=>$quantity]); $this->db->commit(); return $id;
        } catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function returnItem(int $issueItemId,float $good,float $damaged,string $by,string $reason,int $actor): int {
        if($good<0||$damaged<0||$good+$damaged<=0) throw new DomainException('จำนวนคืนไม่ถูกต้อง');
        $this->db->beginTransaction();
        try { $s=$this->db->prepare('SELECT ii.*,ri.material_id,ri.requisition_id FROM issue_items ii JOIN requisition_items ri ON ri.id=ii.requisition_item_id WHERE ii.id=? FOR UPDATE'); $s->execute([$issueItemId]); $row=$s->fetch(); if(!$row)throw new DomainException('ไม่พบรายการจ่าย');
            $s=$this->db->prepare('SELECT COALESCE(SUM(good_qty+damaged_qty),0) FROM returns WHERE issue_item_id=?'); $s->execute([$issueItemId]);
            if((float)$s->fetchColumn()+$good+$damaged>(float)$row['quantity']) throw new DomainException('จำนวนคืนสะสมเกินจำนวนที่จ่าย');
            $no=document_no('RET'); $this->db->prepare('INSERT INTO returns(return_no,issue_item_id,returned_by_name,good_qty,damaged_qty,reason,recorded_by) VALUES(?,?,?,?,?,?,?)')->execute([$no,$issueItemId,$by,$good,$damaged,$reason,$actor]); $id=(int)$this->db->lastInsertId();
            $this->db->prepare('UPDATE requisition_items SET returned_good_qty=returned_good_qty+?,returned_damaged_qty=returned_damaged_qty+? WHERE id=?')->execute([$good,$damaged,$row['requisition_item_id']]);
            if($good>0)$this->move((int)$row['material_id'],'return_good',$good,'return',$id,$no,$actor,$reason);
            $this->db->prepare('UPDATE asset_assignments SET returned_qty=LEAST(quantity,returned_qty+?) WHERE requisition_item_id=?')->execute([$good+$damaged,$row['requisition_item_id']]);
            audit('stock.return','return',$id,null,['good'=>$good,'damaged'=>$damaged]); $this->db->commit(); return $id;
        } catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function createCount(array $materialIds,int $actor,string $note=''): int {
        $this->db->beginTransaction();
        try { $no=document_no('CNT'); $this->db->prepare('INSERT INTO stock_counts(count_no,note,created_by) VALUES(?,?,?)')->execute([$no,$note,$actor]); $id=(int)$this->db->lastInsertId();
            $q=$this->db->prepare('SELECT id,stock_qty,version FROM materials WHERE id=?'); $ins=$this->db->prepare('INSERT INTO stock_count_items(stock_count_id,material_id,system_qty,material_version) VALUES(?,?,?,?)');
            foreach(array_unique(array_map('intval',$materialIds)) as $mid){$q->execute([$mid]);if($m=$q->fetch())$ins->execute([$id,$mid,$m['stock_qty'],$m['version']]);}
            $this->db->commit(); return $id;
        } catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function confirmCount(int $countId,array $actuals,array $reasons,int $actor): void {
        $this->db->beginTransaction();
        try { $s=$this->db->prepare('SELECT * FROM stock_counts WHERE id=? FOR UPDATE');$s->execute([$countId]);$c=$s->fetch();if(!$c||$c['status']!=='open')throw new DomainException('รอบตรวจนับไม่อยู่ในสถานะยืนยันได้');
            $s=$this->db->prepare('SELECT sci.*,m.version FROM stock_count_items sci JOIN materials m ON m.id=sci.material_id WHERE sci.stock_count_id=? FOR UPDATE');$s->execute([$countId]);
            foreach($s->fetchAll() as $i){if((int)$i['version']!==(int)$i['material_version'])throw new DomainException('ยอดวัสดุมีการเคลื่อนไหวระหว่างตรวจนับ กรุณาสร้างรอบใหม่');$actual=round((float)($actuals[$i['id']]??-1),3);if($actual<0)throw new DomainException('กรุณากรอกยอดจริงให้ครบ');$diff=$actual-(float)$i['system_qty'];$reason=trim((string)($reasons[$i['id']]??''));if(abs($diff)>.0001&&$reason==='')throw new DomainException('รายการที่มีผลต่างต้องระบุเหตุผล');$this->db->prepare('UPDATE stock_count_items SET actual_qty=?,reason=? WHERE id=?')->execute([$actual,$reason,$i['id']]);if(abs($diff)>.0001)$this->move((int)$i['material_id'],'count_adjustment',$diff,'stock_count',$countId,$c['count_no'],$actor,$reason);}
            $this->db->prepare("UPDATE stock_counts SET status='confirmed',confirmed_by=?,confirmed_at=NOW() WHERE id=?")->execute([$actor,$countId]); audit('stock.count_confirm','stock_count',$countId,null,['confirmed'=>true]); $this->db->commit();
        } catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function reverseMovement(int $movementId,string $reason,int $actor): void {
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT * FROM stock_movements WHERE id=? FOR UPDATE');$s->execute([$movementId]);$m=$s->fetch();
            if(!$m)throw new DomainException('ไม่พบรายการเคลื่อนไหว');if($m['movement_type']==='reversal')throw new DomainException('ไม่สามารถกลับรายการกลับรายการได้');
            $s=$this->db->prepare('SELECT id FROM stock_movements WHERE reversal_of_id=?');$s->execute([$movementId]);if($s->fetch())throw new DomainException('รายการนี้ถูกกลับรายการแล้ว');
            $this->move((int)$m['material_id'],'reversal',-(float)$m['quantity_delta'],'movement',(int)$m['id'],document_no('REV'),$actor,$reason,$movementId);
            if($m['reference_type']==='issue_item'){
                $s=$this->db->prepare('SELECT ii.quantity,ri.id item_id,ri.requisition_id FROM issue_items ii JOIN requisition_items ri ON ri.id=ii.requisition_item_id WHERE ii.id=? FOR UPDATE');$s->execute([$m['reference_id']]);
                if($i=$s->fetch()){$this->db->prepare('UPDATE requisition_items SET issued_qty=GREATEST(0,issued_qty-?) WHERE id=?')->execute([$i['quantity'],$i['item_id']]);$s=$this->db->prepare('SELECT SUM(issued_qty),SUM(approved_qty) FROM requisition_items WHERE requisition_id=?');$s->execute([$i['requisition_id']]);[$issued,$approved]=$s->fetch(PDO::FETCH_NUM);$status=(float)$issued<=0?'approved':((float)$issued<(float)$approved?'partially_issued':'issued');$this->db->prepare('UPDATE requisitions SET status=?,completed_at=IF(?="issued",completed_at,NULL) WHERE id=?')->execute([$status,$status,$i['requisition_id']]);}
            } elseif($m['reference_type']==='return'){
                $s=$this->db->prepare('SELECT rt.good_qty,ii.requisition_item_id FROM returns rt JOIN issue_items ii ON ii.id=rt.issue_item_id WHERE rt.id=? FOR UPDATE');$s->execute([$m['reference_id']]);if($r=$s->fetch())$this->db->prepare('UPDATE requisition_items SET returned_good_qty=GREATEST(0,returned_good_qty-?) WHERE id=?')->execute([$r['good_qty'],$r['requisition_item_id']]);
            }
            audit('stock.reverse','stock_movement',$movementId,$m,['reason'=>$reason]);$this->db->commit();
        } catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
}
