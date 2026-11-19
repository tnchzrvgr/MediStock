-- Stock Adjustment script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER dashboard.sql and login.sql.
-- (the table has no foreign key, so dashboard.sql can still be run again at any time)

DROP VIEW IF EXISTS v_adjustment_log;
DROP PROCEDURE IF EXISTS sp_adjust_medicines;
DROP PROCEDURE IF EXISTS sp_adjustment_log;
DROP PROCEDURE IF EXISTS sp_record_adjustment;
DROP PROCEDURE IF EXISTS sp_pending_adjustments;
DROP PROCEDURE IF EXISTS sp_decide_adjustment;
DROP PROCEDURE IF EXISTS sp_expired_medicines;
DROP TABLE IF EXISTS stock_adjustments;

CREATE TABLE stock_adjustments (
    adjust_id INT AUTO_INCREMENT PRIMARY KEY,
    adjust_time DATETIME NOT NULL,
    medicine_id INT NOT NULL,
    change_qty INT NOT NULL,            -- negative = removed, positive = added
    reason VARCHAR(30) NOT NULL,        -- Damaged, Expired, Count correction
    notes VARCHAR(200) NOT NULL DEFAULT '',
    old_qty INT NOT NULL,
    new_qty INT NOT NULL,
    user_id INT NULL,                   -- who made or requested it
    status VARCHAR(10) NOT NULL DEFAULT 'Approved',   -- Pending, Approved or Rejected
    approved_by INT NULL,               -- the owner who approved or rejected it
    decided_time DATETIME NULL
);

-- sample adjustments (medicine ids are found by name, the users come from login.sql)
INSERT INTO stock_adjustments (adjust_time, medicine_id, change_qty, reason, notes, old_qty, new_qty, user_id)
SELECT DATE_SUB(NOW(), INTERVAL 25 HOUR), medicine_id, -2, 'Damaged', 'Two capsule strips damaged during delivery handling.', quantity + 2, quantity, 2
FROM medicines WHERE medicine_name = 'Amoxicillin 500mg';

INSERT INTO stock_adjustments (adjust_time, medicine_id, change_qty, reason, notes, old_qty, new_qty, user_id)
SELECT DATE_SUB(NOW(), INTERVAL 46 HOUR), medicine_id, -3, 'Expired', '', quantity + 3, quantity, 2
FROM medicines WHERE medicine_name = 'Insulin Glargine';

INSERT INTO stock_adjustments (adjust_time, medicine_id, change_qty, reason, notes, old_qty, new_qty, user_id)
SELECT DATE_SUB(NOW(), INTERVAL 70 HOUR), medicine_id, 5, 'Count correction', '', quantity - 5, quantity, 1
FROM medicines WHERE medicine_name = 'Cetirizine 10mg';

INSERT INTO stock_adjustments (adjust_time, medicine_id, change_qty, reason, notes, old_qty, new_qty, user_id)
SELECT DATE_SUB(NOW(), INTERVAL 93 HOUR), medicine_id, -1, 'Damaged', '', quantity + 1, quantity, 2
FROM medicines WHERE medicine_name = 'Metformin 850mg';

-- the adjustments with the medicine and staff names
CREATE VIEW v_adjustment_log AS
SELECT
    a.adjust_id,
    a.adjust_time,
    m.medicine_name,
    m.unit,
    m.quantity AS stock_now,
    a.change_qty,
    a.reason,
    a.notes,
    a.status,
    IFNULL(u.full_name, '—') AS staff_name,
    IFNULL(ap.full_name, '—') AS approver_name
FROM stock_adjustments a
JOIN medicines m ON a.medicine_id = m.medicine_id
LEFT JOIN users u ON a.user_id = u.user_id
LEFT JOIN users ap ON a.approved_by = ap.user_id;

DELIMITER //

CREATE PROCEDURE sp_adjust_medicines()
BEGIN
    SELECT medicine_id, medicine_name, quantity, unit
    FROM v_medicine_status
    ORDER BY medicine_name ASC;
END //

-- the last 8 decided adjustments (approved or rejected)
CREATE PROCEDURE sp_adjustment_log()
BEGIN
    SELECT adjust_time, medicine_name, unit, change_qty, reason, status, staff_name, approver_name
    FROM v_adjustment_log
    WHERE status <> 'Pending'
    ORDER BY adjust_time DESC
    LIMIT 8;
END //

-- the requests that are waiting for the owner
CREATE PROCEDURE sp_pending_adjustments()
BEGIN
    SELECT adjust_id, adjust_time, medicine_name, unit, stock_now, change_qty, reason, notes, staff_name
    FROM v_adjustment_log
    WHERE status = 'Pending'
    ORDER BY adjust_time ASC;
END //

