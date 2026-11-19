-- New Sale script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql.

DROP PROCEDURE IF EXISTS sp_sale_medicines;
DROP PROCEDURE IF EXISTS sp_create_sale;
DROP PROCEDURE IF EXISTS sp_add_sale_item;
DROP PROCEDURE IF EXISTS sp_finish_sale;

DELIMITER //

-- medicines that can be searched on the New Sale page (archived ones are not in the view)
CREATE PROCEDURE sp_sale_medicines()
BEGIN
    SELECT medicine_id, medicine_name, unit, unit_price, quantity, status, expiry_date,
           (expiry_date IS NOT NULL AND expiry_date < CURDATE()) AS is_expired
    FROM v_medicine_status
    ORDER BY medicine_name ASC;
END //

-- step 1: make the sale record and its order number (the next #OR number)
CREATE PROCEDURE sp_create_sale(
    IN p_cashier_id INT,
    IN p_payment VARCHAR(20),
    IN p_received DECIMAL(10,2)
)
BEGIN
    DECLARE next_no INT;
    DECLARE new_id INT;

    SELECT IFNULL(MAX(CAST(SUBSTRING(order_no, 5) AS UNSIGNED)), 1000) + 1
    INTO next_no
    FROM sales;

    INSERT INTO sales (order_no, customer_name, total_amount, sale_date, payment_method, cashier_id, amount_received)
    VALUES (CONCAT('#OR-', next_no), 'Walk-in Customer', 0, NOW(), p_payment, p_cashier_id, p_received);

    SET new_id = LAST_INSERT_ID();

    SELECT new_id AS sale_id, CONCAT('#OR-', next_no) AS order_no;
END //

-- step 2: add one item to the sale and take it out of the stock
-- the stock is only taken out if there is enough, so the stock can never go below 0
-- an expired medicine is never sold (the answer is 'expired')
CREATE PROCEDURE sp_add_sale_item(
    IN p_sale_id INT,
    IN p_medicine_id INT,
    IN p_qty INT
)
BEGIN
    DECLARE item_price DECIMAL(10,2);

    UPDATE medicines
    SET quantity = quantity - p_qty
    WHERE medicine_id = p_medicine_id
      AND is_archived = 0
      AND quantity >= p_qty
      AND (expiry_date IS NULL OR expiry_date >= CURDATE());

    IF ROW_COUNT() = 1 THEN
        SELECT unit_price INTO item_price FROM medicines WHERE medicine_id = p_medicine_id;

        INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price)
        VALUES (p_sale_id, p_medicine_id, p_qty, item_price);

        SELECT 'ok' AS result;
    ELSEIF EXISTS (SELECT 1 FROM medicines WHERE medicine_id = p_medicine_id
                   AND expiry_date IS NOT NULL AND expiry_date < CURDATE()) THEN
        SELECT 'expired' AS result;
    ELSE
        SELECT 'not enough stock' AS result;
    END IF;
END //

-- step 3: work out the total of the sale
CREATE PROCEDURE sp_finish_sale(IN p_sale_id INT)
BEGIN
    UPDATE sales
    SET total_amount = (SELECT IFNULL(SUM(quantity * unit_price), 0)
                        FROM sale_items WHERE sale_id = p_sale_id)
    WHERE sale_id = p_sale_id;

    -- write it in the activity log (shown in User Management)
    INSERT INTO activity_log (log_time, user_id, action, details)
    SELECT NOW(), cashier_id, 'Sale Recorded', CONCAT('Order ', order_no, ' — ₱ ', FORMAT(total_amount, 2))
    FROM sales WHERE sale_id = p_sale_id;

    SELECT total_amount FROM sales WHERE sale_id = p_sale_id;
END //

DELIMITER ;
