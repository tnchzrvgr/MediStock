-- Suppliers script for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER login.sql (it writes to the activity log).
-- Running it again resets the suppliers.

DROP VIEW IF EXISTS v_supplier_list;
DROP PROCEDURE IF EXISTS sp_supplier_kpis;
DROP PROCEDURE IF EXISTS sp_supplier_list;
DROP PROCEDURE IF EXISTS sp_get_supplier;
DROP PROCEDURE IF EXISTS sp_add_supplier;
DROP PROCEDURE IF EXISTS sp_update_supplier;

DROP TABLE IF EXISTS suppliers;

CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100) NOT NULL,
    products_supplied VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL DEFAULT '',
    email VARCHAR(100) NOT NULL DEFAULT '',
    address VARCHAR(200) NOT NULL DEFAULT '',
    payment_terms VARCHAR(30) NOT NULL DEFAULT 'Net 30 days',
    lead_time_days INT NOT NULL DEFAULT 0,
    notes VARCHAR(200) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'Active',   -- Active, On Hold or Inactive (chosen by the staff)
    date_added DATE NOT NULL
);

-- sample suppliers
INSERT INTO suppliers (supplier_name, contact_person, products_supplied, phone, email, address, payment_terms, lead_time_days, notes, status, date_added) VALUES
('Mercury Distribution Inc.', 'Carlo Mendoza', 'Antibiotics, Analgesics',        '0917 555 0123', 'orders@mercury-dist.ph', '45 Industrial Ave., Calamba, Laguna', 'Net 30 days', 3, 'Delivers Mon-Fri. Minimum order P 5,000.', 'Active',           DATE_SUB(CURDATE(), INTERVAL 400 DAY)),
('PharmaPlus Wholesale',      'Liza Ramos',    'Cardiovascular, Antidiabetic',   '0918 555 0144', 'sales@pharmaplus.ph',    '12 Rizal St., Quezon City',            'Net 15 days', 4, '', 'Active',           DATE_SUB(CURDATE(), INTERVAL 380 DAY)),
('MedSource Philippines',     'Paolo Garcia',  'Respiratory, Hormones',          '0919 555 0165', 'info@medsource.ph',      '88 Ayala Ave., Makati City',           'Net 30 days', 5, '', 'Active', DATE_SUB(CURDATE(), INTERVAL 300 DAY)),
('HealthFirst Supplies',      'Grace Tan',     'Antihistamines, Vitamins',       '0920 555 0186', 'grace@healthfirst.ph',   '5 Mabini St., Pasig City',             'Cash on delivery', 2, '', 'Active',           DATE_SUB(CURDATE(), INTERVAL 200 DAY)),
('BioCare Pharma Corp.',      'Ramon Dizon',   'Insulin, Injectables',           '0921 555 0107', 'orders@biocare.ph',      '20 Bonifacio Dr., Taguig City',        'Net 60 days', 7, '', 'On Hold',          DATE_SUB(CURDATE(), INTERVAL 90 DAY)),
('Luzon Medical Traders',     'Anna Villanueva','Gastrointestinal, Analgesics',  '0922 555 0128', 'anna@luzonmedical.ph',   '33 Rizal Ave., Batangas City',         'Net 30 days', 3, '', 'Active',           DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
('Visayas Health Depot',      'Mark Salazar',  'Vitamins, Supplements',          '0923 555 0149', 'mark@visayashealth.ph',  '9 Colon St., Cebu City',               'Net 15 days', 6, '', 'Active',           DATE_SUB(CURDATE(), INTERVAL 5 DAY));

-- the supplier list (the status is the one the staff saved)
CREATE VIEW v_supplier_list AS
SELECT
    supplier_id,
    supplier_name,
    contact_person,
    products_supplied,
    phone,
    email,
    address,
    payment_terms,
    lead_time_days,
    notes,
    status,
    date_added
FROM suppliers;

DELIMITER //

-- the 4 cards
CREATE PROCEDURE sp_supplier_kpis()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM v_supplier_list) AS total_suppliers,
        (SELECT COUNT(*) FROM v_supplier_list
         WHERE date_added >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS new_this_month,
        (SELECT COUNT(*) FROM v_supplier_list WHERE status = 'Active') AS active_suppliers,
        (SELECT COUNT(*) FROM v_supplier_list WHERE status = 'On Hold') AS on_hold_suppliers,
        (SELECT COUNT(*) FROM v_supplier_list WHERE status = 'Inactive') AS inactive_suppliers;
END //

CREATE PROCEDURE sp_supplier_list()
BEGIN
    SELECT supplier_id, supplier_name, contact_person, products_supplied, date_added, status
    FROM v_supplier_list
    ORDER BY supplier_name ASC;
END //

-- one supplier (used to fill the form when editing)
CREATE PROCEDURE sp_get_supplier(IN p_id INT)
BEGIN
    SELECT supplier_id, supplier_name, contact_person, phone, email, address, products_supplied,
           payment_terms, lead_time_days, notes, status
    FROM v_supplier_list
    WHERE supplier_id = p_id;
END //

-- add a supplier. Gives back result = 'ok' or 'duplicate' (same name and phone)
CREATE PROCEDURE sp_add_supplier(
    IN p_name VARCHAR(100), IN p_contact VARCHAR(100), IN p_phone VARCHAR(30), IN p_email VARCHAR(100),
    IN p_address VARCHAR(200), IN p_products VARCHAR(150), IN p_terms VARCHAR(30), IN p_lead INT,
    IN p_status VARCHAR(20), IN p_notes VARCHAR(200), IN p_by INT)
BEGIN
    IF EXISTS (SELECT 1 FROM v_supplier_list WHERE supplier_name = p_name AND phone = p_phone) THEN
        SELECT 'duplicate' AS result;
    ELSE
        INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, products_supplied,
                               payment_terms, lead_time_days, notes, status, date_added)
        VALUES (p_name, p_contact, p_phone, p_email, p_address, p_products, p_terms, p_lead, p_notes, p_status, CURDATE());

        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'Supplier Added', CONCAT('Added supplier ', p_name));

        SELECT 'ok' AS result;
    END IF;
END //

-- update a supplier (the duplicate check ignores the supplier itself)
CREATE PROCEDURE sp_update_supplier(
    IN p_id INT, IN p_name VARCHAR(100), IN p_contact VARCHAR(100), IN p_phone VARCHAR(30), IN p_email VARCHAR(100),
    IN p_address VARCHAR(200), IN p_products VARCHAR(150), IN p_terms VARCHAR(30), IN p_lead INT,
    IN p_status VARCHAR(20), IN p_notes VARCHAR(200), IN p_by INT)
BEGIN
    IF EXISTS (SELECT 1 FROM v_supplier_list WHERE supplier_name = p_name AND phone = p_phone AND supplier_id <> p_id) THEN
        SELECT 'duplicate' AS result;
    ELSE
        UPDATE suppliers
        SET supplier_name = p_name, contact_person = p_contact, phone = p_phone, email = p_email,
            address = p_address, products_supplied = p_products, payment_terms = p_terms,
            lead_time_days = p_lead, notes = p_notes, status = p_status
        WHERE supplier_id = p_id;

        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'Supplier Edited', CONCAT('Edited supplier ', p_name));

        SELECT 'ok' AS result;
    END IF;
END //

DELIMITER ;
