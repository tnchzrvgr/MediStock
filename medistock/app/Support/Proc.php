<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;

// runs a stored procedure (CALL ...) and gives back all the rows in an array
// example: Proc::call('sp_dashboard_kpis')   or   Proc::call('sp_inventory_list', array($filter, $search))
class Proc
{
    public static function call($name, $params = array())
    {
        // one ? for every value, so the values are never put inside the sql text
        $marks = implode(", ", array_fill(0, count($params), "?"));

        $stmt = DB::getPdo()->prepare("CALL " . $name . "(" . $marks . ")");
        $stmt->execute($params);

        // some procedures only change data and send no rows
        $rows = array();
        if ($stmt->columnCount() > 0) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // CALL sends an extra result, so read it before the next query
        while ($stmt->nextRowset()) {
        }
        $stmt->closeCursor();

        return $rows;
    }
}
