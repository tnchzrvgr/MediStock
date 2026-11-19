-- Add / Edit Medicine script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql.

DROP VIEW IF EXISTS v_category_list;
DROP VIEW IF EXISTS v_medicine_details;

DROP PROCEDURE IF EXISTS sp_list_categories;
DROP PROCEDURE IF EXISTS sp_get_medicine;
DROP PROCEDURE IF EXISTS sp_add_medicine;
DROP PROCEDURE IF EXISTS sp_update_medicine;
DROP PROCEDURE IF EXISTS sp_archive_medicine;

-- categories for the drop-down list
CREATE VIEW v_category_list AS
SELECT category_id, category_name
FROM categories;

-- the fields of the medicine form
CREATE VIEW v_medicine_details AS
SELECT
    medicine_id,
    medicine_name,
    generic_name,
    category_id,
    unit,
    unit_price,
    quantity,
    reorder_level,
    max_stock,
    is_archived
FROM medicines;

DELIMITER //

CREATE PROCEDURE sp_list_categories()
BEGIN
    SELECT category_id, category_name
    FROM v_category_list
    ORDER BY category_name ASC;
END //

-- one medicine (used to fill the form when editing)
CREATE PROCEDURE sp_get_medicine(IN p_id INT)
BEGIN
    SELECT medicine_id, medicine_name, generic_name, category_id, unit,
           unit_price, quantity, reorder_level, max_stock
    FROM v_medicine_details
    WHERE medicine_id = p_id AND is_archived = 0;
END //

-- add a new medicine
-- gives back 'duplicate' if a medicine with the same name, generic name and unit already exists
CREATE PROCEDURE sp_add_medicine(
    IN p_name VARCHAR(100),
    IN p_generic VARCHAR(100),
    IN p_category_id INT,
    IN p_unit VARCHAR(20),
    IN p_price DECIMAL(10,2),
    IN p_qty INT,
    IN p_min INT,
    IN p_max INT
)
BEGIN
    DECLARE new_id INT;

    IF EXISTS (SELECT 1 FROM medicines
               WHERE medicine_name = p_name AND generic_name = p_generic AND unit = p_unit) THEN
        SELECT 'duplicate' AS result, 0 AS medicine_id;
    ELSE
        INSERT INTO medicines
            (medicine_name, generic_name, category_id, unit, unit_price, quantity,
             reorder_level, max_stock, expiry_date, date_added)
        VALUES
            (p_name, p_generic, p_category_id, p_unit, p_price, p_qty,
             p_min, p_max, NULL, CURDATE());

        SET new_id = LAST_INSERT_ID();

        -- batch number like the other medicines (BN-2201, BN-2202, ...)
        UPDATE medicines SET batch_no = CONCAT('BN-', 2200 + new_id) WHERE medicine_id = new_id;

        SELECT 'ok' AS result, new_id AS medicine_id;
    END IF;
END //

-- save the changes of an existing medicine (same duplicate check, but it ignores the medicine itself)
CREATE PROCEDURE sp_update_medicine(
    IN p_id INT,
    IN p_name VARCHAR(100),
    IN p_generic VARCHAR(100),
    IN p_category_id INT,
    IN p_unit VARCHAR(20),
    IN p_price DECIMAL(10,2),
    IN p_qty INT,
    IN p_min INT,
    IN p_max INT
)
BEGIN
    IF EXISTS (SELECT 1 FROM medicines
               WHERE medicine_name = p_name AND generic_name = p_generic AND unit = p_unit
               AND medicine_id <> p_id) THEN
        SELECT 'duplicate' AS result, p_id AS medicine_id;
    ELSE
        UPDATE medicines
        SET medicine_name = p_name,
            generic_name = p_generic,
            category_id = p_category_id,
            unit = p_unit,
            unit_price = p_price,
            quantity = p_qty,
            reorder_level = p_min,
            max_stock = p_max
        WHERE medicine_id = p_id;

        SELECT 'ok' AS result, p_id AS medicine_id;
    END IF;
END //

-- archive = hide the medicine from the lists, but keep all its history
CREATE PROCEDURE sp_archive_medicine(IN p_id INT)
BEGIN
    UPDATE medicines SET is_archived = 1 WHERE medicine_id = p_id;
END //

DELIMITER ;
