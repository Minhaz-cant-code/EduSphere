-- =====================================================================
--  EduSphere - demo data for the At-Risk Student Early Warning feature
--  Run this AFTER edusphere.sql, in phpMyAdmin > edusphere > SQL tab.
--  It uses today's date, so the demo always looks recent.
--
--  Result: Mehedi Hasan (HSC-003) becomes HIGH risk:
--    attendance below 60% + 3 absences in a row + falling marks + overdue fee
-- =====================================================================
USE edusphere;

-- Attendance for Mehedi Hasan (student 3) over the last 9 days
-- 3 present, 1 late, 5 absent -> 44% ; the last 3 classes are absences
INSERT IGNORE INTO attendance (student_id, batch_id, att_date, status, marked_by) VALUES
(3, 1, DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'Present', 2),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'Absent',  2),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Present', 2),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Absent',  2),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Late',    2),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Present', 2),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Absent',  2),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Absent',  2),
(3, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Absent',  2);

-- Good attendance for the other two students, for comparison
INSERT IGNORE INTO attendance (student_id, batch_id, att_date, status, marked_by) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Present', 2),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Present', 2),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Present', 2),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Present', 2),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Late',    2),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Present', 2);

-- Two more published Physics tests (40 marks each)
INSERT INTO exams (subject_id, exam_name, exam_type, exam_date, total_marks, is_published, created_by)
VALUES (1, 'Physics Class Test 2', 'Quiz', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 40, 1, 2);
SET @test2 = LAST_INSERT_ID();

INSERT INTO exams (subject_id, exam_name, exam_type, exam_date, total_marks, is_published, created_by)
VALUES (1, 'Physics Class Test 3', 'Quiz', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 40, 1, 2);
SET @test3 = LAST_INSERT_ID();

-- Mehedi: 54% -> 75% -> 30%  (falling trend)
INSERT INTO results (exam_id, student_id, marks, grade, gpa) VALUES
(@test2, 1, 36, 'A+', 5.00), (@test2, 2, 32, 'A+', 5.00), (@test2, 3, 30, 'A', 4.00),
(@test3, 1, 38, 'A+', 5.00), (@test3, 2, 33, 'A+', 5.00), (@test3, 3, 12, 'F', 0.00);
