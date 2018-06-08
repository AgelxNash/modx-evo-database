<?php namespace AgelxNash\Modx\Evo\Database\Interfaces;

interface DriverInterface
{
    /**
     * @param array $config
     */
    public function __construct(array $config = []);

    /**
     * @return mixed
     */
    public function getConnect();

    /**
     * @return mixed
     */
    public function connect();

    /**
     * @return DriverInterface
     */
    public function disconnect() : self;

    /**
     * @return bool
     */
    public function isConnected() : bool;

    /**
     * @param $data
     * @return mixed
     */
    public function escape($data);

    /**
     * @return mixed
     */
    public function getInsertId();

    /**
     * @return int
     */
    public function getAffectedRows() : int;

    /**
     * @return string
     */
    public function getVersion() : string;

    /**
     * @param $result
     * @return int
     */
    public function getRecordCount($result) : int;

    /**
     * @param string $charset
     * @param string $collation
     * @param null|string $method
     * @return bool
     */
    public function setCharset(string $charset, string $collation, $method = null) : bool;

    /**
     * @param $result
     * @return bool
     */
    public function isResult($result) : bool;

    /**
     * @param $result
     * @return int
     */
    public function numFields($result) : int;

    /**
     * @param $result
     * @param int $col
     * @return null|string
     */
    public function fieldName($result, $col = 0) :? string;

    /**
     * @param string $name
     * @return bool
     */
    public function selectDb(string $name) : bool;

    /**
     * @param $result
     * @param string $mode
     * @return mixed
     */
    public function getRow($result, $mode = 'assoc');

    /**
     * @param string $query
     * @return mixed
     */
    public function query(string $query);

    /**
     * @param string $name
     * @param $result
     * @return array
     */
    public function getColumn(string $name, $result) : array;

    /**
     * @param $result
     * @return array
     */
    public function getColumnNames($result) : array;

    /**
     * @param $result
     * @return mixed
     */
    public function getValue($result);

    /**
     * @param $result
     * @return mixed
     */
    public function getTableMetaData($result);

    /**
     * @return string|null
     */
    public function getLastError() :? string;

    /**
     * @return string|null
     */
    public function getLastErrorNo() :? string;

    /**
     * @param $result
     * @param int $position
     * @return bool
     */
    public function dataSeek(&$result, $position) : bool;
}
