<?php namespace AgelxNash\Modx\Evo\Database;

/**
 * @deprecated
 */
class LegacyDatabase extends AbstractDatabase
{
    /**
     * @param string $host
     * @param string $database
     * @param string $username
     * @param string $password
     * @param string $prefix
     * @param string $charset
     * @param string $method
     * @param string $collation
     * @param string $driver
     * @throws Exceptions\Exception
     */
    public function __construct(
        $host = '',
        $database = '',
        $username = '',
        $password = '',
        $prefix = '',
        $charset = 'utf8mb4',
        $method = 'SET CHARACTER SET',
        $collation = 'utf8mb4_unicode_ci',
        $driver = Drivers\MySqliDriver::class
    ) {
        $database = trim($database, '`');

        $this->setConfig(compact(
            'host',
            'database',
            'username',
            'password',
            'prefix',
            'charset',
            'method',
            'collation'
        ));

        $this->setDriver($driver);
    }

    /**
     * @param $tableName
     * @param bool $force
     * @return null|string|string[]
     * @throws Exceptions\Exception
     */
    public function replaceFullTableName($tableName, $force = false)
    {
        $tableName = trim($tableName);
        if ((bool)$force === true) {
            $result = $this->getFullTableName($tableName);
        } elseif (strpos($tableName, '[+prefix+]') !== false) {
            $dbase = trim($this->getConfig('database'), '`');
            $prefix = $this->getConfig('prefix');

            $result = preg_replace('@\[\+prefix\+\]([0-9a-zA-Z_]+)@', "`{$dbase}`.`{$prefix}$1`", $tableName);
        } else {
            $result = $tableName;
        }

        return $result;
    }

    /**
     * @param mixed $sql
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function query($sql)
    {
        return parent::query($this->replaceFullTableName($sql));
    }
}
