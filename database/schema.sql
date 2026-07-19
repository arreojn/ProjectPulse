CREATE DATABASE IF NOT EXISTS project_pulse;
USE project_pulse;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    first_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'attendance', 'teacher', 'parent', 'learner', 'health') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE parents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(30) NULL,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_parents_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE learners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL UNIQUE,
    learner_number VARCHAR(30) NOT NULL UNIQUE,
    lrn VARCHAR(30) NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    birthdate DATE NULL,
    mother_tongue VARCHAR(120) NULL,
    religion VARCHAR(120) NULL,
    address_house_number VARCHAR(120) NULL,
    address_barangay VARCHAR(120) NULL,
    address_city_municipality VARCHAR(120) NULL,
    address_province VARCHAR(120) NULL,
    sex ENUM('male', 'female') NULL,
    current_status ENUM('active', 'inactive', 'graduated', 'transferred') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_learners_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE parent_learner_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED NOT NULL,
    learner_id INT UNSIGNED NOT NULL,
    relationship VARCHAR(50) NOT NULL,
    is_primary_contact TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_parent_learner (parent_id, learner_id),
    CONSTRAINT fk_parent_learner_parent
        FOREIGN KEY (parent_id) REFERENCES parents(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_parent_learner_learner
        FOREIGN KEY (learner_id) REFERENCES learners(id)
        ON DELETE CASCADE
);

CREATE TABLE school_years (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(20) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    grade_level VARCHAR(20) NOT NULL,
    school_year_id INT UNSIGNED NOT NULL,
    adviser_name VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_section_year (name, grade_level, school_year_id),
    CONSTRAINT fk_sections_school_year
        FOREIGN KEY (school_year_id) REFERENCES school_years(id)
        ON DELETE CASCADE
);

CREATE TABLE teacher_section_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_user_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED NOT NULL UNIQUE,
    school_year_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teacher_assignment_user
        FOREIGN KEY (teacher_user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_teacher_assignment_section
        FOREIGN KEY (section_id) REFERENCES sections(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_teacher_assignment_school_year
        FOREIGN KEY (school_year_id) REFERENCES school_years(id)
        ON DELETE CASCADE
);

CREATE TABLE auth_login_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    identity_value VARCHAR(120) NOT NULL,
    username_snapshot VARCHAR(50) NULL,
    full_name_snapshot VARCHAR(255) NULL,
    role_snapshot VARCHAR(50) NULL,
    login_status ENUM('success', 'failed') NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    logged_in_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auth_login_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE learner_enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    learner_id INT UNSIGNED NOT NULL,
    school_year_id INT UNSIGNED NOT NULL,
    grade_level VARCHAR(20) NOT NULL,
    section_id INT UNSIGNED NULL,
    enrollment_status ENUM('enrolled', 'completed', 'transferred_out', 'dropped') NOT NULL DEFAULT 'enrolled',
    enrolled_at DATE NOT NULL,
    completed_at DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_learner_school_year (learner_id, school_year_id),
    CONSTRAINT fk_enrollment_learner
        FOREIGN KEY (learner_id) REFERENCES learners(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_enrollment_school_year
        FOREIGN KEY (school_year_id) REFERENCES school_years(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_enrollment_section
        FOREIGN KEY (section_id) REFERENCES sections(id)
        ON DELETE SET NULL
);

CREATE TABLE learner_health_measurements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    learner_enrollment_id INT UNSIGNED NOT NULL UNIQUE,
    height_cm DECIMAL(5,2) NULL,
    weight_kg DECIMAL(5,2) NULL,
    recorded_on DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_health_measurement_enrollment
        FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
        ON DELETE CASCADE
);

CREATE TABLE learner_deworming_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    learner_enrollment_id INT UNSIGNED NOT NULL,
    dose_number TINYINT UNSIGNED NOT NULL,
    administered_on DATE NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_deworming_enrollment_dose (learner_enrollment_id, dose_number),
    CONSTRAINT fk_deworming_enrollment
        FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_deworming_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE feeding_program_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    learner_enrollment_id INT UNSIGNED NOT NULL UNIQUE,
    school_year_id INT UNSIGNED NOT NULL,
    enrolled_on DATE NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_feeding_recipient_enrollment
        FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_feeding_recipient_school_year
        FOREIGN KEY (school_year_id) REFERENCES school_years(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_feeding_recipient_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE grade_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    display_order TINYINT UNSIGNED NOT NULL
);

CREATE TABLE grade_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    learner_enrollment_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    grade_period_id INT UNSIGNED NOT NULL,
    numeric_grade DECIMAL(5,2) NOT NULL,
    remarks VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_grade_record (learner_enrollment_id, subject_id, grade_period_id),
    CONSTRAINT fk_grade_enrollment
        FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_grade_subject
        FOREIGN KEY (subject_id) REFERENCES subjects(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_grade_period
        FOREIGN KEY (grade_period_id) REFERENCES grade_periods(id)
        ON DELETE CASCADE
);

CREATE TABLE learner_subject_grades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    learner_enrollment_id INT UNSIGNED NOT NULL,
    subject_name VARCHAR(160) NOT NULL,
    quarter_1_grade DECIMAL(5,2) NULL,
    quarter_2_grade DECIMAL(5,2) NULL,
    quarter_3_grade DECIMAL(5,2) NULL,
    quarter_4_grade DECIMAL(5,2) NULL,
    first_semester_average DECIMAL(5,2) NULL,
    second_semester_average DECIMAL(5,2) NULL,
    final_average DECIMAL(5,2) NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment_subject (learner_enrollment_id, subject_name),
    CONSTRAINT fk_subject_grades_enrollment
        FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
        ON DELETE CASCADE
);

