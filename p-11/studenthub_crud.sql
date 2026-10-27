CREATE DATABASE IF NOT EXISTS studenthub_db;
USE studenthub_db;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id_no VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mobile VARCHAR(15) NOT NULL,
    department VARCHAR(50) NOT NULL,
    semester VARCHAR(10) DEFAULT '4',
    cgpa DECIMAL(3,2) DEFAULT 8.00,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO students (student_id_no, name, email, mobile, department, semester, cgpa, status) VALUES
('25CE082', 'Miral Patel', '25ce082@charusat.edu.in', '9876543210', 'Computer Engineering', '4', 8.85, 'Active'),
('25CE001', 'Aarav Mehta', '25ce001@charusat.edu.in', '9876500001', 'Computer Engineering', '4', 8.45, 'Active'),
('25CE015', 'Bhavya Shah', '25ce015@charusat.edu.in', '9876500015', 'Computer Engineering', '4', 7.90, 'Active'),
('25CE042', 'Dhruv Joshi', '25ce042@charusat.edu.in', '9876500042', 'Computer Engineering', '4', 9.10, 'Active'),
('25CE065', 'Kavya Trivedi', '25ce065@charusat.edu.in', '9876500065', 'Computer Engineering', '4', 8.75, 'Active'),
('25IT004', 'Ananya Sharma', '25it004@charusat.edu.in', '9876510004', 'Information Technology', '4', 8.60, 'Active'),
('25IT021', 'Devansh Patel', '25it021@charusat.edu.in', '9876510021', 'Information Technology', '4', 7.80, 'Active'),
('25IT038', 'Harshil Vora', '25it038@charusat.edu.in', '9876510038', 'Information Technology', '4', 8.30, 'Active'),
('25IT055', 'Isha Pandya', '25it055@charusat.edu.in', '9876510055', 'Information Technology', '4', 9.25, 'Active'),
('25CS008', 'Jaydeep Barot', '25cs008@charusat.edu.in', '9876520008', 'Computer Science', '4', 8.15, 'Active'),
('25CS019', 'Khushi Prajapati', '25cs019@charusat.edu.in', '9876520019', 'Computer Science', '4', 8.90, 'Active'),
('25CS034', 'Manan Desai', '25cs034@charusat.edu.in', '9876520034', 'Computer Science', '4', 7.65, 'Inactive'),
('25CS050', 'Nishi Gajjar', '25cs050@charusat.edu.in', '9876520050', 'Computer Science', '4', 8.55, 'Active'),
('25EC003', 'Omkar Soni', '25ec003@charusat.edu.in', '9876530003', 'Electronics & Communication', '4', 7.95, 'Active'),
('25EC017', 'Parth Parmar', '25ec017@charusat.edu.in', '9876530017', 'Electronics & Communication', '4', 8.20, 'Active'),
('25EC029', 'Riddhi Dave', '25ec029@charusat.edu.in', '9876530029', 'Electronics & Communication', '4', 9.00, 'Active'),
('25ME005', 'Smit Makwana', '25me005@charusat.edu.in', '9876540005', 'Mechanical Engineering', '4', 7.40, 'Inactive'),
('25ME018', 'Tanvi Solanki', '25me018@charusat.edu.in', '9876540018', 'Mechanical Engineering', '4', 8.10, 'Active'),
('25CL002', 'Urvish Rathod', '25cl002@charusat.edu.in', '9876550002', 'Civil Engineering', '4', 7.70, 'Active'),
('25CL014', 'Vishwa Panchal', '25cl014@charusat.edu.in', '9876550014', 'Civil Engineering', '4', 8.40, 'Active'),
('25CE095', 'Yashvi Raval', '25ce095@charusat.edu.in', '9876500095', 'Computer Engineering', '4', 8.95, 'Active'),
('25IT062', 'Zeel Shah', '25it062@charusat.edu.in', '9876510062', 'Information Technology', '4', 9.05, 'Active')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    mobile = VALUES(mobile),
    department = VALUES(department),
    semester = VALUES(semester),
    cgpa = VALUES(cgpa),
    status = VALUES(status);
