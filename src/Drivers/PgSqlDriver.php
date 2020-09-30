<?php namespace AgelxNash\Modx\Evo\Database\Drivers;

use AgelxNash\Modx\Evo\Database\Exceptions;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_driver;
use ReflectionClass;

/**
 * @property mysqli $conn
 */
class PgSqlDriver extends IlluminateDriver
{
    /**
     * {@inheritDoc}
     */
    public function getTableName($table, $escape = true)
    {
        if (empty($table)) {
            throw new Exceptions\TableNotDefinedException($table);
        }

        $out = $this->getConfig('prefix') . $table;

        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function getFullTableName($table)
    {
        return $this->getTableName($table);
    }
}