CREATE TABLE attendance_legends (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    color_hex CHAR(7) NULL,
    counts_as_present TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE attendance_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    learner_enrollment_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    legend_id INT UNSIGNED NOT NULL,
    am_time_in TIME NULL,
    am_time_out TIME NULL,
    pm_time_in TIME NULL,
    pm_time_out TIME NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_day (learner_enrollment_id, attendance_date),
    CONSTRAINT fk_attendance_enrollment
        FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_attendance_legend
        FOREIGN KEY (legend_id) REFERENCES attendance_legends(id)
        ON DELETE RESTRICT
);

CREATE TABLE attendance_scan_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_record_id INT UNSIGNED NOT NULL,
    learner_enrollment_id INT UNSIGNED NOT NULL,
    legend_id INT UNSIGNED NOT NULL,
    slot_key ENUM('am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out') NOT NULL,
    slot_label VARCHAR(30) NOT NULL,
    scanned_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scan_log_slot (attendance_record_id, slot_key),
    CONSTRAINT fk_scan_logs_record
        FOREIGN KEY (attendance_record_id) REFERENCES attendance_records(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_scan_logs_enrollment
        FOREIGN KEY (learner_enrollment_id) REFERENCES learner_enrollments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_scan_logs_legend
        FOREIGN KEY (legend_id) REFERENCES attendance_legends(id)
        ON DELETE RESTRICT
);

INSERT IGNORE INTO grade_periods (name, display_order) VALUES
('1st Quarter', 1),
('2nd Quarter', 2),
('3rd Quarter', 3),
('4th Quarter', 4),
('Final', 5);

INSERT IGNORE INTO attendance_legends (code, label, color_hex, counts_as_present) VALUES
('P', 'Present', '#2F855A', 1),
('L', 'Late', '#DD6B20', 1),
('A', 'Absent', '#C53030', 0),
('E', 'Excused', '#3182CE', 0);

INSERT IGNORE INTO users (username, email, first_name, middle_name, last_name, password_hash, role, is_active) VALUES
('attendance_admin', 'attendance@projectpulse.local', 'Attendance', NULL, 'Admin', '$2y$10$v7qjEmsTgoPzJGUOGr0aL.YGT1PAB6j/yuqMcg6evfkLSqrDwaDLC', 'admin', 1),
('portal_admin', 'admin@projectpulse.local', 'Portal', NULL, 'Admin', '$2y$10$8vrYlwt9a/sRLnGWs01UDO5UYQ1iisGoy3m2LiOtne99.IuOR4n7G', 'admin', 1),
('attendance_user', 'attendance-user@projectpulse.local', 'Attendance', NULL, 'User', '$2y$10$v7qjEmsTgoPzJGUOGr0aL.YGT1PAB6j/yuqMcg6evfkLSqrDwaDLC', 'attendance', 1),
('health_coordinator', 'health@projectpulse.local', 'Health', NULL, 'Coordinator', '$2y$10$cLV/PRK6X6TVzrXWbGsRQe40bsHF6HXj./M8DLmLgIvln/.yDUHoS', 'health', 1),
('teacher_mabini', 'teacher.mabini@projectpulse.local', 'Mabini', 'Demo', 'Teacher', '$2y$10$MowOCypAlH70pG7wAMix3.cddt8d.B66dIBvCfhptP958vYLiu5bi', 'teacher', 1),
('demo_parent', 'parent@projectpulse.local', 'Ana', 'Santos', 'Dela Cruz', '$2y$10$bRKpueTjVab73zPzrBUyBe.3iRircjMowF66LfB1UuA/QZv4Vw9T.', 'parent', 1);

