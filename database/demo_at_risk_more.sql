-- =====================================================================
--  EduSphere - MORE demo data for the At-Risk Student Early Warning
--  Run this ONCE, after edusphere.sql, in phpMyAdmin > edusphere > SQL.
--  (It works with or without demo_at_risk.sql.)
--  Dates are relative to today, so the demo always looks recent.
--
--  New students (password for all: student123)
--   rakib    Rakib Hossain   HSC-004  -> HIGH   (bad attendance, low marks, fee overdue)
--   nabila   Nabila Islam    HSC-005  -> MEDIUM (good attendance, but marks suddenly dropped + fee overdue)
--   imran    Imran Khan      HSC-006  -> LOW    (excellent student, for comparison)
--   tasnim   Tasnim Jahan    IEL-003  -> HIGH   (absent 3 days in a row, fee overdue)
--   shafin   Shafin Ahmed    IEL-004  -> MEDIUM (attendance 67%, marks below 50%)
--   riya     Riya Das        IEL-005  -> NOT ENOUGH DATA (just admitted)
--
--  New parents (password for both: parent123)
--   parent2  Mozammel Hossain  -> child Rakib  (shows a RED high-risk alert)
--   parent3  Shahana Islam     -> child Nabila (shows a YELLOW medium-risk alert)
-- =====================================================================
USE edusphere;

-- ---------- Login accounts ----------
-- student123
INSERT INTO users (name, username, email, password, role) VALUES ('Rakib Hossain', 'rakib', 'rakib@edusphere.test', '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student');
SET @u_rakib = LAST_INSERT_ID();
INSERT INTO users (name, username, email, password, role) VALUES ('Nabila Islam', 'nabila', 'nabila@edusphere.test', '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student');
SET @u_nabila = LAST_INSERT_ID();
INSERT INTO users (name, username, email, password, role) VALUES ('Imran Khan', 'imran', 'imran@edusphere.test', '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student');
SET @u_imran = LAST_INSERT_ID();
INSERT INTO users (name, username, email, password, role) VALUES ('Tasnim Jahan', 'tasnim', 'tasnim@edusphere.test', '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student');
SET @u_tasnim = LAST_INSERT_ID();
INSERT INTO users (name, username, email, password, role) VALUES ('Shafin Ahmed', 'shafin', 'shafin@edusphere.test', '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student');
SET @u_shafin = LAST_INSERT_ID();
INSERT INTO users (name, username, email, password, role) VALUES ('Riya Das', 'riya', 'riya@edusphere.test', '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student');
SET @u_riya = LAST_INSERT_ID();
-- parent123
INSERT INTO users (name, username, email, password, role) VALUES ('Mozammel Hossain', 'parent2', 'mozammel@edusphere.test', '$2y$10$Gxz5IbjTe52QvUZe9CdJ..Mr4ryahM7CwMcSI/2zqXWh7nCclhLcW', 'parent');
SET @u_parent2 = LAST_INSERT_ID();
INSERT INTO users (name, username, email, password, role) VALUES ('Shahana Islam', 'parent3', 'shahana@edusphere.test', '$2y$10$Gxz5IbjTe52QvUZe9CdJ..Mr4ryahM7CwMcSI/2zqXWh7nCclhLcW', 'parent');
SET @u_parent3 = LAST_INSERT_ID();

