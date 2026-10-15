<?php
declare(strict_types=1);

final class StockIntegrityService
{
    public function __construct(private readonly PDO $db) {}

    public function inspect(): array
    {
        $sql="SELECT m.id,m.code,m.name,m.unit,m.stock_qty,
            COALESCE(SUM(sm.quantity_delta),0) ledger_qty,
            COUNT(sm.id) movement_count,
            (SELECT sm2.balance_after FROM stock_movements sm2 WHERE sm2.material_id=m.id ORDER BY sm2.id DESC LIMIT 1) latest_balance
            FROM materials m LEFT JOIN stock_movements sm ON sm.material_id=m.id
            GROUP BY m.id,m.code,m.name,m.unit,m.stock_qty ORDER BY m.name";
        $rows=$this->db->query($sql)->fetchAll();$mismatches=[];
        foreach($rows as&$row){$stock=round((float)$row['stock_qty'],3);$ledger=round((float)$row['ledger_qty'],3);$latest=$row['latest_balance']===null?0.0:round((float)$row['latest_balance'],3);$row['difference']=round($stock-$ledger,3);$row['latest_difference']=round($stock-$latest,3);$row['ok']=abs($row['difference'])<0.0005&&abs($row['latest_difference'])<0.0005;if(!$row['ok'])$mismatches[]=$row;}unset($row);
        return ['checked_at'=>date('Y-m-d H:i:s'),'total'=>count($rows),'ok'=>count($rows)-count($mismatches),'mismatch_count'=>count($mismatches),'rows'=>$rows,'mismatches'=>$mismatches];
    }
}
