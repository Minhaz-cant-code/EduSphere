-- =====================================================================
--  EduSphere AI - Smart Coaching & Institute Management System
--  Database schema (Version 1)  |  MySQL 5.7+ / MariaDB 10.3+
--  15 tables, normalized to 3NF (see ER diagram)
--  Import this file in phpMyAdmin (it creates the database itself).
-- =====================================================================

DROP DATABASE IF EXISTS edusphere;
CREATE DATABASE edusphere CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edusphere;

-- ---------------------------------------------------------------------
-- 1. users  (one login row for every Admin / Teacher / Student / Parent)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(100) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,              -- bcrypt hash
    role            ENUM('admin','teacher','student','parent') NOT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    failed_attempts INT NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    reset_token     VARCHAR(64) NULL,
    reset_expires   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. batches
-- ---------------------------------------------------------------------
CREATE TABLE batches (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    session     VARCHAR(20)  NOT NULL,                  -- e.g. 2026
    description VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. subjects  (Batch 1 --- N Subject : "Offers")
-- ---------------------------------------------------------------------
CREATE TABLE subjects (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    name     VARCHAR(100) NOT NULL,
    code     VARCHAR(20)  NULL,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. students  (User "Is_A" Student, Student N --- 1 Batch : "Enrolls")
-- ---------------------------------------------------------------------
CREATE TABLE students (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL UNIQUE,
    batch_id       INT NULL,
    roll_no        VARCHAR(20) NOT NULL,
    dob            DATE NULL,
    gender         ENUM('Male','Female','Other') NULL,
    phone          VARCHAR(20) NULL,
    address        VARCHAR(255) NULL,
    guardian_name  VARCHAR(100) NULL,
    photo          VARCHAR(255) NULL,
    admission_date DATE NULL,
    FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. teachers  (User "Is_A" Teacher)
-- ---------------------------------------------------------------------
CREATE TABLE teachers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL UNIQUE,
    qualification VARCHAR(150) NULL,
    phone         VARCHAR(20) NULL,
    salary        DECIMAL(10,2) NOT NULL DEFAULT 0,
    joining_date  DATE NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. parents  (User "Is_A" Parent, linked to one student)
-- ---------------------------------------------------------------------
CREATE TABLE parents (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL UNIQUE,
    student_id INT NOT NULL,
    relation   VARCHAR(30) NOT NULL DEFAULT 'Guardian',
    phone      VARCHAR(20) NULL,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. teacher_subjects  (Teacher M --- N Subject : "Teaches")
-- ---------------------------------------------------------------------
CREATE TABLE teacher_subjects (
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (teacher_id, subject_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. attendance  (Student 1 --- N Attendance : "Has")
-- ---------------------------------------------------------------------
CREATE TABLE attendance (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    batch_id   INT NOT NULL,
    att_date   DATE NOT NULL,
    status     ENUM('Present','Absent','Late') NOT NULL,
    marked_by  INT NULL,                                -- users.id
    UNIQUE KEY uq_att (student_id, att_date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id)   REFERENCES batches(id)  ON DELETE CASCADE,
    FOREIGN KEY (marked_by)  REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. fees  (Student 1 --- N Fee : "Owes")
--    The due amount is NOT stored; it is calculated as
--    amount - SUM(payments.amount_paid)  (keeps the table in 3NF)
-- ---------------------------------------------------------------------
CREATE TABLE fees (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    fee_type    ENUM('Monthly','Admission','Exam','Fine') NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    fee_month   VARCHAR(7) NULL,                        -- YYYY-MM for monthly fees
    due_date    DATE NULL,
    description VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. payments  (Fee 1 --- N Payment : "Paid_via")
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    fee_id       INT NOT NULL,
    amount_paid  DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    method       VARCHAR(30) NOT NULL DEFAULT 'Cash',
    receipt_no   VARCHAR(30) NOT NULL UNIQUE,
    received_by  INT NULL,                              -- users.id
    FOREIGN KEY (fee_id)      REFERENCES fees(id)  ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. exams  (Subject 1 --- N Exam : "Has")
-- ---------------------------------------------------------------------
CREATE TABLE exams (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    subject_id   INT NOT NULL,
    exam_name    VARCHAR(100) NOT NULL,
    exam_type    ENUM('Quiz','Midterm','Final','Assignment') NOT NULL DEFAULT 'Quiz',
    exam_date    DATE NOT NULL,
    total_marks  INT NOT NULL DEFAULT 100,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_by   INT NULL,                              -- users.id
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. results  (Exam 1 --- N Result : "Produces", Student 1 --- N Result)
-- ---------------------------------------------------------------------
CREATE TABLE results (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    exam_id    INT NOT NULL,
    student_id INT NOT NULL,
    marks      DECIMAL(6,2) NOT NULL,
    grade      VARCHAR(3) NOT NULL,
    gpa        DECIMAL(3,2) NOT NULL,
    UNIQUE KEY uq_result (exam_id, student_id),
    FOREIGN KEY (exam_id)    REFERENCES exams(id)    ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 13. assignments  (Teacher 1 --- N Assignment : "Uploads", Subject 1 --- N)
-- ---------------------------------------------------------------------
CREATE TABLE assignments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    subject_id  INT NOT NULL,
    teacher_id  INT NOT NULL,
    title       VARCHAR(150) NOT NULL,
    description TEXT NULL,
    file_path   VARCHAR(255) NULL,
    deadline    DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 14. submissions  (Assignment 1 --- N Submission, Student 1 --- N)
-- ---------------------------------------------------------------------
CREATE TABLE submissions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id    INT NOT NULL,
    file_path     VARCHAR(255) NOT NULL,
    submitted_at  DATETIME NOT NULL,
    marks         DECIMAL(6,2) NULL,
    feedback      VARCHAR(255) NULL,
    UNIQUE KEY uq_sub (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)    REFERENCES students(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 15. notices  (User 1 --- N Notice : "Posts")
-- ---------------------------------------------------------------------
CREATE TABLE notices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(150) NOT NULL,
    body        TEXT NOT NULL,
    category    VARCHAR(30) NOT NULL DEFAULT 'General',  -- Holiday, Exam, Emergency, Event...
    target_role ENUM('all','teacher','student','parent') NOT NULL DEFAULT 'all',
    posted_by   INT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_on  DATE NULL,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- =====================================================================
--  DEMO DATA
--  Logins:  admin / admin123    teacher1 / teacher123
--           student1 / student123    parent1 / parent123
-- =====================================================================

INSERT INTO users (id, name, username, email, password, role) VALUES
(1, 'Institute Admin',   'admin',    'admin@edusphere.test',    '$2y$10$k4E3zNAqQ3tz2dJ1hs3/teLM4m7Rc/w2qbkIMnJ2cd0Tk89hb3qSi', 'admin'),
(2, 'Rafiqul Islam',     'teacher1', 'rafiq@edusphere.test',    '$2y$10$q.ALzRoaMhPgIkxfdCAhwObUge6679JuelwvKrOrNubp2Ah1paGQy', 'teacher'),
(3, 'Nusrat Jahan',      'teacher2', 'nusrat@edusphere.test',   '$2y$10$q.ALzRoaMhPgIkxfdCAhwObUge6679JuelwvKrOrNubp2Ah1paGQy', 'teacher'),
(4, 'Tanvir Ahmed',      'student1', 'tanvir@edusphere.test',   '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student'),
(5, 'Sadia Akter',       'student2', 'sadia@edusphere.test',    '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student'),
(6, 'Mehedi Hasan',      'student3', 'mehedi@edusphere.test',   '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student'),
(7, 'Farzana Rahman',    'student4', 'farzana@edusphere.test',  '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student'),
(8, 'Arif Chowdhury',    'student5', 'arif@edusphere.test',     '$2y$10$pudlSdr7h6QkaClAGhpDs.ermSwv4PUHBsMN8RWI3qsjPzLXJlYMm', 'student'),
(9, 'Abdul Karim',       'parent1',  'karim@edusphere.test',    '$2y$10$Gxz5IbjTe52QvUZe9CdJ..Mr4ryahM7CwMcSI/2zqXWh7nCclhLcW', 'parent');

INSERT INTO batches (id, name, session, description) VALUES
(1, 'HSC Science - Batch A', '2026', 'HSC 2nd year science group'),
(2, 'IELTS Evening',         '2026', 'IELTS preparation, evening shift');

INSERT INTO subjects (id, batch_id, name, code) VALUES
(1, 1, 'Physics',     'PHY'),
(2, 1, 'Chemistry',   'CHE'),
(3, 1, 'Higher Math', 'HMA'),
(4, 2, 'IELTS Reading & Writing', 'IRW'),
(5, 2, 'IELTS Listening & Speaking', 'ILS');

INSERT INTO teachers (id, user_id, qualification, phone, salary, joining_date) VALUES
(1, 2, 'M.Sc in Physics (DU)',  '01711000001', 25000, '2025-01-10'),
(2, 3, 'MA in English (JU)',    '01711000002', 22000, '2025-03-01');

INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES
(1, 1), (1, 3), (2, 2), (2, 4), (2, 5);

INSERT INTO students (id, user_id, batch_id, roll_no, dob, gender, phone, address, guardian_name, admission_date) VALUES
(1, 4, 1, 'HSC-001', '2008-03-14', 'Male',   '01811000001', 'Mirpur, Dhaka',     'Abdul Karim',   '2026-01-05'),
(2, 5, 1, 'HSC-002', '2008-07-22', 'Female', '01811000002', 'Uttara, Dhaka',     'Jamal Uddin',   '2026-01-05'),
(3, 6, 1, 'HSC-003', '2008-01-09', 'Male',   '01811000003', 'Dhanmondi, Dhaka',  'Selim Hasan',   '2026-01-07'),
(4, 7, 2, 'IEL-001', '2004-11-30', 'Female', '01811000004', 'Banani, Dhaka',     'Mizanur Rahman','2026-02-01'),
(5, 8, 2, 'IEL-002', '2003-05-18', 'Male',   '01811000005', 'Mohammadpur, Dhaka','Habib Chowdhury','2026-02-01');

INSERT INTO parents (id, user_id, student_id, relation, phone) VALUES
(1, 9, 1, 'Father', '01911000001');

INSERT INTO attendance (student_id, batch_id, att_date, status, marked_by) VALUES
(1,1,'2026-10-19','Present',2),(2,1,'2026-10-19','Present',2),(3,1,'2026-10-19','Absent',2),
(1,1,'2026-10-20','Present',2),(2,1,'2026-10-20','Late',2),(3,1,'2026-10-20','Present',2),
(1,1,'2026-10-21','Absent',2),(2,1,'2026-10-21','Present',2),(3,1,'2026-10-21','Present',2),
(1,1,'2026-10-22','Present',2),(2,1,'2026-10-22','Present',2),(3,1,'2026-10-22','Late',2),
(4,2,'2026-10-20','Present',3),(5,2,'2026-10-20','Present',3),
(4,2,'2026-10-22','Present',3),(5,2,'2026-10-22','Absent',3);

INSERT INTO fees (id, student_id, fee_type, amount, fee_month, due_date, description) VALUES
(1, 1, 'Admission', 2000, NULL,      '2026-01-10', 'Admission fee'),
(2, 1, 'Monthly',   3000, '2026-10', '2026-10-10', 'Monthly fee October'),
(3, 2, 'Monthly',   3000, '2026-10', '2026-10-10', 'Monthly fee October'),
(4, 3, 'Monthly',   3000, '2026-10', '2026-10-10', 'Monthly fee October'),
(5, 4, 'Monthly',   4500, '2026-10', '2026-10-10', 'Monthly fee October'),
(6, 5, 'Monthly',   4500, '2026-10', '2026-10-10', 'Monthly fee October'),
(7, 3, 'Fine',       200, NULL,      '2026-10-25', 'Late library book');

INSERT INTO payments (fee_id, amount_paid, payment_date, method, receipt_no, received_by) VALUES
(1, 2000, '2026-01-05', 'Cash',  'RCPT-20260105-0001', 1),
(2, 1500, '2026-10-05', 'bKash', 'RCPT-20261005-0002', 1),
(3, 3000, '2026-10-06', 'Cash',  'RCPT-20261006-0003', 1),
(5, 4500, '2026-10-08', 'Bank',  'RCPT-20261008-0004', 1);

INSERT INTO exams (id, subject_id, exam_name, exam_type, exam_date, total_marks, is_published, created_by) VALUES
(1, 1, 'Physics Chapter 1-3 Test', 'Quiz',    '2026-10-15', 50,  1, 2),
(2, 3, 'Higher Math Midterm',      'Midterm', '2026-11-05', 100, 0, 2),
(3, 4, 'Writing Task Mock Test',   'Quiz',    '2026-10-18', 40,  1, 3);

INSERT INTO results (exam_id, student_id, marks, grade, gpa) VALUES
(1, 1, 42, 'A+', 5.00),
(1, 2, 36, 'A',  4.00),
(1, 3, 27, 'B',  3.00),
(3, 4, 26, 'A-', 3.50),
(3, 5, 34, 'A+', 5.00);

INSERT INTO assignments (id, subject_id, teacher_id, title, description, file_path, deadline) VALUES
(1, 1, 1, 'Newton''s Laws Problem Set', 'Solve all 10 problems and upload as PDF.', NULL, '2026-11-02 23:59:00');

INSERT INTO notices (title, body, category, target_role, posted_by, created_at) VALUES
('Welcome to EduSphere', 'All attendance, fees, results and notices are now available online. Please log in regularly.', 'General', 'all', 1, '2026-10-01 10:00:00'),
('Higher Math Midterm on 5 November', 'The Higher Math midterm for HSC Batch A will be held on 5 November at 4 PM. Syllabus: Chapters 1-5.', 'Exam', 'student', 2, '2026-10-20 15:00:00'),
('Office closed on Friday', 'The institute office will remain closed this Friday.', 'Holiday', 'all', 1, '2026-10-24 09:00:00');
