-- Dashboard database script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER login.sql.
-- You can run it again if you want to reset the sample data.

-- ---------------------------------------------------------
-- 1. TABLES
-- ---------------------------------------------------------
DROP VIEW IF EXISTS v_medicine_status;
DROP VIEW IF EXISTS v_recent_sales;
DROP VIEW IF EXISTS v_daily_sales;
DROP VIEW IF EXISTS v_sales_last_30_days;
DROP VIEW IF EXISTS v_weekly_demand;

DROP PROCEDURE IF EXISTS sp_dashboard_kpis;
DROP PROCEDURE IF EXISTS sp_low_stock_expiring;
DROP PROCEDURE IF EXISTS sp_recent_sales;
DROP PROCEDURE IF EXISTS sp_sales_trend;
DROP PROCEDURE IF EXISTS sp_top_demand;

DROP TABLE IF EXISTS stock_in;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS medicines;
DROP TABLE IF EXISTS categories;

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL
);

CREATE TABLE medicines (
    medicine_id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL DEFAULT 'units',
    reorder_level INT NOT NULL DEFAULT 20,
    max_stock INT NOT NULL DEFAULT 200,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    expiry_date DATE NULL,
    batch_no VARCHAR(20) NOT NULL DEFAULT '',
    generic_name VARCHAR(100) NOT NULL DEFAULT '',
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    date_added DATE NULL,
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

CREATE TABLE sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(20) NOT NULL,
    customer_name VARCHAR(100) NOT NULL DEFAULT 'Walk-in Customer',
    total_amount DECIMAL(10,2) NOT NULL,
    sale_date DATETIME NOT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'Cash',
    cashier_id INT NULL,
    amount_received DECIMAL(10,2) NULL
);

CREATE TABLE sale_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (sale_id) REFERENCES sales(sale_id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id)
);

-- medicines received from suppliers (used by the Analytics page for "Stock-In Cost")
CREATE TABLE stock_in (
    stock_in_id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    date_received DATE NOT NULL,
    supplier_name VARCHAR(100) NOT NULL DEFAULT '',
    batch_no VARCHAR(20) NOT NULL DEFAULT '',
    expiry_date DATE NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id)
);

-- ---------------------------------------------------------
-- 2. SAMPLE DATA (dates are based on today so the charts always have data)
-- ---------------------------------------------------------
INSERT INTO categories (category_name) VALUES
('Analgesic'),
('Antibiotic'),
('Antihistamine'),
('Cardiovascular'),
('Diabetes'),
('Respiratory'),
('Gastrointestinal'),
('Vitamins & Supplements'),
('Anti-inflammatory');