INSERT IGNORE INTO school_years (label, start_date, end_date, is_current) VALUES
('2026-2027', '2026-06-01', '2027-03-31', 1);

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('attendance_scan_mode', 'strict_windows');

INSERT IGNORE INTO sections (name, grade_level, school_year_id, adviser_name)
SELECT 'Mabini', 'Grade 7', sy.id, 'Adviser Demo'
FROM school_years sy
WHERE sy.label = '2026-2027';

INSERT IGNORE INTO teacher_section_assignments (
    teacher_user_id,
    section_id,
    school_year_id
)
SELECT
    u.id,
    s.id,
    sy.id
FROM users u
INNER JOIN school_years sy ON sy.label = '2026-2027'
INNER JOIN sections s ON s.name = 'Mabini' AND s.school_year_id = sy.id
WHERE u.username = 'teacher_mabini';

INSERT IGNORE INTO learners (learner_number, lrn, first_name, middle_name, last_name, current_status) VALUES
('LP-0001', '123456789012', 'Juan', 'Santos', 'Dela Cruz', 'active'),
('LP-0002', '987654321098', 'Maria', 'Reyes', 'Lopez', 'active');

INSERT IGNORE INTO parents (
    user_id,
    first_name,
    middle_name,
    last_name,
    contact_number,
    address
)
SELECT
    u.id,
    'Ana',
    'Santos',
    'Dela Cruz',
    '09171234567',
    'ProjectPulse Demo Household'
FROM users u
WHERE u.username = 'demo_parent';

INSERT IGNORE INTO parent_learner_links (
    parent_id,
    learner_id,
    relationship,
    is_primary_contact
)
SELECT
    p.id,
    l.id,
    'Mother',
    CASE WHEN l.lrn = '123456789012' THEN 1 ELSE 0 END
FROM parents p
INNER JOIN learners l ON l.lrn IN ('123456789012', '987654321098')
INNER JOIN users u ON u.id = p.user_id
WHERE u.username = 'demo_parent';

INSERT IGNORE INTO learner_enrollments (
    learner_id,
    school_year_id,
    grade_level,
    section_id,
    enrollment_status,
    enrolled_at
)
SELECT
    l.id,
    sy.id,
    'Grade 7',
    s.id,
    'enrolled',
    '2026-06-15'
FROM learners l
INNER JOIN school_years sy ON sy.label = '2026-2027'
LEFT JOIN sections s ON s.name = 'Mabini' AND s.school_year_id = sy.id
WHERE l.lrn IN ('123456789012', '987654321098');

INSERT IGNORE INTO learner_health_measurements (
    learner_enrollment_id,
    height_cm,
    weight_kg,
    recorded_on
)
SELECT
    le.id,
    CASE WHEN l.lrn = '123456789012' THEN 142.00 ELSE 138.00 END,
    CASE WHEN l.lrn = '123456789012' THEN 36.50 ELSE 34.25 END,
    '2026-07-01'
FROM learner_enrollments le
INNER JOIN learners l ON l.id = le.learner_id
INNER JOIN school_years sy ON sy.id = le.school_year_id
WHERE sy.label = '2026-2027'
  AND l.lrn IN ('123456789012', '987654321098');

INSERT IGNORE INTO learner_deworming_records (
    learner_enrollment_id,
    dose_number,
    administered_on,
    created_by_user_id
)
SELECT
    le.id,
    1,
    '2026-07-10',
    u.id
FROM learner_enrollments le
INNER JOIN learners l ON l.id = le.learner_id
INNER JOIN school_years sy ON sy.id = le.school_year_id
INNER JOIN users u ON u.username = 'health_coordinator'
WHERE sy.label = '2026-2027'
  AND l.lrn IN ('123456789012', '987654321098');

INSERT IGNORE INTO feeding_program_recipients (
    learner_enrollment_id,
    school_year_id,
    enrolled_on,
    created_by_user_id
)
SELECT
    le.id,
    le.school_year_id,
    '2026-07-12',
    u.id
