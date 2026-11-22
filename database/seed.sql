-- =====================================================================
-- Seed data for sk_monitoring
-- Run AFTER schema.sql
--
-- Test accounts (change these before the defense demo):
--   admin        / Admin@123     Administrator
--   fed.pres     / Fed@123       SK Federation
--   brgy1.chair  / Brgy@123      SK Barangay Official  (Barangay 1)
--   maria.santos / Member@123    KK Member             (Barangay 1, verified)
--   juan.cruz    / Member@123    KK Member             (Barangay 1, PENDING)
-- =====================================================================

USE sk_monitoring;

-- --------------------------------------------------------- roles ----
INSERT INTO roles (role_id, role_name) VALUES
  (1, 'Administrator'),
  (2, 'SK Federation'),
  (3, 'SK Barangay Official'),
  (4, 'KK Member');

-- ----------------------------------------------------- barangays ----
-- NOTE: replace with the complete official list of Nasipit barangays.
INSERT INTO barangays (barangay_id, barangay_name, municipality) VALUES
  (1, 'Barangay 1 (Apagan)', 'Nasipit'),
  (2, 'Camagong',            'Nasipit'),
  (3, 'Culit',               'Nasipit'),
  (4, 'Jaguimitan',          'Nasipit'),
  (5, 'Talisay',             'Nasipit'),
  (6, 'Triangulo',           'Nasipit');

-- --------------------------------------------------------- users ----
-- Administrator and SK Federation have no barangay_id.
INSERT INTO users
  (user_id, role_id, barangay_id, verified_by, username, email, password_hash,
   verification_status, date_verified, is_active)
VALUES
  (1, 1, NULL, NULL, 'admin',   'admin@sknasipit.gov.ph',
   '$2y$10$MlVFBrIWnJbmqIQCJNqyrOX/hDoy2DDPT/EDaaT5evi0fo0Qz2Hf.',
   'verified', NOW(), 1),

  (2, 2, NULL, NULL, 'fed.pres', 'federation@sknasipit.gov.ph',
   '$2y$10$S3.oh6f8MFJhAq1jpDGt4.sGnb5cdao5N.OPA0uIHV1T3hIglaXjO',
   'verified', NOW(), 1),

  (3, 3, 1, NULL, 'brgy1.chair', 'brgy1@sknasipit.gov.ph',
   '$2y$10$himhfxaiaE.WnfuQ2RL7f.f53NMsSFzuP6PNWftU.LwVaLyLrMEK2',
   'verified', NOW(), 1),

  (4, 3, 2, NULL, 'camagong.chair', 'camagong@sknasipit.gov.ph',
   '$2y$10$himhfxaiaE.WnfuQ2RL7f.f53NMsSFzuP6PNWftU.LwVaLyLrMEK2',
   'verified', NOW(), 1),

  -- verified KK member of Barangay 1, approved by brgy1.chair (user 3)
  (5, 4, 1, 3, 'maria.santos', 'maria.santos@example.com',
   '$2y$10$ZkHjkgNtVgBgBWaz7Icp4eKkPRAF8kry7ivYUjnl9umWtCJmk1oE2',
   'verified', NOW(), 1),

  -- pending KK member, waiting for the Barangay 1 chairman to verify
  (6, 4, 1, NULL, 'juan.cruz', 'juan.cruz@example.com',
   '$2y$10$ZkHjkgNtVgBgBWaz7Icp4eKkPRAF8kry7ivYUjnl9umWtCJmk1oE2',
   'pending', NULL, 1);

-- ------------------------------------------------ youth_profiles ----
INSERT INTO youth_profiles
  (profile_id, user_id, barangay_id, full_name, birthdate, sex, contact_no, education, work_status)
VALUES
  (1, 5, 1, 'Maria Santos',   '2004-03-12', 'Female', '09171234567', 'College',     'Student'),
  (2, 6, 1, 'Juan Cruz',      '2006-09-01', 'Male',   '09181234567', 'Senior High', 'Student'),
  (3, NULL, 1, 'Ana Reyes',   '2002-01-25', 'Female', '09191234567', 'College',     'Employed'),
  (4, NULL, 2, 'Pedro Lim',   '2005-07-18', 'Male',   '09201234567', 'Senior High', 'Student');

-- ------------------------------------------------------ programs ----
INSERT INTO programs
  (program_id, barangay_id, created_by, program_code, title, centre, status)
VALUES
  (1, 1, 3, 'PRG-001', 'Health & Wellness Campaign',           'Health',              'completed'),
  (2, 1, 3, 'PRG-002', 'Digital Literacy Workshop',            'Education',           'completed'),
  (3, 1, 3, 'PRG-003', 'Livelihood Training - Ube Processing', 'Economic Empowerment','evaluated'),
  (4, 1, 3, 'PRG-004', 'Environmental Clean-up Drive',         'Environment',         'completed'),
  (5, 1, 3, 'PRG-005', 'Sports Fest 2026',                     'Sports & Recreation', 'ongoing');

-- ---------------------------------------------------- activities ----
INSERT INTO activities
  (activity_id, program_id, title, activity_date, start_time, end_time, venue, qr_token, is_open, target_count)