INSERT INTO medicines (medicine_name, category_id, quantity, unit, reorder_level, max_stock, unit_price, expiry_date) VALUES
('Paracetamol 500mg',   1, 240, 'units', 50, 400,   2.50, DATE_ADD(CURDATE(), INTERVAL 400 DAY)),
('Amoxicillin 500mg',   2,  12, 'units', 30, 200,  12.00, DATE_ADD(CURDATE(), INTERVAL 300 DAY)),
('Cetirizine 10mg',     3,   0, 'units', 20, 150,   6.00, DATE_ADD(CURDATE(), INTERVAL 250 DAY)),
('Losartan 50mg',       4,  18, 'units', 25, 200,  15.00, DATE_ADD(CURDATE(), INTERVAL 500 DAY)),
('Metformin 850mg',     5,  20, 'units', 15, 150,   8.50, DATE_ADD(CURDATE(), INTERVAL 45 DAY)),
('Ibuprofen 200mg',     9, 160, 'units', 40, 300,   5.00, DATE_ADD(CURDATE(), INTERVAL 350 DAY)),
('Insulin Glargine',    5,   8, 'vials',  5,  40, 950.00, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
('Salbutamol Inhaler',  6,  15, 'units', 20,  80, 280.00, DATE_ADD(CURDATE(), INTERVAL 200 DAY)),
('Omeprazole 20mg',     7, 320, 'units', 60, 300,   9.00, DATE_ADD(CURDATE(), INTERVAL 420 DAY)),
('Vitamin C 500mg',     8,  10, 'units', 40, 400,   4.00, DATE_ADD(CURDATE(), INTERVAL 600 DAY)),
('Amlodipine 5mg',      4,  90, 'units', 30, 200,  11.00, DATE_ADD(CURDATE(), INTERVAL 380 DAY)),
('Mefenamic Acid 500mg',1, 450, 'units', 50, 400,   7.00, DATE_ADD(CURDATE(), INTERVAL 330 DAY)),
('Loperamide 2mg',      7,  60, 'units', 20, 100,   6.50, DATE_ADD(CURDATE(), INTERVAL 280 DAY)),
('Aspirin 80mg',        4, 500, 'units', 60, 400,   3.00, DATE_ADD(CURDATE(), INTERVAL 450 DAY));

-- batch numbers (BN-2201, BN-2202, ...) and the date each medicine was added (used by the Inventory page)
UPDATE medicines
SET batch_no = CONCAT('BN-', 2200 + medicine_id),
    date_added = DATE_SUB(CURDATE(), INTERVAL (medicine_id * 3) DAY);

-- generic name = the first word of the medicine name (example: Amoxicillin 500mg -> Amoxicillin)
UPDATE medicines
SET generic_name = SUBSTRING_INDEX(medicine_name, ' ', 1);

-- the sale_id numbers below follow the order of this INSERT (1 to 29)
INSERT INTO sales (order_no, customer_name, total_amount, sale_date) VALUES
('#OR-1019', 'Walk-in Customer', 780.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 29 DAY), '09:15:00')),
('#OR-1020', 'Maria Santos',     1250.50, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 27 DAY), '10:20:00')),
('#OR-1021', 'Juan Dela Cruz',   430.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 26 DAY), '13:05:00')),
('#OR-1022', 'Walk-in Customer', 960.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 24 DAY), '11:45:00')),
('#OR-1023', 'Pedro Garcia',     1840.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 22 DAY), '14:30:00')),
('#OR-1024', 'Walk-in Customer', 520.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 21 DAY), '09:50:00')),
('#OR-1025', 'Ana Lim',          1320.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 19 DAY), '16:10:00')),
('#OR-1026', 'Walk-in Customer', 640.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 18 DAY), '10:00:00')),
('#OR-1027', 'Rosa Mendoza',     2100.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 16 DAY), '15:25:00')),
('#OR-1028', 'Walk-in Customer', 880.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '12:40:00')),
('#OR-1029', 'Carlo Reyes',      1475.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:35:00')),
('#OR-1030', 'Walk-in Customer', 590.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '17:00:00')),
('#OR-1031', 'Maria Santos',     1960.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '11:15:00')),
('#OR-1032', 'Walk-in Customer', 710.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '13:50:00')),
('#OR-1033', 'Juan Dela Cruz',   2250.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '10:30:00')),
('#OR-1034', 'Walk-in Customer', 830.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY),  '14:05:00')),
('#OR-1035', 'Liza Tan',         1690.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY),  '16:45:00')),
('#OR-1036', 'Walk-in Customer', 920.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY),  '09:20:00')),
('#OR-1037', 'Pedro Garcia',     1540.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY),  '12:15:00')),
('#OR-1038', 'Maria Santos',     1080.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY),  '15:00:00')),
('#OR-1039', 'Walk-in Customer', 875.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY),  '10:05:00')),
('#OR-1040', 'Maria Santos',     320.00,  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY),  '15:40:00')),
('#OR-1041', 'Juan Dela Cruz',   1280.00, TIMESTAMP(CURDATE(), '08:30:00')),
('#OR-1042', 'Walk-in Customer', 540.00,  TIMESTAMP(CURDATE(), '09:10:00')),
-- older sales (31 to 60 days ago) used to compare with the last 30 days
('#OR-1011', 'Rosa Mendoza',     5200.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 31 DAY), '10:00:00')),
('#OR-1012', 'Carlo Reyes',      4800.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 36 DAY), '11:00:00')),
('#OR-1013', 'Ana Lim',          5600.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 41 DAY), '14:00:00')),
('#OR-1014', 'Liza Tan',         4700.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 47 DAY), '09:30:00')),
('#OR-1015', 'Pedro Garcia',     5600.00, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 55 DAY), '13:15:00'));

-- payment method of each sale (Cash or GCash) used by the Sales page
UPDATE sales
SET payment_method = ELT(MOD(sale_id, 10) + 1, 'Cash', 'GCash', 'Cash', 'GCash', 'Cash', 'GCash', 'Cash', 'Cash', 'GCash', 'GCash');

