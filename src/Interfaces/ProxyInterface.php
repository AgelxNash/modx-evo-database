<?php namespace AgelxNash\Modx\Evo\Database\Interfaces;

use AgelxNash\Modx\Evo\Database\Exceptions;

interface ProxyInterface extends ConfigInterface
{
    /**
     * @return string|null
     */
    public function getLastError();

    /**
     * @return string|null
     */
    public function getLastErrorNo();

    /**
     * @return mixed
     */
    public function connect();

    /**
     * @return DriverInterface
     */
    public function disconnect();

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
     * @param string $charset
     * @param null|string $method
     * @return bool
     */
    public function setCharset($charset, $method = null);

    /**
     * @param string $name
     * @return bool
     */
    public function selectDb($name);

    /**
     * @param $data
     * @return mixed
     */
    public function escape($data);

    /**
     * @param string $sql
     * @return mixed
     */
    public function query($sql);

    /**
     * @param mixed $result
     * @return int
     */
    public function getRecordCount($result);

    /**
     * @param $result
     * @param string $mode
     * @return mixed
     * @throws Exceptions\UnknownFetchTypeException
     */
    public function getRow($result, $mode = 'assoc');

    /**
     * @return string
     */
    public function getVersion();

    /**
     * @return mixed
     */
    public function getInsertId();

    /**
     * @return int
     */
    public function getAffectedRows();

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
