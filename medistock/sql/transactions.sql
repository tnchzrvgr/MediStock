-- transactions.sql : views and procedures for the Transaction History page (with Void / Return)
-- run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER receipt.sql (it makes the sale_status table),
-- reports.sql and login.sql
USE medistock;

DROP VIEW IF EXISTS v_transactions;
DROP PROCEDURE IF EXISTS sp_transactions;
DROP PROCEDURE IF EXISTS sp_void_sale;
DROP PROCEDURE IF EXISTS sp_return_start;
DROP PROCEDURE IF EXISTS sp_return_item;
DROP PROCEDURE IF EXISTS sp_return_finish;

-- the items that were taken back in a "Return items" (never dropped, so the history is kept)
CREATE TABLE IF NOT EXISTS sale_returns (
    return_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    returned_at DATETIME NOT NULL
);

-- every sale with its staff, number of items and status
CREATE VIEW v_transactions AS
SELECT
    s.sale_id,
    s.order_no,
    s.sale_date,
    s.payment_method,
    s.cashier_id,
    IFNULL(ss.original_amount, s.total_amount) AS total_amount,
    IFNULL(ss.status, 'Completed') AS status,
    IFNULL(u.full_name, '—') AS staff_name,
    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.sale_id) AS item_count
FROM sales s
LEFT JOIN users u ON u.user_id = s.cashier_id
LEFT JOIN sale_status ss ON ss.sale_id = s.sale_id;

DELIMITER //

-- the search of the page. p_item = part of a medicine name ('' = any), p_staff = 0 means all staff,
-- p_status = '' means all statuses
CREATE PROCEDURE sp_transactions(IN p_item VARCHAR(100), IN p_from DATE, IN p_to DATE, IN p_staff INT, IN p_status VARCHAR(10))
BEGIN
    SELECT t.sale_id, t.order_no, t.sale_date, t.item_count, t.staff_name, t.payment_method, t.total_amount, t.status
    FROM v_transactions t
    WHERE DATE(t.sale_date) BETWEEN p_from AND p_to
      AND (p_staff = 0 OR t.cashier_id = p_staff)
      AND (p_status = '' OR t.status = p_status)
      AND (p_item = '' OR EXISTS (
            SELECT 1
            FROM sale_items si
            JOIN medicines m ON m.medicine_id = si.medicine_id
            WHERE si.sale_id = t.sale_id AND m.medicine_name LIKE CONCAT('%', p_item, '%')))
    ORDER BY t.sale_date DESC, t.sale_id DESC
    LIMIT 200;
END //

-- Void or Return a sale: the stock goes back, the money of the sale is removed from the reports
-- and the action is saved in the activity log. p_action = 'Voided' or 'Returned'
-- gives back result = 'ok', 'not found' or 'already'
CREATE PROCEDURE sp_void_sale(IN p_sale_id INT, IN p_action VARCHAR(10), IN p_reason VARCHAR(100), IN p_by INT)
BEGIN
    DECLARE v_order VARCHAR(20);
    DECLARE v_total DECIMAL(10,2);

    SELECT order_no, total_amount INTO v_order, v_total FROM sales WHERE sale_id = p_sale_id;

    IF v_order IS NULL THEN
        SELECT 'not found' AS result;
    ELSEIF EXISTS (SELECT 1 FROM sale_status WHERE sale_id = p_sale_id) THEN
        SELECT 'already' AS result;
    ELSE
        -- put the medicines back in the stock
        UPDATE medicines m
        JOIN sale_items si ON si.medicine_id = m.medicine_id
        SET m.quantity = m.quantity + si.quantity
        WHERE si.sale_id = p_sale_id;

        INSERT INTO sale_status (sale_id, status, original_amount, reason, changed_at, changed_by)
        VALUES (p_sale_id, p_action, v_total, p_reason, NOW(), p_by);

        UPDATE sales SET total_amount = 0 WHERE sale_id = p_sale_id;

        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, IF(p_action = 'Voided', 'Sale Voided', 'Sale Returned'),
                CONCAT('Order ', v_order, ' — ₱ ', FORMAT(v_total, 2), ' — ', p_reason));

        SELECT 'ok' AS result;
    END IF;
END //

-- Return items, step 1: marks the sale as Returned (keeps the original total)
-- gives back result = 'ok', 'not found' or 'already'
CREATE PROCEDURE sp_return_start(IN p_sale_id INT, IN p_reason VARCHAR(100), IN p_by INT)
BEGIN
    DECLARE v_total DECIMAL(10,2);

    SELECT total_amount INTO v_total FROM sales WHERE sale_id = p_sale_id;

    IF v_total IS NULL THEN
        SELECT 'not found' AS result;
    ELSEIF EXISTS (SELECT 1 FROM sale_status WHERE sale_id = p_sale_id) THEN
        SELECT 'already' AS result;
    ELSE
        INSERT INTO sale_status (sale_id, status, original_amount, reason, changed_at, changed_by)
        VALUES (p_sale_id, 'Returned', v_total, p_reason, NOW(), p_by);

        SELECT 'ok' AS result;
    END IF;
END //

-- Return items, step 2: one medicine of the sale. The stock goes back and the total of the sale goes down.
-- gives back result = 'ok' or 'invalid' (the quantity is more than the quantity sold)
CREATE PROCEDURE sp_return_item(IN p_sale_id INT, IN p_medicine_id INT, IN p_qty INT)
BEGIN
    DECLARE v_sold INT;
    DECLARE v_price DECIMAL(10,2);

    SELECT SUM(quantity), MAX(unit_price) INTO v_sold, v_price
    FROM sale_items
    WHERE sale_id = p_sale_id AND medicine_id = p_medicine_id;

    IF v_sold IS NULL OR p_qty < 1 OR p_qty > v_sold THEN
        SELECT 'invalid' AS result;
    ELSE
        UPDATE medicines SET quantity = quantity + p_qty WHERE medicine_id = p_medicine_id;

        INSERT INTO sale_returns (sale_id, medicine_id, quantity, amount, returned_at)
        VALUES (p_sale_id, p_medicine_id, p_qty, p_qty * v_price, NOW());

        UPDATE sales SET total_amount = GREATEST(0, total_amount - p_qty * v_price) WHERE sale_id = p_sale_id;

        SELECT 'ok' AS result;
    END IF;
END //

-- Return items, step 3: write it in the activity log
CREATE PROCEDURE sp_return_finish(IN p_sale_id INT, IN p_by INT)
BEGIN
    DECLARE v_order VARCHAR(20);
    DECLARE v_amount DECIMAL(10,2);
    DECLARE v_reason VARCHAR(100);

    SELECT order_no INTO v_order FROM sales WHERE sale_id = p_sale_id;
    SELECT IFNULL(SUM(amount), 0) INTO v_amount FROM sale_returns WHERE sale_id = p_sale_id;
    SELECT reason INTO v_reason FROM sale_status WHERE sale_id = p_sale_id;

    INSERT INTO activity_log (log_time, user_id, action, details)
    VALUES (NOW(), p_by, 'Sale Returned',
            CONCAT('Order ', v_order, ' — ₱ ', FORMAT(v_amount, 2), ' returned — ', v_reason));
END //

DELIMITER ;
