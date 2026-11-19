-- Remove purchase orders - patch for a database that is already set up
-- Run in phpMyAdmin (SQL tab) on the "medistock" database. Your suppliers and stock-in data are kept.
-- It deletes the purchase_orders table and the views/procedures that used it.
USE medistock;

DROP VIEW IF EXISTS v_pending_orders;
DROP VIEW IF EXISTS v_supplier_list;
DROP PROCEDURE IF EXISTS sp_pending_orders;
DROP PROCEDURE IF EXISTS sp_supplier_orders;
DROP PROCEDURE IF EXISTS sp_supplier_kpis;
DROP PROCEDURE IF EXISTS sp_supplier_list;
DROP PROCEDURE IF EXISTS sp_get_supplier;
DROP PROCEDURE IF EXISTS sp_supplier_detail;
DROP PROCEDURE IF EXISTS sp_stock_in_suppliers;
DROP TABLE IF EXISTS purchase_orders;

-- "Delivery Delayed" came from the purchase orders, so it is not used anymore
UPDATE suppliers SET status = 'Active' WHERE status = 'Delivery Delayed';

-- the supplier list (the status is the one the staff saved)
CREATE VIEW v_supplier_list AS
SELECT
    supplier_id,
    supplier_name,
    contact_person,
    products_supplied,
    phone,
    email,
    address,
    payment_terms,
    lead_time_days,
    notes,
    status,
    date_added
FROM suppliers;

DELIMITER //

-- the 4 cards
CREATE PROCEDURE sp_supplier_kpis()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM v_supplier_list) AS total_suppliers,
        (SELECT COUNT(*) FROM v_supplier_list
         WHERE date_added >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS new_this_month,
        (SELECT COUNT(*) FROM v_supplier_list WHERE status = 'Active') AS active_suppliers,
        (SELECT COUNT(*) FROM v_supplier_list WHERE status = 'On Hold') AS on_hold_suppliers,
        (SELECT COUNT(*) FROM v_supplier_list WHERE status = 'Inactive') AS inactive_suppliers;
END //

CREATE PROCEDURE sp_supplier_list()
BEGIN
    SELECT supplier_id, supplier_name, contact_person, products_supplied, date_added, status
    FROM v_supplier_list
    ORDER BY supplier_name ASC;
END //

-- one supplier (used to fill the form when editing)
CREATE PROCEDURE sp_get_supplier(IN p_id INT)
BEGIN
    SELECT supplier_id, supplier_name, contact_person, phone, email, address, products_supplied,
           payment_terms, lead_time_days, notes, status
    FROM v_supplier_list
    WHERE supplier_id = p_id;
END //

-- one supplier
CREATE PROCEDURE sp_supplier_detail(IN p_id INT)
BEGIN
    SELECT supplier_id, supplier_name, contact_person, phone, email, address, products_supplied,
           payment_terms, lead_time_days, notes, status, date_added
    FROM v_supplier_list
    WHERE supplier_id = p_id;
END //

-- suppliers for the drop-down (only Active ones, so On Hold and Inactive suppliers can't be chosen)
CREATE PROCEDURE sp_stock_in_suppliers()
BEGIN
    SELECT supplier_name FROM v_supplier_list WHERE status = 'Active' ORDER BY supplier_name ASC;
END //

DELIMITER ;
