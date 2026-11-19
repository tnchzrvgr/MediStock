-- detail_pages.sql : views and procedures for the Medicine details page and the Supplier details page
-- run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER these scripts:
-- dashboard.sql, login.sql, suppliers.sql, stock_in.sql, stock_adjust.sql, receipt.sql and transactions.sql
USE medistock;

DROP VIEW IF EXISTS v_medicine_detail;
DROP VIEW IF EXISTS v_stock_movements;
DROP PROCEDURE IF EXISTS sp_medicine_detail;
DROP PROCEDURE IF EXISTS sp_medicine_batches;
DROP PROCEDURE IF EXISTS sp_medicine_movements;
DROP PROCEDURE IF EXISTS sp_supplier_detail;
DROP PROCEDURE IF EXISTS sp_supplier_deliveries;

-- one medicine with its category and the date of its last stock-in
CREATE VIEW v_medicine_detail AS
SELECT
    m.medicine_id,
    m.medicine_name,
    m.generic_name,
    c.category_name,
    m.unit,
    m.unit_price,
    m.quantity,
    m.reorder_level,
    m.max_stock,
    m.expiry_date,
    m.batch_no,
    m.date_added,
    m.is_archived,
    (SELECT MAX(si.date_received) FROM stock_in si WHERE si.medicine_id = m.medicine_id) AS last_stock_in
FROM medicines m
JOIN categories c ON c.category_id = m.category_id;

-- everything that changed the stock of a medicine: sales, voids, returns, stock-ins and adjustments
CREATE VIEW v_stock_movements AS
SELECT si.medicine_id, s.sale_date AS move_time, CONCAT('Sale ', s.order_no) AS move_type,
       -si.quantity AS qty_change, IFNULL(u.full_name, '—') AS by_name
FROM sale_items si
JOIN sales s ON s.sale_id = si.sale_id
LEFT JOIN users u ON u.user_id = s.cashier_id
UNION ALL
SELECT si.medicine_id, ss.changed_at, CONCAT('Void ', s.order_no),
       si.quantity, IFNULL(u.full_name, '—')
FROM sale_items si
JOIN sale_status ss ON ss.sale_id = si.sale_id AND ss.status = 'Voided'
JOIN sales s ON s.sale_id = si.sale_id
LEFT JOIN users u ON u.user_id = ss.changed_by
UNION ALL
SELECT r.medicine_id, r.returned_at, CONCAT('Return ', s.order_no),
       r.quantity, IFNULL(u.full_name, '—')
FROM sale_returns r
JOIN sales s ON s.sale_id = r.sale_id
LEFT JOIN sale_status ss ON ss.sale_id = r.sale_id
LEFT JOIN users u ON u.user_id = ss.changed_by
UNION ALL
SELECT si.medicine_id, CAST(si.date_received AS DATETIME), CONCAT('Stock-In ', si.batch_no),
       si.quantity, IFNULL(u.full_name, '—')
FROM stock_in si
LEFT JOIN stock_in_by b ON b.stock_in_id = si.stock_in_id
LEFT JOIN users u ON u.user_id = b.user_id
UNION ALL
SELECT a.medicine_id, a.adjust_time, CONCAT('Adjustment (', a.reason, ')'),
       a.change_qty, IFNULL(u.full_name, '—')
FROM stock_adjustments a
LEFT JOIN users u ON u.user_id = a.user_id
WHERE a.status = 'Approved';

DELIMITER //

CREATE PROCEDURE sp_medicine_detail(IN p_id INT)
BEGIN
    SELECT medicine_id, medicine_name, generic_name, category_name, unit, unit_price, quantity,
           reorder_level, max_stock, expiry_date, batch_no, date_added, is_archived, last_stock_in
    FROM v_medicine_detail
    WHERE medicine_id = p_id;
END //

-- the deliveries (batches) of a medicine, newest first
CREATE PROCEDURE sp_medicine_batches(IN p_id INT)
BEGIN
    SELECT batch_no, date_received, expiry_date, quantity
    FROM stock_in
    WHERE medicine_id = p_id
    ORDER BY date_received DESC, stock_in_id DESC;
END //

-- the last 10 stock movements of a medicine
CREATE PROCEDURE sp_medicine_movements(IN p_id INT)
BEGIN
    SELECT move_time, move_type, qty_change, by_name
    FROM v_stock_movements
    WHERE medicine_id = p_id
    ORDER BY move_time DESC
    LIMIT 10;
END //

-- one supplier
CREATE PROCEDURE sp_supplier_detail(IN p_id INT)
BEGIN
    SELECT supplier_id, supplier_name, contact_person, phone, email, address, products_supplied,
           payment_terms, lead_time_days, notes, status, date_added
    FROM v_supplier_list
    WHERE supplier_id = p_id;
END //

-- the last 5 deliveries recorded in Stock-In with this supplier name
CREATE PROCEDURE sp_supplier_deliveries(IN p_name VARCHAR(100))
BEGIN
    SELECT date_received, medicine_name, batch_no, quantity
    FROM v_stock_in_history
    WHERE supplier_name = p_name
    ORDER BY date_received DESC, stock_in_id DESC
    LIMIT 5;
END //

DELIMITER ;
