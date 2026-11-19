-- Reports script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql, login.sql and new_sale.sql.

DROP VIEW IF EXISTS v_report_sales;
DROP VIEW IF EXISTS v_report_stock_in;
DROP PROCEDURE IF EXISTS sp_report_staff;
DROP PROCEDURE IF EXISTS sp_report_daily;
DROP PROCEDURE IF EXISTS sp_report_weekly;
DROP PROCEDURE IF EXISTS sp_report_monthly;
DROP PROCEDURE IF EXISTS sp_report_month_total;
DROP PROCEDURE IF EXISTS sp_report_inventory;
DROP PROCEDURE IF EXISTS sp_report_stock_levels;
DROP PROCEDURE IF EXISTS sp_report_stock_in;

-- every sale with the cashier name and the number of units sold
CREATE VIEW v_report_sales AS
SELECT
    s.sale_id,
    s.order_no,
    s.sale_date,
    DATE(s.sale_date) AS sale_day,
    IFNULL(s.cashier_id, 0) AS cashier_id,
    IFNULL(u.full_name, '—') AS staff_name,
    s.payment_method,
    s.total_amount,
    (SELECT IFNULL(SUM(si.quantity), 0) FROM sale_items si WHERE si.sale_id = s.sale_id) AS units_sold
FROM sales s
LEFT JOIN users u ON s.cashier_id = u.user_id;

-- every stock-in with the medicine and batch
CREATE VIEW v_report_stock_in AS
SELECT
    si.stock_in_id,
    si.date_received,
    m.medicine_name,
    si.batch_no,
    si.supplier_name,
    si.quantity,
    si.unit_cost
FROM stock_in si
JOIN medicines m ON si.medicine_id = m.medicine_id;

DELIMITER //

-- staff for the "Staff" drop-down
CREATE PROCEDURE sp_report_staff()
BEGIN
    SELECT user_id, full_name FROM v_user_list ORDER BY full_name ASC;
END //

-- Daily Sales Report: every sale of one day (p_staff = 0 means all staff)
CREATE PROCEDURE sp_report_daily(IN p_date DATE, IN p_staff INT)
BEGIN
    SELECT sale_date, order_no, units_sold, staff_name, payment_method, total_amount
    FROM v_report_sales
    WHERE sale_day = p_date AND (p_staff = 0 OR cashier_id = p_staff)
    ORDER BY sale_date ASC;
END //

-- Weekly Sales Report: the week (Monday to Sunday) of the date, one row per day
CREATE PROCEDURE sp_report_weekly(IN p_date DATE, IN p_staff INT)
BEGIN
    DECLARE week_start DATE;
    SET week_start = DATE_SUB(p_date, INTERVAL WEEKDAY(p_date) DAY);

    SELECT sale_day, COUNT(*) AS orders, SUM(units_sold) AS units, SUM(total_amount) AS total
    FROM v_report_sales
    WHERE sale_day BETWEEN week_start AND DATE_ADD(week_start, INTERVAL 6 DAY)
      AND (p_staff = 0 OR cashier_id = p_staff)
    GROUP BY sale_day
    ORDER BY sale_day ASC;
END //

-- Monthly Sales Report: the month of the date, one row per day
CREATE PROCEDURE sp_report_monthly(IN p_date DATE, IN p_staff INT)
BEGIN
    SELECT sale_day, COUNT(*) AS orders, SUM(units_sold) AS units, SUM(total_amount) AS total
    FROM v_report_sales
    WHERE sale_day >= DATE_FORMAT(p_date, '%Y-%m-01')
      AND sale_day <= LAST_DAY(p_date)
      AND (p_staff = 0 OR cashier_id = p_staff)
    GROUP BY sale_day
    ORDER BY sale_day ASC;
END //

-- total of the month before (to compare with the month of the report)
CREATE PROCEDURE sp_report_month_total(IN p_date DATE, IN p_staff INT)
BEGIN
    SELECT IFNULL(SUM(total_amount), 0) AS total
    FROM v_report_sales
    WHERE sale_day >= DATE_SUB(DATE_FORMAT(p_date, '%Y-%m-01'), INTERVAL 1 MONTH)
      AND sale_day < DATE_FORMAT(p_date, '%Y-%m-01')
      AND (p_staff = 0 OR cashier_id = p_staff);
END //

-- Current Inventory Report: all medicines with quantity, batch and value
CREATE PROCEDURE sp_report_inventory()
BEGIN
    SELECT medicine_name, category_name, batch_no, quantity, unit, unit_price, status
    FROM v_medicine_status
    ORDER BY medicine_name ASC;
END //

-- Low-Stock & Overstock Report: items below the minimum or above the maximum
CREATE PROCEDURE sp_report_stock_levels()
BEGIN
    SELECT medicine_name, category_name, quantity, unit, reorder_level, max_stock, status
    FROM v_medicine_status
    WHERE status IN ('Low Stock', 'Out of Stock', 'Overstock')
    ORDER BY status ASC, medicine_name ASC;
END //

-- Stock-In History Report: everything received during the month of the date
CREATE PROCEDURE sp_report_stock_in(IN p_date DATE)
BEGIN
    SELECT date_received, medicine_name, supplier_name, batch_no, quantity, unit_cost
    FROM v_report_stock_in
    WHERE date_received >= DATE_FORMAT(p_date, '%Y-%m-01')
      AND date_received <= LAST_DAY(p_date)
    ORDER BY date_received DESC;
END //

DELIMITER ;