VALUES
  (1, 1, 'Free Medical Check-up',   '2026-07-05', '08:00:00', '15:00:00', 'Barangay Hall',   'ACT1-7f3a9c2e5b81d4', 1, 60),
  (2, 2, 'Basic Computer Literacy', '2026-07-12', '09:00:00', '16:00:00', 'Covered Court',   'ACT2-2b7e4d1a9c3f68', 1, 40),
  (3, 4, 'Coastal Clean-up',        '2026-07-08', '06:00:00', '10:00:00', 'Nasipit Seaside', 'ACT3-9d1c6a4b2e7f35', 0, 50);

-- ---------------------------------------------- evaluation forms ----
INSERT INTO evaluation_forms (form_id, activity_id, form_title, is_open) VALUES
  (1, 1, 'Evaluation - Free Medical Check-up',   1),
  (2, 2, 'Evaluation - Basic Computer Literacy', 1);

INSERT INTO evaluation_questions (question_id, form_id, question_text, question_type, sort_order) VALUES
  (1, 1, 'The activity was well organised and started on time.', 'rating', 1),
  (2, 1, 'The topic was relevant to the needs of the youth.',    'rating', 2),
  (3, 1, 'The facilitators explained the content clearly.',      'rating', 3),
  (4, 1, 'I would join a similar activity again.',               'rating', 4),
  (5, 1, 'Comments and suggestions',                             'text',   5),
  (6, 2, 'The activity was well organised and started on time.', 'rating', 1),
  (7, 2, 'The topic was relevant to the needs of the youth.',    'rating', 2),
  (8, 2, 'Comments and suggestions',                             'text',   3);

-- ---------------------------------------------- inventory (brgy 1) ---
INSERT INTO inventory_items
  (item_id, barangay_id, item_name, unit, quantity_on_hand, quantity_released, reorder_level, stock_status)
VALUES
  (1, 1, 'Monoblock Chairs', 'pcs',  120, 30, 20, 'available'),
  (2, 1, 'Folding Tables',   'pcs',   15,  5,  5, 'available'),
  (3, 1, 'Sound System',     'set',    2,  1,  1, 'low stock'),
  (4, 1, 'Projector',        'unit',   1,  1,  1, 'out of stock'),
  (5, 1, 'Tent (3x3m)',      'pcs',    8,  2,  3, 'available'),
  (6, 2, 'Monoblock Chairs', 'pcs',   80, 10, 20, 'available');

-- ----------------------------------------------- budget (CY 2026) ----
-- Figures match the approved prototype screens (Figures 25 and 37).
INSERT INTO budget_allocations
  (allocation_id, entered_by, period, total_amount, remaining_amount)
VALUES
  (1, 2, 'CY 2026', 1500000.00, 415000.00);

INSERT INTO budget_proposals
  (proposal_id, allocation_id, barangay_id, submitted_by, reviewed_by, document_id,
   proposal_code, title, proposed_amount, approved_amount, prop_status, remarks,
   date_submitted, date_reviewed)
VALUES
  (1, 1, 1, 3, 2, NULL, 'BPR-2026-001', 'Annual Budget Proposal - CY 2026',
   155000.00, 140000.00, 'approved',
   'Approved with reduction on representation expense.',
   '2026-01-10 09:00:00', '2026-01-18 14:30:00'),

  (2, 1, 1, 3, NULL, NULL, 'BPR-2026-002', 'Supplemental Budget Proposal - 2nd Semester 2026',
   95000.00, NULL, 'pending', NULL,
   '2026-10-21 10:15:00', NULL),

  (3, 1, 2, 4, NULL, NULL, 'BPR-2026-009', 'Camagong - Annual Budget Proposal',
   148000.00, NULL, 'pending', NULL,
   '2026-10-19 11:00:00', NULL);

INSERT INTO program_budgets
  (program_budget_id, proposal_id, program_id, allocated_amount, utilised_amount, completion_status, balance_note)
VALUES
  (1, 1, 1, 45000.00, 38200.00, 'incomplete',
     'PHP 6,800 is held for medical supplies ordered on Sep 30 but not yet delivered.'),
  (2, 1, 2, 30000.00, 30000.00, 'complete',   'Fully utilised and liquidated.'),
  (3, 1, 3, 60000.00, 55300.00, 'incomplete', 'Awaiting receipt from supplier.'),
  (4, 1, 4, 15000.00, 15000.00, 'complete',   'No remaining balance.'),
  (5, 1, 5, 90000.00, 43000.00, 'ongoing',    'Activity still in progress.');

INSERT INTO budget_expenses
  (program_budget_id, activity_id, recorded_by, amount, particulars, expense_date)
VALUES
  (1, 1, 3, 25000.00, 'Medicines and vitamins',        '2026-07-02'),
  (1, 1, 3, 13200.00, 'Honorarium of medical staff',   '2026-07-05'),
  (2, 2, 3, 18000.00, 'Rental of laptops',             '2026-07-10'),
  (2, 2, 3, 12000.00, 'Meals and snacks',              '2026-07-12'),
  (4, 3, 3, 15000.00, 'Clean-up materials and shirts', '2026-07-08');

-- ------------------------------------------------- announcements ----
INSERT INTO announcements (posted_by, barangay_id, title, body) VALUES
  (2, NULL, 'Federation Assembly on November 20',
   'All SK Barangay Chairpersons are required to attend the quarterly federation assembly.'),
  (3, 1,    'Sports Fest 2026 Registration Open',
   'Registration for Sports Fest 2026 is now open at the barangay hall until November 15.');

SELECT 'Seed data loaded.' AS status;
