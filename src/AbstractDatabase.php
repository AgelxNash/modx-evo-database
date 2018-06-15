<?php namespace AgelxNash\Modx\Evo\Database;

abstract class AbstractDatabase implements Interfaces\DatabaseInterface
{
    use Traits\DebugTrait,
        Traits\SupportTrait;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var Interfaces\DriverInterface
     */
    protected $driver;

    /**
     * @var int
     */
    protected $safeLoopCount = 1000;

    /**
     * @param $data
     * @return $this
     */
    public function setConfig($data)
    {
        $this->config = $data;

        return $this;
    }

    /**
     * @param null|string $key
     * @return mixed
     */
    public function getConfig($key = null)
    {
        return ($key === null ? $this->config : (isset($this->config[$key]) ? $this->config[$key] : null));
    }

    /**
     * @return Interfaces\DriverInterface
     * @throws Exceptions\Exception
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @param string|Interfaces\DriverInterface $driver
     * @return Interfaces\DriverInterface
     * @throws Exceptions\Exception
     */
    public function setDriver($driver)
    {
        if (! \in_array(Interfaces\DriverInterface::class, class_implements($driver), true)) {
            throw new Exceptions\DriverException(
                $driver . ' should implements the ' . Interfaces\DriverInterface::class
            );
        }

        if (is_scalar($driver)) {
            $this->driver = new $driver($this->getConfig());
        } else {
            $this->driver = $driver;
            $this->config = array_merge($this->config, $driver->getConfig());
        }

        return $this->driver;
    }

    /**
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function connect()
    {
        $tStart = microtime(true);

        $out = $this->getDriver()->getConnect();

        $totalTime = microtime(true) - $tStart;
        if ($this->isDebug()) {
            $this->connectionTime = $totalTime;
        }
        $this->setCharset(
            $this->getConfig('charset'),
            $this->getConfig('collation'),
            $this->getConfig('method')
        );

        return $out;
    }

    /**
     * @return $this
     */
    public function disconnect()
    {
        $this->getDriver()->disconnect();

        $this->connectionTime = 0;
        $this->flushExecutedQuery();

        return $this;
    }

    /**
     * @param $result
     * @return bool
     */
    public function isResult($result)
    {
        return $this->getDriver()->isResult($result);
    }

    /**
     * @param $result
     * @return int
     */
    public function numFields($result)
    {
        return $this->getDriver()->numFields($result);
    }

    /**
     * @param $result
     * @param int $col
     * @return string|null
     */
    public function fieldName($result, $col = 0)
    {
        return $this->getDriver()->fieldName($result, $col);
    }

    /**
     * @param string $charset
     * @param string $collation
     * @param string|null $method
     * @return bool
     * @throws Exceptions\Exception
     */
    public function setCharset($charset, $collation, $method = null)
    {
        $tStart = microtime(true);

        $result = $this->getDriver()->setCharset($charset, $collation, $method);

        $this->queryTime += microtime(true) - $tStart;

        return $result;
    }

    /**
     * @param string $name
     * @return bool
     * @throws Exceptions\Exception
     */
    public function selectDb($name)
    {
        $tStart = microtime(true);

        $result = $this->getDriver()->selectDb($name);

        $this->queryTime += microtime(true) - $tStart;

        return $result;
    }

    /**
     * @param string|array $data
     * @param int $safeCount
     * @return array|string
     * @throws Exceptions\Exception
     */
    public function escape($data, $safeCount = 0)
    {
        $safeCount++;
        if ($this->safeLoopCount < $safeCount) {
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
            $data = $this->getDriver()->escape($data);
        }

        return $data;
    }

