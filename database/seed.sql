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
-- The 19 barangays of Nasipit, as listed in the approved prototype
-- (Barangays & System Settings screen). IDs 1-6 are kept stable because
-- the rest of the seed refers to them. Confirm spellings against the
-- official municipal list before the defense.
INSERT INTO barangays (barangay_id, barangay_name, municipality) VALUES
  (1,  'Barangay 1 (Apagan)', 'Nasipit'),
  (2,  'Camagong',            'Nasipit'),
  (3,  'Culit',               'Nasipit'),
  (4,  'Jaguimitan',          'Nasipit'),
  (5,  'Talisay',             'Nasipit'),
  (6,  'Triangulo',           'Nasipit'),
  (7,  'Barangay 2',          'Nasipit'),
  (8,  'Barangay 3',          'Nasipit'),
  (9,  'Barangay 4',          'Nasipit'),
  (10, 'Barangay 5',          'Nasipit'),
  (11, 'Barangay 6',          'Nasipit'),
  (12, 'Barangay 7',          'Nasipit'),
  (13, 'Aclan',               'Nasipit'),
  (14, 'Amontay',             'Nasipit'),
  (15, 'Ata-Atahon',          'Nasipit'),
  (16, 'Cubi-Cubi',           'Nasipit'),
  (17, 'Kinabjangan',         'Nasipit'),
  (18, 'Punta',               'Nasipit'),
  (19, 'Santa Ana',           'Nasipit');

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

-- Designated SK Chairmen (Administrator > Users & Roles). Only the
-- designated chairman of a barangay may verify its KK registrations.
UPDATE barangays SET chairman_id = 3 WHERE barangay_id = 1;
UPDATE barangays SET chairman_id = 4 WHERE barangay_id = 2;

-- ------------------------------------------------ youth_profiles ----
INSERT INTO youth_profiles
  (profile_id, user_id, barangay_id, full_name, birthdate, sex, contact_no, civil_status, education, work_status,
   youth_org, school_status, is_pwd, attended_assembly, id_document_type, consent_date)
VALUES
  (1, 5, 1, 'Maria Santos',   '2004-03-12', 'Female', '09171234567', 'Single', 'College Level',               'Student',
   'Kabataan Kontra Droga at Terorismo (KKDAT)', 'In-School Youth', 0, 1, 'School ID', NOW()),
  (2, 6, 1, 'Juan Cruz',      '2006-09-01', 'Male',   '09181234567', 'Single', 'Senior High School Level',    'Student',
   NULL, 'In-School Youth', 0, 0, 'PhilSys National ID', NOW()),
  (3, NULL, 1, 'Ana Reyes',   '2002-01-25', 'Female', '09191234567', 'Single', 'College Graduate',            'Employed',
   NULL, 'Out-of-School Youth', 0, 1, NULL, NULL),
  (4, NULL, 2, 'Pedro Lim',   '2005-07-18', 'Male',   '09201234567', 'Single', 'Senior High School Graduate', 'Student',
   NULL, 'Out-of-School Youth', 0, 0, NULL, NULL);

-- ------------------------------------------------------ programs ----
INSERT INTO programs
  (program_id, barangay_id, created_by, program_code, title, centre, status)
VALUES
  (1, 1, 3, 'PRG-001', 'Health & Wellness Campaign',           'Health',              'completed'),
  (2, 1, 3, 'PRG-002', 'Digital Literacy Workshop',            'Education',           'completed'),
  (3, 1, 3, 'PRG-003', 'Livelihood Training - Ube Processing', 'Economic Empowerment','evaluated'),
  (4, 1, 3, 'PRG-004', 'Environmental Clean-up Drive',         'Environment',         'completed'),
  (5, 1, 3, 'PRG-005', 'Sports Fest 2026',                     'Sports & Recreation', 'ongoing'),
  (6, 2, 4, 'PRG-006', 'Camagong Youth Summit',                'Active Citizenship',  'planned'),
  (7, 2, 4, 'PRG-007', 'Camagong Inter-Purok Basketball League', 'Sports & Recreation', 'planned');

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

-- Questions follow the three dimensions of the approved evaluation screen:
-- Program Quality, Implementer Quality, Perceived Benefits (1-5 scale),
-- then an optional suggestion. New forms get the same default set.
INSERT INTO evaluation_forms (form_id, activity_id, form_title, is_open) VALUES
  (3, 3, 'Evaluation - Coastal Clean-up', 1);

INSERT INTO evaluation_questions (form_id, question_text, question_type, section, sort_order)
SELECT f.form_id, q.question_text, q.question_type, q.section, q.sort_order
FROM evaluation_forms f
CROSS JOIN (
  SELECT 'The goals and objectives of this activity were clearly explained.' AS question_text, 'rating' AS question_type, 'Program Quality' AS section, 1 AS sort_order
  UNION ALL SELECT 'The activity was well-organized, with no unnecessary delays or confusion.', 'rating', 'Program Quality', 2
  UNION ALL SELECT 'The schedule and venue made it easy for me to join.', 'rating', 'Program Quality', 3
  UNION ALL SELECT 'The facilitators were well-prepared and knowledgeable.', 'rating', 'Implementer Quality', 4
  UNION ALL SELECT 'They encouraged us to participate actively.', 'rating', 'Implementer Quality', 5
  UNION ALL SELECT 'They answered our questions properly.', 'rating', 'Implementer Quality', 6
  UNION ALL SELECT 'I now understand the issue or topic better than before.', 'rating', 'Perceived Benefits', 7
  UNION ALL SELECT 'I gained something useful that I can apply in real life.', 'rating', 'Perceived Benefits', 8
  UNION ALL SELECT 'I am more willing to join the next SK activity.', 'rating', 'Perceived Benefits', 9
  UNION ALL SELECT 'Do you have any suggestions to make future activities better?', 'text', 'Suggestions', 10
) q
ORDER BY f.form_id, q.sort_order;

