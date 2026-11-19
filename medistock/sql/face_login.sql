-- Face Login for MediStock
-- Run this in phpMyAdmin (SQL tab) on the "medistock" database AFTER login.sql and users.sql.
-- Only the face "numbers" (128 numbers made by the camera program) are saved. The photo of the face
-- and the photo of the National ID are NEVER saved.

DROP PROCEDURE IF EXISTS sp_face_get;
DROP PROCEDURE IF EXISTS sp_face_save;
DROP PROCEDURE IF EXISTS sp_face_remove;
DROP PROCEDURE IF EXISTS sp_face_users;
DROP PROCEDURE IF EXISTS sp_log_face;
DROP TABLE IF EXISTS user_faces;

CREATE TABLE user_faces (
    user_id INT NOT NULL PRIMARY KEY,
    descriptor TEXT NOT NULL,              -- the 128 face numbers (JSON list)
    registered_time DATETIME NOT NULL,
    registered_by INT NULL                 -- the owner who registered it
);

DELIMITER //

-- the saved face numbers of one user (no row = no face registered)
CREATE PROCEDURE sp_face_get(IN p_user_id INT)
BEGIN
    SELECT descriptor FROM user_faces WHERE user_id = p_user_id;
END //

-- which users have a face registered (for the User Management page)
CREATE PROCEDURE sp_face_users()
BEGIN
    SELECT user_id, registered_time FROM user_faces;
END //

-- register (or replace) the face of a user and write it in the activity log
CREATE PROCEDURE sp_face_save(IN p_user_id INT, IN p_descriptor TEXT, IN p_by INT, IN p_note VARCHAR(100))
BEGIN
    DECLARE who VARCHAR(100);

    SELECT full_name INTO who FROM users WHERE user_id = p_user_id;

    IF who IS NULL THEN
        SELECT 'not found' AS result;
    ELSE
        INSERT INTO user_faces (user_id, descriptor, registered_time, registered_by)
        VALUES (p_user_id, p_descriptor, NOW(), p_by)
        ON DUPLICATE KEY UPDATE descriptor = p_descriptor, registered_time = NOW(), registered_by = p_by;

        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_by, 'Face Registered', CONCAT(who, ' (', p_note, ')'));

        SELECT 'ok' AS result;
    END IF;
END //

CREATE PROCEDURE sp_face_remove(IN p_user_id INT, IN p_by INT)
BEGIN
    DECLARE who VARCHAR(100);

    SELECT full_name INTO who FROM users WHERE user_id = p_user_id;

    DELETE FROM user_faces WHERE user_id = p_user_id;

    INSERT INTO activity_log (log_time, user_id, action, details)
    VALUES (NOW(), p_by, 'Face Removed', IFNULL(who, 'Unknown user'));
END //

-- writes a face login result in the activity log (only these two actions are allowed)
CREATE PROCEDURE sp_log_face(IN p_user_id INT, IN p_action VARCHAR(30), IN p_details VARCHAR(100))
BEGIN
    IF p_action IN ('Face Login', 'Face Login Failed') THEN
        INSERT INTO activity_log (log_time, user_id, action, details)
        VALUES (NOW(), p_user_id, p_action, p_details);
    END IF;
END //

DELIMITER ;
