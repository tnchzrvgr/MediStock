-- Cleanup patch for a database that is already set up
-- Run in phpMyAdmin (SQL tab) on the "medistock" database. Your data is kept.
-- It removes the switches that did nothing (four security rules and three alert switches).
USE medistock;

DROP PROCEDURE IF EXISTS sp_backup_settings;
DROP PROCEDURE IF EXISTS sp_save_security_settings;
DROP PROCEDURE IF EXISTS sp_get_settings;
DROP PROCEDURE IF EXISTS sp_save_settings;
DROP PROCEDURE IF EXISTS sp_reset_settings;

ALTER TABLE backup_settings
    DROP COLUMN duplicate_check,
    DROP COLUMN input_validation,
    DROP COLUMN require_reason,
    DROP COLUMN admin_void;

ALTER TABLE settings
    DROP COLUMN alert_low,
    DROP COLUMN alert_over,
    DROP COLUMN alert_expiry;

DELIMITER //

CREATE PROCEDURE sp_backup_settings()
BEGIN
    SELECT schedule, backup_time, keep_last, session_timeout
    FROM backup_settings
    WHERE id = 1;
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

CREATE PROCEDURE sp_get_settings()
BEGIN
    SELECT pharmacy_name, address, contact_number, email, receipt_header, receipt_footer, print_receipt,
           default_min, default_max, expiry_days
    FROM settings
    WHERE id = 1;
END //

CREATE PROCEDURE sp_save_settings(
    IN p_name VARCHAR(100), IN p_address VARCHAR(200), IN p_contact VARCHAR(30), IN p_email VARCHAR(100),
    IN p_header VARCHAR(100), IN p_footer VARCHAR(150), IN p_print TINYINT,
    IN p_min INT, IN p_max INT, IN p_expiry INT,
    IN p_by INT)
BEGIN
    UPDATE settings
    SET pharmacy_name = p_name, address = p_address, contact_number = p_contact, email = p_email,
        receipt_header = p_header, receipt_footer = p_footer, print_receipt = p_print,
        default_min = p_min, default_max = p_max, expiry_days = p_expiry
    WHERE id = 1;

    INSERT INTO activity_log (log_time, user_id, action, details)
    VALUES (NOW(), p_by, 'Settings Changed', 'Pharmacy settings updated');
END //

-- put the original values back
CREATE PROCEDURE sp_reset_settings(IN p_by INT)
BEGIN
    UPDATE settings
    SET pharmacy_name = 'MediStock Pharmacy',
        address = '123 Main Street, San Pedro, Laguna',
        contact_number = '(02) 8123 4567',
        email = 'hello@medistock.ph',
        receipt_header = 'MediStock Pharmacy · 123 Main Street',
        receipt_footer = 'Thank you! Please keep this receipt.',
        print_receipt = 1, default_min = 50, default_max = 500, expiry_days = 30
    WHERE id = 1;

    INSERT INTO activity_log (log_time, user_id, action, details)
    VALUES (NOW(), p_by, 'Settings Changed', 'Settings reset to defaults');
END //

DELIMITER ;
