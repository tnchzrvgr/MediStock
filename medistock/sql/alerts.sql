-- Alerts & Reorder script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql.

DROP VIEW IF EXISTS v_reorder_list;
DROP VIEW IF EXISTS v_reorder_base;
DROP VIEW IF EXISTS v_low_stock;
DROP VIEW IF EXISTS v_overstock;
DROP VIEW IF EXISTS v_near_expiry;
DROP PROCEDURE IF EXISTS sp_alert_kpis;
DROP PROCEDURE IF EXISTS sp_low_stock_alerts;
DROP PROCEDURE IF EXISTS sp_overstock_alerts;
DROP PROCEDURE IF EXISTS sp_reorder_suggestions;
DROP PROCEDURE IF EXISTS sp_near_expiry;

-- medicines at or below their minimum stock level
CREATE VIEW v_low_stock AS
SELECT medicine_id, medicine_name, quantity, unit, reorder_level
FROM medicines
WHERE is_archived = 0 AND quantity <= reorder_level;

-- medicines above their maximum stock level
CREATE VIEW v_overstock AS
SELECT medicine_id, medicine_name, quantity, unit, max_stock, (quantity - max_stock) AS excess
FROM medicines
WHERE is_archived = 0 AND quantity > max_stock;

-- batches that still have stock, with the days left before they expire
CREATE VIEW v_near_expiry AS
SELECT medicine_id, medicine_name, batch_no, expiry_date, quantity, unit,
       DATEDIFF(expiry_date, CURDATE()) AS days_left
FROM medicines
WHERE is_archived = 0 AND quantity > 0 AND expiry_date IS NOT NULL;

-- low-stock medicines with their average weekly sales (last 4 weeks) and the last supplier
CREATE VIEW v_reorder_base AS
SELECT
    m.medicine_id,
    m.medicine_name,
    m.quantity,
    m.unit,
    m.reorder_level,
    m.max_stock,
    ROUND(IFNULL((SELECT SUM(si.quantity)
                  FROM sale_items si
                  JOIN sales s ON si.sale_id = s.sale_id
                  WHERE si.medicine_id = m.medicine_id
                    AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 28 DAY)), 0) / 4) AS weekly_sales,
    IFNULL((SELECT st.supplier_name
            FROM stock_in st
            WHERE st.medicine_id = m.medicine_id AND st.supplier_name <> ''
            ORDER BY st.date_received DESC, st.stock_in_id DESC
            LIMIT 1), '—') AS supplier
FROM medicines m
WHERE m.is_archived = 0 AND m.quantity <= m.reorder_level;

-- suggested quantity = enough for 4 weeks of sales (or twice the minimum level), but never above the maximum level
CREATE VIEW v_reorder_list AS
SELECT
    medicine_id, medicine_name, quantity, unit, weekly_sales, supplier,
    LEAST(max_stock - quantity, GREATEST(weekly_sales * 4, reorder_level * 2) - quantity) AS suggested_qty
FROM v_reorder_base;

DELIMITER //

-- the 4 cards
CREATE PROCEDURE sp_alert_kpis()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM v_low_stock) AS low_count,
        (SELECT COUNT(*) FROM v_overstock) AS over_count,
        (SELECT COUNT(*) FROM v_reorder_list WHERE suggested_qty > 0) AS reorder_count,
        (SELECT COUNT(*) FROM v_near_expiry WHERE days_left BETWEEN 0 AND 30) AS expiring_count;
END //

-- the worst low-stock items first (Critical = less than half of the minimum level)
CREATE PROCEDURE sp_low_stock_alerts()
BEGIN
    SELECT medicine_name, quantity, reorder_level,
           CASE
               WHEN quantity = 0 THEN 'Out of Stock'
               WHEN quantity * 2 < reorder_level THEN 'Critical'
               ELSE 'Low'
           END AS alert_status
    FROM v_low_stock
    ORDER BY quantity / reorder_level ASC, medicine_name ASC
    LIMIT 5;
END //

CREATE PROCEDURE sp_overstock_alerts()
BEGIN
    SELECT medicine_name, quantity, max_stock, excess
    FROM v_overstock
    ORDER BY excess DESC
    LIMIT 5;
END //

CREATE PROCEDURE sp_reorder_suggestions()
BEGIN
    SELECT medicine_name, quantity, unit, weekly_sales, suggested_qty, supplier
    FROM v_reorder_list
    WHERE suggested_qty > 0
    ORDER BY quantity / GREATEST(weekly_sales, 1) ASC
    LIMIT 5;
END //

-- batches that expire in the next p_days days (the soonest first)
CREATE PROCEDURE sp_near_expiry(IN p_days INT)
BEGIN
    SELECT medicine_name, batch_no, expiry_date, days_left, quantity, unit
    FROM v_near_expiry
    WHERE days_left <= p_days
    ORDER BY days_left ASC
    LIMIT 8;
END //

DELIMITER ;
