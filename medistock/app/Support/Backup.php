<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

// makes and restores database backups (a .sql file saved in storage/app/backups)
// only the tables (data) are saved. The views and stored procedures come from the .sql scripts.
class Backup
{
    // line that separates two statements inside the backup file
    const SEPARATOR = "\n-- ----\n";

    // folder where the files are saved
    public static function folder()
    {
        $folder = storage_path('app/backups');
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        return $folder;
    }

    // makes a backup file and saves the result in the history. $type = "Manual" or "Scheduled"
    // gives back array(true/false, message)
    public static function create($type, $user_id)
    {
        $file_name = "medistock_" . date("Y-m-d_His") . ".sql";

        try {
            $text = self::dumpDatabase();
            file_put_contents(self::folder() . "/" . $file_name, $text);
            $size = strlen($text);
            Proc::call('sp_add_backup', array($type, $size, "Successful", $file_name, $user_id));
        } catch (\Exception $e) {
            Proc::call('sp_add_backup', array($type, 0, "Failed", "", $user_id));
            return array(false, "The backup failed: " . $e->getMessage());
        }

        // delete the old backups (more than "keep last")
        $settings = Proc::call('sp_backup_settings');
        if (count($settings) > 0) {
            $old = Proc::call('sp_old_backups', array((int) $settings[0]['keep_last']));
            foreach ($old as $row) {
                if ($row['file_name'] != "") {
                    @unlink(self::folder() . "/" . basename($row['file_name']));
                }
                Proc::call('sp_delete_backup', array($row['backup_id']));
            }
        }

        return array(true, "Backup saved: " . $file_name);
    }

    // builds the text of the backup file (structure + data of every table)
    public static function dumpDatabase()
    {
        $pdo = DB::getPdo();

        $text = "-- MediStock backup\n";
        $text .= "-- Date: " . date("Y-m-d H:i:s") . "\n";
        $text .= self::SEPARATOR . "SET FOREIGN_KEY_CHECKS = 0";

        // the list of tables (not the views). The backup tables themselves are not saved.
        $tables = array();
        $result = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        foreach ($result->fetchAll(\PDO::FETCH_NUM) as $row) {
            if ($row[0] != "backup_history" && $row[0] != "backup_settings") {
                $tables[] = $row[0];
            }
        }

        foreach ($tables as $table) {
            // structure
            $create = $pdo->query("SHOW CREATE TABLE `" . $table . "`")->fetch(\PDO::FETCH_NUM);
            $text .= self::SEPARATOR . "DROP TABLE IF EXISTS `" . $table . "`";
            $text .= self::SEPARATOR . $create[1];

            // data (50 rows in one INSERT)
            $rows = $pdo->query("SELECT * FROM `" . $table . "`")->fetchAll(\PDO::FETCH_ASSOC);
            foreach (array_chunk($rows, 50) as $group) {
                $lines = array();
                foreach ($group as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = "NULL";
                        } else {
                            $values[] = $pdo->quote($value);
                        }
                    }
                    $lines[] = "(" . implode(", ", $values) . ")";
                }
                $text .= self::SEPARATOR . "INSERT INTO `" . $table . "` VALUES\n" . implode(",\n", $lines);
            }
        }

        $text .= self::SEPARATOR . "SET FOREIGN_KEY_CHECKS = 1\n";
        return $text;
    }

    // puts the data of a backup file back in the database
    // gives back array(true/false, message)
    public static function restore($text)
    {
        if (substr($text, 0, 19) != "-- MediStock backup") {
            return array(false, "This is not a MediStock backup file.");
        }

        $pdo = DB::getPdo();

        try {
            foreach (explode(self::SEPARATOR, $text) as $statement) {
                $statement = trim($statement);

                // skip the comment lines and empty parts
                if ($statement == "" || substr($statement, 0, 2) == "--") {
                    continue;
                }
                $pdo->exec($statement);
            }
        } catch (\Exception $e) {
            return array(false, "The restore failed: " . $e->getMessage());
        }

        return array(true, "Database restored.");
    }

    // runs a scheduled backup when it is time (called every minute by the Laravel scheduler)
    public static function runIfDue()
    {
        $settings = Proc::call('sp_backup_settings');
        if (count($settings) == 0 || $settings[0]['schedule'] == "Manual") {
            return;
        }

        // weekly backups run on Monday
        if ($settings[0]['schedule'] == "Weekly" && date("N") != 1) {
            return;
        }

        $due_time = strtotime(date("Y-m-d") . " " . $settings[0]['backup_time']);
        if (time() < $due_time) {
            return;
        }

        // already tried today? (successful or failed)
        foreach (Proc::call('sp_backup_history') as $row) {
            if ($row['backup_type'] == "Scheduled" && strtotime($row['created_at']) >= $due_time) {
                return;
            }
        }

        self::create("Scheduled", null);
    }
}
