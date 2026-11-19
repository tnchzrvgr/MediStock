-- backup.sql : tables, procedures for the Backup & Security page
-- run this in phpMyAdmin (after login.sql and users.sql)
USE medistock;

DROP PROCEDURE IF EXISTS sp_backup_settings;
DROP PROCEDURE IF EXISTS sp_save_backup_settings;
DROP PROCEDURE IF EXISTS sp_save_security_settings;
DROP PROCEDURE IF EXISTS sp_backup_history;
DROP PROCEDURE IF EXISTS sp_add_backup;
DROP PROCEDURE IF EXISTS sp_get_backup;
DROP PROCEDURE IF EXISTS sp_delete_backup;
DROP PROCEDURE IF EXISTS sp_old_backups;
DROP PROCEDURE IF EXISTS sp_log_restore;
DROP TABLE IF EXISTS backup_history;
DROP TABLE IF EXISTS backup_settings;

-- one row only (id = 1) with all the settings of the page
CREATE TABLE backup_settings (
    id INT PRIMARY KEY,
    schedule VARCHAR(10) NOT NULL DEFAULT 'Daily',
    backup_time TIME NOT NULL DEFAULT '02:00:00',
    keep_last INT NOT NULL DEFAULT 30,
    session_timeout INT NOT NULL DEFAULT 15
);

INSERT INTO backup_settings (id) VALUES (1);

CREATE TABLE backup_history (
    backup_id INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL,
    backup_type VARCHAR(10) NOT NULL,
    size_bytes BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(10) NOT NULL,
    file_name VARCHAR(100) NOT NULL DEFAULT '',
    created_by INT NULL
);

DELIMITER //

CREATE PROCEDURE sp_backup_settings()
BEGIN
    SELECT schedule, backup_time, keep_last, session_timeout
    FROM backup_settings
    WHERE id = 1;
END //

-- Database Backup card: schedule, time and keep last
CREATE PROCEDURE sp_save_backup_settings(IN p_schedule VARCHAR(10), IN p_time TIME, IN p_keep INT, IN p_by INT)
BEGIN
    UPDATE backup_settings
    SET schedule = p_schedule, backup_time = p_time, keep_last = p_keep
    WHERE id = 1;

    INSERT INTO activity_log (log_time, user_id, action, details)
    VALUES (NOW(), p_by, 'Settings Changed', CONCAT('Backup schedule: ', p_schedule));
END //

-- Session Security card
CREATE PROCEDURE sp_save_security_settings(IN p_timeout INT, IN p_by INT)
BEGIN
    UPDATE backup_settings
    SET session_timeout = p_timeout
    WHERE id = 1;

    INSERT INTO activity_log (log_time, user_id, action, details)
    VALUES (NOW(), p_by, 'Settings Changed', CONCAT('Session timeout: ', p_timeout, ' minutes'));
END //

-- the last 10 backups (newest first)
CREATE PROCEDURE sp_backup_history()
BEGIN
    SELECT backup_id, created_at, backup_type, size_bytes, status, file_name
    FROM backup_history
    ORDER BY created_at DESC, backup_id DESC
    LIMIT 10;
END //

-- save the result of a backup (Successful or Failed) and give back the new id
CREATE PROCEDURE sp_add_backup(IN p_type VARCHAR(10), IN p_size BIGINT, IN p_status VARCHAR(10),
                               IN p_file VARCHAR(100), IN p_by INT)
BEGIN
    INSERT INTO backup_history (created_at, backup_type, size_bytes, status, file_name, created_by)
    VALUES (NOW(), p_type, p_size, p_status, p_file, p_by);

    IF p_status = 'Successful' THEN
        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'Backup Created', CONCAT(p_type, ' backup: ', p_file));
    END IF;

    SELECT LAST_INSERT_ID() AS backup_id;
END //

CREATE PROCEDURE sp_get_backup(IN p_id INT)
BEGIN
    SELECT backup_id, created_at, backup_type, size_bytes, status, file_name
    FROM backup_history
    WHERE backup_id = p_id;
END //

CREATE PROCEDURE sp_delete_backup(IN p_id INT)
BEGIN
    DELETE FROM backup_history WHERE backup_id = p_id;
END //

-- backups older than the "keep last" number (the files can be deleted)
CREATE PROCEDURE sp_old_backups(IN p_keep INT)
BEGIN
    SELECT backup_id, file_name
    FROM backup_history
    ORDER BY created_at DESC, backup_id DESC
    LIMIT p_keep, 1000000;
END //

CREATE PROCEDURE sp_log_restore(IN p_by INT, IN p_name VARCHAR(100))
BEGIN
    INSERT INTO activity_log (log_time, user_id, action, details)
    VALUES (NOW(), p_by, 'Backup Restored', CONCAT('Database restored from ', p_name));
END //

DELIMITER ;
