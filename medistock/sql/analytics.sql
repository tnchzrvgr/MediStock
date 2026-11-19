-- Analytics page script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql and sales.sql.
-- (dashboard.sql makes the tables, sales.sql makes the v_sales_list view that this file uses)

DROP VIEW IF EXISTS v_medicine_movement;
DROP VIEW IF EXISTS v_medicine_sales;
DROP VIEW IF EXISTS v_stock_in_list;

DROP PROCEDURE IF EXISTS sp_analytics_kpis;
DROP PROCEDURE IF EXISTS sp_best_sellers;
DROP PROCEDURE IF EXISTS sp_weekly_demand;
DROP PROCEDURE IF EXISTS sp_weekly_sales;
DROP PROCEDURE IF EXISTS sp_category_sales;
DROP PROCEDURE IF EXISTS sp_category_trend;
DROP PROCEDURE IF EXISTS sp_stock_alerts;
DROP PROCEDURE IF EXISTS sp_period_comparison;
DROP PROCEDURE IF EXISTS sp_inventory_value;
DROP PROCEDURE IF EXISTS sp_movement_list;

-- ---------------------------------------------------------
-- VIEWS
-- ---------------------------------------------------------

-- every medicine sold in every sale (one row per item)
CREATE VIEW v_medicine_sales AS
SELECT
    si.item_id,
    s.sale_id,
    s.sale_date,
    m.medicine_id,
    m.medicine_name,
    c.category_name,
    si.quantity,
    m.unit_price,
    si.quantity * m.unit_price AS line_total
FROM sale_items si
JOIN sales s ON si.sale_id = s.sale_id
JOIN medicines m ON si.medicine_id = m.medicine_id
JOIN categories c ON m.category_id = c.category_id;

-- every stock-in with its total cost
CREATE VIEW v_stock_in_list AS
SELECT
    stock_in_id,
    medicine_id,
    date_received,
    quantity,
    quantity * unit_cost AS total_cost
FROM stock_in;

-- every medicine with how many units were sold in the last 90 days
-- 150 units or more = Fast-moving (about 50 per month), 1 to 149 = Slow-moving, 0 = Non-moving
CREATE VIEW v_medicine_movement AS
SELECT
    m.medicine_id,
    m.medicine_name,
    c.category_name,
    m.quantity,
    m.quantity * m.unit_price AS stock_value,
    IFNULL(SUM(vs.quantity), 0) AS units_90,
    CASE
        WHEN IFNULL(SUM(vs.quantity), 0) >= 150 THEN 'Fast-moving'
        WHEN IFNULL(SUM(vs.quantity), 0) > 0 THEN 'Slow-moving'
        ELSE 'Non-moving'
    END AS movement
FROM medicines m
JOIN categories c ON m.category_id = c.category_id
LEFT JOIN v_medicine_sales vs
    ON vs.medicine_id = m.medicine_id
    AND DATE(vs.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 89 DAY)
WHERE m.is_archived = 0
GROUP BY m.medicine_id, m.medicine_name, c.category_name, m.quantity, m.unit_price;

-- ---------------------------------------------------------
-- STORED PROCEDURES (analytics.php only calls these)
-- ---------------------------------------------------------
DELIMITER //

-- numbers for the cards on top
CREATE PROCEDURE sp_analytics_kpis()
BEGIN
    DECLARE month_start DATE;
    SET month_start = DATE_FORMAT(CURDATE(), '%Y-%m-01');

    SELECT
        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day BETWEEN month_start AND CURDATE()) AS month_sales,
        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day BETWEEN DATE_SUB(month_start, INTERVAL 1 MONTH)
            AND DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AS last_month_sales,
        -- medicines that will run out within 2 weeks at the speed they sold in the last 30 days
        (SELECT COUNT(*) FROM v_medicine_status m
            JOIN (SELECT medicine_id, SUM(quantity) AS sold FROM v_medicine_sales
                  WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                  GROUP BY medicine_id) t ON t.medicine_id = m.medicine_id
            WHERE m.quantity > 0 AND m.quantity < (t.sold / 30) * 14) AS running_out;
END //

-- top 10 best sellers of the last 30 days (units_prev = the 30 days before, to show the trend)
CREATE PROCEDURE sp_best_sellers()
BEGIN
    SELECT
        medicine_id,
        medicine_name,
        category_name,
        SUM(CASE WHEN DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            THEN quantity ELSE 0 END) AS units_now,
        SUM(CASE WHEN DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            THEN line_total ELSE 0 END) AS revenue_now,
        SUM(CASE WHEN DATE(sale_date) < DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            THEN quantity ELSE 0 END) AS units_prev
    FROM v_medicine_sales
    WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 59 DAY)
    GROUP BY medicine_id, medicine_name, category_name
    HAVING units_now > 0
    ORDER BY units_now DESC
    LIMIT 10;
END //

-- units sold per week of one medicine (last 8 weeks) for the demand forecast chart
-- weeks_ago = 0 is the last 7 days, 1 is the 7 days before that, and so on
CREATE PROCEDURE sp_weekly_demand(IN p_medicine_id INT)
BEGIN
    SELECT
        FLOOR(DATEDIFF(CURDATE(), DATE(sale_date)) / 7) AS weeks_ago,
        SUM(quantity) AS units
    FROM v_medicine_sales
    WHERE medicine_id = p_medicine_id
      AND DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 55 DAY)
    GROUP BY FLOOR(DATEDIFF(CURDATE(), DATE(sale_date)) / 7)
    ORDER BY weeks_ago DESC;
