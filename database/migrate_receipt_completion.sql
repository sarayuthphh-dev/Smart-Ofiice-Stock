-- ปรับข้อมูลเดิมให้คำขอเสร็จสมบูรณ์เมื่อผู้เบิกยืนยันรับครบทุกใบจ่าย
-- คำสั่งนี้รันซ้ำได้และไม่แก้ไขรายการเคลื่อนไหวสต๊อก
UPDATE requisitions r
JOIN (
  SELECT i.requisition_id,
         COUNT(*) AS issue_count,
         SUM(CASE WHEN rc.id IS NULL THEN 1 ELSE 0 END) AS pending_count,
         SUM(CASE WHEN rc.id IS NOT NULL THEN 1 ELSE 0 END) AS confirmed_count
  FROM issues i
  LEFT JOIN receipts_confirmation rc ON rc.issue_id=i.id
  GROUP BY i.requisition_id
) x ON x.requisition_id=r.id
SET r.receipt_status=CASE
      WHEN x.confirmed_count=0 THEN 'not_received'
      WHEN x.pending_count=0 THEN 'received'
      ELSE 'partially_received'
    END,
    r.completed_at=CASE
      WHEN r.status IN ('issued','closed') AND x.pending_count=0 THEN COALESCE(r.completed_at,NOW())
      ELSE NULL
    END;
