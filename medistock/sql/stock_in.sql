-- Stock-In script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql, login.sql and suppliers.sql.

-- who recorded each delivery (used by the Medicine details page). Never dropped, so the history is kept.
CREATE TABLE IF NOT EXISTS stock_in_by (
    stock_in_id INT PRIMARY KEY,
    user_id INT NULL
);

DROP VIEW IF EXISTS v_stock_in_history;
DROP PROCEDURE IF EXISTS sp_stock_in_medicines;
DROP PROCEDURE IF EXISTS sp_stock_in_suppliers;
DROP PROCEDURE IF EXISTS sp_recent_stock_in;
DROP PROCEDURE IF EXISTS sp_record_stock_in;

-- every stock-in with the medicine name
CREATE VIEW v_stock_in_history AS
SELECT
    si.stock_in_id,
    si.date_received,
    m.medicine_name,
    si.supplier_name,
    si.quantity,
    si.batch_no,
    si.expiry_date
FROM stock_in si
JOIN medicines m ON si.medicine_id = m.medicine_id;

DELIMITER //

-- medicines for the drop-down (archived ones are not in the view)
CREATE PROCEDURE sp_stock_in_medicines()
BEGIN
    SELECT medicine_id, medicine_name, quantity, unit, reorder_level, max_stock
    FROM v_medicine_status
    ORDER BY medicine_name ASC;
END //

-- suppliers for the drop-down (only Active ones, so On Hold and Inactive suppliers can't be chosen)
CREATE PROCEDURE sp_stock_in_suppliers()
BEGIN
    SELECT supplier_name FROM v_supplier_list WHERE status = 'Active' ORDER BY supplier_name ASC;
END //

CREATE PROCEDURE sp_recent_stock_in()
BEGIN
    SELECT date_received, medicine_name, supplier_name, quantity, batch_no, expiry_date
    FROM v_stock_in_history
    ORDER BY date_received DESC, stock_in_id DESC
    LIMIT 5;
END //

-- record a delivery: adds the quantity to the stock, saves the batch and expiry, and writes the activity log
-- (the unit cost is not in the form, so 80% of the selling price is used for the Analytics page)
CREATE PROCEDURE sp_record_stock_in(
    IN p_medicine_id INT,
    IN p_supplier VARCHAR(100),
    IN p_qty INT,
    IN p_batch VARCHAR(20),
    IN p_expiry DATE,
    IN p_received DATE,
    IN p_by INT
)
BEGIN
    DECLARE old_qty INT;
    DECLARE old_expiry DATE;
    DECLARE med_name VARCHAR(100);
    DECLARE price DECIMAL(10,2);

    SELECT quantity, expiry_date, medicine_name, unit_price
    INTO old_qty, old_expiry, med_name, price
    FROM medicines
    WHERE medicine_id = p_medicine_id AND is_archived = 0;

    IF med_name IS NULL THEN
        SELECT 'medicine not found' AS result, 0 AS new_quantity;
    ELSE
        INSERT INTO stock_in (medicine_id, quantity, unit_cost, date_received, supplier_name, batch_no, expiry_date)
        VALUES (p_medicine_id, p_qty, ROUND(price * 0.8, 2), p_received, p_supplier, p_batch, p_expiry);

        INSERT INTO stock_in_by (stock_in_id, user_id) VALUES (LAST_INSERT_ID(), p_by);

        -- the medicine keeps the newest batch number and the nearest expiry date of the stock it has
        UPDATE medicines
        SET quantity = quantity + p_qty,
            batch_no = p_batch,
            expiry_date = IF(old_qty > 0 AND old_expiry IS NOT NULL AND old_expiry < p_expiry, old_expiry, p_expiry)
        WHERE medicine_id = p_medicine_id;

        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'Stock Updated',
                CONCAT(med_name, ': ', old_qty, ' → ', old_qty + p_qty, ' units'));

        SELECT 'ok' AS result, old_qty + p_qty AS new_quantity;
    END IF;
END //

DELIMITER ;
