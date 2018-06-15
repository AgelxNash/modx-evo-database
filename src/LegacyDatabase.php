<?php namespace AgelxNash\Modx\Evo\Database;

/**
 * @deprecated
 */
class LegacyDatabase extends Database
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
}
