<?php namespace AgelxNash\Modx\Evo\Database\Drivers;

use AgelxNash\Modx\Evo\Database\Interfaces\DriverInterface;
use AgelxNash\Modx\Evo\Database\Exceptions;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PDOStatement;
use Illuminate\Events\Dispatcher;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Container\Container;
use ReflectionClass;
use PDO;

class IlluminateDriver implements DriverInterface
{
    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var string
     */
    protected $connection = 'default';

    /**
     * @var Capsule
     */
    protected $capsule;

    private $affectedRows = 0;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    public $lastError;

    /**
     * @var string
     */
    public $lastErrorNo;

    protected $driver = 'mysql';

    /**
     * {@inheritDoc}
     */
    public function __construct(array $config = [], $connection = 'default')
    {
        $reflection = new ReflectionClass(Capsule::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        /**
         * @var Capsule|null $capsule
         */
        $capsule = $property->getValue(Capsule::class);
        if ($capsule === null) {
            $this->capsule = new Capsule;

            $this->getCapsule()->setAsGlobal();
        } else {
            $this->capsule = $capsule;
        }

        if ($this->hasConnectionName($connection)) {
            if (empty($config)) {
                $config = $this->getCapsule()->getConnection($connection)->getConfig();
                unset($config['name'], $config['driver']);
            } else {
                $diff = array_diff_assoc(
                    array_merge(['driver' => $this->driver], $config),
                    $this->getCapsule()->getConnection($connection)->getConfig()
                );
                if (array_intersect(['driver', 'host', 'database', 'password', 'username'], array_keys($diff))) {
                    throw new Exceptions\ConnectException(
                        sprintf('The connection name "%s" is already used', $connection)
                    );
                }
            }
        }

        $this->connection = $connection;

        $this->useEloquent();

        $this->config = $config;
    }

    /**
     * @param DispatcherContract|null $dispatcher
     * @return bool
     */
    public function useEloquent(DispatcherContract $dispatcher = null)
    {
        $out = false;
        if ($dispatcher === null) {
            $dispatcher = $this->getCapsule()->getEventDispatcher();
        }

        if ($dispatcher === null && class_exists(Dispatcher::class)) {
            $dispatcher = new Dispatcher(new Container);
        }

        if ($dispatcher !== null) {
            $this->getCapsule()->setEventDispatcher($dispatcher);

            $out = true;
        }

        $this->getCapsule()->bootEloquent();

        return $out;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return Capsule
     */
    public function getCapsule()
    {
        return $this->capsule;
    }

    /**
     * @return Connection
     * @throws Exceptions\Exception
     */
    public function getConnect()
    {
        if (! $this->isConnected()) {
            $this->connect();
            if (! $this->conn->getPdo() instanceof PDO) {
                $this->conn->reconnect();
            }
        }

        return $this->conn;
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasConnectionName($name)
    {
        $connections = $this->getCapsule()->getDatabaseManager()->getConnections();
        return isset($connections[$name]);
    }

    /**
     * @return Connection
     * @throws Exceptions\Exception
     */
    public function connect()
    {
        try {
            if (! $this->hasConnectionName($this->connection)) {
                $this->getCapsule()->addConnection([
                    'driver'    => $this->driver,
                    'host'      => $this->config['host'],
                    'database'  => $this->config['database'],
                    'username'  => $this->config['username'],
                    'password'  => $this->config['password'],
                    'charset'   => $this->config['charset'],
                    'collation' => $this->config['collation'],
                    'prefix'    => $this->config['prefix'],
                ], $this->connection);
            }

            $this->conn = $this->getCapsule()->getConnection($this->connection);
        } catch (\Exception $exception) {
            $this->conn = null;
            throw new Exceptions\ConnectException($exception->getMessage(), $exception->getCode());
        }

        return $this->conn;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect()
    {
        if ($this->isConnected()) {
            $this->conn->disconnect();
        }

        $this->conn = null;

        return $this;
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        return ($this->conn instanceof Connection && $this->conn->getDatabaseName());
    }

    /**
     * @param $data
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function escape($data)
    {
        /**
         * It's not secure
         * But need for backward compatibility
         */

        $quote = $this->getConnect()->getPdo()->quote($data);
        return strpos($quote, '\'') === 0 ? mb_substr($quote, 1, -1) : $quote;
    }

    /**
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function getInsertId()
    {
        return $this->getConnect()->getPdo()->lastInsertId();
    }

    /**
     * @return int
     * @throws Exceptions\Exception
     */
    public function getAffectedRows()
    {
        return $this->affectedRows;
    }

    /**
     * @return string
     * @throws Exceptions\Exception
     */
    public function getVersion()
    {
        return $this->getConnect()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        //return $this->getConnect()->server_info;
    }

    /**
     * @param PDOStatement $result
     * @return int
     */
    public function getRecordCount($result)
    {
        return $this->isResult($result) ? $result->rowCount() : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function setCharset($charset, $collation, $method = null)
    {
        if ($method === null) {
            $method = $this->config['method'];
        }
        $query = $method . ' ' . $charset . ' COLLATE ' . $collation;

        return (bool)$this->query($query);
    }

    /**
     * @param $result
     * @return bool
     */
    public function isResult($result)
    {
        return $result instanceof PDOStatement;
    }

    /**
     * @param PDOStatement $result
     * @return int
     */
    public function numFields($result)
    {
        return $this->isResult($result) ? $result->columnCount() : 0;
    }

    /**
     * @param PDOStatement $result
     * @param int $col
     * @return string|null
     */
    public function fieldName($result, $col = 0)
    {
        $field = $this->isResult($result) ? $result->getColumnMeta($col) : [];
        return isset($field['name']) ? $field['name'] : null;
    }

    /**
     * @param string $name
     * @return bool
     * @throws Exceptions\Exception
     */
    public function selectDb($name)
    {
        $this->getConnect()->setDatabaseName($name);

        return true;
    }

    /**
     * @param PDOStatement $result
     * @param string $mode
     * @return array|mixed|object|\stdClass
     * @throws Exceptions\Exception
     */
    public function getRow($result, $mode = 'assoc')
    {
        switch ($mode) {
            case 'assoc':
                $out = $result->fetch(\PDO::FETCH_ASSOC);
                break;
            case 'num':
                $out = $result->fetch(\PDO::FETCH_NUM);
                break;
            case 'object':
                $out = $result->fetchObject();
                break;
            case 'both':
                $out = $result->fetch(\PDO::FETCH_BOTH);
                break;
            default:
                throw new Exceptions\UnknownFetchTypeException(
                    "Unknown get type ($mode) specified for fetchRow - must be empty, 'assoc', 'num' or 'both'."
                );
        }

        return $out;
    }

    /**
     * @param string $query
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function query($query)
    {
        $result = null;
        try {
            $result = $this->getConnect()->getPdo()->prepare(
                $query,
                [
                    \PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL
                ]
            );
            $result->setFetchMode(\PDO::FETCH_ASSOC);
            if ($result->execute() === false) {
                $result = false;
            }
            $this->affectedRows = \is_bool($result) ? 0 : $result->rowCount();
            if ($this->affectedRows === 0 && $this->isResult($result) && 0 !== mb_stripos(trim($query), 'SELECT')) {
                $result = true;
            }
        } catch (\Exception $exception) {
            $this->lastError = $this->isResult($result) ? $result->errorInfo() : [];
            $code = $this->isResult($result) ? $result->errorCode() : '';
            $this->lastErrorNo = $this->isResult($result) ? (empty($code) ? $exception->getCode() : $code) : '';
            throw (new Exceptions\QueryException($exception->getMessage(), $exception->getCode()))
                ->setQuery($query);
        }

        return $result;
    }

    /**
     * @param string $name
     * @param $result
     * @return array
     * @throws Exceptions\Exception
     */
    public function getColumn($name, $result)
    {
        $col = [];

        if ($this->isResult($result)) {
            while ($row = $this->getRow($result)) {
                $col[] = $row[$name];
            }
        }

        return $col;
    }

    /**
     * @param $result
     * @return array
     */
    public function getColumnNames($result)
    {
        $names = [];

        if ($this->isResult($result)) {
            $limit = $this->numFields($result);
            for ($i = 0; $i < $limit; $i++) {
                $names[] = $this->fieldName($result, $i);
            }
        }

        return $names;
    }

    /**
     * @param $result
     * @return bool|mixed
     * @throws Exceptions\Exception
     */
    public function getValue($result)
    {
        $out = false;

        if ($this->isResult($result)) {
            $result = $this->getRow($result, 'num');
            $out = isset($result[0]) ? $result[0] : false;
        }

        return $out;
    }

    /**
     * @param $result
     * @return array|mixed
     * @throws Exceptions\Exception
     */
    public function getTableMetaData($result)
    {
        $out = [];

        if ($this->isResult($result)) {
            while ($row = $this->getRow($result)) {
                $fieldName = $row['Field'];
                $out[$fieldName] = $row;
            }
        }

        return $out;
    }

    /**
     * @return string|null
     * @throws Exceptions\Exception
     */
    public function getLastError()
    {
        $pdo = $this->getConnect()->getPdo();
        $error = $pdo->errorInfo();
        return (string)(isset($error[2]) ? $error[2] : $this->lastError[2]);
    }

    /**
     * @return string|null
     * @throws Exceptions\Exception
     */
    public function getLastErrorNo()
    {
        $pdo = $this->getConnect()->getPdo();
        $error = $pdo->errorInfo();
        return (string)(isset($error[1]) ? $error[1] : $this->lastErrorNo);
    }

    /**
     * {@inheritDoc}
     * @param \PDOStatement $result
     */
    public function dataSeek(&$result, $position)
    {
        throw new Exceptions\DriverException('No implemented');
    }
}
