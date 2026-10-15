<?php
declare(strict_types=1);

final class ExecutiveReportService
{
    public function __construct(private readonly PDO $db) {}

    public function build(?string $from = null, ?string $to = null): array
    {
        $where=[];$params=[];
        if($from){$where[]='DATE(i.issued_at)>=?';$params[]=$from;}
        if($to){$where[]='DATE(i.issued_at)<=?';$params[]=$to;}
        $filter=$where?' WHERE '.implode(' AND ',$where):'';
        $base=' FROM issue_items ii JOIN issues i ON i.id=ii.issue_id JOIN requisition_items ri ON ri.id=ii.requisition_item_id JOIN requisitions r ON r.id=ri.requisition_id JOIN materials m ON m.id=ri.material_id LEFT JOIN work_groups g ON g.id=r.work_group_id';
        $one=function(string$sql,array$p=[]){$s=$this->db->prepare($sql);$s->execute($p);return$s->fetch();};
        $all=function(string$sql,array$p=[]){$s=$this->db->prepare($sql);$s->execute($p);return$s->fetchAll();};
        $summary=$one('SELECT COUNT(DISTINCT r.id) request_count,COUNT(DISTINCT i.id) issue_count,COUNT(ii.id) line_count,COUNT(DISTINCT r.work_group_id) group_count,COALESCE(SUM(ii.quantity),0) total_units'.$base.$filter,$params);
        $monthly=$all("SELECT DATE_FORMAT(i.issued_at,'%Y-%m') month_key,COUNT(DISTINCT r.id) request_count,COUNT(DISTINCT i.id) issue_count".$base.$filter." GROUP BY DATE_FORMAT(i.issued_at,'%Y-%m') ORDER BY month_key",$params);
        $materials=$all('SELECT m.code,m.name,m.unit,SUM(ii.quantity) issued_qty,COUNT(DISTINCT r.id) request_count'.$base.$filter.' GROUP BY m.id,m.code,m.name,m.unit ORDER BY issued_qty DESC LIMIT 10',$params);
        $groups=$all("SELECT COALESCE(g.name,'ไม่ระบุกลุ่มงาน') group_name,COUNT(DISTINCT r.id) request_count,COUNT(DISTINCT i.id) issue_count,COUNT(DISTINCT ri.material_id) material_count".$base.$filter." GROUP BY r.work_group_id,g.name ORDER BY request_count DESC,group_name LIMIT 10",$params);
        $anomalies=$all("SELECT m.code,m.name,m.unit,SUM(CASE WHEN i.issued_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) THEN ii.quantity ELSE 0 END) current_qty,SUM(CASE WHEN i.issued_at<DATE_SUB(NOW(),INTERVAL 30 DAY) THEN ii.quantity ELSE 0 END) previous_qty FROM issue_items ii JOIN issues i ON i.id=ii.issue_id JOIN requisition_items ri ON ri.id=ii.requisition_item_id JOIN materials m ON m.id=ri.material_id WHERE i.issued_at>=DATE_SUB(NOW(),INTERVAL 60 DAY) GROUP BY m.id,m.code,m.name,m.unit HAVING SUM(CASE WHEN i.issued_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) THEN ii.quantity ELSE 0 END)>0 AND (SUM(CASE WHEN i.issued_at<DATE_SUB(NOW(),INTERVAL 30 DAY) THEN ii.quantity ELSE 0 END)=0 OR SUM(CASE WHEN i.issued_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) THEN ii.quantity ELSE 0 END)>=SUM(CASE WHEN i.issued_at<DATE_SUB(NOW(),INTERVAL 30 DAY) THEN ii.quantity ELSE 0 END)*1.5) ORDER BY (SUM(CASE WHEN i.issued_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) THEN ii.quantity ELSE 0 END)-SUM(CASE WHEN i.issued_at<DATE_SUB(NOW(),INTERVAL 30 DAY) THEN ii.quantity ELSE 0 END)) DESC LIMIT 10");
        $forecast=$all("SELECT m.code,m.name,m.unit,m.stock_qty,m.minimum_qty,COALESCE(x.used_90,0) used_90,CASE WHEN COALESCE(x.used_90,0)>0 THEN ROUND(m.stock_qty/(x.used_90/90),1) ELSE NULL END days_left FROM materials m LEFT JOIN (SELECT ri.material_id,SUM(ii.quantity) used_90 FROM issue_items ii JOIN issues i ON i.id=ii.issue_id JOIN requisition_items ri ON ri.id=ii.requisition_item_id WHERE i.issued_at>=DATE_SUB(NOW(),INTERVAL 90 DAY) GROUP BY ri.material_id)x ON x.material_id=m.id WHERE m.active=1 ORDER BY days_left IS NULL,days_left,m.stock_qty LIMIT 10");
        return compact('summary','monthly','materials','groups','anomalies','forecast','from','to');
    }
}
