-- run this in phpMyAdmin (SQL tab) after creating the database "medistock"
-- running it again resets the users and the activity log

DROP PROCEDURE IF EXISTS sp_login_user;
DROP PROCEDURE IF EXISTS sp_record_login;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'Staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,   -- 1 = active, 0 = deactivated (cannot log in)
    last_login DATETIME NULL
);
-- roles: Admin (Owner / Admin) and Staff (Pharmacy Staff)

-- who did what and when (shown in User Management)
CREATE TABLE activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    log_time DATETIME NOT NULL,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details VARCHAR(200) NOT NULL
);

DELIMITER //

-- stored procedure used by login.php (user can type username OR email)
CREATE PROCEDURE sp_login_user(IN p_login VARCHAR(100))
BEGIN
    SELECT user_id, username, password, full_name, role, is_active
    FROM users
    WHERE username = p_login OR email = p_login;
END //

-- saves the time of the last login
CREATE PROCEDURE sp_record_login(IN p_user_id INT)
BEGIN
    UPDATE users SET last_login = NOW() WHERE user_id = p_user_id;
END //

DELIMITER ;

-- test accounts: password for all of them is admin123
-- (the long text is a bcrypt hash made with PHP password_hash; to make your own,
--  run: echo password_hash("yourpassword", PASSWORD_DEFAULT);)
-- you can log in with the username (admin / inventory / sales) or with the email
INSERT INTO users (username, email, password, full_name, role, is_active, last_login) VALUES
('admin',     'anna.reyes@medistock.ph',  '$2y$10$BSaTKSBlL0HbjMyeuTfHkOdiM3JDl0vyEJBUS932Xi6MHh8Sl3wgq', 'Dr. Anna Reyes',    'Admin',           1, NOW()),
('inventory', 'mark.v@medistock.ph',      '$2y$10$BSaTKSBlL0HbjMyeuTfHkOdiM3JDl0vyEJBUS932Xi6MHh8Sl3wgq', 'Mark Villanueva',   'Staff', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('sales',     'jenny.c@medistock.ph',     '$2y$10$BSaTKSBlL0HbjMyeuTfHkOdiM3JDl0vyEJBUS932Xi6MHh8Sl3wgq', 'Jenny Castillo',    'Staff', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('paulo',     'paulo.a@medistock.ph',     '$2y$10$BSaTKSBlL0HbjMyeuTfHkOdiM3JDl0vyEJBUS932Xi6MHh8Sl3wgq', 'Paulo Aquino',      'Staff', 0, DATE_SUB(NOW(), INTERVAL 20 DAY));

-- sample activity log
INSERT INTO activity_log (log_time, user_id, action, details) VALUES
(DATE_SUB(NOW(), INTERVAL 30 HOUR), 2, 'Stock Updated',     'Amoxicillin 500mg: 12 → 512 units'),
(DATE_SUB(NOW(), INTERVAL 26 HOUR), 1, 'User Edited',       'Changed Jenny Castillo''s role to Staff'),
(DATE_SUB(NOW(), INTERVAL 22 HOUR), 3, 'Sale Recorded',     'Order #OR-1040 — ₱ 320.00'),
(DATE_SUB(NOW(), INTERVAL 20 DAY),  1, 'User Deactivated',  'Paulo Aquino''s account deactivated');
