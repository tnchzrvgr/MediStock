-- Sales page script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql.
-- (dashboard.sql already makes the sales tables and the v_daily_sales view)

DROP VIEW IF EXISTS v_sales_list;

DROP PROCEDURE IF EXISTS sp_sales_kpis;
DROP PROCEDURE IF EXISTS sp_sales_this_week;
DROP PROCEDURE IF EXISTS sp_payment_methods;
DROP PROCEDURE IF EXISTS sp_recent_transactions;

-- every sale with how many items it has
CREATE VIEW v_sales_list AS
SELECT
    s.sale_id,
    s.order_no,
    s.sale_date,
    s.customer_name,
    s.payment_method,
    s.total_amount,
    COUNT(si.item_id) AS item_count
FROM sales s
LEFT JOIN sale_items si ON s.sale_id = si.sale_id
GROUP BY s.sale_id, s.order_no, s.sale_date, s.customer_name, s.payment_method, s.total_amount;

DELIMITER //

-- numbers for the 4 cards (week and month are compared with the period before it)
CREATE PROCEDURE sp_sales_kpis()
BEGIN
    DECLARE week_start DATE;
    DECLARE month_start DATE;

    -- the week starts on Monday
    SET week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);
    SET month_start = DATE_FORMAT(CURDATE(), '%Y-%m-01');

    SELECT
        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day = CURDATE()) AS today_sales,
        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day = DATE_SUB(CURDATE(), INTERVAL 1 DAY)) AS yesterday_sales,

        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day BETWEEN week_start AND CURDATE()) AS week_sales,
        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day BETWEEN DATE_SUB(week_start, INTERVAL 7 DAY)
            AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS last_week_sales,

        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day BETWEEN month_start AND CURDATE()) AS month_sales,
        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day BETWEEN DATE_SUB(month_start, INTERVAL 1 MONTH)
            AND DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AS last_month_sales,

        -- customers (sales that were not voided) and items sold today and yesterday
        (SELECT COUNT(*) FROM sales
            WHERE DATE(sale_date) = CURDATE() AND total_amount > 0) AS transactions_today,
        (SELECT IFNULL(SUM(si.quantity), 0) FROM sale_items si
            JOIN sales s ON s.sale_id = si.sale_id
            WHERE DATE(s.sale_date) = CURDATE() AND s.total_amount > 0) AS units_today,
        (SELECT IFNULL(SUM(si.quantity), 0) FROM sale_items si
            JOIN sales s ON s.sale_id = si.sale_id
            WHERE DATE(s.sale_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND s.total_amount > 0) AS units_yesterday;
END //

-- "Sales by Day (This Week)" bar chart
CREATE PROCEDURE sp_sales_this_week()
BEGIN
    SELECT sale_day, daily_total
    FROM v_daily_sales
    WHERE sale_day >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    ORDER BY sale_day ASC;
END //

-- "Payment Methods" list (share of the sales in the last 30 days)
CREATE PROCEDURE sp_payment_methods()
BEGIN
    SELECT
        payment_method,
        SUM(total_amount) AS total,
        ROUND(SUM(total_amount) * 100 /
            (SELECT SUM(total_amount) FROM v_sales_list
             WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY))) AS percent
    FROM v_sales_list
    WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY payment_method
    ORDER BY total DESC;
END //

-- "Recent Transactions" table
CREATE PROCEDURE sp_recent_transactions()
BEGIN
    SELECT order_no, sale_date, item_count, customer_name, payment_method, total_amount
    FROM v_sales_list
    ORDER BY sale_date DESC, sale_id DESC
    LIMIT 5;
END //

DELIMITER ;
