<?php namespace AgelxNash\Modx\Evo\Database;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_driver;

class Database implements DatabaseInterface
{
    use Traits\DebugTrait,
        Traits\SupportTrait;

    protected const SAFE_LOOP_COUNT = 1000;

    /**
     * @var mysqli
     */
    public $conn;
    public $config = [];


    /**
     * @param string $host
     * @param string $base
     * @param string $user
     * @param string $pass
     * @param null|string $prefix
     * @param string $charset
     * @param string $method
     */
    public function __construct(
        $host = '',
        $base = '',
        $user = '',
        $pass = '',
        $prefix = null,
        $charset = 'utf8mb4',
        $method = 'SET CHARACTER SET'
    ) {
        $base = trim($base, '`');

        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR;

        $this->setConfig(compact(
            'host',
            'base',
            'user',
            'pass',
            'prefix',
            'charset',
            'method'
        ));
    }

    /**
     * @param $data
     * @return $this
     */
    public function setConfig($data) : self
    {
        $this->config = $data;

        return $this;
    }

    /**
     * @param null $key
     * @return array|mixed|null
     */
    public function getConfig($key = null)
    {
        return ($key === null ? $this->config : ($this->config[$key] ?? null));
    }

    /**
     * @return mysqli
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     */
    public function getConnect() : mysqli
    {
        if (! $this->isConnected()) {
            return $this->connect();
        }

        return $this->conn;
    }

