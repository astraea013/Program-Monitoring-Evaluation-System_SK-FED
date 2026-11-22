-- =====================================================================
-- Web-Based Program Monitoring and Evaluation System
-- for the Sangguniang Kabataan Federation
--
-- Database schema  (MySQL / MariaDB -- XAMPP)
-- Built from Figure 13. ERD (Database Design)
--
-- Import:  phpMyAdmin -> Import -> choose this file
--      or  mysql -u root -p < schema.sql
-- =====================================================================

DROP DATABASE IF EXISTS sk_monitoring;
CREATE DATABASE sk_monitoring
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE sk_monitoring;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. roles
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  role_id    INT(11)     NOT NULL AUTO_INCREMENT,
  role_name  VARCHAR(50) NOT NULL,
  PRIMARY KEY (role_id),
  UNIQUE KEY uq_roles_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. barangays
-- ---------------------------------------------------------------------
CREATE TABLE barangays (
  barangay_id    INT(11)      NOT NULL AUTO_INCREMENT,
  barangay_name  VARCHAR(100) NOT NULL,
  municipality   VARCHAR(100) NOT NULL DEFAULT 'Nasipit',
  PRIMARY KEY (barangay_id),
  UNIQUE KEY uq_barangay (barangay_name, municipality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. users
--    One account table for all four roles. verified_by points at the
--    SK Barangay Chairman who approved a KK registration.
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id              INT(11)      NOT NULL AUTO_INCREMENT,
  role_id              INT(11)      NOT NULL,
  barangay_id          INT(11)      DEFAULT NULL,
  verified_by          INT(11)      DEFAULT NULL,
  username             VARCHAR(50)  NOT NULL,
  email                VARCHAR(150) NOT NULL,
  password_hash        VARCHAR(255) NOT NULL,
  verification_status  VARCHAR(20)  NOT NULL DEFAULT 'pending',
  date_verified        DATETIME     DEFAULT NULL,
  is_active            TINYINT(1)   NOT NULL DEFAULT 1,
  last_login           DATETIME     DEFAULT NULL,
  date_created         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY fk_users_role (role_id),
  KEY fk_users_barangay (barangay_id),
  KEY fk_users_verifier (verified_by),
  CONSTRAINT fk_users_role     FOREIGN KEY (role_id)     REFERENCES roles (role_id),
  CONSTRAINT fk_users_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (barangay_id),
  CONSTRAINT fk_users_verifier FOREIGN KEY (verified_by) REFERENCES users (user_id),
  CONSTRAINT chk_users_status  CHECK (verification_status IN ('pending','verified','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. youth_profiles
-- ---------------------------------------------------------------------
CREATE TABLE youth_profiles (
  profile_id    INT(11)      NOT NULL AUTO_INCREMENT,
  user_id       INT(11)      DEFAULT NULL,
  barangay_id   INT(11)      NOT NULL,
  full_name     VARCHAR(255) NOT NULL,
  birthdate     DATE         NOT NULL,
  sex           VARCHAR(10)  DEFAULT NULL,
  contact_no    VARCHAR(20)  DEFAULT NULL,
  address       VARCHAR(255) DEFAULT NULL,
  civil_status  VARCHAR(30)  DEFAULT NULL,
  education     VARCHAR(100) DEFAULT NULL,
  work_status   VARCHAR(100) DEFAULT NULL,
  date_encoded  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (profile_id),
  UNIQUE KEY uq_profile_user (user_id),
  KEY fk_profile_barangay (barangay_id),
  CONSTRAINT fk_profile_user     FOREIGN KEY (user_id)     REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT fk_profile_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (barangay_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. programs
-- ---------------------------------------------------------------------
CREATE TABLE programs (
  program_id   INT(11)      NOT NULL AUTO_INCREMENT,
  barangay_id  INT(11)      NOT NULL,
  created_by   INT(11)      NOT NULL,
  program_code VARCHAR(20)  NOT NULL,
  title        VARCHAR(255) NOT NULL,
  centre       VARCHAR(100) DEFAULT NULL,
  description  TEXT         DEFAULT NULL,
  status       VARCHAR(50)  NOT NULL DEFAULT 'planned',
  date_created DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (program_id),
  UNIQUE KEY uq_program_code (program_code),
  KEY fk_program_barangay (barangay_id),
  KEY fk_program_creator (created_by),
  CONSTRAINT fk_program_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (barangay_id),
  CONSTRAINT fk_program_creator  FOREIGN KEY (created_by)  REFERENCES users (user_id),
  CONSTRAINT chk_program_status  CHECK (status IN ('planned','ongoing','completed','evaluated','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. activities      (qr_token is unique -- it identifies the activity)
-- ---------------------------------------------------------------------
CREATE TABLE activities (
  activity_id   INT(11)      NOT NULL AUTO_INCREMENT,
  program_id    INT(11)      NOT NULL,
  title         VARCHAR(255) NOT NULL,
  activity_date DATE         NOT NULL,
  start_time    TIME         DEFAULT NULL,
  end_time      TIME         DEFAULT NULL,
  venue         VARCHAR(255) DEFAULT NULL,
  qr_token      VARCHAR(64)  NOT NULL,
  is_open       TINYINT(1)   NOT NULL DEFAULT 1,
  target_count  INT(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (activity_id),
  UNIQUE KEY uq_activity_qr (qr_token),
  KEY fk_activity_program (program_id),
  CONSTRAINT fk_activity_program FOREIGN KEY (program_id) REFERENCES programs (program_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. attendance      (one scan per member per activity)
-- ---------------------------------------------------------------------
CREATE TABLE attendance (
  attendance_id INT(11)     NOT NULL AUTO_INCREMENT,
  activity_id   INT(11)     NOT NULL,
  profile_id    INT(11)     NOT NULL,
  time_scanned  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  entry_method  VARCHAR(20) NOT NULL DEFAULT 'qr_scan',
  PRIMARY KEY (attendance_id),
  UNIQUE KEY uq_attendance (activity_id, profile_id),
  KEY fk_att_profile (profile_id),
  CONSTRAINT fk_att_activity FOREIGN KEY (activity_id) REFERENCES activities (activity_id) ON DELETE CASCADE,
  CONSTRAINT fk_att_profile  FOREIGN KEY (profile_id)  REFERENCES youth_profiles (profile_id),
  CONSTRAINT chk_att_method  CHECK (entry_method IN ('qr_scan','manual'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. evaluation_forms   (one form per activity)
-- ---------------------------------------------------------------------
CREATE TABLE evaluation_forms (
  form_id     INT(11)      NOT NULL AUTO_INCREMENT,
  activity_id INT(11)      NOT NULL,
  form_title  VARCHAR(255) NOT NULL,
  is_open     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (form_id),
  UNIQUE KEY uq_form_activity (activity_id),
  CONSTRAINT fk_form_activity FOREIGN KEY (activity_id) REFERENCES activities (activity_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. evaluation_questions
-- ---------------------------------------------------------------------
CREATE TABLE evaluation_questions (
  question_id   INT(11)     NOT NULL AUTO_INCREMENT,
  form_id       INT(11)     NOT NULL,
  question_text TEXT        NOT NULL,
  question_type VARCHAR(20) NOT NULL DEFAULT 'rating',
  sort_order    INT(11)     NOT NULL DEFAULT 1,
  PRIMARY KEY (question_id),
  KEY fk_question_form (form_id),
  CONSTRAINT fk_question_form FOREIGN KEY (form_id) REFERENCES evaluation_forms (form_id) ON DELETE CASCADE,
  CONSTRAINT chk_question_type CHECK (question_type IN ('rating','text'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 10. evaluation_responses   (one submission per member per form)
-- ---------------------------------------------------------------------
CREATE TABLE evaluation_responses (
  response_id    INT(11)  NOT NULL AUTO_INCREMENT,
  form_id        INT(11)  NOT NULL,
  profile_id     INT(11)  NOT NULL,
  date_submitted DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (response_id),
  UNIQUE KEY uq_response (form_id, profile_id),
  KEY fk_response_profile (profile_id),
  CONSTRAINT fk_response_form    FOREIGN KEY (form_id)    REFERENCES evaluation_forms (form_id) ON DELETE CASCADE,
  CONSTRAINT fk_response_profile FOREIGN KEY (profile_id) REFERENCES youth_profiles (profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 11. evaluation_answers
-- ---------------------------------------------------------------------
CREATE TABLE evaluation_answers (
  answer_id    INT(11) NOT NULL AUTO_INCREMENT,
  response_id  INT(11) NOT NULL,
  question_id  INT(11) NOT NULL,
  rating_value INT(11) DEFAULT NULL,
  answer_text  TEXT    DEFAULT NULL,
  PRIMARY KEY (answer_id),
  UNIQUE KEY uq_answer (response_id, question_id),
  KEY fk_answer_question (question_id),
  CONSTRAINT fk_answer_response FOREIGN KEY (response_id) REFERENCES evaluation_responses (response_id) ON DELETE CASCADE,
  CONSTRAINT fk_answer_question FOREIGN KEY (question_id) REFERENCES evaluation_questions (question_id),
  CONSTRAINT chk_answer_rating  CHECK (rating_value IS NULL OR rating_value BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 12. certificates   (issued automatically once an evaluation is saved)
-- ---------------------------------------------------------------------
CREATE TABLE certificates (
  certificate_id INT(11)     NOT NULL AUTO_INCREMENT,
  response_id    INT(11)     NOT NULL,
  profile_id     INT(11)     NOT NULL,
  activity_id    INT(11)     NOT NULL,
  certificate_no VARCHAR(50) NOT NULL,
  date_issued    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (certificate_id),
  UNIQUE KEY uq_cert_no (certificate_no),
  UNIQUE KEY uq_cert_response (response_id),
  KEY fk_cert_profile (profile_id),
  KEY fk_cert_activity (activity_id),
  CONSTRAINT fk_cert_response FOREIGN KEY (response_id) REFERENCES evaluation_responses (response_id) ON DELETE CASCADE,
  CONSTRAINT fk_cert_profile  FOREIGN KEY (profile_id)  REFERENCES youth_profiles (profile_id),
  CONSTRAINT fk_cert_activity FOREIGN KEY (activity_id) REFERENCES activities (activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 13. documents     (repository; also holds budget proposal attachments)
-- ---------------------------------------------------------------------
CREATE TABLE documents (
  document_id   INT(11)      NOT NULL AUTO_INCREMENT,
  program_id    INT(11)      DEFAULT NULL,
  barangay_id   INT(11)      DEFAULT NULL,
  uploaded_by   INT(11)      NOT NULL,
  title         VARCHAR(255) NOT NULL,
  category      VARCHAR(50)  NOT NULL DEFAULT 'other',
  file_path     VARCHAR(255) NOT NULL,
  file_size     INT(11)      NOT NULL DEFAULT 0,
  date_uploaded DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (document_id),
  KEY fk_doc_program (program_id),
  KEY fk_doc_barangay (barangay_id),
  KEY fk_doc_uploader (uploaded_by),
  CONSTRAINT fk_doc_program  FOREIGN KEY (program_id)  REFERENCES programs (program_id) ON DELETE SET NULL,
  CONSTRAINT fk_doc_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (barangay_id),
  CONSTRAINT fk_doc_uploader FOREIGN KEY (uploaded_by) REFERENCES users (user_id),
  CONSTRAINT chk_doc_category CHECK (category IN ('minutes','resolution','letter','report','proposal','other'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 14. accomplishments
-- ---------------------------------------------------------------------
CREATE TABLE accomplishments (
  accomplishment_id INT(11)  NOT NULL AUTO_INCREMENT,
  program_id        INT(11)  NOT NULL,
  narrative         TEXT     NOT NULL,
  date_posted       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (accomplishment_id),
  KEY fk_acc_program (program_id),
  CONSTRAINT fk_acc_program FOREIGN KEY (program_id) REFERENCES programs (program_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 15. inventory_items      (held per barangay -- not by the federation)
-- ---------------------------------------------------------------------
CREATE TABLE inventory_items (
  item_id           INT(11)      NOT NULL AUTO_INCREMENT,
  barangay_id       INT(11)      NOT NULL,
  item_name         VARCHAR(150) NOT NULL,
  unit              VARCHAR(30)  DEFAULT NULL,
  quantity_on_hand  INT(11)      NOT NULL DEFAULT 0,
  quantity_released INT(11)      NOT NULL DEFAULT 0,
  reorder_level     INT(11)      NOT NULL DEFAULT 0,
  stock_status      VARCHAR(50)  NOT NULL DEFAULT 'available',
  PRIMARY KEY (item_id),
  KEY fk_item_barangay (barangay_id),
  CONSTRAINT fk_item_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (barangay_id),
  CONSTRAINT chk_item_status  CHECK (stock_status IN ('available','low stock','out of stock')),
  CONSTRAINT chk_item_qty     CHECK (quantity_on_hand >= 0 AND quantity_released >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 16. equipment_requests   (filed by a KK member, acted on by the barangay)
-- ---------------------------------------------------------------------
CREATE TABLE equipment_requests (
  request_id     INT(11)      NOT NULL AUTO_INCREMENT,
  item_id        INT(11)      NOT NULL,
  requested_by   INT(11)      NOT NULL,
  approved_by    INT(11)      DEFAULT NULL,
  activity_id    INT(11)      DEFAULT NULL,
  quantity       INT(11)      NOT NULL,
  purpose        VARCHAR(255) DEFAULT NULL,
  req_status     VARCHAR(50)  NOT NULL DEFAULT 'pending',
  remarks        VARCHAR(255) DEFAULT NULL,
  date_requested DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_acted     DATETIME     DEFAULT NULL,
  date_returned  DATETIME     DEFAULT NULL,
  PRIMARY KEY (request_id),
  KEY fk_req_item (item_id),
  KEY fk_req_requester (requested_by),
  KEY fk_req_approver (approved_by),
  KEY fk_req_activity (activity_id),
  CONSTRAINT fk_req_item      FOREIGN KEY (item_id)      REFERENCES inventory_items (item_id),
  CONSTRAINT fk_req_requester FOREIGN KEY (requested_by) REFERENCES youth_profiles (profile_id),
  CONSTRAINT fk_req_approver  FOREIGN KEY (approved_by)  REFERENCES users (user_id),
  CONSTRAINT fk_req_activity  FOREIGN KEY (activity_id)  REFERENCES activities (activity_id) ON DELETE SET NULL,
  CONSTRAINT chk_req_status   CHECK (req_status IN ('pending','approved','declined','returned')),
  CONSTRAINT chk_req_qty      CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 17. inventory_transactions
-- ---------------------------------------------------------------------
CREATE TABLE inventory_transactions (
  transaction_id INT(11)     NOT NULL AUTO_INCREMENT,
  item_id        INT(11)     NOT NULL,
  request_id     INT(11)     DEFAULT NULL,
  recorded_by    INT(11)     NOT NULL,
  trans_type     VARCHAR(50) NOT NULL,
  quantity       INT(11)     NOT NULL,
  trans_date     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (transaction_id),
  KEY fk_trans_item (item_id),
  KEY fk_trans_request (request_id),
  KEY fk_trans_user (recorded_by),
  CONSTRAINT fk_trans_item    FOREIGN KEY (item_id)     REFERENCES inventory_items (item_id),
  CONSTRAINT fk_trans_request FOREIGN KEY (request_id)  REFERENCES equipment_requests (request_id) ON DELETE SET NULL,
  CONSTRAINT fk_trans_user    FOREIGN KEY (recorded_by) REFERENCES users (user_id),
  CONSTRAINT chk_trans_type   CHECK (trans_type IN ('stock_in','issue','return','adjustment'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 18. budget_allocations    (entered by the SK Federation)
-- ---------------------------------------------------------------------
CREATE TABLE budget_allocations (
  allocation_id    INT(11)       NOT NULL AUTO_INCREMENT,
  entered_by       INT(11)       NOT NULL,
  period           VARCHAR(50)   NOT NULL,
  total_amount     DECIMAL(12,2) NOT NULL,
  remaining_amount DECIMAL(12,2) NOT NULL,
  date_entered     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (allocation_id),
  UNIQUE KEY uq_allocation_period (period),
  KEY fk_alloc_user (entered_by),
  CONSTRAINT fk_alloc_user FOREIGN KEY (entered_by) REFERENCES users (user_id),
  CONSTRAINT chk_alloc_amt CHECK (total_amount >= 0 AND remaining_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 19. budget_proposals
--     document_id links the proposal to its attachment in the repository
-- ---------------------------------------------------------------------
CREATE TABLE budget_proposals (
  proposal_id     INT(11)       NOT NULL AUTO_INCREMENT,
  allocation_id   INT(11)       NOT NULL,
  barangay_id     INT(11)       NOT NULL,
  submitted_by    INT(11)       NOT NULL,
  reviewed_by     INT(11)       DEFAULT NULL,
  document_id     INT(11)       DEFAULT NULL,
  proposal_code   VARCHAR(30)   NOT NULL,
  title           VARCHAR(255)  NOT NULL,
  proposed_amount DECIMAL(12,2) NOT NULL,
  approved_amount DECIMAL(12,2) DEFAULT NULL,
  prop_status     VARCHAR(50)   NOT NULL DEFAULT 'draft',
  remarks         TEXT          DEFAULT NULL,
  date_submitted  DATETIME      DEFAULT NULL,
  date_reviewed   DATETIME      DEFAULT NULL,
  PRIMARY KEY (proposal_id),
  UNIQUE KEY uq_proposal_code (proposal_code),
  KEY fk_prop_allocation (allocation_id),
  KEY fk_prop_barangay (barangay_id),
  KEY fk_prop_submitter (submitted_by),
  KEY fk_prop_reviewer (reviewed_by),
  KEY fk_prop_document (document_id),
  CONSTRAINT fk_prop_allocation FOREIGN KEY (allocation_id) REFERENCES budget_allocations (allocation_id),
  CONSTRAINT fk_prop_barangay   FOREIGN KEY (barangay_id)   REFERENCES barangays (barangay_id),
  CONSTRAINT fk_prop_submitter  FOREIGN KEY (submitted_by)  REFERENCES users (user_id),
  CONSTRAINT fk_prop_reviewer   FOREIGN KEY (reviewed_by)   REFERENCES users (user_id),
  CONSTRAINT fk_prop_document   FOREIGN KEY (document_id)   REFERENCES documents (document_id) ON DELETE SET NULL,
  CONSTRAINT chk_prop_status    CHECK (prop_status IN ('draft','pending','approved','returned')),
  CONSTRAINT chk_prop_amount    CHECK (proposed_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 20. program_budgets
--     completion_status + balance_note = the complete/incomplete flag
--     and the note on the remaining amount
-- ---------------------------------------------------------------------
CREATE TABLE program_budgets (
  program_budget_id INT(11)       NOT NULL AUTO_INCREMENT,
  proposal_id       INT(11)       NOT NULL,
  program_id        INT(11)       NOT NULL,
  allocated_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  utilised_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  completion_status VARCHAR(20)   NOT NULL DEFAULT 'ongoing',
  balance_note      TEXT          DEFAULT NULL,
  PRIMARY KEY (program_budget_id),
  UNIQUE KEY uq_progbudget (proposal_id, program_id),
  KEY fk_pb_program (program_id),
  CONSTRAINT fk_pb_proposal FOREIGN KEY (proposal_id) REFERENCES budget_proposals (proposal_id) ON DELETE CASCADE,
  CONSTRAINT fk_pb_program  FOREIGN KEY (program_id)  REFERENCES programs (program_id),
  CONSTRAINT chk_pb_status  CHECK (completion_status IN ('complete','incomplete','ongoing')),
  CONSTRAINT chk_pb_amounts CHECK (allocated_amount >= 0 AND utilised_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 21. budget_expenses
-- ---------------------------------------------------------------------
CREATE TABLE budget_expenses (
  expense_id        INT(11)       NOT NULL AUTO_INCREMENT,
  program_budget_id INT(11)       NOT NULL,
  activity_id       INT(11)       DEFAULT NULL,
  recorded_by       INT(11)       NOT NULL,
  amount            DECIMAL(12,2) NOT NULL,
  particulars       VARCHAR(255)  NOT NULL,
  expense_date      DATE          NOT NULL,
  date_recorded     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (expense_id),
  KEY fk_exp_progbudget (program_budget_id),
  KEY fk_exp_activity (activity_id),
  KEY fk_exp_user (recorded_by),
  CONSTRAINT fk_exp_progbudget FOREIGN KEY (program_budget_id) REFERENCES program_budgets (program_budget_id) ON DELETE CASCADE,
  CONSTRAINT fk_exp_activity   FOREIGN KEY (activity_id)       REFERENCES activities (activity_id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_user       FOREIGN KEY (recorded_by)       REFERENCES users (user_id),
  CONSTRAINT chk_exp_amount    CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 22. announcements
-- ---------------------------------------------------------------------
CREATE TABLE announcements (
  announcement_id INT(11)      NOT NULL AUTO_INCREMENT,
  posted_by       INT(11)      NOT NULL,
  barangay_id     INT(11)      DEFAULT NULL,
  title           VARCHAR(255) NOT NULL,
  body            TEXT         NOT NULL,
  date_posted     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id),
  KEY fk_ann_user (posted_by),
  KEY fk_ann_barangay (barangay_id),
  CONSTRAINT fk_ann_user     FOREIGN KEY (posted_by)   REFERENCES users (user_id),
  CONSTRAINT fk_ann_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (barangay_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 23. feedback     (is_anonymous hides the member from the SK officials)
-- ---------------------------------------------------------------------
CREATE TABLE feedback (
  feedback_id  INT(11)    NOT NULL AUTO_INCREMENT,
  profile_id   INT(11)    NOT NULL,
  replied_by   INT(11)    DEFAULT NULL,
  message      TEXT       NOT NULL,
  reply        TEXT       DEFAULT NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  date_sent    DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_replied DATETIME   DEFAULT NULL,
  PRIMARY KEY (feedback_id),
  KEY fk_fb_profile (profile_id),
  KEY fk_fb_replier (replied_by),
  CONSTRAINT fk_fb_profile FOREIGN KEY (profile_id) REFERENCES youth_profiles (profile_id),
  CONSTRAINT fk_fb_replier FOREIGN KEY (replied_by) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 24. notifications
--     NOTE: not present in Figure 13 of the manuscript. The ERD should
--     be updated to include it, since notifications appear in the scope,
--     the use case diagram and every prototype screen.
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  notification_id INT(11)      NOT NULL AUTO_INCREMENT,
  user_id         INT(11)      NOT NULL,
  title           VARCHAR(255) NOT NULL,
  body            VARCHAR(500) DEFAULT NULL,
  link            VARCHAR(255) DEFAULT NULL,
  is_read         TINYINT(1)   NOT NULL DEFAULT 0,
  date_created    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id),
  KEY fk_notif_user (user_id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 25. reports
-- ---------------------------------------------------------------------
CREATE TABLE reports (
  report_id      INT(11)      NOT NULL AUTO_INCREMENT,
  generated_by   INT(11)      NOT NULL,
  barangay_id    INT(11)      DEFAULT NULL,
  report_type    VARCHAR(100) NOT NULL,
  period         VARCHAR(50)  DEFAULT NULL,
  file_path      VARCHAR(255) DEFAULT NULL,
  date_generated DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (report_id),
  KEY fk_rep_user (generated_by),
  KEY fk_rep_barangay (barangay_id),
  CONSTRAINT fk_rep_user     FOREIGN KEY (generated_by) REFERENCES users (user_id),
  CONSTRAINT fk_rep_barangay FOREIGN KEY (barangay_id)  REFERENCES barangays (barangay_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 26. audit_logs
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  log_id     INT(11)      NOT NULL AUTO_INCREMENT,
  user_id    INT(11)      DEFAULT NULL,
  action     VARCHAR(100) NOT NULL,
  details    VARCHAR(500) DEFAULT NULL,
  ip_address VARCHAR(45)  DEFAULT NULL,
  timestamp  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY fk_log_user (user_id),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
