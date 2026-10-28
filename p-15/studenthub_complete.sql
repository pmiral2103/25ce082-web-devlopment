CREATE DATABASE IF NOT EXISTS studenthub_db;
USE studenthub_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    role ENUM('Student', 'Faculty', 'Admin') DEFAULT 'Student',
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    event_time VARCHAR(20) NOT NULL,
    venue VARCHAR(100) NOT NULL,
    coordinator VARCHAR(100) NOT NULL,
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    poster VARCHAR(255) DEFAULT 'default_poster.png',
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Confirmed',
    remarks VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY unique_reg (student_id, event_id),
    CONSTRAINT fk_p15_reg_stud FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_p15_reg_ev FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (id, username, email, password, full_name, mobile, role, status, last_login) VALUES
(1, 'admin_hub', 'admin@charusat.edu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '9123456780', 'Admin', 'Active', NOW()),
(2, 'miral082', '25ce082@charusat.edu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Miral Patel', '9876543210', 'Student', 'Active', NOW()),
(3, 'prof_sharma', 'sharma@charusat.edu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. R. K. Sharma', '9898989898', 'Faculty', 'Active', NOW()),
(4, 'rahul_shah', 'rahul@charusat.edu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rahul Shah', '9898012345', 'Student', 'Active', NOW()),
(5, 'priya_ce', 'priya@charusat.edu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Priya Patel', '9898123456', 'Student', 'Inactive', NOW()),
(6, 'karan_ce', 'karan@charusat.edu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Karan Joshi', '9898234567', 'Student', 'Active', NOW()),
(7, 'ananya_it', 'ananya@charusat.edu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ananya Sharma', '9898345678', 'Student', 'Active', NOW())
ON DUPLICATE KEY UPDATE status=VALUES(status);

INSERT INTO students (id, student_id_no, name, email, mobile, department, semester, cgpa, status) VALUES
(1, '25CE082', 'Miral Patel', '25ce082@charusat.edu.in', '9876543210', 'Computer Engineering', '4', 8.85, 'Active'),
(2, '25CE001', 'Aarav Mehta', '25ce001@charusat.edu.in', '9876500001', 'Computer Engineering', '4', 8.45, 'Active'),
(3, '25CE015', 'Bhavya Shah', '25ce015@charusat.edu.in', '9876500015', 'Computer Engineering', '4', 7.90, 'Active'),
(4, '25CE042', 'Dhruv Joshi', '25ce042@charusat.edu.in', '9876500042', 'Computer Engineering', '4', 9.10, 'Active'),
(5, '25CE065', 'Kavya Trivedi', '25ce065@charusat.edu.in', '9876500065', 'Computer Engineering', '4', 8.75, 'Active'),
(6, '25IT004', 'Ananya Sharma', '25it004@charusat.edu.in', '9876510004', 'Information Technology', '4', 8.60, 'Active'),
(7, '25IT021', 'Devansh Patel', '25it021@charusat.edu.in', '9876510021', 'Information Technology', '4', 7.80, 'Active'),
(8, '25IT038', 'Harshil Vora', '25it038@charusat.edu.in', '9876510038', 'Information Technology', '4', 8.30, 'Active'),
(9, '25IT055', 'Isha Pandya', '25it055@charusat.edu.in', '9876510055', 'Information Technology', '4', 9.25, 'Active'),
(10, '25CS008', 'Jaydeep Barot', '25cs008@charusat.edu.in', '9876520008', 'Computer Science', '4', 8.15, 'Active'),
(11, '25CS019', 'Khushi Prajapati', '25cs019@charusat.edu.in', '9876520019', 'Computer Science', '4', 8.90, 'Active'),
(12, '25CS034', 'Manan Desai', '25cs034@charusat.edu.in', '9876520034', 'Computer Science', '4', 7.65, 'Inactive'),
(13, '25CS050', 'Nishi Gajjar', '25cs050@charusat.edu.in', '9876520050', 'Computer Science', '4', 8.55, 'Active'),
(14, '25EC003', 'Omkar Soni', '25ec003@charusat.edu.in', '9876530003', 'Electronics & Communication', '4', 7.95, 'Active'),
(15, '25EC017', 'Parth Parmar', '25ec017@charusat.edu.in', '9876530017', 'Electronics & Communication', '4', 8.20, 'Active'),
(16, '25EC029', 'Riddhi Dave', '25ec029@charusat.edu.in', '9876530029', 'Electronics & Communication', '4', 9.00, 'Active'),
(17, '25ME005', 'Smit Makwana', '25me005@charusat.edu.in', '9876540005', 'Mechanical Engineering', '4', 7.40, 'Inactive'),
(18, '25ME018', 'Tanvi Solanki', '25me018@charusat.edu.in', '9876540018', 'Mechanical Engineering', '4', 8.10, 'Active'),
(19, '25CL002', 'Urvish Rathod', '25cl002@charusat.edu.in', '9876550002', 'Civil Engineering', '4', 7.70, 'Active'),
(20, '25CL014', 'Vishwa Panchal', '25cl014@charusat.edu.in', '9876550014', 'Civil Engineering', '4', 8.40, 'Active')
ON DUPLICATE KEY UPDATE status=VALUES(status);

INSERT INTO events (id, name, category, event_date, event_time, venue, coordinator, status, poster, description) VALUES
(1, 'HackCHARUSAT 2026', 'Technical', '2026-11-15', '09:00 AM', 'CSPIT Computer Center', 'Dr. A. K. Patel', 'Open', 'default_poster.png', 'Annual 36-hour national level hackathon for innovative software and AI solutions.'),
(2, 'Cognizance Tech Fest', 'Technical', '2026-11-20', '10:00 AM', 'CHARUSAT Auditorium', 'Prof. R. M. Shah', 'Open', 'default_poster.png', 'State level tech symposium with robotics, coding sprints, and paper presentations.'),
(3, 'Spoural Cultural Night', 'Cultural', '2026-12-05', '06:00 PM', 'University Central Ground', 'Dr. N. B. Joshi', 'Open', 'default_poster.png', 'Grand university cultural fest featuring music, drama, dance, and fashion show.'),
(4, 'AI & Deep Learning Workshop', 'Workshop', '2026-11-10', '11:00 AM', 'Seminar Hall, Depstar', 'Prof. S. V. Trivedi', 'Closed', 'default_poster.png', 'Hands-on training session on PyTorch and Generative AI transformer models.'),
(5, 'Inter-College Cricket Cup', 'Sports', '2026-11-28', '08:00 AM', 'CHARUSAT Sports Complex', 'Mr. D. P. Parmar', 'Open', 'default_poster.png', 'Annual inter-departmental leather ball cricket tournament with 16 teams.'),
(6, 'Cyber Security & Cloud Summit', 'Seminar', '2026-10-15', '02:00 PM', 'Conference Room, CSPIT', 'Dr. K. G. Desai', 'Closed', 'default_poster.png', 'Expert talk by industry leaders on zero trust architecture and cloud safety.')
ON DUPLICATE KEY UPDATE status=VALUES(status);

INSERT INTO registrations (id, student_id, event_id, registration_date, status, remarks) VALUES
(1, 1, 1, '2026-08-15 10:30:00', 'Confirmed', 'Early Bird Registration'),
(2, 2, 1, '2026-08-22 14:15:00', 'Confirmed', 'Department Nominee'),
(3, 3, 2, '2026-09-05 09:45:00', 'Confirmed', 'Solo participant'),
(4, 4, 2, '2026-09-18 11:20:00', 'Confirmed', 'Hackathon team leader'),
(5, 1, 2, '2026-10-02 16:00:00', 'Confirmed', 'Campus Ambassador'),
(6, 2, 3, '2026-10-14 12:30:00', 'Confirmed', 'Cultural night participant'),
(7, 3, 3, '2026-10-25 15:45:00', 'Confirmed', 'Group dance entry'),
(8, 4, 1, '2026-11-04 10:00:00', 'Confirmed', 'Coding sprint entry'),
(9, 1, 3, '2026-11-12 11:30:00', 'Confirmed', 'Drama team entry')
ON DUPLICATE KEY UPDATE status=VALUES(status);