-- ---------- Student profiles (batch 1 = HSC Science, batch 2 = IELTS) ----------
INSERT INTO students (user_id, batch_id, roll_no, gender, phone, guardian_name, admission_date) VALUES (@u_rakib, 1, 'HSC-004', 'Male', '01811000006', 'Mozammel Hossain', '2026-01-10');
SET @s_rakib = LAST_INSERT_ID();
INSERT INTO students (user_id, batch_id, roll_no, gender, phone, guardian_name, admission_date) VALUES (@u_nabila, 1, 'HSC-005', 'Female', '01811000007', 'Shahana Islam', '2026-01-10');
SET @s_nabila = LAST_INSERT_ID();
INSERT INTO students (user_id, batch_id, roll_no, gender, phone, guardian_name, admission_date) VALUES (@u_imran, 1, 'HSC-006', 'Male', '01811000008', 'Kamal Khan', '2026-01-12');
SET @s_imran = LAST_INSERT_ID();
INSERT INTO students (user_id, batch_id, roll_no, gender, phone, guardian_name, admission_date) VALUES (@u_tasnim, 2, 'IEL-003', 'Female', '01811000009', 'Jahangir Alam', '2026-02-03');
SET @s_tasnim = LAST_INSERT_ID();
INSERT INTO students (user_id, batch_id, roll_no, gender, phone, guardian_name, admission_date) VALUES (@u_shafin, 2, 'IEL-004', 'Male', '01811000010', 'Rafiq Ahmed', '2026-02-03');
SET @s_shafin = LAST_INSERT_ID();
INSERT INTO students (user_id, batch_id, roll_no, gender, phone, guardian_name, admission_date) VALUES (@u_riya, 2, 'IEL-005', 'Female', '01811000011', 'Sujit Das', CURDATE());
SET @s_riya = LAST_INSERT_ID();

INSERT INTO parents (user_id, student_id, relation, phone) VALUES (@u_parent2, @s_rakib, 'Father', '01911000002');
INSERT INTO parents (user_id, student_id, relation, phone) VALUES (@u_parent3, @s_nabila, 'Mother', '01911000003');

-- ---------- Attendance, last 10 days ----------
-- Rakib: 4 of 10 attended (40%), last 3 classes absent
INSERT IGNORE INTO attendance (student_id, batch_id, att_date, status, marked_by) VALUES
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Present', 2),
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 9 DAY),  'Absent',  2),
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY),  'Late',    2),
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  'Absent',  2),
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY),  'Present', 2),
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  'Absent',  2),
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY),  'Present', 2),
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  'Absent',  2),
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  'Absent',  2),
(@s_rakib, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY),  'Absent',  2);

-- Nabila and Imran: present every day (100%)
INSERT IGNORE INTO attendance (student_id, batch_id, att_date, status, marked_by) VALUES
(@s_nabila, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Present', 2),
(@s_nabila, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY),  'Present', 2),
(@s_nabila, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY),  'Present', 2),
(@s_nabila, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY),  'Present', 2),
(@s_nabila, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  'Present', 2),
(@s_nabila, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY),  'Present', 2),
(@s_imran, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Present', 2),
(@s_imran, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY),  'Present', 2),
(@s_imran, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY),  'Present', 2),
(@s_imran, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY),  'Present', 2),
(@s_imran, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  'Present', 2),
(@s_imran, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY),  'Present', 2);

