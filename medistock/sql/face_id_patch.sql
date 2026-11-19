-- Patch: the ID scanned at login must be the ID that was registered
-- Run in phpMyAdmin (SQL tab) on the "medistock" database. The registered faces are kept. It can be run again.
-- The column id_descriptor is added only if it is missing (a normal MySQL cannot do ADD COLUMN IF NOT EXISTS,
-- so the check is done below with a small procedure).
-- After this, every user must be registered again (User Management > Register > Update) so that the ID is saved.

DROP PROCEDURE IF EXISTS sp_add_id_column;
DROP PROCEDURE IF EXISTS sp_face_get;
DROP PROCEDURE IF EXISTS sp_face_save;

DELIMITER //

-- adds the column only when it is not there yet
CREATE PROCEDURE sp_add_id_column()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_faces' AND COLUMN_NAME = 'id_descriptor') THEN
        ALTER TABLE user_faces ADD COLUMN id_descriptor TEXT NULL AFTER descriptor;
    END IF;
END //

CREATE PROCEDURE sp_face_get(IN p_user_id INT)
BEGIN
    SELECT descriptor, id_descriptor FROM user_faces WHERE user_id = p_user_id;
END //

CREATE PROCEDURE sp_face_save(IN p_user_id INT, IN p_descriptor TEXT, IN p_id_descriptor TEXT, IN p_by INT, IN p_note VARCHAR(100))
BEGIN
    DECLARE who VARCHAR(100);

    SELECT full_name INTO who FROM users WHERE user_id = p_user_id;

    IF who IS NULL THEN
        SELECT 'not found' AS result;
    ELSE
        INSERT INTO user_faces (user_id, descriptor, id_descriptor, registered_time, registered_by)
        VALUES (p_user_id, p_descriptor, p_id_descriptor, NOW(), p_by)
        ON DUPLICATE KEY UPDATE descriptor = p_descriptor, id_descriptor = p_id_descriptor, registered_time = NOW(), registered_by = p_by;

        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'Face Registered', CONCAT(who, ' (', p_note, ')'));

        SELECT 'ok' AS result;
    END IF;
END //

DELIMITER ;

CALL sp_add_id_column();
DROP PROCEDURE sp_add_id_column;
