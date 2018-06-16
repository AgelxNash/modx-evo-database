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
class MySqliDriver extends AbstractDriver
{
    /**
     * {@inheritDoc}
     */
    public function __construct(array $config = [])
    {
        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR;

        $this->config = $config;
    }

    /**
     * @return mixed
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
     * @return mysqli
     * @throws Exceptions\Exception
     */
    public function connect()
    {
        try {
            $this->conn = new mysqli(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['database']
            );

            if ($this->isConnected() && $this->getConnect()->connect_error) {
                throw new Exceptions\ConnectException($this->conn->connect_error);
            }

            if (! $this->isConnected()) {
                throw new Exceptions\ConnectException(
                    $this->getLastError() ?: 'Failed to create the database connection!'
                );
            }
        } catch (mysqli_sql_exception $exception) {
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
            $this->conn->close();
        }

        $this->conn = null;

        return $this;
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        return ($this->conn instanceof mysqli);
    }

    /**
     * @param $data
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function escape($data)
    {
        return $this->getConnect()->escape_string($data);
    }

    /**
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function getInsertId()
    {
        return $this->getConnect()->insert_id;
    }

    /**
     * @return int
     * @throws Exceptions\Exception
     */
    public function getAffectedRows()
    {
        return $this->getConnect()->affected_rows;
    }

    /**
     * @return string
     * @throws Exceptions\Exception
     */
    public function getVersion()
    {
        return $this->getConnect()->server_info;
    }

    /**
     * @param mysqli_result $result
     * @return int
     */
    public function getRecordCount($result)
    {
        return $this->isResult($result) ? $result->num_rows : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function setCharset($charset, $collation, $method = null)
    {
        if ($method === null) {
            $method = $this->config['method'];
        }
        if ($this->query($method . ' ' . $charset . ' COLLATE ' . $collation) === true) {
            return $this->getConnect()->set_charset($charset);
        }
        return false;
    }

    /**
     * @param $result
     * @return bool
     */
    public function isResult($result)
    {
        return $result instanceof mysqli_result;
    }

    /**
     * @param mysqli_result $result
     * @return int
     */
    public function numFields($result)
    {
        return $this->isResult($result) ? $result->field_count : 0;
    }

    /**
     * @param mysqli_result $result
     * @param int $col
     * @return string|null
     */
    public function fieldName($result, $col = 0)
    {
        $field = $this->isResult($result) ? $result->fetch_field_direct($col) : [];

        return isset($field->name) ? $field->name : null;
    }

    /**
     * @param string $name
     * @return bool
     * @throws Exceptions\Exception
     */
    public function selectDb($name)
    {
        return $this->getConnect()->select_db($name);
    }

    /**
     * @param mysqli_result $result
     * @param string $mode
     * @return array|mixed|object|\stdClass
     * @throws Exceptions\Exception
     */
    public function getRow($result, $mode = 'assoc')
    {
        switch ($mode) {
            case 'assoc':
                $out = $result->fetch_assoc();
                break;
            case 'num':
                $out = $result->fetch_row();
                break;
            case 'object':
                $out = $result->fetch_object();
                break;
            case 'both':
                $out = $result->fetch_array(MYSQLI_BOTH);
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
        try {
            $result = $this->getConnect()->query($query);
        } catch (mysqli_sql_exception $exception) {
            $reflect = new ReflectionClass($exception);
            $property = $reflect->getProperty('sqlstate');
            $property->setAccessible(true);
            throw (new Exceptions\QueryException($exception->getMessage(), $property->getValue($exception)))
                ->setQuery($query);
        }

        return $result;
    }

    /**
     * @return string|null
     * @throws Exceptions\Exception
     */
    public function getLastError()
    {
        return $this->getConnect()->error;
    }

    /**
     * @return string|null
     * @throws Exceptions\Exception
     */
    public function getLastErrorNo()
    {
        return (string)$this->getConnect()->sqlstate;
    }

    /**
     * @param mysqli_result $result
     * @param int $position
     * @return bool
     */
    public function dataSeek(&$result, $position)
    {
        return $this->isResult($result) ? $result->data_seek($position) : false;
    }
}