-- Maria attended the Coastal Clean-up, evaluated it, and received her
-- e-certificate, so the member screens have a complete record to show.
INSERT INTO attendance (attendance_id, activity_id, profile_id, time_scanned, entry_method) VALUES
  (1, 3, 1, '2026-07-08 06:12:00', 'qr_scan'),
  (2, 3, 3, '2026-07-08 06:20:00', 'manual'),
  (3, 1, 1, '2026-07-05 08:03:00', 'qr_scan');

INSERT INTO evaluation_responses (response_id, form_id, profile_id, date_submitted) VALUES
  (1, 3, 1, '2026-07-08 10:14:00');

INSERT INTO evaluation_answers (response_id, question_id, rating_value, answer_text)
SELECT 1, q.question_id,
       CASE WHEN q.question_type = 'rating' THEN 4 + (q.sort_order % 2) END,
       CASE WHEN q.question_type = 'text' THEN 'Please bring more trash bags next time.' END
FROM evaluation_questions q WHERE q.form_id = 3;

INSERT INTO certificates (response_id, profile_id, activity_id, certificate_no, date_issued) VALUES
  (1, 1, 3, 'SKCRT-2026-00001', '2026-07-08 10:14:00');

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
-- Every stored derived value agrees with its source rows:
--   remaining_amount = total_amount - SUM(approved_amount)  (1,500,000 - 240,000)
--   approved lines of BPR-2026-001 total its approved_amount (240,000)
--   each utilised_amount = SUM(budget_expenses.amount) of that line
-- The prototype screens (Figures 25 and 37) showed illustrative figures that
-- did not reconcile with one another, so the amounts here differ from them.
INSERT INTO budget_allocations
  (allocation_id, entered_by, period, total_amount, remaining_amount)
VALUES
  (1, 2, 'CY 2026', 1500000.00, 1260000.00);

INSERT INTO budget_proposals
  (proposal_id, allocation_id, barangay_id, submitted_by, reviewed_by, document_id,
   proposal_code, title, proposed_amount, approved_amount, prop_status, remarks,
   date_submitted, date_reviewed)
VALUES
  (1, 1, 1, 3, 2, NULL, 'BPR-2026-001', 'Annual Budget Proposal - CY 2026',
   250000.00, 240000.00, 'approved',
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
  (5, 1, 5, 90000.00, 43000.00, 'ongoing',    'Activity still in progress.'),
  -- lines of the two pending proposals
  (6, 2, 5, 50000.00, 0.00, 'ongoing', NULL),
  (7, 2, 3, 45000.00, 0.00, 'ongoing', NULL),
  (8, 3, 6, 60000.00, 0.00, 'ongoing', NULL),
  (9, 3, 7, 88000.00, 0.00, 'ongoing', NULL);

INSERT INTO budget_expenses
  (program_budget_id, activity_id, recorded_by, amount, particulars, expense_date)
VALUES
  (1, 1, 3, 25000.00, 'Medicines and vitamins',        '2026-07-02'),
  (1, 1, 3, 13200.00, 'Honorarium of medical staff',   '2026-07-05'),
  (2, 2, 3, 18000.00, 'Rental of laptops',             '2026-07-10'),
  (2, 2, 3, 12000.00, 'Meals and snacks',              '2026-07-12'),
  (4, 3, 3, 15000.00, 'Clean-up materials and shirts', '2026-07-08'),
  (3, NULL, 3, 30000.00, 'Ube processing equipment',      '2026-06-20'),
  (3, NULL, 3, 25300.00, 'Training materials and meals',  '2026-06-28'),
  (5, NULL, 3, 43000.00, 'Sports Fest uniforms and balls', '2026-10-15');

-- ------------------------------------------------- announcements ----
INSERT INTO announcements (posted_by, barangay_id, title, body) VALUES
  (2, NULL, 'Federation Assembly on November 20',
   'All SK Barangay Chairpersons are required to attend the quarterly federation assembly.'),
  (3, 1,    'Sports Fest 2026 Registration Open',
   'Registration for Sports Fest 2026 is now open at the barangay hall until November 15.');

-- ----------------------------------------------------- feedback ----
INSERT INTO feedback (profile_id, replied_by, message, reply, is_anonymous, date_sent, date_replied) VALUES
  (1, 3, 'Can we have more workshops about starting a small business? A lot of us want to earn extra income.',
   'Great suggestion! We are planning a livelihood series for Q3. Watch for the announcement.', 0,
   '2026-06-20 09:00:00', '2026-06-21 10:00:00'),
  (3, NULL, 'The venue for the last forum was too small for everyone who showed up. Next time maybe use the covered court?',
   NULL, 1, '2026-06-15 15:30:00', NULL);

-- ------------------------------------------------------ settings ----
INSERT INTO settings (setting_key, setting_value) VALUES
  ('system_name',                'SK Federation Monitoring & Evaluation System'),
  ('municipality',               'Nasipit, Agusan del Norte'),
  ('audit_retention',            '24 months'),
  ('maintenance_mode',           '0'),
  ('eligibility_min_activities', '2');

SELECT 'Seed data loaded.' AS status;