-- items in each sale (sales 18 to 24 are from the last 7 days, so they feed the "Top Medicine Demand" chart)
INSERT INTO sale_items (sale_id, medicine_id, quantity) VALUES
(18, 1, 40),
(19, 1, 30),
(18, 2, 60),
(20, 2, 40),
(19, 3, 55),
(20, 4, 50),
(21, 4, 35),
(21, 5, 40),
(22, 6, 30),
(23, 6, 30),
(24, 1, 4),
(24, 9, 3),
(24, 10, 2),
-- older sales (more than 7 days ago)
(1, 5, 3),
(1, 2, 3),
(2, 4, 10),
(2, 6, 6),
(2, 9, 4),
(3, 10, 5),
(4, 1, 8),
(4, 11, 4),
(5, 2, 12),
(5, 7, 1),
(5, 8, 2),
(6, 13, 6),
(7, 4, 10),
(7, 13, 10),
(8, 1, 6),
(9, 2, 10),
(9, 5, 8),
(9, 8, 2),
(9, 9, 6),
(10, 10, 10),
(10, 1, 5),
(11, 11, 10),
(11, 6, 8),
(12, 13, 10),
(13, 4, 12),
(13, 11, 8),
(13, 2, 5),
(14, 1, 10),
(14, 13, 4),
(15, 5, 12),
(15, 9, 10),
(15, 7, 2),
(16, 6, 6),
(17, 2, 8),
(17, 3, 6),
(17, 11, 10),
(25, 2, 20),
(25, 5, 10),
(26, 4, 15),
(26, 1, 30),
(27, 6, 20),
(27, 9, 20),
(27, 13, 10),
(28, 2, 25),
(28, 11, 20),
(29, 1, 40),
(29, 13, 20),
(29, 10, 10);

-- the price of each item when it was sold (the sample sales use the current price)
UPDATE sale_items si
JOIN medicines m ON si.medicine_id = m.medicine_id
SET si.unit_price = m.unit_price;

