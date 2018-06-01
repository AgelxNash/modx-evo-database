<?php namespace AgelxNash\Modx\Evo\Database;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_driver;

class Database implements DatabaseInterface
{
    const SAFE_LOOP_COUNT = 1000;

    /**
     * @var mysqli
     */
    public $conn;
    public $config = [];

    protected $debug = false;
    protected $queryCollection = [];
    protected $queryTime = 0;
    protected $executedQueries = 0;
    protected $lastQuery = '';
    protected $connectionTime = 0;

    /**
     * @param string $host
     * @param string $dbase
     * @param string $user
     * @param string $pass
     * @param null|string $table_prefix
     * @param string $charset
     * @param string $connection_method
     */
    public function __construct(
        $host = '',
        $dbase = '',
        $user = '',
        $pass = '',
        $table_prefix = null,
        $charset = 'utf8mb4',
        $connection_method = 'SET CHARACTER SET'
    ) {
        $dbase = trim($dbase, '`');

        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR;

        $this->setConfig(compact(
            'host',
            'dbase',
            'user',
            'pass',
            'table_prefix',
            'charset',
            'connection_method'
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
     */
    public function connect() : mysqli
    {
        $tstart = microtime(true);
        try {
            $this->conn = new mysqli(
                $this->getConfig('host'), $this->getConfig('user'), $this->getConfig('pass'), $this->getConfig('dbase')
            );

            if ($this->isConnected() && $this->getConnect()->connect_error) {
                throw new Exceptions\ConnectException($this->conn->connect_error);
            }

            if (! $this->isConnected()) {
                throw new Exceptions\ConnectException($this->getLastError() ?: 'Failed to create the database connection!');
            }

        } catch(mysqli_sql_exception $exception) {
            throw new Exceptions\ConnectException($exception->getMessage(), $exception->getCode());
        }

        $this->query($this->getConfig('connection_method') . ' ' . $this->getConfig('charset'));
        $totaltime = microtime(true) - $tstart;
        if ($this->getDebug()) {
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
     * @param $s
     * @param int $safeCount
     * @return array|string
     * @throws Exceptions\ConnectException
     * @throws Exceptions\TooManyLoopsException
     */
    public function escape($s, $safeCount = 0)
    {
        $safeCount++;
        if (self::SAFE_LOOP_COUNT < $safeCount) {
            throw new Exceptions\TooManyLoopsException("Too many loops '{$safeCount}'");
        }
        if (\is_array($s)) {
            if (\count($s) === 0) {
                $s = '';
            } else {
                foreach ($s as $i => $v) {
                    $s[$i] = $this->escape($v, $safeCount);
                }
            }
        } else {
            $s = $this->getConnect()->escape_string($s);
        }

        return $s;
    }

    /**
     * @param string|array $sql
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     */
    public function query($sql)
    {
        $tStart = microtime(true);
        if (\is_array($sql)) {
            $sql = implode("\n", $sql);
        }
        $this->lastQuery = $sql;

        try{
            $result = $this->getConnect()->query($sql);
        } catch(mysqli_sql_exception $exception) {
            throw new Exceptions\QueryException($exception->getMessage(), $exception->getCode());
        }

        if ($result === false) {
            $this->checkLastError($sql);
        } else {
            $tend = microtime(true);
            $totalTime = $tend - $tStart;
            $this->queryTime += $totalTime;
            if ($this->getDebug()) {
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
     * @param string $from
     * @param string $where
     * @param string $orderBy
     * @param string $limit
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\TableNotDefinedException
     */
    public function delete($from, $where = '', $orderBy = '', $limit = '')
    {
        if (! is_scalar($from) || empty($from)) {
            throw new Exceptions\TableNotDefinedException($from);
        }

        $where = trim($where);
        if (! empty($where) && stripos($where, 'WHERE') !== 0) {
            $where = "WHERE {$where}";
        }

        $orderBy = trim($orderBy);
        if (! empty($orderBy) && stripos($orderBy, 'ORDER BY') !== 0) {
            $orderBy = "ORDER BY {$orderBy}";
        }

        $limit = trim($limit);
        if (! empty($limit) && stripos($limit, 'LIMIT') !== 0) {
            $limit = "LIMIT {$limit}";
        }

        return $this->query("DELETE FROM {$from} {$where} {$orderBy} {$limit}");
    }

    /**
     * @param array|string $fields
     * @param array|string $from
     * @param array|string $where
     * @param string $orderBy
     * @param string $limit
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\TableNotDefinedException
     */
    public function select($fields = '*', $from, $where = '', $orderBy = '', $limit = '')
    {
        if (\is_array($fields)) {
            if (empty($fields)) {
                $fields = '*';
            } else {
                $_ = array();
                foreach ($fields as $k => $v) {
                    $_[] = ($k !== $v) ? ($v . ' as ' . $k) : $v;
                }

                $fields = implode(',', $_);
            }
        }
        if (\is_array($from)) {
            $_ = array();
            foreach ($from as $k => $v) {
                $_[] = $v;
            }
            $from = implode(' ', $_);
        }
        if (! is_scalar($from) || empty($from)) {
            throw new Exceptions\TableNotDefinedException($from);
        }

        if (\is_array($where)) {
            $where = implode(' ', $where);
        }
        $where = trim($where);
        if ($where !== '' && stripos($where, 'WHERE') !== 0) {
            $where = "WHERE {$where}";
        }

        $orderBy = trim($orderBy);
        if ($orderBy !== '' && stripos($orderBy, 'ORDER') !== 0) {
            $orderBy = "ORDER BY {$orderBy}";
        }

        $limit = trim($limit);
        if ($limit !== '' && stripos($limit, 'LIMIT') !== 0) {
            $limit = "LIMIT {$limit}";
        }

        return $this->query("SELECT {$fields} FROM {$from} {$where} {$orderBy} {$limit}");
    }

    /**
     * @param array|string $fields
     * @param string $table
     * @param string $where
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     * @throws Exceptions\TableNotDefinedException
     */
    public function update($fields, string $table, $where = '')
    {
        if (! is_scalar($table) || empty($table)) {
            throw new Exceptions\TableNotDefinedException($table);
        }

        if (\is_array($fields)) {
            foreach ($fields as $key => $value) {
                if ($value === null || strtolower($value) === 'null') {
                    $f = 'NULL';
                } else {
                    $f = "'" . $value . "'";
                }
                $fields[$key] = "`{$key}` = " . $f;
            }
            $fields = implode(',', $fields);
        }
        $where = trim($where);
        if ($where !== '' && stripos($where, 'WHERE') !== 0) {
            $where = 'WHERE '.$where;
        }

        return $this->query("UPDATE {$table} SET {$fields} {$where}");
    }

    /**
     * @param array|string $fields
     * @param string $table
     * @param string $fromfields
     * @param string $fromtable
     * @param string $where
     * @param string $limit
     * @return mixed
     * @throws Exceptions\ConnectException
     * @throws Exceptions\GetDataException
     * @throws Exceptions\TableNotDefinedException
     */
    public function insert(
        $fields,
        string $table,
        string $fromfields = '*',
        string $fromtable = '',
        string $where = '',
        string $limit = ''
    )
    {
        if (! is_scalar($table) || empty($table)) {
            throw new Exceptions\TableNotDefinedException($table);
        }

        if (is_scalar($fields)) {
            $this->query("INSERT INTO {$table} {$fields}");
        } else {
            if (empty($fromtable) && \is_array($fields)) {
                $fields = '(`' . implode('`, `', array_keys($fields)) . "`) VALUES ('" . implode("', '", array_values($fields)) . "')";
                $this->query("INSERT INTO {$table} {$fields}");
            } else {
                $fields = '(' . implode(',', array_keys($fields)) . ')';
                $where = trim($where);
                $limit = trim($limit);
                if ($where !== '' && stripos($where, 'WHERE') !== 0) {
                    $where = "WHERE {$where}";
                }
                if ($limit !== '' && stripos($limit, 'LIMIT') !== 0) {
                    $limit = "LIMIT {$limit}";
                }
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
     * @throws Exceptions\TableNotDefinedException
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
     * @param $rs
     * @return bool
     */
    public function isResult($rs) : bool
    {
        return $rs instanceof mysqli_result;
    }

    /**
     * @param mysqli_result $rs
     * @return $this
     */
    public function freeResult(mysqli_result $rs) : self
    {
        $rs->free_result();

        return $this;
    }

    /**
     * @param mysqli_result $rs
     * @return int
     */
    public function numFields(mysqli_result $rs) : int
    {
        return $rs->field_count;
    }

    /**
     * @param mysqli_result $rs
     * @param int $col
     * @return string|null
     */
    public function fieldName(mysqli_result $rs, $col = 0) :? string
    {
        $field = $rs->fetch_field_direct($col);

        return $field->name ?? null;
    }

    /**
     * @param $name
     * @throws Exceptions\ConnectException
     * @return bool
     */
    public function selectDb($name) : bool
    {
        return $this->getConnect()->select_db($name);
    }

    /**
     * @return mixed
     * @throws Exceptions\ConnectException
     */
    public function getInsertId()
    {
        return $this->getConnect()->insert_id;
    }

    /**
     * @return int
     * @throws Exceptions\ConnectException
     */
    public function getAffectedRows() : int
    {
        return $this->getConnect()->affected_rows;
    }

    /**
     * @return string
     * @throws Exceptions\ConnectException
     */
    public function getLastError() : string
    {
        return $this->getConnect()->error;
    }

    /**
     * @return int
     * @throws Exceptions\ConnectException
     */
    public function getLastErrorNo() : int
    {
        return $this->getConnect()->errno;
    }

    /**
     * @param null $query
     * @throws Exceptions\ConnectException
     */
    public function checkLastError($query = null) : void
    {
        switch ($this->getLastErrorNo()) {
            case 1054:
            case 1060:
            case 1061:
            case 1062:
            case 1091:
                break;
            default:
                throw (new Exceptions\QueryException($this->getLastError()))
                    ->setQuery($query);
        }
    }

    /**
     * @param mysqli_result $ds
     * @return int
     */
    public function getRecordCount(mysqli_result $ds) : int
    {
        return $ds->num_rows;
    }

    /**
     * @param mysqli_result $ds
     * @param string $mode
     * @return array|mixed|object|\stdClass
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getRow(mysqli_result $ds, $mode = 'assoc')
    {
        switch($mode){
            case 'assoc':
                $out = $ds->fetch_assoc();
                break;
            case 'num':
                $out = $ds->fetch_row();
                break;
            case 'object':
                $out = $ds->fetch_object();
                break;
            case 'both':
                $out = $ds->fetch_array(MYSQLI_BOTH);
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
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getColumn(string $name, $dsq) : array
    {
        $col = [];
        if ( ! ($dsq instanceof mysqli_result)) {
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
     */
    public function getColumnNames($dsq) : array
    {
        $names = [];
        if ( ! ($dsq instanceof mysqli_result)) {
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
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getValue($dsq)
    {
        $out = false;
        if ( ! ($dsq instanceof mysqli_result)) {
            $dsq = $this->query($dsq);
        }
        if ($dsq) {
            $r = $this->getRow($dsq, 'num');
            $out = $r[0] ?? false;
        }

        return $out;
    }

    /**
     * @param string $table
     * @return array
     * @throws Exceptions\ConnectException
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getTableMetaData(string $table) : array
    {
        $metadata = [];
        if (! empty($table)) {
            $sql = 'SHOW FIELDS FROM '.$table;
            if ($ds = $this->query($sql)) {
                while ($row = $this->getRow($ds)) {
                    $fieldName = $row['Field'];
                    $metadata[$fieldName] = $row;
                }
            }
        }

        return $metadata;
    }

    /**
     * @param int $timestamp
     * @param string $fieldType
     * @return bool|false|string
     */
    public function prepareDate(int $timestamp, string $fieldType = 'DATETIME')
    {
        $date = false;
        if (! empty($timestamp) && $timestamp > 0) {
            switch ($fieldType) {
                case 'DATE' :
                    $date = date('Y-m-d', $timestamp);
                    break;
                case 'TIME' :
                    $date = date('H:i:s', $timestamp);
                    break;
                case 'YEAR' :
                    $date = date('Y', $timestamp);
                    break;
                case 'DATETIME':
                default :
                    $date = date('Y-m-d H:i:s', $timestamp);
                    break;
            }
        }

        return $date;
    }

    /**
     * @param mysqli_result $rs
     * @param bool $index
     * @return array
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function makeArray(mysqli_result $rs, bool $index = false) : array
    {
        $rsArray = [];
        $iterator = 0;
        while ($row = $this->getRow($rs)) {
            $returnIndex = $index !== false && isset($row[$index]) ? $row[$index] : $iterator;
            $rsArray[$returnIndex] = $row;
            $iterator++;
        }

        return $rsArray;
    }

    /**
     * @return string
     * @throws Exceptions\ConnectException
     */
    public function getVersion() : string
    {
        return $this->getConnect()->server_info;
    }

    /**
     * @param string $table
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     */
    public function optimize(string $table)
    {
        $rs = $this->query('OPTIMIZE TABLE '.$table);
        if ($rs) {
            $rs = $this->query('ALTER TABLE '.$table);
        }

        return $rs;
    }

    /**
     * @param string $table
     * @return bool|mysqli_result
     * @throws Exceptions\ConnectException
     */
    public function truncate(string $table)
    {
        return $this->query('TRUNCATE '.$table);
    }

    /**
     * @param mysqli_result $result
     * @param int $row_number
     * @return bool
     */
    public function dataSeek(mysqli_result $result, int $row_number) : bool
    {
        return $result->data_seek($row_number);
    }

    /**
     * @param mysqli_result|bool $result
     * @param string $sql
     * @param int $iteration
     * @param int $time
     * @throws Exceptions\ConnectException
     */
    protected function collectQuery($result, string $sql, int $iteration, int $time) : void
    {
        $debug = debug_backtrace();
        array_shift($debug);
        array_shift($debug);
        $path = array();
        foreach ($debug as $line) {
            $path[] = ($line['class'] ? ($line['class'] . '::') : null) . $line['function'];
            /*$path[] = [
                'method' => ($line['class'] ? ($line['class'] . '::') : null) . $line['function'],
                'file' => ($line['file'] ? ($line['file'] . ':') : null) . ($line['line'] ?? 0)
            ];*/
        }
        $path = implode(' > ', array_reverse($path));

        $this->queryCollection[$iteration] = [
            'sql' => $sql,
            'time' => sprintf('%2.2f ms',$time * 1000),
            'rows' => (stripos($sql, 'SELECT') === 0) ? $this->getRecordCount($result) : $this->getAffectedRows(),
            'path' => $path,
            //'event' => $modx->event->name,
            //'element' => [
            //      'name' => $modx->event->activePlugin ?? ($modx->currentSnippet ?? null),
            //      'type' => $modx->event->activePlugin ? 'Plugin' : ($modx->currentSnippet ? 'Snippet' : 'Source code')
            // ]
        ];

    }

    /**
     * @return string
     */
    public function lastQuery() : string
    {
        return $this->lastQuery;
    }

    /**
     * @return array
     */
    public function getAllExecutedQuery() : array
    {
        return $this->queryCollection;
    }

    public function flushExecutedQuery() : self
    {
        $this->queryCollection = [];
        $this->executedQueries = 0;
        $this->queryTime = 0;
        $this->lastQuery = '';
    }

    /**
     * @return string
     */
    public function renderExecutedQuery() : string
    {
        $out = '';

        foreach ($this->getAllExecuteQuery() as $i => $query) {
            $out .= '<fieldset style="text-align:left">';
            $out .= '<legend>Query ' . $i . ' - ' . $query['time'] . '</legend>';
            $out .= $query['sql'] . '<br><br>';
            if (! empty($query['element'])) {
                $out .= $query['element']['type'] . '  => ' . $query['element']['name'] . '<br>';
            }
            if (! empty($query['event'])) {
                $out .= 'Current Event  => ' . $query['event'] . '<br>';
            }
            $out .= 'Affected Rows => ' . $query['rows'] . '<br>';
            if (! empty($query['path'])) {
                $out .= 'Functions Path => ' . $query['path'] . '<br>';
            }
            /*$out .= 'Functions Path => ' . $query['path']['method'] . '<br>';
            $out .= empty($query['path']['file']) ?: $query['path']['file'] . '<br />';*/
            $out .= '</fieldset><br />';
        }

        return $out;
    }

    /**
     * @param bool $format
     * @return int
     */
    public function getConnectionTime(bool $format = false) : int
    {
        return $format ? sprintf('%2.4f', $this->connectionTime) : $this->connectionTime;
    }

    /**
     * @return string
     */
    public function renderConnectionTime() : string
    {
        return '<fieldset style="text-align:left">' .
                    '<legend>Database connection</legend>' .
                    'Database connection was created in ' . $this->getConnectionTime(true) . ' s.' .
                '</fieldset>' .
                '<br />';
    }

    /**
     * @param bool $flag
     * @return $this
     */
    public function setDebug(bool $flag) : self
    {
        $this->debug = $flag;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDebug() : bool
    {
        return $this->debug;
    }

    /**
     * @param string $table
     * @return string
     * @throws Exceptions\TableNotDefinedException
     */
    public function getFullTableName(string $table) : string
    {
        if(empty($table)) {
            throw new Exceptions\TableNotDefinedException($table);
        }

        return implode('.', [
            '`' . $this->getConfig('dbase') . '`',
            '`' . $this->getConfig('table_prefix') . $table . '`'
        ]);
    }
}
