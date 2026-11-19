-- Payment methods patch: the system now accepts only Cash and GCash
-- Run in phpMyAdmin (SQL tab) on the "medistock" database. Your data is kept.
-- Old Card and HMO sales are changed to GCash so the Sales page still adds up.
USE medistock;

UPDATE sales SET payment_method = 'GCash' WHERE payment_method IN ('Card', 'HMO');