    /**
     * @param mixed $sql
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function query($sql)
    {
        $tStart = microtime(true);
        if (\is_array($sql)) {
            $sql = implode("\n", $sql);
        }
        $this->lastQuery = $sql;

        $result = $this->getDriver()->query(
            $this->getLastQuery()
        );

        if ($result === false) {
            $this->checkLastError($this->getLastQuery());
        } else {
            $tend = microtime(true);
            $totalTime = $tend - $tStart;
            $this->queryTime += $totalTime;
            if ($this->isDebug()) {
                $this->collectQuery(
                    $result,
                    $this->getLastQuery(),
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
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function delete($table, $where = '', $orderBy = '', $limit = '')
    {
        $table = $this->prepareFrom($table);
        $where = $this->prepareWhere($where);
        $orderBy = $this->prepareOrder($orderBy);
        $limit = $this->prepareOrder($limit);

        $result = $this->query("DELETE FROM {$table} {$where} {$orderBy} {$limit}");
        return $this->isResult($result) ? true : $result;
    }

    /**
     * @param array|string $fields
     * @param array|string $tables
     * @param array|string $where
     * @param string $orderBy
     * @param string $limit
     * @return mixed
     * @throws Exceptions\Exception
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
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function update($values, $table, $where = '')
    {
        $table = $this->prepareFrom($table);
        $values = $this->prepareValuesSet($values);
        if (mb_strtoupper(mb_substr($values, 0, 4)) !== 'SET ') {
            $values = 'SET ' . $values;
        }
        $where = $this->prepareWhere($where);

        $result = $this->query("UPDATE {$table} {$values} {$where}");
        return $this->isResult($result) ? true : $result;
    }

    /**
     * @param array|string $fields
     * @param string $table
     * @param array|string $fromFields
     * @param string $fromTable
     * @param array|string $where
     * @param string $limit
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function insert(
        $fields,
        $table,
        $fromFields = '*',
        $fromTable = '',
        $where = '',
        $limit = ''
    ) {
        $table = $this->prepareFrom($table);

        $useFields = null;
        $lid = null;

        if (\is_array($fields)) {
            $useFields = empty($fromTable) ?
                $this->prepareValues($fields) :
                $this->prepareFields($fields, true);
        } else {
            $useFields = $fields;
        }

        if (empty($useFields) || ! \is_scalar($useFields) || ($useFields === '*' && ! empty($fromTable))) {
            throw (new Exceptions\InvalidFieldException('Invalid insert fields'))
                ->setData($fields);
        }

        if (empty($fromTable)) {
            $this->query("INSERT INTO {$table} {$useFields}");
        } else {
            if (empty($fromFields) || $fromFields === '*') {
                $fromFields = $this->prepareFields($fields, true);
            } else {
                $fromFields = $this->prepareFields($fromFields, true);
            }

            $where = $this->prepareWhere($where);
            $limit = $this->prepareLimit($limit);

            $lid = $this->query(
                "INSERT INTO {$table} ({$useFields}) SELECT {$fromFields} FROM {$fromTable} {$where} {$limit}"
            );
            $lid = $this->isResult($lid) ? true : $lid;
        }

        if ($lid === null && ($lid = $this->getInsertId()) === false) {
            throw new Exceptions\GetDataException("Couldn't get last insert key!");
        }

        return $this->convertValue($lid);
    }

    /**
     * @param string|array $fields
     * @param string $table
     * @param array|string $where
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function save($fields, $table, $where = '')
    {
        if ($where === '') {
            $mode = 'insert';
        } else {
            $result = $this->select('*', $table, $where);

            if ($this->getRecordCount($result) === 0) {
                $mode = 'insert';
            } else {
                $mode = 'update';
            }
        }

        return ($mode === 'insert') ? $this->insert($fields, $table) : $this->update($fields, $table, $where);
    }

    /**
     * @param string $table
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function optimize($table)
    {
        $result = $this->query('OPTIMIZE TABLE ' . $table);
        if ($result !== false) {
            $result = $this->alterTable($table);
        }

        return $this->isResult($result) ? true : $result;
    }

    /**
     * @param string $table
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function alterTable($table)
    {
        $result = $this->query('ALTER TABLE ' . $table);

        return $this->isResult($result) ? true : $result;
    }

    /**
     * @param string $table
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function truncate($table)
    {
        $result = $this->query('TRUNCATE ' . $table);

        return $this->isResult($result) ? $this->getValue($result) : $result;
    }

    /**
     * @param $result
     * @return int
     */
    public function getRecordCount($result)
    {
        return $this->getDriver()->getRecordCount($result);
    }

    /**
     * @param $result
     * @param string $mode
     * @return array|mixed|object|\stdClass
     * @throws Exceptions\Exception
     */
    public function getRow($result, $mode = 'assoc')
    {
        if (\is_scalar($result)) {
            $result = $this->query($result);
        }

        return $this->getDriver()->getRow($result, $mode);
    }

    /**
     * @param string string $name
     * @param mixed $result
     * @return array
     * @throws Exceptions\Exception
     */
    public function getColumn($name, $result)
    {
        if (\is_scalar($result)) {
            $result = $this->query($result);
        }

        return $this->getDriver()->getColumn($name, $result);
    }

    /**
     * @param mixed $result
     * @return array
     * @throws Exceptions\Exception
     */
    public function getColumnNames($result)
    {
        if (\is_scalar($result)) {
            $result = $this->query($result);
        }
        return $this->getDriver()->getColumnNames($result);
    }

    /**
     * @param mixed $result
     * @return bool|mixed
     * @throws Exceptions\Exception
     */
    public function getValue($result)
    {
        if (\is_scalar($result)) {
            $result = $this->query($result);
        }

        return $this->convertValue(
            $this->getDriver()->getValue($result)
        );
    }

    /**
     * @param string $table
     * @return array
     * @throws Exceptions\Exception
     */
    public function getTableMetaData($table)
    {
        $metadata = [];
        if (! empty($table)) {
            $sql = 'SHOW FIELDS FROM ' . $table;
            $result = $this->query($sql);
            $metadata = $this->getDriver()->getTableMetaData($result);
        }

        return $metadata;
    }

    /**
     * @param $result
     * @param bool $index
     * @return array
     * @throws Exceptions\Exception
     */
    public function makeArray($result, $index = false)
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
     * @return string
     * @throws Exceptions\Exception
     */
    public function getVersion()
    {
        return $this->getDriver()->getVersion();
    }

    /**
     * @return mixed
     * @throws Exceptions\Exception
     */
    public function getInsertId()
    {
        return $this->convertValue(
            $this->getDriver()->getInsertId()
        );
    }

    /**
     * @return int
     * @throws Exceptions\Exception
     */
    public function getAffectedRows()
    {
        return $this->getDriver()->getAffectedRows();
    }
}