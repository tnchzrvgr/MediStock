-- Inventory page script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql.
-- (dashboard.sql already makes the medicines table and the v_medicine_status view)

DROP PROCEDURE IF EXISTS sp_inventory_kpis;
DROP PROCEDURE IF EXISTS sp_inventory_list;

DELIMITER //

-- numbers for the 4 cards on top of the Inventory page
CREATE PROCEDURE sp_inventory_kpis()
BEGIN
    SELECT
        COUNT(*) AS total_skus,
        IFNULL(SUM(date_added >= DATE_FORMAT(CURDATE(), '%Y-%m-01')), 0) AS added_this_month,
        IFNULL(SUM(quantity > reorder_level), 0) AS in_stock,
        IFNULL(SUM(quantity > 0 AND quantity <= reorder_level), 0) AS low_stock,
        IFNULL(SUM(quantity = 0), 0) AS out_of_stock
    FROM v_medicine_status;
END //

-- the medicine table
-- p_filter: 'all', 'low', 'expiring' or 'out'
-- p_search: text to look for in the medicine name, category or batch no. ('' = show everything)
CREATE PROCEDURE sp_inventory_list(IN p_filter VARCHAR(20), IN p_search VARCHAR(100))
BEGIN
    SELECT
        medicine_id,
        medicine_name,
        category_name,
        batch_no,
        quantity,
        unit,
        unit_price,
        expiry_date,
        status
    FROM v_medicine_status
    WHERE (
            p_filter = 'all'
            OR (p_filter = 'low' AND status = 'Low Stock')
            OR (p_filter = 'expiring' AND status = 'Expiring Soon')
            OR (p_filter = 'out' AND status = 'Out of Stock')
          )
      AND (
            medicine_name LIKE CONCAT('%', p_search, '%')
            OR category_name LIKE CONCAT('%', p_search, '%')
            OR batch_no LIKE CONCAT('%', p_search, '%')
          )
    ORDER BY medicine_name ASC;
END //

DELIMITER ;