END //

-- total sales amount per week (last 8 weeks) for the "Expected Sales Next Week" card
CREATE PROCEDURE sp_weekly_sales()
BEGIN
    SELECT
        FLOOR(DATEDIFF(CURDATE(), sale_day) / 7) AS weeks_ago,
        SUM(daily_total) AS total
    FROM v_daily_sales
    WHERE sale_day >= DATE_SUB(CURDATE(), INTERVAL 55 DAY)
    GROUP BY FLOOR(DATEDIFF(CURDATE(), sale_day) / 7)
    ORDER BY weeks_ago DESC;
END //

-- "Sales by Category" (share of the sales amount in the last 30 days)
CREATE PROCEDURE sp_category_sales()
BEGIN
    SELECT
        category_name,
        SUM(line_total) AS total,
        ROUND(SUM(line_total) * 100 /
            (SELECT SUM(line_total) FROM v_medicine_sales
             WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY))) AS percent
    FROM v_medicine_sales
    WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY category_name
    ORDER BY total DESC;
END //

-- units sold per category: last 30 days and the 30 days before (to find rising demand)
CREATE PROCEDURE sp_category_trend()
BEGIN
    SELECT
        category_name,
        SUM(CASE WHEN DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            THEN quantity ELSE 0 END) AS units_now,
        SUM(CASE WHEN DATE(sale_date) < DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            THEN quantity ELSE 0 END) AS units_prev
    FROM v_medicine_sales
    WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 59 DAY)
    GROUP BY category_name;
END //

-- medicines that need attention (used for the insights)
CREATE PROCEDURE sp_stock_alerts()
BEGIN
    SELECT
        v.medicine_name,
        v.quantity,
        v.unit,
        v.unit_price,
        v.status,
        DATEDIFF(v.expiry_date, CURDATE()) AS days_to_expiry,
        (SELECT IFNULL(SUM(vs.quantity), 0) FROM v_medicine_sales vs
            WHERE vs.medicine_id = v.medicine_id
            AND DATE(vs.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sold_30
    FROM v_medicine_status v
    WHERE v.status IN ('Out of Stock', 'Low Stock', 'Expiring Soon')
    ORDER BY v.quantity ASC;
END //

-- "Period Comparison" table
-- p_period: 'daily' (today vs yesterday), 'weekly' (this week vs last week) or 'monthly' (this month vs last month)
-- the previous period only counts the same number of days, so the comparison is fair
CREATE PROCEDURE sp_period_comparison(IN p_period VARCHAR(10))
BEGIN
    DECLARE cur_start DATE;
    DECLARE cur_end DATE;
    DECLARE prev_start DATE;
    DECLARE prev_end DATE;

    SET cur_end = CURDATE();

    IF p_period = 'daily' THEN
        SET cur_start = CURDATE();
        SET prev_start = DATE_SUB(CURDATE(), INTERVAL 1 DAY);
        SET prev_end = prev_start;
    ELSEIF p_period = 'weekly' THEN
        SET cur_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);
        SET prev_start = DATE_SUB(cur_start, INTERVAL 7 DAY);
        SET prev_end = DATE_SUB(CURDATE(), INTERVAL 7 DAY);
    ELSE
        SET cur_start = DATE_FORMAT(CURDATE(), '%Y-%m-01');
        SET prev_start = DATE_SUB(cur_start, INTERVAL 1 MONTH);
        SET prev_end = DATE_SUB(CURDATE(), INTERVAL 1 MONTH);
    END IF;

    SELECT
        (SELECT IFNULL(SUM(total_amount), 0) FROM v_sales_list
            WHERE DATE(sale_date) BETWEEN cur_start AND cur_end) AS sales_now,
        (SELECT IFNULL(SUM(total_amount), 0) FROM v_sales_list
            WHERE DATE(sale_date) BETWEEN prev_start AND prev_end) AS sales_prev,
        (SELECT COUNT(*) FROM v_sales_list
            WHERE DATE(sale_date) BETWEEN cur_start AND cur_end) AS orders_now,
        (SELECT COUNT(*) FROM v_sales_list
            WHERE DATE(sale_date) BETWEEN prev_start AND prev_end) AS orders_prev,
        (SELECT IFNULL(SUM(quantity), 0) FROM v_medicine_sales
            WHERE DATE(sale_date) BETWEEN cur_start AND cur_end) AS units_now,
        (SELECT IFNULL(SUM(quantity), 0) FROM v_medicine_sales
            WHERE DATE(sale_date) BETWEEN prev_start AND prev_end) AS units_prev,
        (SELECT IFNULL(SUM(total_cost), 0) FROM v_stock_in_list
            WHERE date_received BETWEEN cur_start AND cur_end) AS stockin_now,
        (SELECT IFNULL(SUM(total_cost), 0) FROM v_stock_in_list
            WHERE date_received BETWEEN prev_start AND prev_end) AS stockin_prev;
END //

-- "Inventory Value Summary": how much money is in stock for each category
CREATE PROCEDURE sp_inventory_value()
BEGIN
    SELECT category_name, SUM(quantity * unit_price) AS total_value
    FROM v_medicine_status
    GROUP BY category_name
    ORDER BY total_value DESC;
END //

-- "Fast vs. Slow vs. Non-moving Medicines"
CREATE PROCEDURE sp_movement_list()
BEGIN
    SELECT medicine_name, category_name, quantity, stock_value, units_90, movement
    FROM v_medicine_movement
    ORDER BY units_90 DESC, stock_value DESC;
END //

DELIMITER ;
