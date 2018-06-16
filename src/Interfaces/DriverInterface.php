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
    public function disconnect();

    /**
     * @return bool
     */
    public function isConnected();

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
    public function getAffectedRows();

    /**
     * @return string
     */
    public function getVersion();

    /**
     * @param mixed $result
     * @return int
     */
    public function getRecordCount($result);

    /**
     * @param string $charset
     * @param string $collation
     * @param null|string $method
     * @return bool
     */
    public function setCharset($charset, $collation, $method = null);

    /**
     * @param $result
     * @return bool
     */
    public function isResult($result);

    /**
     * @param $result
     * @return int
     */
    public function numFields($result);

    /**
     * @param $result
     * @param int $col
     * @return null|string
     */
    public function fieldName($result, $col = 0);

    /**
     * @param string $name
     * @return bool
     */
    public function selectDb($name);

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
    public function query($query);

    /**
     * @return string|null
     */
    public function getLastError();

    /**
     * @return string|null
     */
    public function getLastErrorNo();

    /**
     * @param string $name
     * @param $result
     * @return array
     */
    public function getColumn($name, $result);

    /**
     * @param $result
     * @return array
     */
    public function getColumnNames($result);

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


}
