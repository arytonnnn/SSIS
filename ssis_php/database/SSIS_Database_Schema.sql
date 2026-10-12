-- ============================================================================
-- CuyoTech University Student Services Information System (SSIS)
-- Database Schema (3NF)
-- Generated from the ERD and 3NF table designs in Task 3, Section 3.2
-- Target: MySQL 8.0 / MariaDB 10.4+ (InnoDB, utf8mb4)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS ssis_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ssis_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Table 7a. Users
-- Base table for authentication and role-based access, shared by every
-- account type (Student, Registrar, Cashier, DepartmentStaff, Admin).
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS Users;
CREATE TABLE Users (
    userID          INT             NOT NULL AUTO_INCREMENT,
    username        VARCHAR(50)     NOT NULL,
    passwordHash    VARCHAR(255)    NOT NULL,
    email           VARCHAR(100)    NOT NULL,
    role            ENUM('Student','Registrar','Cashier','DepartmentStaff','Admin') NOT NULL,
    status          ENUM('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
    createdAt       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (userID),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7c. Departments
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS Departments;
CREATE TABLE Departments (
    deptID          INT             NOT NULL AUTO_INCREMENT,
    deptName        VARCHAR(60)     NOT NULL,
    deptHead        VARCHAR(100)    NULL,
    PRIMARY KEY (deptID),
    UNIQUE KEY uq_departments_name (deptName)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7b. Students
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS Students;
CREATE TABLE Students (
    studentID       INT             NOT NULL AUTO_INCREMENT,
    userID          INT             NOT NULL,
    studentNo       VARCHAR(15)     NOT NULL,
    fullName        VARCHAR(100)    NOT NULL,
    program         VARCHAR(60)     NOT NULL,
    yearLevel       INT             NOT NULL,
    PRIMARY KEY (studentID),
    UNIQUE KEY uq_students_studentno (studentNo),
    UNIQUE KEY uq_students_userid (userID),
    CONSTRAINT fk_students_user
        FOREIGN KEY (userID) REFERENCES Users(userID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_students_yearlevel CHECK (yearLevel BETWEEN 1 AND 6)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7d. DepartmentStaff
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS DepartmentStaff;
CREATE TABLE DepartmentStaff (
    staffID         INT             NOT NULL AUTO_INCREMENT,
    userID          INT             NOT NULL,
    deptID          INT             NOT NULL,
    employeeNo      VARCHAR(15)     NOT NULL,
    position        VARCHAR(40)     NOT NULL,
    PRIMARY KEY (staffID),
    UNIQUE KEY uq_deptstaff_employeeno (employeeNo),
    UNIQUE KEY uq_deptstaff_userid (userID),
    CONSTRAINT fk_deptstaff_user
        FOREIGN KEY (userID) REFERENCES Users(userID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_deptstaff_dept
        FOREIGN KEY (deptID) REFERENCES Departments(deptID)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7e. Courses
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS Courses;
CREATE TABLE Courses (
    courseID        INT             NOT NULL AUTO_INCREMENT,
    deptID          INT             NOT NULL,
    courseCode      VARCHAR(10)     NOT NULL,
    title           VARCHAR(100)    NOT NULL,
    units           INT             NOT NULL,
    semester        VARCHAR(20)     NOT NULL,
    PRIMARY KEY (courseID),
    UNIQUE KEY uq_courses_code (courseCode),
    CONSTRAINT fk_courses_dept
        FOREIGN KEY (deptID) REFERENCES Departments(deptID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_courses_units CHECK (units > 0)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7f. Enrollments
-- Resolves the many-to-many relationship between Students and Courses.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS Enrollments;
CREATE TABLE Enrollments (
    enrollmentID    INT             NOT NULL AUTO_INCREMENT,
    studentID       INT             NOT NULL,
    courseID        INT             NOT NULL,
    schoolYear      VARCHAR(9)      NOT NULL,          -- e.g. 2026-2027
    term            VARCHAR(15)     NOT NULL,
    status          ENUM('Pending','Approved','Dropped') NOT NULL DEFAULT 'Pending',
    PRIMARY KEY (enrollmentID),
    UNIQUE KEY uq_enrollment_unique (studentID, courseID, schoolYear, term),
    CONSTRAINT fk_enroll_student
        FOREIGN KEY (studentID) REFERENCES Students(studentID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_enroll_course
        FOREIGN KEY (courseID) REFERENCES Courses(courseID)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7g. Grades
-- One grade record per enrollment.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS Grades;
CREATE TABLE Grades (
    gradeID         INT             NOT NULL AUTO_INCREMENT,
    enrollmentID    INT             NOT NULL,
    midterm         DECIMAL(3,2)    NULL,
    finalGrade      DECIMAL(3,2)    NULL,
    remarks         ENUM('Passed','Failed','Incomplete') NULL,
    PRIMARY KEY (gradeID),
    UNIQUE KEY uq_grades_enrollment (enrollmentID),
    CONSTRAINT fk_grades_enrollment
        FOREIGN KEY (enrollmentID) REFERENCES Enrollments(enrollmentID)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7k. DocumentRequests
-- (created before Payments because Payments has an optional FK to it)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS DocumentRequests;
CREATE TABLE DocumentRequests (
    requestID       INT             NOT NULL AUTO_INCREMENT,
    studentID       INT             NOT NULL,
    approvedBy      INT             NULL,              -- Registrar (Users.userID); set once approved
    docType         ENUM('TOR','COR','Certification') NOT NULL,
    purpose         VARCHAR(60)     NOT NULL,
    status          ENUM('Pending Payment','Paid','Under Review','Rejected','Ready','Completed')
                    NOT NULL DEFAULT 'Pending Payment',
    requestDate     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (requestID),
    CONSTRAINT fk_docreq_student
        FOREIGN KEY (studentID) REFERENCES Students(studentID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_docreq_approver
        FOREIGN KEY (approvedBy) REFERENCES Users(userID)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7h. Payments
-- docRequestID is nullable: it is set only when the payment settles a
-- document-request fee (as opposed to a tuition/enrollment payment).
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS Payments;
CREATE TABLE Payments (
    paymentID       INT             NOT NULL AUTO_INCREMENT,
    studentID       INT             NOT NULL,
    docRequestID    INT             NULL,
    amount          DECIMAL(10,2)   NOT NULL,
    method          ENUM('Cash','Online','Over-the-counter') NOT NULL,
    paymentDate     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (paymentID),
    CONSTRAINT fk_payments_student
        FOREIGN KEY (studentID) REFERENCES Students(studentID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_payments_docreq
        FOREIGN KEY (docRequestID) REFERENCES DocumentRequests(requestID)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_payments_amount CHECK (amount > 0)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7i. Receipts
-- One receipt per payment.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS Receipts;
CREATE TABLE Receipts (
    receiptID       INT             NOT NULL AUTO_INCREMENT,
    paymentID       INT             NOT NULL,
    receiptNo       VARCHAR(20)     NOT NULL,
    dateIssued      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (receiptID),
    UNIQUE KEY uq_receipts_receiptno (receiptNo),
    UNIQUE KEY uq_receipts_payment (paymentID),
    CONSTRAINT fk_receipts_payment
        FOREIGN KEY (paymentID) REFERENCES Payments(paymentID)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Table 7j. ClearanceRecords
-- One row per student per department.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS ClearanceRecords;
CREATE TABLE ClearanceRecords (
    clearanceID     INT             NOT NULL AUTO_INCREMENT,
    studentID       INT             NOT NULL,
    deptID          INT             NOT NULL,
    status          ENUM('Pending','Signed','Rejected') NOT NULL DEFAULT 'Pending',
    remarks         VARCHAR(100)    NULL,
    dateSigned      DATETIME        NULL,
    PRIMARY KEY (clearanceID),
    UNIQUE KEY uq_clearance_student_dept (studentID, deptID),
    CONSTRAINT fk_clearance_student
        FOREIGN KEY (studentID) REFERENCES Students(studentID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_clearance_dept
        FOREIGN KEY (deptID) REFERENCES Departments(deptID)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Helpful indexes on foreign keys that are not already covered by a
-- UNIQUE KEY above (MySQL auto-indexes FKs, but these are explicit for
-- portability to engines that do not).
-- ============================================================================
CREATE INDEX idx_enrollments_student   ON Enrollments(studentID);
CREATE INDEX idx_enrollments_course    ON Enrollments(courseID);
CREATE INDEX idx_payments_student      ON Payments(studentID);
CREATE INDEX idx_docrequests_student   ON DocumentRequests(studentID);
CREATE INDEX idx_courses_dept          ON Courses(deptID);

-- ============================================================================
-- Sample seed data (optional) -- uncomment to load a minimal working set
-- for demoing the prototype in Task 5.
-- ============================================================================
-- INSERT INTO Departments (deptName, deptHead) VALUES
--   ('College of Computing Studies', 'Dr. Ana Reyes'),
--   ('Registrar''s Office', 'Mr. Ramon Cruz');
--
-- INSERT INTO Users (username, passwordHash, email, role) VALUES
--   ('jdelacruz', '$2y$10$examplehashonly', 'jdelacruz@cuyotech.edu.ph', 'Student'),
--   ('registrar1', '$2y$10$examplehashonly', 'registrar1@cuyotech.edu.ph', 'Registrar');
--
-- INSERT INTO Students (userID, studentNo, fullName, program, yearLevel) VALUES
--   (1, '2023-00123', 'Juan Dela Cruz', 'BS Information Technology', 3);
--
-- INSERT INTO Courses (deptID, courseCode, title, units, semester) VALUES
--   (1, 'IT301', 'Systems Analysis and Design', 3, '1st Semester');
--
-- INSERT INTO Enrollments (studentID, courseID, schoolYear, term) VALUES
--   (1, 1, '2026-2027', '1st Semester');