-- Tasnim: 4 of 8 attended (50%), last 3 classes absent
INSERT IGNORE INTO attendance (student_id, batch_id, att_date, status, marked_by) VALUES
(@s_tasnim, 2, DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'Present', 3),
(@s_tasnim, 2, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'Present', 3),
(@s_tasnim, 2, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Absent',  3),
(@s_tasnim, 2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Present', 3),
(@s_tasnim, 2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Late',    3),
(@s_tasnim, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Absent',  3),
(@s_tasnim, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Absent',  3),
(@s_tasnim, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Absent',  3);

-- Shafin: 6 of 9 attended (67%), absences spread out (not in a row)
INSERT IGNORE INTO attendance (student_id, batch_id, att_date, status, marked_by) VALUES
(@s_shafin, 2, DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'Present', 3),
(@s_shafin, 2, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'Absent',  3),
(@s_shafin, 2, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Present', 3),
(@s_shafin, 2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Late',    3),
(@s_shafin, 2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Absent',  3),
(@s_shafin, 2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Present', 3),
(@s_shafin, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Present', 3),
(@s_shafin, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Absent',  3),
(@s_shafin, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Present', 3);

-- Riya: only 2 classes so far (not enough data to judge)
INSERT IGNORE INTO attendance (student_id, batch_id, att_date, status, marked_by) VALUES
(@s_riya, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Present', 3),
(@s_riya, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Absent',  3);

-- ---------- Published exams ----------
-- HSC Physics weekly tests (50 marks)
INSERT INTO exams (subject_id, exam_name, exam_type, exam_date, total_marks, is_published, created_by) VALUES (1, 'Physics Weekly Test 1', 'Quiz', DATE_SUB(CURDATE(), INTERVAL 25 DAY), 50, 1, 2);
SET @w1 = LAST_INSERT_ID();
INSERT INTO exams (subject_id, exam_name, exam_type, exam_date, total_marks, is_published, created_by) VALUES (1, 'Physics Weekly Test 2', 'Quiz', DATE_SUB(CURDATE(), INTERVAL 15 DAY), 50, 1, 2);
SET @w2 = LAST_INSERT_ID();
INSERT INTO exams (subject_id, exam_name, exam_type, exam_date, total_marks, is_published, created_by) VALUES (1, 'Physics Weekly Test 3', 'Quiz', DATE_SUB(CURDATE(), INTERVAL 4 DAY), 50, 1, 2);
SET @w3 = LAST_INSERT_ID();

-- IELTS reading mocks (40 marks)
INSERT INTO exams (subject_id, exam_name, exam_type, exam_date, total_marks, is_published, created_by) VALUES (4, 'Reading Mock Test 1', 'Quiz', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 40, 1, 3);
SET @r1 = LAST_INSERT_ID();
INSERT INTO exams (subject_id, exam_name, exam_type, exam_date, total_marks, is_published, created_by) VALUES (4, 'Reading Mock Test 2', 'Quiz', DATE_SUB(CURDATE(), INTERVAL 6 DAY), 40, 1, 3);
SET @r2 = LAST_INSERT_ID();

INSERT INTO results (exam_id, student_id, marks, grade, gpa) VALUES
-- Rakib: 40%, 36%, 30% (low every time)
(@w1, @s_rakib, 20, 'C', 2.00), (@w2, @s_rakib, 18, 'D', 1.00), (@w3, @s_rakib, 15, 'F', 0.00),
-- Nabila: 80%, 78%, then a sudden drop to 44%
(@w1, @s_nabila, 40, 'A+', 5.00), (@w2, @s_nabila, 39, 'A', 4.00), (@w3, @s_nabila, 22, 'C', 2.00),
-- Imran: 92%, 94%, 96%
(@w1, @s_imran, 46, 'A+', 5.00), (@w2, @s_imran, 47, 'A+', 5.00), (@w3, @s_imran, 48, 'A+', 5.00),
-- Tasnim: 60%, 50%
(@r1, @s_tasnim, 24, 'A-', 3.50), (@r2, @s_tasnim, 20, 'B', 3.00),
-- Shafin: 50%, 45%
(@r1, @s_shafin, 20, 'B', 3.00), (@r2, @s_shafin, 18, 'C', 2.00);

-- ---------- Fees ----------
INSERT INTO fees (student_id, fee_type, amount, fee_month, due_date, description) VALUES
(@s_rakib,  'Monthly', 3000, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 45 DAY), '%Y-%m'), DATE_SUB(CURDATE(), INTERVAL 45 DAY), 'Monthly fee (unpaid)'),
(@s_nabila, 'Monthly', 3000, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 40 DAY), '%Y-%m'), DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'Monthly fee (unpaid)'),
(@s_tasnim, 'Monthly', 4500, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 38 DAY), '%Y-%m'), DATE_SUB(CURDATE(), INTERVAL 38 DAY), 'Monthly fee (unpaid)'),
(@s_shafin, 'Monthly', 4500, DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '%Y-%m'),  DATE_ADD(CURDATE(), INTERVAL 5 DAY),  'Monthly fee (not due yet)');

-- Imran paid his fee in full
INSERT INTO fees (student_id, fee_type, amount, fee_month, due_date, description) VALUES
(@s_imran, 'Monthly', 3000, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 20 DAY), '%Y-%m'), DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'Monthly fee');
SET @f_imran = LAST_INSERT_ID();
INSERT INTO payments (fee_id, amount_paid, payment_date, method, receipt_no, received_by)
VALUES (@f_imran, 3000, DATE_SUB(CURDATE(), INTERVAL 22 DAY), 'bKash', CONCAT('RCPT-DEMO-', @f_imran), 1);
