-- ข้อมูลนี้เป็นเพียงทะเบียนตัวอย่าง ไม่มี stock สมมติ และไม่ถูกรันโดยตัวติดตั้งอัตโนมัติ
-- หลังนำเข้า ให้บันทึกยอดตั้งต้นจากผลตรวจนับจริงผ่านเมนู "คลังวัสดุและรับเข้า"
INSERT INTO materials(code,name,category_id,unit,minimum_qty,location,track_assignee)
SELECT 'PAPER-A4-80','กระดาษถ่ายเอกสาร A4 80 แกรม',id,'รีม',5,'ชั้น A1',0 FROM categories WHERE name='กระดาษ';
INSERT INTO materials(code,name,category_id,unit,minimum_qty,location,track_assignee)
SELECT 'PEN-BLUE-01','ปากกาลูกลื่น สีน้ำเงิน 0.5 มม.',id,'ด้าม',10,'ชั้น B1',0 FROM categories WHERE name='เครื่องเขียน';
INSERT INTO materials(code,name,category_id,unit,minimum_qty,location,track_assignee)
SELECT 'TAPE-CLEAR-18','เทปใส 18 มม.',id,'ม้วน',5,'ชั้น B2',0 FROM categories WHERE name='เครื่องเขียน';
INSERT INTO materials(code,name,category_id,unit,minimum_qty,location,track_assignee)
SELECT 'STAPLE-10','ลวดเย็บกระดาษ เบอร์ 10',id,'กล่อง',5,'ชั้น B3',0 FROM categories WHERE name='เครื่องเขียน';
INSERT INTO materials(code,name,category_id,unit,minimum_qty,location,track_assignee)
SELECT 'STAPLER-10','เครื่องเย็บกระดาษ เบอร์ 10',id,'เครื่อง',2,'ตู้ C1',1 FROM categories WHERE name='อุปกรณ์สำนักงาน';

