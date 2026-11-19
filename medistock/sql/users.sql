-- User Management script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER login.sql.

DROP VIEW IF EXISTS v_user_list;
DROP VIEW IF EXISTS v_activity_log;
DROP PROCEDURE IF EXISTS sp_user_kpis;
DROP PROCEDURE IF EXISTS sp_user_list;
DROP PROCEDURE IF EXISTS sp_activity_log;
DROP PROCEDURE IF EXISTS sp_set_user_active;
DROP PROCEDURE IF EXISTS sp_get_user;
DROP PROCEDURE IF EXISTS sp_add_user;
DROP PROCEDURE IF EXISTS sp_update_user;

-- the password is NOT in this view, so the page can never read it
CREATE VIEW v_user_list AS
SELECT user_id, full_name, email, role, last_login, is_active
FROM users;

CREATE VIEW v_activity_log AS
SELECT
    a.log_id,
    a.log_time,
    IFNULL(u.full_name, 'System') AS user_name,
    a.action,
    a.details
FROM activity_log a
LEFT JOIN users u ON a.user_id = u.user_id;

DELIMITER //

-- the 4 cards
CREATE PROCEDURE sp_user_kpis()
BEGIN
    SELECT
        COUNT(*) AS total_users,
        IFNULL(SUM(is_active = 1), 0) AS active_accounts,
        IFNULL(SUM(is_active = 0), 0) AS deactivated,
        -- "logged in now" = logged in during the last 8 hours (one work shift)
        IFNULL(SUM(is_active = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 8 HOUR)), 0) AS active_sessions
    FROM v_user_list;
END //

CREATE PROCEDURE sp_user_list()
BEGIN
    SELECT user_id, full_name, email, role, last_login, is_active
    FROM v_user_list
    ORDER BY is_active DESC, user_id ASC;
END //

CREATE PROCEDURE sp_activity_log()
BEGIN
    SELECT log_time, user_name, action, details
    FROM v_activity_log
    ORDER BY log_time DESC
    LIMIT 8;
END //

-- deactivate (p_active = 0) or reactivate (p_active = 1) an account, and write it in the activity log
CREATE PROCEDURE sp_set_user_active(IN p_user_id INT, IN p_active TINYINT, IN p_by INT)
BEGIN
    DECLARE person VARCHAR(100);

    SELECT full_name INTO person FROM users WHERE user_id = p_user_id;

    UPDATE users SET is_active = p_active WHERE user_id = p_user_id;

    IF p_active = 0 THEN
        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'User Deactivated', CONCAT(person, '''s account deactivated'));
    ELSE
        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'User Reactivated', CONCAT(person, '''s account reactivated'));
    END IF;
END //

-- one user (used to fill the form when editing, no password)
CREATE PROCEDURE sp_get_user(IN p_user_id INT)
BEGIN
    SELECT user_id, full_name, username, email, role
    FROM users
    WHERE user_id = p_user_id;
END //

-- add a new user (the password arrives already hashed from PHP)
-- the answer is 'ok', 'username taken' or 'email taken'
CREATE PROCEDURE sp_add_user(
    IN p_name VARCHAR(100),
    IN p_username VARCHAR(50),
    IN p_email VARCHAR(100),
    IN p_password VARCHAR(255),
    IN p_role VARCHAR(30),
    IN p_by INT
)
BEGIN
    IF EXISTS (SELECT 1 FROM users WHERE username = p_username) THEN
        SELECT 'username taken' AS result;
    ELSEIF EXISTS (SELECT 1 FROM users WHERE email = p_email) THEN
        SELECT 'email taken' AS result;
    ELSE
        INSERT INTO users (username, email, password, full_name, role, is_active)
        VALUES (p_username, p_email, p_password, p_name, p_role, 1);

        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'User Added', CONCAT('Added ', p_name, ' as ', p_role));

        SELECT 'ok' AS result;
    END IF;
END //

-- save changes of a user; if p_password is empty the old password is kept
CREATE PROCEDURE sp_update_user(
    IN p_user_id INT,
    IN p_name VARCHAR(100),
    IN p_username VARCHAR(50),
    IN p_email VARCHAR(100),
    IN p_password VARCHAR(255),
    IN p_role VARCHAR(30),
    IN p_by INT
)
BEGIN
    DECLARE old_role VARCHAR(30);

    IF EXISTS (SELECT 1 FROM users WHERE username = p_username AND user_id <> p_user_id) THEN
        SELECT 'username taken' AS result;
    ELSEIF EXISTS (SELECT 1 FROM users WHERE email = p_email AND user_id <> p_user_id) THEN
        SELECT 'email taken' AS result;
    ELSE
        SELECT role INTO old_role FROM users WHERE user_id = p_user_id;

        UPDATE users
        SET full_name = p_name,
            username = p_username,
            email = p_email,
            role = p_role
        WHERE user_id = p_user_id;

        IF p_password <> '' THEN
            UPDATE users SET password = p_password WHERE user_id = p_user_id;
        END IF;

        -- write it in the activity log
        IF old_role <> p_role THEN
            INSERT INTO activity_log (log_time, user_id, action, details)
            VALUES (NOW(), p_by, 'User Edited', CONCAT('Changed ', p_name, '''s role to ', p_role));
        ELSE
            INSERT INTO activity_log (log_time, user_id, action, details)
            VALUES (NOW(), p_by, 'User Edited', CONCAT('Updated ', p_name, '''s account'));
        END IF;

        SELECT 'ok' AS result;
    END IF;
END //

DELIMITER ;
