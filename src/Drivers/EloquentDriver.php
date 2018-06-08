<?php namespace AgelxNash\Modx\Evo\Database\Drivers;

use AgelxNash\Modx\Evo\Database\Interfaces\DriverInterface;
use AgelxNash\Modx\Evo\Database\Exceptions;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PDOStatement;

class EloquentDriver implements DriverInterface
{
    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var Capsule
     */
    protected $capsule;

    private $affected_rows = 0;

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
    public function __construct(array $config = [])
    {
        $this->capsule = new Capsule;

        $this->capsule->setAsGlobal();

        $this->config = $config;
    }

    /**
     * @return Connection
     * @throws Exceptions\Exception
     */
    public function getConnect() : Connection
    {
        if (! $this->isConnected()) {
            return $this->connect();
        }

        return $this->conn;
    }

    /**
     * @return
     * @throws Exceptions\Exception
     */
    public function connect() : Connection
    {
        try {
            $this->capsule->addConnection([
                'driver'    => $this->driver,
                'host'      => $this->config['host'],
                'database'  => $this->config['base'],
                'username'  => $this->config['user'],
                'password'  => $this->config['pass'],
                'charset'   => $this->config['charset'],
                'collation' => $this->config['collation'],
                'prefix'    => $this->config['prefix'],
            ]);

            $this->conn = $this->capsule->getConnection();
        } catch (\Exception $exception) {
            throw new Exceptions\ConnectException($exception->getMessage(), $exception->getCode());
        }

        return $this->conn;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect() : DriverInterface
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
    public function isConnected() : bool
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
    public function getAffectedRows() : int
    {
        return $this->affected_rows;
    }

    /**
     * @return string
     * @throws Exceptions\Exception
     */
    public function getVersion() : string
    {

        return $this->getConnect()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        //return $this->getConnect()->server_info;
    }

    /**
     * @param PDOStatement $result
     * @return int
     */
    public function getRecordCount($result) : int
    {
        return $result->rowCount();
    }

    /**
     * {@inheritDoc}
     */
    public function setCharset(string $charset, $method = null) : bool
    {
        if ($method === null) {
            $method = $this->config['method'];
        }

        return (bool)$this->query($method . ' ' . $charset);
    }

    /**
     * @param $result
     * @return bool
     */
    public function isResult($result) : bool
    {
        return $result instanceof PDOStatement;
    }

    /**
     * @param PDOStatement $result
     * @return int
     */
    public function numFields($result) : int
    {
        return $result->columnCount();
    }

    /**
     * @param PDOStatement $result
     * @param int $col
     * @return string|null
     */
    public function fieldName($result, $col = 0) :? string
    {
        $field = $result->getColumnMeta($col);
        return $field['name'] ?? null;
    }

    /**
     * @param string $name
     * @return bool
     * @throws Exceptions\Exception
     */
    public function selectDb(string $name) : bool
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
    public function query(string $query)
    {
        try {
            $result = $this->getConnect()->getPdo()->prepare(
                $query,
                array(
                    \PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL
                )
            );
            $result->setFetchMode(\PDO::FETCH_ASSOC);
            if ($result->execute() === false) {
                $result = false;
            }
            $this->affected_rows = \is_bool($result) ? 0 : $result->rowCount();
            if ($this->affected_rows === 0 && $this->isResult($result) && 0 !== mb_stripos(trim($query), 'SELECT')) {
                $result = true;
            }
        } catch (\Exception $exception) {
            $this->lastError = $this->isResult($result) ? $result->errorInfo() : [];
            $this->lastErrorNo = $this->isResult($result) ? ($result->errorCode() ?? $exception->getCode()) : '';
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
    public function getColumn(string $name, $result) : array
    {
        $col = [];

        if ($result instanceof PDOStatement) {
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
    public function getColumnNames($result) : array
    {
        $names = [];

        if ($result instanceof PDOStatement) {
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

        if ($result instanceof PDOStatement) {
            $result = $this->getRow($result, 'num');
            $out = $result[0] ?? false;
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

        if ($result instanceof PDOStatement) {
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
    public function getLastError() :? string
    {
        return (string)($this->getConnect()->getPdo()->errorInfo()[2] ?? $this->lastError[2]);
    }

    /**
     * @return string|null
     * @throws Exceptions\Exception
     */
    public function getLastErrorNo() :? string
    {
        return (string)($this->getConnect()->getPdo()->errorInfo()[1] ?? $this->lastErrorNo);
    }

    /**
     * {@inheritDoc}
     * @param \PDOStatement $result
     */
    public function dataSeek(&$result, $position) : bool
    {
        throw new Exceptions\DriverException('No implemented');
    }
}
