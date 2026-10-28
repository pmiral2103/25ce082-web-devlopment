CREATE DATABASE IF NOT EXISTS studenthub_db;
USE studenthub_db;

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    event_time VARCHAR(20) NOT NULL,
    venue VARCHAR(100) NOT NULL,
    coordinator VARCHAR(100) NOT NULL,
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO events (id, name, category, event_date, event_time, venue, coordinator, status, description) VALUES
(1, 'HackCHARUSAT 2026', 'Technical', '2026-11-15', '09:00 AM', 'CSPIT Computer Center', 'Dr. A. K. Patel', 'Open', 'Annual 36-hour national level hackathon for innovative software and AI solutions.'),
(2, 'Cognizance Tech Fest', 'Technical', '2026-11-20', '10:00 AM', 'CHARUSAT Auditorium', 'Prof. R. M. Shah', 'Open', 'State level tech symposium with robotics, coding sprints, and paper presentations.'),
(3, 'Spoural Cultural Night', 'Cultural', '2026-12-05', '06:00 PM', 'University Central Ground', 'Dr. N. B. Joshi', 'Open', 'Grand university cultural fest featuring music, drama, dance, and fashion show.'),
(4, 'AI & Deep Learning Workshop', 'Workshop', '2026-11-10', '11:00 AM', 'Seminar Hall, Depstar', 'Prof. S. V. Trivedi', 'Closed', 'Hands-on training session on PyTorch and Generative AI transformer models.'),
(5, 'Inter-College Cricket Cup', 'Sports', '2026-11-28', '08:00 AM', 'CHARUSAT Sports Complex', 'Mr. D. P. Parmar', 'Open', 'Annual inter-departmental leather ball cricket tournament with 16 teams.'),
(6, 'Cyber Security & Cloud Summit', 'Seminar', '2026-10-15', '02:00 PM', 'Conference Room, CSPIT', 'Dr. K. G. Desai', 'Closed', 'Expert talk by industry leaders on zero trust architecture and cloud safety.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    category = VALUES(category),
    event_date = VALUES(event_date),
    event_time = VALUES(event_time),
    venue = VALUES(venue),
    coordinator = VALUES(coordinator),
    status = VALUES(status),
    description = VALUES(description);
