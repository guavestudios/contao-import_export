<?php

namespace Guave\ImportExport\Models;

class ExportModel {

    private static $instance = null;

    protected function __construct() {

    }

    public static function getInstance()
    {
        if(!self::$instance) {
            self::$instance = new Helper();
        }
        return self::$instance;
    }

    public static function findAllFromTable($table, $ids = null)
    {

        $query = 'SELECT * FROM '.$table;
        if(!empty($ids)) {
            $query .= ' WHERE id IN ('.implode(',', $ids).')';
        }
        $stm = \Database::getInstance()->prepare($query)->execute();
        return $stm->fetchAllAssoc();

    }

    public static function findIdsFromTableByPid($table, $pids, $pTable = '')
    {
        $query = 'SELECT id FROM '.$table;
        $params = array();
        $and = array();
        if(!empty($pids)) {
            $and[] = ' pid IN ('.implode(',', $pids).')';
        }
        if(!empty($pTable)) {
            $and[] = 'pTable = ?';
            $params[] = $pTable;
        }
        if(!empty($and)) {
            $query.= ' WHERE '.implode(' AND ', $and);
        }
        $stm = \Database::getInstance()->prepare($query)->execute($params);
        return $stm->fetchAllAssoc();
    }

}