-- the Owner / Admin changes the stock at once (p_is_admin = 1)
-- a staff member only sends a request (p_is_admin = 0): the stock changes when the owner approves it
-- p_change is negative to remove, positive to add
-- the answer is 'ok', 'requested', 'medicine not found', 'invalid reason' or 'below zero'
CREATE PROCEDURE sp_record_adjustment(
    IN p_medicine_id INT,
    IN p_change INT,
    IN p_reason VARCHAR(30),
    IN p_notes VARCHAR(200),
    IN p_by INT,
    IN p_is_admin TINYINT
)
BEGIN
    DECLARE old_qty INT;
    DECLARE med_name VARCHAR(100);

    SELECT quantity, medicine_name
    INTO old_qty, med_name
    FROM medicines
    WHERE medicine_id = p_medicine_id AND is_archived = 0;

    IF med_name IS NULL THEN
        SELECT 'medicine not found' AS result;
    ELSEIF p_reason NOT IN ('Damaged', 'Expired', 'Count correction') THEN
        SELECT 'invalid reason' AS result;
    ELSEIF old_qty + p_change < 0 THEN
        SELECT 'below zero' AS result;
    ELSEIF p_is_admin = 1 THEN
        UPDATE medicines SET quantity = old_qty + p_change WHERE medicine_id = p_medicine_id;

        -- expired stock was removed: the medicine now shows the expiry date of its next valid batch
        IF p_reason = 'Expired' THEN
            UPDATE medicines
            SET expiry_date = IFNULL((SELECT MIN(expiry_date) FROM stock_in
                                      WHERE medicine_id = p_medicine_id AND expiry_date >= CURDATE()), expiry_date)
            WHERE medicine_id = p_medicine_id AND expiry_date < CURDATE();
        END IF;

        INSERT INTO stock_adjustments (adjust_time, medicine_id, change_qty, reason, notes, old_qty, new_qty,
                                       user_id, status, approved_by, decided_time)
        VALUES (NOW(), p_medicine_id, p_change, p_reason, p_notes, old_qty, old_qty + p_change,
                p_by, 'Approved', p_by, NOW());

        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'Stock Updated',
                CONCAT(med_name, ': ', old_qty, ' → ', old_qty + p_change, ' units (', p_reason, ')'));

        SELECT 'ok' AS result;
    ELSE
        INSERT INTO stock_adjustments (adjust_time, medicine_id, change_qty, reason, notes, old_qty, new_qty,
                                       user_id, status)
        VALUES (NOW(), p_medicine_id, p_change, p_reason, p_notes, old_qty, old_qty + p_change, p_by, 'Pending');

        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'Adjustment Requested',
                CONCAT(med_name, ': ', IF(p_change > 0, '+', ''), p_change, ' units (', p_reason, ')'));

        SELECT 'requested' AS result;
    END IF;
END //

-- the owner approves (p_approve = 1) or rejects (p_approve = 0) a request
-- the answer is 'approved', 'rejected', 'below zero' or 'not found' (also when it was already decided)
CREATE PROCEDURE sp_decide_adjustment(IN p_id INT, IN p_approve TINYINT, IN p_by INT)
BEGIN
    DECLARE med_id INT;
    DECLARE chg INT;
    DECLARE why VARCHAR(30);
    DECLARE st VARCHAR(10);
    DECLARE cur_qty INT;
    DECLARE med_name VARCHAR(100);

    SELECT medicine_id, change_qty, reason, status
    INTO med_id, chg, why, st
    FROM stock_adjustments
    WHERE adjust_id = p_id;

    IF st IS NULL OR st <> 'Pending' THEN
        SELECT 'not found' AS result;
    ELSE
        SELECT quantity, medicine_name INTO cur_qty, med_name FROM medicines WHERE medicine_id = med_id;

        IF p_approve = 0 THEN
            UPDATE stock_adjustments
            SET status = 'Rejected', approved_by = p_by, decided_time = NOW()
            WHERE adjust_id = p_id;

            INSERT INTO activity_log (log_time, user_id, action, details)
            VALUES (NOW(), p_by, 'Adjustment Rejected', CONCAT(med_name, ' (', why, ')'));

            SELECT 'rejected' AS result;
        ELSEIF cur_qty + chg < 0 THEN
            SELECT 'below zero' AS result;
        ELSE
            UPDATE medicines SET quantity = cur_qty + chg WHERE medicine_id = med_id;

            IF why = 'Expired' THEN
                UPDATE medicines
                SET expiry_date = IFNULL((SELECT MIN(expiry_date) FROM stock_in
                                          WHERE medicine_id = med_id AND expiry_date >= CURDATE()), expiry_date)
                WHERE medicine_id = med_id AND expiry_date < CURDATE();
            END IF;

            UPDATE stock_adjustments
            SET status = 'Approved', old_qty = cur_qty, new_qty = cur_qty + chg,
                approved_by = p_by, decided_time = NOW()
            WHERE adjust_id = p_id;

            INSERT INTO activity_log (log_time, user_id, action, details)
            VALUES (NOW(), p_by, 'Stock Updated',
                    CONCAT(med_name, ': ', cur_qty, ' → ', cur_qty + chg, ' units (', why, ', approved)'));

            SELECT 'approved' AS result;
        END IF;
    END IF;
END //

-- medicines whose expiry date has passed and that still have stock (used by "Remove Expired Stock")
-- expired_qty is an estimate: the stock left after the units of the batches that are not expired yet
-- (a medicine that already has a waiting "Expired" request is not shown again)
CREATE PROCEDURE sp_expired_medicines()
BEGIN
    SELECT
        m.medicine_id,
        m.medicine_name,
        m.unit,
        m.quantity,
        m.batch_no,
        m.expiry_date,
        CASE
            WHEN m.quantity - IFNULL((SELECT SUM(s.quantity) FROM stock_in s
                                      WHERE s.medicine_id = m.medicine_id AND s.expiry_date >= CURDATE()), 0) > 0
            THEN LEAST(m.quantity, m.quantity - IFNULL((SELECT SUM(s.quantity) FROM stock_in s
                                      WHERE s.medicine_id = m.medicine_id AND s.expiry_date >= CURDATE()), 0))
            ELSE m.quantity
        END AS expired_qty
    FROM medicines m
    WHERE m.is_archived = 0
      AND m.quantity > 0
      AND m.expiry_date IS NOT NULL
      AND m.expiry_date < CURDATE()
      AND NOT EXISTS (SELECT 1 FROM stock_adjustments a
                      WHERE a.medicine_id = m.medicine_id AND a.reason = 'Expired' AND a.status = 'Pending')
    ORDER BY m.expiry_date ASC;
END //

DELIMITER ;