FROM learner_enrollments le
INNER JOIN learners l ON l.id = le.learner_id
INNER JOIN school_years sy ON sy.id = le.school_year_id
INNER JOIN users u ON u.username = 'health_coordinator'
WHERE sy.label = '2026-2027'
  AND l.lrn = '123456789012';

INSERT IGNORE INTO attendance_records (
    learner_enrollment_id,
    attendance_date,
    legend_id,
    am_time_in,
    am_time_out,
    pm_time_in,
    pm_time_out,
    remarks
)
SELECT
    le.id,
    seeded.attendance_date,
    al.id,
    seeded.am_time_in,
    seeded.am_time_out,
    seeded.pm_time_in,
    seeded.pm_time_out,
    'Demo attendance seed'
FROM learner_enrollments le
INNER JOIN learners l ON l.id = le.learner_id
INNER JOIN (
    SELECT '2026-06-18' AS attendance_date, 'P' AS legend_code, '07:08:00' AS am_time_in, '11:58:00' AS am_time_out, '12:35:00' AS pm_time_in, '16:02:00' AS pm_time_out
    UNION ALL
    SELECT '2026-06-19', 'L', '07:26:00', '12:05:00', '12:36:00', '16:05:00'
    UNION ALL
    SELECT '2026-06-20', 'P', '07:11:00', '12:00:00', '12:34:00', '16:00:00'
    UNION ALL
    SELECT '2026-06-21', 'E', NULL, NULL, NULL, NULL
    UNION ALL
    SELECT '2026-06-22', 'P', '07:03:00', '12:02:00', '12:33:00', '15:58:00'
) AS seeded
INNER JOIN attendance_legends al ON al.code = seeded.legend_code
WHERE l.lrn = '123456789012';

INSERT INTO attendance_scan_logs (
    attendance_record_id,
    learner_enrollment_id,
    legend_id,
    slot_key,
    slot_label,
    scanned_at
)
SELECT
    ar.id,
    ar.learner_enrollment_id,
    ar.legend_id,
    seeded.slot_key,
    seeded.slot_label,
    seeded.scanned_at
FROM attendance_records ar
INNER JOIN learner_enrollments le ON le.id = ar.learner_enrollment_id
INNER JOIN learners l ON l.id = le.learner_id
INNER JOIN (
    SELECT '2026-06-18' AS attendance_date, 'am_time_in' AS slot_key, 'AM time in' AS slot_label, '2026-06-18 07:08:00' AS scanned_at
    UNION ALL
    SELECT '2026-06-18', 'am_time_out', 'AM time out', '2026-06-18 11:58:00'
    UNION ALL
    SELECT '2026-06-18', 'pm_time_in', 'PM time in', '2026-06-18 12:35:00'
    UNION ALL
    SELECT '2026-06-18', 'pm_time_out', 'PM time out', '2026-06-18 16:02:00'
    UNION ALL
    SELECT '2026-06-19', 'am_time_in', 'AM time in', '2026-06-19 07:26:00'
    UNION ALL
    SELECT '2026-06-19', 'am_time_out', 'AM time out', '2026-06-19 12:05:00'
    UNION ALL
    SELECT '2026-06-19', 'pm_time_in', 'PM time in', '2026-06-19 12:36:00'
    UNION ALL
    SELECT '2026-06-19', 'pm_time_out', 'PM time out', '2026-06-19 16:05:00'
    UNION ALL
    SELECT '2026-06-20', 'am_time_in', 'AM time in', '2026-06-20 07:11:00'
    UNION ALL
    SELECT '2026-06-20', 'am_time_out', 'AM time out', '2026-06-20 12:00:00'
    UNION ALL
    SELECT '2026-06-20', 'pm_time_in', 'PM time in', '2026-06-20 12:34:00'
    UNION ALL
    SELECT '2026-06-20', 'pm_time_out', 'PM time out', '2026-06-20 16:00:00'
    UNION ALL
    SELECT '2026-06-22', 'am_time_in', 'AM time in', '2026-06-22 07:03:00'
    UNION ALL
    SELECT '2026-06-22', 'am_time_out', 'AM time out', '2026-06-22 12:02:00'
    UNION ALL
    SELECT '2026-06-22', 'pm_time_in', 'PM time in', '2026-06-22 12:33:00'
    UNION ALL
    SELECT '2026-06-22', 'pm_time_out', 'PM time out', '2026-06-22 15:58:00'
) AS seeded
    ON seeded.attendance_date = ar.attendance_date
WHERE l.lrn = '123456789012';