    /**
     * @return mysqli
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     */
    public function connect() : mysqli
    {
        $tstart = microtime(true);
        try {
            $this->conn = new mysqli(
                $this->getConfig('host'),
                $this->getConfig('user'),
                $this->getConfig('pass'),
                $this->getConfig('base')
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

        $this->query($this->getConfig('method') . ' ' . $this->getConfig('charset'));
        $totaltime = microtime(true) - $tstart;
        if ($this->isDebug()) {
            $this->connectionTime = $totaltime;
        }
        $this->getConnect()->set_charset($this->getConfig('charset'));
        $this->queryTime += $totaltime;

        return $this->conn;
    }

    /**
     * @return $this
     */
    public function disconnect() : self
    {
        if ($this->isConnected()) {
            $this->conn->close();
        }
        $this->conn = null;
        $this->connectionTime = 0;
        $this->flushExecutedQuery();

        return $this;
    }

    /**
     * @return bool
     */
    public function isConnected() : bool
    {
        return ($this->conn instanceof mysqli);
    }

    /**
     * @param string|array $data
     * @param int $safeCount
     * @return array|string
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     * @throws Exceptions\TooManyLoopsException
     */
    public function escape($data, $safeCount = 0)
    {
        $safeCount++;
        if (self::SAFE_LOOP_COUNT < $safeCount) {
            throw new Exceptions\TooManyLoopsException("Too many loops '{$safeCount}'");
        }
        if (\is_array($data)) {
            if (\count($data) === 0) {
                $data = '';
            } else {
                foreach ($data as $i => $v) {
                    $data[$i] = $this->escape($v, $safeCount);
                }
            }
        } else {
            $data = $this->getConnect()->escape_string($data);
        }

        return $data;
    }

    /**
     * @param mixed $sql
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     */
    public function query($sql)
    {
        $tStart = microtime(true);
        if (\is_array($sql)) {
            $sql = implode("\n", $sql);
        }
        $this->lastQuery = $sql;

        try {
            $result = $this->getConnect()->query($sql);
        } catch (mysqli_sql_exception $exception) {
            throw new Exceptions\QueryException($exception->getMessage(), $exception->getCode());
        }

        if ($result === false) {
            $this->checkLastError($sql);
        } else {
            $tend = microtime(true);
            $totalTime = $tend - $tStart;
            $this->queryTime += $totalTime;
            if ($this->isDebug()) {
                $this->collectQuery(
                    $result,
                    $sql,
                    $this->executedQueries + 1,
                    $totalTime
                );
            }
            $this->executedQueries++;

            return $result;
        }
        return false;
    }

    /**
     * @param string $table
     * @param array|string $where
     * @param string $orderBy
     * @param string $limit
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     * @throws Exceptions\TableNotDefinedException
     */
    public function delete($table, $where = '', $orderBy = '', $limit = '')
    {
        $table = $this->prepareFrom($table);
        $where = $this->prepareWhere($where);
        $orderBy = $this->prepareOrder($orderBy);
        $limit = $this->prepareOrder($limit);

        return $this->query("DELETE FROM {$table} {$where} {$orderBy} {$limit}");
    }

    /**
     * @param array|string $fields
     * @param array|string $tables
     * @param array|string $where
     * @param string $orderBy
     * @param string $limit
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     * @throws Exceptions\TableNotDefinedException
     */
    public function select($fields, $tables, $where = '', $orderBy = '', $limit = '')
    {
        $fields = $this->prepareFields($fields);
        $tables = $this->prepareFrom($tables, true);
        $where = $this->prepareWhere($where);
        $orderBy = $this->prepareOrder($orderBy);
        $limit = $this->prepareLimit($limit);

        return $this->query("SELECT {$fields} FROM {$tables} {$where} {$orderBy} {$limit}");
    }

    /**
     * @param array|string $values
     * @param string $table
     * @param array|string $where
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     * @throws Exceptions\TableNotDefinedException
     */
    public function update($values, string $table, $where = '')
    {
        $table = $this->prepareFrom($table);
        $values = $this->prepareValuesSet($values);
        $where = $this->prepareWhere($where);

        return $this->query("UPDATE {$table} SET {$values} {$where}");
    }

    /**
     * @param array|string $fields
     * @param string $table
     * @param string $fromfields
     * @param string $fromtable
     * @param array|string $where
     * @param string $limit
     * @return mixed
     * @throws Exceptions\ConnectException
     * @throws Exceptions\GetDataException
     * @throws Exceptions\QueryException
     * @throws Exceptions\TableNotDefinedException
     * @throws Exceptions\TooManyLoopsException
     */
    public function insert(
        $fields,
        string $table,
        string $fromfields = '*',
        string $fromtable = '',
        $where = '',
        string $limit = ''
    ) {
        $table = $this->prepareFrom($table);

        if (is_scalar($fields)) {
            $this->query("INSERT INTO {$table} {$fields}");
        } else {
            if (empty($fromtable) && \is_array($fields)) {
                $fields = $this->prepareValues($fields);
                $this->query("INSERT INTO {$table} {$fields}");
            } else {
                $fields = $this->prepareValues($fields, 2, true)['values'];
                $where = $this->prepareWhere($where);
                $limit = $this->prepareLimit($limit);
                $this->query("INSERT INTO {$table} {$fields} SELECT {$fromfields} FROM {$fromtable} {$where} {$limit}");
            }
        }
        if (($lid = $this->getInsertId()) === false) {
            throw new Exceptions\GetDataException("Couldn't get last insert key!");
        }

        return $lid;
    }

    /**
     * @param string|array $fields
     * @param string $table
     * @param string $where
     * @return bool|mixed|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\GetDataException
     * @throws Exceptions\QueryException
     * @throws Exceptions\TableNotDefinedException
     * @throws Exceptions\TooManyLoopsException
     */
    public function save($fields, string $table, string $where = '')
    {
        if ($where === '') {
            $mode = 'insert';
        } elseif ($this->getRecordCount($this->select('*', $table, $where)) === 0) {
            $mode = 'insert';
        } else {
            $mode = 'update';
        }

        return ($mode === 'insert') ? $this->insert($fields, $table) : $this->update($fields, $table, $where);
    }

    /**
     * @param $result
     * @return bool
     */
    public function isResult($result) : bool
    {
        return $result instanceof mysqli_result;
    }

    /**
     * @param mysqli_result $result
     * @return $this
     */
    public function freeResult(mysqli_result $result) : self
    {
        $result->free_result();

        return $this;
    }

    /**
     * @param mysqli_result $result
     * @return int
     */
    public function numFields(mysqli_result $result) : int
    {
        return $result->field_count;
    }

    /**
     * @param mysqli_result $result
     * @param int $col
     * @return string|null
     */
    public function fieldName(mysqli_result $result, $col = 0) :? string
    {
        $field = $result->fetch_field_direct($col);

        return $field->name ?? null;
    }

    /**
     * @param string $name
     * @return bool
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     */
    public function selectDb(string $name) : bool
    {
        return $this->getConnect()->select_db($name);
    }

    /**
     * @param mysqli_result $result
     * @return int
     */
    public function getRecordCount(mysqli_result $result) : int
    {
        return $result->num_rows;
    }

    /**
     * @param mysqli_result $result
     * @param string $mode
     * @return array|mixed|object|\stdClass
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getRow(mysqli_result $result, $mode = 'assoc')
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
     * @param string string $name
     * @param array|string|mysqli_result $dsq
     * @return array
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getColumn(string $name, $dsq) : array
    {
        $col = [];
        if (! ($dsq instanceof mysqli_result)) {
            $dsq = $this->query($dsq);
        }
        if ($dsq) {
            while ($row = $this->getRow($dsq)) {
                $col[] = $row[$name];
            }
        }

        return $col;
    }

    /**
     * @param array|string|mysqli_result $dsq
     * @return array
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getColumnNames($dsq) : array
    {
        $names = [];
        if (! ($dsq instanceof mysqli_result)) {
            $dsq = $this->query($dsq);
        }
        if ($dsq) {
            $limit = $this->numFields($dsq);
            for ($i = 0; $i < $limit; $i++) {
                $names[] = $this->fieldName($dsq, $i);
            }
        }

        return $names;
    }

    /**
     * @param array|string|mysqli_result $dsq
     * @return bool|mixed
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getValue($dsq)
    {
        $out = false;
        if (! ($dsq instanceof mysqli_result)) {
            $dsq = $this->query($dsq);
        }
        if ($dsq) {
            $result = $this->getRow($dsq, 'num');
            $out = $result[0] ?? false;
        }

        return $out;
    }

    /**
     * @param string $table
     * @return array
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getTableMetaData(string $table) : array
    {
        $metadata = [];
        if (! empty($table)) {
            $sql = 'SHOW FIELDS FROM ' . $table;
            if ($result = $this->query($sql)) {
                while ($row = $this->getRow($result)) {
                    $fieldName = $row['Field'];
                    $metadata[$fieldName] = $row;
                }
            }
        }

        return $metadata;
    }

    /**
     * @param mysqli_result $result
     * @param bool $index
     * @return array
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function makeArray(mysqli_result $result, bool $index = false) : array
    {
        $rsArray = [];
        $iterator = 0;
        while ($row = $this->getRow($result)) {
            $returnIndex = $index !== false && isset($row[$index]) ? $row[$index] : $iterator;
            $rsArray[$returnIndex] = $row;
            $iterator++;
        }

        return $rsArray;
    }

    /**
     * @param mysqli_result $result
     * @param int $row
     * @return bool
     */
    public function dataSeek(mysqli_result $result, int $row) : bool
    {
        return $result->data_seek($row);
    }

    /**
     * @return string
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     */
    public function getVersion() : string
    {
        return $this->getConnect()->server_info;
    }

    /**
     * @param string $table
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     */
    public function optimize(string $table)
    {
        $result = $this->query('OPTIMIZE TABLE ' . $table);
        if ($result) {
            $result = $this->query('ALTER TABLE ' . $table);
        }

        return $result;
    }

    /**
     * @param string $table
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     */
    public function truncate(string $table)
    {
        return $this->query('TRUNCATE ' . $table);
    }

    /**
     * @return mixed
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     */
    public function getInsertId()
    {
        return $this->getConnect()->insert_id;
    }

    /**
     * @return int
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
     */
    public function getAffectedRows() : int
    {
        return $this->getConnect()->affected_rows;
    }
}
