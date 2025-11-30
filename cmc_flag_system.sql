CREATE DATABASE cmc_flag_system;
use cmc_flag_system;

CREATE TABLE students (
  student_id VARCHAR(15) PRIMARY KEY,
  fullname VARCHAR(100) NOT NULL,
  course VARCHAR(50),
  year VARCHAR(20),
  section VARCHAR(20),
  scholarship_type VARCHAR(50),
  status VARCHAR(20) DEFAULT 'active'
);


CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert a sample admin (username: admin, password: secret123)
INSERT INTO admins (username, password) VALUES ('admin', 'secret123'),
('Chui', 'Gentiles')
ON DUPLICATE KEY UPDATE username = username;


CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(15),
  date DATE NOT NULL,
  day VARCHAR(20),
  time_in TIME,
  status VARCHAR(10),
  photo_path VARCHAR(255),
  approval_status VARCHAR(20) DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  approved_by INT DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  fullname VARCHAR(100) AFTER student_id,
  course VARCHAR(50) AFTER fullname,
  year VARCHAR(20) AFTER course,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- Sample Scholars (for testing)
INSERT INTO students (student_id, fullname, course, year, section, scholarship_type) VALUES
('23-1001', 'Jammael Magallanes', 'BSIS', '3rd Year', '3C', 'BCBP Scholar'),
('23-1002', 'Philip Reyan J. Etorma', 'BSIS', '3rd Year', '3C', 'BCBP Scholar'),
('23-1003', 'Melanie Alingasa', 'BSIS', '3rd Year', '3C', 'BCBP Scholar'),
('23-1004', 'Lloyd Calamba', 'BSIS', '3rd Year', '3C', 'Academic Scholar'),
('23-1005', 'Jane Smith', 'BSIT', '1st Year', '1B', 'Athletic Scholar'),
('23-1006', 'Alice Johnson', 'BSIS', '4th Year', '4D', 'Cultural Scholar'),
('23-1007', 'Bob Brown', 'BSCS', '2nd Year', '2C', 'Academic Scholar'),
('23-1008', 'Charlie Davis', 'BSIT', '3rd Year', '3A', 'Athletic Scholar'),
('23-1009', 'Diana Evans', 'BSIS', '1st Year', '1C', 'Cultural Scholar'),
('23-1010', 'Ethan Wilson', 'BSCS', '4th Year', '4B', 'Academic Scholar');

-- find attendance rows whose student_id has no students record
SELECT DISTINCT a.student_id
FROM attendance a
LEFT JOIN students s ON TRIM(a.student_id) = TRIM(s.student_id)
WHERE s.student_id IS NULL
ORDER BY a.student_id;


ALTER TABLE attendance ADD COLUMN photo_path VARCHAR(255) AFTER status;

ALTER TABLE attendance ADD COLUMN approval_status VARCHAR(20) DEFAULT 'pending' AFTER photo_path;

ALTER TABLE attendance ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE attendance ADD COLUMN approved_by INT DEFAULT NULL;
ALTER TABLE attendance ADD COLUMN approved_at DATETIME DEFAULT NULL;

ALTER TABLE attendance ADD COLUMN fullname VARCHAR(100) AFTER student_id;
ALTER TABLE attendance ADD COLUMN course VARCHAR(50) AFTER fullname;
ALTER TABLE attendance ADD COLUMN year VARCHAR(20) AFTER course;