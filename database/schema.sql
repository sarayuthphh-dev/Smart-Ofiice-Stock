SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(50) PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(180) NOT NULL,
  email VARCHAR(190) NULL,
  line_user_id VARCHAR(33) NULL,
  work_group_id BIGINT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_group FOREIGN KEY (work_group_id) REFERENCES work_groups(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name_th VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(user_id, role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  description TEXT NULL,
  unit VARCHAR(50) NOT NULL,
  stock_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  minimum_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  location VARCHAR(150) NULL,
  image_path VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  track_assignee TINYINT(1) NOT NULL DEFAULT 0,
  version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_material_category FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT chk_material_stock CHECK (stock_qty >= 0),
  INDEX idx_material_name(name), INDEX idx_material_low(active, stock_qty, minimum_qty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requisitions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_no VARCHAR(30) NULL UNIQUE,
  requester_id BIGINT UNSIGNED NOT NULL,
  work_group_id BIGINT UNSIGNED NULL,
  purpose TEXT NOT NULL,
  status ENUM('draft','pending','returned','approved','rejected','cancelled','partially_issued','issued','closed') NOT NULL DEFAULT 'draft',
  receipt_status ENUM('not_received','partially_received','received') NOT NULL DEFAULT 'not_received',
  submitted_at DATETIME NULL,
  approved_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  close_reason TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_req_user FOREIGN KEY (requester_id) REFERENCES users(id),
  CONSTRAINT fk_req_group FOREIGN KEY (work_group_id) REFERENCES work_groups(id),
  INDEX idx_req_status(status), INDEX idx_req_requester(requester_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requisition_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requisition_id BIGINT UNSIGNED NOT NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  requested_qty DECIMAL(14,3) NOT NULL,
  approved_qty DECIMAL(14,3) NULL,
  issued_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  returned_good_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  returned_damaged_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  note VARCHAR(500) NULL,
  CONSTRAINT fk_ri_req FOREIGN KEY (requisition_id) REFERENCES requisitions(id),
  CONSTRAINT fk_ri_material FOREIGN KEY (material_id) REFERENCES materials(id),
  CONSTRAINT uq_req_material UNIQUE(requisition_id, material_id),
  CONSTRAINT chk_requested CHECK(requested_qty > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requisition_actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requisition_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NOT NULL,
  reason TEXT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ra_req FOREIGN KEY(requisition_id) REFERENCES requisitions(id),
  CONSTRAINT fk_ra_actor FOREIGN KEY(actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(30) NOT NULL UNIQUE,
  received_date DATE NOT NULL,
  source VARCHAR(200) NOT NULL,
  reference_no VARCHAR(100) NULL,
  note TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issues (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  issue_no VARCHAR(30) NOT NULL UNIQUE,
  requisition_id BIGINT UNSIGNED NOT NULL,
  receiver_name VARCHAR(180) NOT NULL,
  issued_by BIGINT UNSIGNED NOT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  idempotency_key VARCHAR(100) NOT NULL UNIQUE,
  note TEXT NULL,
  CONSTRAINT fk_issue_req FOREIGN KEY(requisition_id) REFERENCES requisitions(id),
  CONSTRAINT fk_issue_user FOREIGN KEY(issued_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issue_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  issue_id BIGINT UNSIGNED NOT NULL,
  requisition_item_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  CONSTRAINT fk_ii_issue FOREIGN KEY(issue_id) REFERENCES issues(id),
  CONSTRAINT fk_ii_item FOREIGN KEY(requisition_item_id) REFERENCES requisition_items(id),
  CONSTRAINT chk_issue_qty CHECK(quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS returns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_no VARCHAR(30) NOT NULL UNIQUE,
  issue_item_id BIGINT UNSIGNED NOT NULL,
  returned_by_name VARCHAR(180) NOT NULL,
  good_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  damaged_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  reason TEXT NOT NULL,
  recorded_by BIGINT UNSIGNED NOT NULL,
  returned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_return_issue FOREIGN KEY(issue_item_id) REFERENCES issue_items(id),
  CONSTRAINT fk_return_user FOREIGN KEY(recorded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_counts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  count_no VARCHAR(30) NOT NULL UNIQUE,
  status ENUM('open','confirmed','cancelled') NOT NULL DEFAULT 'open',
  note TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  confirmed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at DATETIME NULL,
  CONSTRAINT fk_sc_creator FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_sc_confirmer FOREIGN KEY(confirmed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_count_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stock_count_id BIGINT UNSIGNED NOT NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  system_qty DECIMAL(14,3) NOT NULL,
  actual_qty DECIMAL(14,3) NULL,
  material_version BIGINT UNSIGNED NOT NULL,
  reason TEXT NULL,
  CONSTRAINT fk_sci_count FOREIGN KEY(stock_count_id) REFERENCES stock_counts(id),
  CONSTRAINT fk_sci_material FOREIGN KEY(material_id) REFERENCES materials(id),
  UNIQUE(stock_count_id, material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  material_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('opening','receipt','issue','return_good','count_adjustment','reversal') NOT NULL,
  quantity_delta DECIMAL(14,3) NOT NULL,
  balance_after DECIMAL(14,3) NOT NULL,
  reference_type VARCHAR(40) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  reference_no VARCHAR(30) NOT NULL,
  reversal_of_id BIGINT UNSIGNED NULL UNIQUE,
  reason TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sm_material FOREIGN KEY(material_id) REFERENCES materials(id),
  CONSTRAINT fk_sm_user FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_sm_reversal FOREIGN KEY(reversal_of_id) REFERENCES stock_movements(id),
  INDEX idx_sm_material_date(material_id, created_at), INDEX idx_sm_ref(reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  material_id BIGINT UNSIGNED NOT NULL,
  requisition_item_id BIGINT UNSIGNED NOT NULL,
  assigned_user_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  returned_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aa_material FOREIGN KEY(material_id) REFERENCES materials(id),
  CONSTRAINT fk_aa_item FOREIGN KEY(requisition_item_id) REFERENCES requisition_items(id),
  CONSTRAINT fk_aa_user FOREIGN KEY(assigned_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipts_confirmation (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  issue_id BIGINT UNSIGNED NOT NULL UNIQUE,
  confirmed_by BIGINT UNSIGNED NOT NULL,
  confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rc_issue FOREIGN KEY(issue_id) REFERENCES issues(id),
  CONSTRAINT fk_rc_user FOREIGN KEY(confirmed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY(actor_id) REFERENCES users(id),
  INDEX idx_audit_entity(entity_type, entity_id), INDEX idx_audit_date(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_setting_user FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_key VARCHAR(190) NOT NULL,
  requisition_id BIGINT UNSIGNED NULL,
  channel VARCHAR(30) NOT NULL,
  recipient VARCHAR(100) NOT NULL,
  status ENUM('sending','sent','failed') NOT NULL DEFAULT 'sending',
  http_status SMALLINT UNSIGNED NULL,
  error_message VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  CONSTRAINT fk_notification_requisition FOREIGN KEY(requisition_id) REFERENCES requisitions(id),
  CONSTRAINT uq_notification_event_recipient UNIQUE(event_key, recipient),
  INDEX idx_notification_status(status, created_at),
  INDEX idx_notification_requisition(requisition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles(code,name_th) VALUES
('requester','เจ้าหน้าที่ผู้เบิก'),('approver','ผู้อนุมัติ'),('supply','เจ้าหน้าที่พัสดุ'),('admin','ผู้ดูแลระบบ');
INSERT IGNORE INTO categories(name) VALUES ('กระดาษ'),('เครื่องเขียน'),('อุปกรณ์สำนักงาน');
INSERT IGNORE INTO settings(setting_key,setting_value) VALUES ('request_prefix','REQ'),('office_name','สำนักงานตรวจบัญชีสหกรณ์อุทัยธานี'),('system_name','ระบบเบิกวัสดุสำนักงานออนไลน์'),('theme_color','blue'),('logo_path',''),('report_header_text',''),('report_footer_text',''),('report_prepared_label','ผู้จัดทำ'),('report_checked_label','ผู้ตรวจสอบ'),('report_orientation','landscape'),('report_show_logo','1'),('requisition_print_title','ใบเบิกวัสดุสำนักงาน'),('requisition_print_subtitle',''),('requisition_print_header_text',''),('requisition_print_footer_text',''),('requisition_print_show_logo','1'),('requisition_print_show_status','0'),('requisition_print_request_no_label','เลขคำขอ'),('requisition_print_date_label','วันที่'),('requisition_print_requester_label','ผู้เบิก'),('requisition_print_group_label','กลุ่มงาน'),('requisition_print_purpose_label','วัตถุประสงค์'),('requisition_print_item_label','รหัส/รายการ'),('requisition_print_requested_label','ขอ'),('requisition_print_approved_label','อนุมัติ'),('requisition_print_issued_label','จ่ายแล้ว'),('requisition_print_sign_requester','ผู้เบิก'),('requisition_print_sign_approver','ผู้อนุมัติ'),('requisition_print_sign_issuer','ผู้จ่าย'),('requisition_print_sign_receiver','ผู้รับ'),('line_notifications_enabled','0'),('line_recipient_user_ids','');
INSERT IGNORE INTO schema_migrations(version) VALUES ('001_initial');
INSERT IGNORE INTO settings(setting_key,setting_value) VALUES
('reminder_notifications_enabled','0'),
('reminder_pending_days','1'),
('reminder_issue_days','1'),
('reminder_receipt_days','2');