-- stock-in records (medicine_id, quantity, unit_cost, date_received)
-- Mefenamic Acid (12) and Aspirin (14) have no sales on purpose, so they show as "non-moving" in Analytics
INSERT INTO stock_in (medicine_id, quantity, unit_cost, date_received) VALUES
(1, 200, 1.80, DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(2, 100, 9.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(4, 80, 11.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY)),
(9, 150, 7.00, DATE_SUB(CURDATE(), INTERVAL 12 DAY)),
(11, 300, 8.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY)),
(6, 200, 3.80, DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
(5, 120, 6.50, DATE_SUB(CURDATE(), INTERVAL 26 DAY)),
(8, 20, 210.00, DATE_SUB(CURDATE(), INTERVAL 33 DAY)),
(13, 50, 5.00, DATE_SUB(CURDATE(), INTERVAL 38 DAY)),
(10, 200, 3.00, DATE_SUB(CURDATE(), INTERVAL 45 DAY)),
(7, 10, 700.00, DATE_SUB(CURDATE(), INTERVAL 52 DAY)),
(1, 250, 1.90, DATE_SUB(CURDATE(), INTERVAL 58 DAY));

-- the supplier of each stock-in (sample suppliers, used by the Reports page)
UPDATE stock_in
SET supplier_name = ELT(1 + MOD(stock_in_id, 4), 'Mercury Distribution Inc.', 'PharmaPlus Wholesale', 'MedSource Philippines', 'HealthFirst Supplies');

-- batch and expiry of the sample stock-ins (same as the medicine, used by the Stock-In page)
UPDATE stock_in si
JOIN medicines m ON si.medicine_id = m.medicine_id
SET si.batch_no = m.batch_no, si.expiry_date = m.expiry_date;

-- the sample sales were made by user 3 (Jenny Castillo, Pharmacy Staff from login.sql)
UPDATE sales SET cashier_id = 3 WHERE cashier_id IS NULL;

-- ---------------------------------------------------------
-- 3. VIEWS (the pages read from views, not directly from the tables)
-- ---------------------------------------------------------

-- every medicine with its stock status
CREATE VIEW v_medicine_status AS
SELECT
    m.medicine_id,
    m.medicine_name,
    c.category_name,
    m.quantity,
    m.unit,
    m.reorder_level,
    m.max_stock,
    m.expiry_date,
    m.unit_price,
    m.batch_no,
    m.date_added,
    CASE
        WHEN m.quantity = 0 THEN 'Out of Stock'
        WHEN m.quantity <= m.reorder_level THEN 'Low Stock'
        WHEN m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 'Expiring Soon'
        WHEN m.quantity > m.max_stock THEN 'Overstock'
        ELSE 'In Stock'
    END AS status
FROM medicines m
JOIN categories c ON m.category_id = c.category_id
WHERE m.is_archived = 0;

-- sales without the extra columns
CREATE VIEW v_recent_sales AS
SELECT sale_id, order_no, customer_name, total_amount, sale_date
FROM sales;

-- total sales per day
CREATE VIEW v_daily_sales AS
SELECT DATE(sale_date) AS sale_day, SUM(total_amount) AS daily_total, COUNT(*) AS order_count
FROM sales
GROUP BY DATE(sale_date);

-- total sales per day for the last 30 days
CREATE VIEW v_sales_last_30_days AS
SELECT sale_day, daily_total
FROM v_daily_sales
WHERE sale_day >= DATE_SUB(CURDATE(), INTERVAL 29 DAY);

-- how many of each medicine was sold in the last 7 days
CREATE VIEW v_weekly_demand AS
SELECT m.medicine_name, SUM(si.quantity) AS total_sold
FROM sale_items si
JOIN sales s ON si.sale_id = s.sale_id
JOIN medicines m ON si.medicine_id = m.medicine_id
WHERE DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
GROUP BY m.medicine_id, m.medicine_name;

-- ---------------------------------------------------------
-- 4. STORED PROCEDURES (dashboard.php only calls these)
-- ---------------------------------------------------------
DELIMITER //

-- numbers for the 4 cards + the sales trend header
CREATE PROCEDURE sp_dashboard_kpis()
BEGIN
    SELECT
        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day = CURDATE()) AS today_sales,
        (SELECT COUNT(*) FROM v_medicine_status) AS total_medicines,
        (SELECT COUNT(DISTINCT category_name) FROM v_medicine_status) AS total_categories,
        (SELECT COUNT(*) FROM v_medicine_status
            WHERE quantity <= reorder_level) AS low_stock,
        (SELECT COUNT(*) FROM v_medicine_status
            WHERE quantity = 0) AS out_of_stock,
        (SELECT COUNT(*) FROM v_medicine_status
            WHERE quantity > max_stock) AS overstock,
        (SELECT IFNULL(SUM(daily_total), 0) FROM v_sales_last_30_days) AS sales_30_days,
        (SELECT IFNULL(SUM(daily_total), 0) FROM v_daily_sales
            WHERE sale_day BETWEEN DATE_SUB(CURDATE(), INTERVAL 59 DAY)
            AND DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS sales_prev_30_days;
END //

-- "Low Stock & Expiring Soon" table
CREATE PROCEDURE sp_low_stock_expiring()
BEGIN
    SELECT medicine_name, quantity, unit, status
    FROM v_medicine_status
    WHERE status IN ('Out of Stock', 'Low Stock', 'Expiring Soon')
    ORDER BY quantity ASC
    LIMIT 4;
END //

-- "Recent Sales" table
CREATE PROCEDURE sp_recent_sales()
BEGIN
    SELECT order_no, customer_name, total_amount
    FROM v_recent_sales
    ORDER BY sale_date DESC, sale_id DESC
    LIMIT 4;
END //

-- "Sales Trend" line chart
CREATE PROCEDURE sp_sales_trend()
BEGIN
    SELECT sale_day, daily_total
    FROM v_sales_last_30_days
    ORDER BY sale_day ASC;
END //

-- "Top Medicine Demand" bar chart
CREATE PROCEDURE sp_top_demand()
BEGIN
    SELECT medicine_name, total_sold
    FROM v_weekly_demand
    ORDER BY total_sold DESC
    LIMIT 6;
END //

DELIMITER ;

-- ---------------------------------------------------------
-- OPTIONAL (extra data security): make a MySQL user for the website that can
-- only run the procedures, so it cannot read or change the tables directly.
-- If you use this, change $user and $pass in db.php.
-- ---------------------------------------------------------
-- CREATE USER 'medistock_app'@'localhost' IDENTIFIED BY 'app12345';
-- GRANT EXECUTE ON medistock.* TO 'medistock_app'@'localhost';
-- FLUSH PRIVILEGES;
