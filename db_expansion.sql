-- db_expansion.sql

-- 1. Add baby_height to postnatal_records for growth charts
ALTER TABLE postnatal_records ADD COLUMN IF NOT EXISTS baby_height DECIMAL(5,2) AFTER baby_weight;

-- 2. Master list of vaccines
CREATE TABLE IF NOT EXISTS vaccines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaccine_name VARCHAR(100) NOT NULL,
    description TEXT,
    recommended_age_weeks INT, -- e.g., 0 for BCG, 6 for DPT-1
    is_required TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Records of vaccinations given
CREATE TABLE IF NOT EXISTS immunization_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    baby_id INT NOT NULL,
    vaccine_id INT NOT NULL,
    dose_number INT DEFAULT 1,
    date_given DATE NOT NULL,
    next_dose_date DATE,
    health_worker_id INT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (baby_id) REFERENCES birth_records(id) ON DELETE CASCADE,
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Initial seed for vaccines (Philippines DOH schedule)
INSERT IGNORE INTO vaccines (vaccine_name, recommended_age_weeks, description) VALUES 
('BCG', 0, 'At birth'),
('Hepatitis B', 0, 'At birth'),
('Penta (DPT-HepB-HiB) 1', 6, '6 weeks'),
('Penta (DPT-HepB-HiB) 2', 10, '10 weeks'),
('Penta (DPT-HepB-HiB) 3', 14, '14 weeks'),
('OPV 1', 6, '6 weeks'),
('OPV 2', 10, '10 weeks'),
('OPV 3', 14, '14 weeks'),
('IPV 1', 14, '14 weeks'),
('IPV 2', 36, '9 months'),
('PCV 1', 6, '6 weeks'),
('PCV 2', 10, '10 weeks'),
('PCV 3', 14, '14 weeks'),
('MMR 1', 36, '9 months'),
('MMR 2', 48, '12 months');

-- 5. Family Planning Master List
CREATE TABLE IF NOT EXISTS family_planning_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(100) NOT NULL,
    description TEXT
) ENGINE=InnoDB;

INSERT IGNORE INTO family_planning_methods (method_name) VALUES 
('Pills'), ('Injectable (DMPA)'), ('IUD'), ('Condom'), ('Implant'), ('Bilateral Tubal Ligation'), ('Vasectomy');

-- 6. Family Planning Records
CREATE TABLE IF NOT EXISTS family_planning_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mother_id INT NOT NULL,
    method_id INT NOT NULL,
    registration_date DATE NOT NULL,
    next_service_date DATE,
    remarks TEXT,
    health_worker_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mother_id) REFERENCES mothers(id) ON DELETE CASCADE,
    FOREIGN KEY (method_id) REFERENCES family_planning_methods(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- 7. Emergency SOS Alerts
CREATE TABLE IF NOT EXISTS emergency_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mother_id INT NOT NULL,
    alert_type VARCHAR(50) DEFAULT 'General Emergency',
    status ENUM('active', 'responding', 'resolved') DEFAULT 'active',
    location_data TEXT,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mother_id) REFERENCES mothers(id) ON DELETE CASCADE
) ENGINE=InnoDB;
