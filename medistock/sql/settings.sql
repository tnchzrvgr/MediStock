-- settings.sql : table and procedures for the Settings page
-- run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER login.sql
USE medistock;

DROP PROCEDURE IF EXISTS sp_get_settings;
DROP PROCEDURE IF EXISTS sp_save_settings;
DROP PROCEDURE IF EXISTS sp_reset_settings;
DROP TABLE IF EXISTS settings;

-- one row only (id = 1) with all the settings of the pharmacy
CREATE TABLE settings (
    id INT PRIMARY KEY,
    pharmacy_name VARCHAR(100) NOT NULL,
    address VARCHAR(200) NOT NULL,
    contact_number VARCHAR(30) NOT NULL,
    email VARCHAR(100) NOT NULL,
    receipt_header VARCHAR(100) NOT NULL,
    receipt_footer VARCHAR(150) NOT NULL,
    print_receipt TINYINT NOT NULL DEFAULT 1,
    default_min INT NOT NULL DEFAULT 50,
    default_max INT NOT NULL DEFAULT 500,
    expiry_days INT NOT NULL DEFAULT 30
);

INSERT INTO settings (id, pharmacy_name, address, contact_number, email, receipt_header, receipt_footer)
VALUES (1, 'MediStock Pharmacy', '123 Main Street, San Pedro, Laguna', '(02) 8123 4567', 'hello@medistock.ph',
        'MediStock Pharmacy · 123 Main Street', 'Thank you! Please keep this receipt.');

DELIMITER //

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
