<?php namespace AgelxNash\Modx\Evo\Database\Drivers;

use AgelxNash\Modx\Evo\Database\Interfaces\DriverInterface;
use AgelxNash\Modx\Evo\Database\Exceptions;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PDOStatement;

class IlluminateDriver implements DriverInterface
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

        $this->capsule->bootEloquent();

        $this->config = $config;
    }

    /**
     * @return Connection
     * @throws Exceptions\Exception
     */
    public function getConnect()
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
    public function connect()
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
        return $this->affected_rows;
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
        return $result->rowCount();
    }

    /**
     * {@inheritDoc}
     */
    public function setCharset($charset, $collation, $method = null)
    {
        if ($method === null) {
            $method = $this->config['method'];
        }

        return (bool)$this->query($method . ' ' . $charset . ' COLLATE ' . $collation);
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
        return $result->columnCount();
    }

    /**
     * @param PDOStatement $result
     * @param int $col
     * @return string|null
     */
    public function fieldName($result, $col = 0)
    {
        $field = $result->getColumnMeta($col);
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
            $this->affected_rows = \is_bool($result) ? 0 : $result->rowCount();
            if ($this->affected_rows === 0 && $this->isResult($result) && 0 !== mb_stripos(trim($query), 'SELECT')) {
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
    public function getColumnNames($result)
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