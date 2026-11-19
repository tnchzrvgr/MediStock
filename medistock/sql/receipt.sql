-- receipt.sql : views and procedures for the Receipt page
-- run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql, login.sql and new_sale.sql
USE medistock;

-- Void / Return information of a sale (a sale without a row here is "Completed")
-- (this table is also used by transactions.sql, it is never dropped so the history is kept)
CREATE TABLE IF NOT EXISTS sale_status (
    sale_id INT PRIMARY KEY,
    status VARCHAR(10) NOT NULL,          -- Voided or Returned
    original_amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    changed_at DATETIME NOT NULL,
    changed_by INT NULL
);

DROP VIEW IF EXISTS v_receipt_header;
DROP VIEW IF EXISTS v_receipt_items;
DROP PROCEDURE IF EXISTS sp_receipt_header;
DROP PROCEDURE IF EXISTS sp_receipt_items;

-- a sale with the name of the cashier
CREATE VIEW v_receipt_header AS
SELECT
    s.sale_id,
    s.order_no,
    s.sale_date,
    s.payment_method,
    IFNULL(ss.original_amount, s.total_amount) AS total_amount,
    s.amount_received,
    IFNULL(ss.status, 'Completed') AS status,
    IFNULL(u.full_name, '—') AS cashier_name
FROM sales s
LEFT JOIN users u ON u.user_id = s.cashier_id
LEFT JOIN sale_status ss ON ss.sale_id = s.sale_id;

-- the medicines of a sale with the stock they have now
CREATE VIEW v_receipt_items AS
SELECT
    si.sale_id,
    si.item_id,
    m.medicine_id,
    m.medicine_name,
    si.quantity,
    si.unit_price,
    m.quantity AS stock_now
FROM sale_items si
JOIN medicines m ON m.medicine_id = si.medicine_id;

DELIMITER //

-- one receipt (p_sale_id = 0 means the last sale)
CREATE PROCEDURE sp_receipt_header(IN p_sale_id INT)
BEGIN
    IF p_sale_id = 0 THEN
        SELECT sale_id, order_no, sale_date, payment_method, total_amount, amount_received, status, cashier_name
        FROM v_receipt_header
        ORDER BY sale_id DESC
        LIMIT 1;
    ELSE
        SELECT sale_id, order_no, sale_date, payment_method, total_amount, amount_received, status, cashier_name
        FROM v_receipt_header
        WHERE sale_id = p_sale_id;
    END IF;
END //

CREATE PROCEDURE sp_receipt_items(IN p_sale_id INT)
BEGIN
    SELECT medicine_id, medicine_name, quantity, unit_price, stock_now
    FROM v_receipt_items
    WHERE sale_id = p_sale_id
    ORDER BY item_id ASC;
END //

DELIMITER ;
