-- ============================================================================
-- Demo seed data for ssis_db
-- Run this AFTER SSIS_Database_Schema.sql. Gives you one working student
-- login plus enough related rows to see every tab in the portal populated.
--
-- Demo login:
--   Username / Student No.:  2026-00123   (or username: jdelacruz)
--   Password:                Student@123
--   Role:                    Student
--
-- The password hash below is a real bcrypt hash of "Student@123",
-- generated with PHP's password_hash(), so password_verify() in
-- login.php will accept it as-is.
-- ============================================================================

USE ssis_db;

INSERT INTO Departments (deptName, deptHead) VALUES
  ('College of Computing Studies', 'Dr. Ana Reyes'),
  ("Registrar's Office", 'Mr. Ramon Cruz'),
  ('University Library', 'Ms. Liza Fernandez'),
  ('Accounting / Cashier', 'Mr. Carlo Ilagan');

INSERT INTO Users (username, passwordHash, email, role, status) VALUES
  ('jdelacruz', '$2y$10$zGSGHYFyxndU0gc4npvfPeiuYWDbTxruhsxnTN6JM919KuwaebUYC', 'jdelacruz@cuyotech.edu.ph', 'Student', 'Active'),
  ('registrar1', '$2y$10$zGSGHYFyxndU0gc4npvfPeiuYWDbTxruhsxnTN6JM919KuwaebUYC', 'registrar1@cuyotech.edu.ph', 'Registrar', 'Active');

INSERT INTO Students (userID, studentNo, fullName, program, yearLevel) VALUES
  (1, '2026-00123', 'Juan Dela Cruz', 'BS Information Technology', 3);

INSERT INTO Courses (deptID, courseCode, title, units, semester) VALUES
  (1, 'CCS109', 'System Analysis and Design', 3, '1st Semester'),
  (1, 'ITP107', 'Mobile Application Development', 3, '1st Semester'),
  (1, 'IT301',  'Database Management Systems 2', 3, '2nd Semester');

INSERT INTO Enrollments (studentID, courseID, schoolYear, term, status) VALUES
  (1, 1, '2026-2027', '1st Semester', 'Approved'),
  (1, 2, '2026-2027', '1st Semester', 'Approved'),
  (1, 3, '2025-2026', '2nd Semester', 'Approved');

INSERT INTO Grades (enrollmentID, midterm, finalGrade, remarks) VALUES
  (1, 1.75, NULL, NULL),
  (2, 1.50, NULL, NULL),
  (3, 1.25, 1.25, 'Passed');

INSERT INTO DocumentRequests (studentID, docType, purpose, status) VALUES
  (1, 'COR', 'Scholarship application (Copies: 1, Delivery: Pick-up)', 'Ready'),
  (1, 'TOR', 'Transfer credential (Copies: 2, Delivery: Pick-up)', 'Pending Payment');

INSERT INTO Payments (studentID, docRequestID, amount, method) VALUES
  (1, 1, 100.00, 'Over-the-counter');

INSERT INTO Receipts (paymentID, receiptNo) VALUES
  (1, 'OR-2026-00417');

INSERT INTO ClearanceRecords (studentID, deptID, status, remarks, dateSigned) VALUES
  (1, 2, 'Signed', NULL, '2026-09-01 10:00:00'),
  (1, 3, 'Signed', NULL, '2026-09-02 14:30:00'),
  (1, 1, 'Pending', NULL, NULL),
  (1, 4, 'Pending', 'Balance not yet fully settled', NULL);
