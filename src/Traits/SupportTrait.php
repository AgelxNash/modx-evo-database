<?php namespace AgelxNash\Modx\Evo\Database\Traits;

use AgelxNash\Modx\Evo\Database\Exceptions;

trait SupportTrait
{
    /**
     * @param string $table
     * @return string
     * @throws Exceptions\TableNotDefinedException
     */
    public function getFullTableName(string $table) : string
    {
        if (empty($table)) {
            throw new Exceptions\TableNotDefinedException($table);
        }

        return implode('.', [
            '`' . $this->getConfig('base') . '`',
            '`' . $this->getConfig('prefix') . $table . '`'
        ]);
    }

    /**
     * @param int $timestamp
     * @param string $fieldType
     * @return bool|false|string
     * @deprecated
     */
    public function prepareDate(int $timestamp, string $fieldType = 'DATETIME')
    {
        $date = false;
        if (! empty($timestamp) && $timestamp > 0) {
            switch ($fieldType) {
                case 'DATE':
                    $date = date('Y-m-d', $timestamp);
                    break;
                case 'TIME':
                    $date = date('H:i:s', $timestamp);
                    break;
                case 'YEAR':
                    $date = date('Y', $timestamp);
                    break;
                case 'DATETIME':
                default:
                    $date = date('Y-m-d H:i:s', $timestamp);
                    break;
            }
        }

        return $date;
    }

    /**
     * @param string|array $data
     * @return string
     */
    protected function prepareFields($data) : string
    {
        if (\is_array($data)) {
            $tmp = [];
            foreach ($data as $alias => $field) {
                $tmp[] = ($alias !== $field && ! \is_int($alias)) ? ($field . ' as `' . $alias . '`') : $field;
            }

            $data = implode(',', $tmp);
        }
        if (empty($data)) {
            $data = '*';
        }

        return $data;
    }

    /**
     * @param string|null $value
     * @return string
     */
    protected function prepareNull($value) : string
    {
        switch (true) {
            case ($value === null || (\is_scalar($value) && strtolower($value) === 'null')):
                $value = 'NULL';
                break;
            case \is_scalar($value):
                $value = "'" . $value . "'";
                break;
            default:
                throw (new Exceptions\InvaludFieldException('NULL'))
                    ->setData($value);
        }

        return $value;
    }

    /**
     * @param string|array $data
     * @param int $level
     * @param bool $skipFieldNames
     * @return array|string
     * @throws Exceptions\TooManyLoopsException
     */
    public function prepareValues($data, $level = 1, $skipFieldNames = false)
    {
        $fields = [];
        $values = [];
        $maxLevel = $level;
        $wrap = false;

        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                if (\is_array($value)) {
                    if ($level > 2) {
                        throw new Exceptions\TooManyLoopsException();
                    }
                    $maxLevel++;
                    $out = $this->prepareValues($value, $level + 1);
                    if (empty($fields)) {
                        $fields = $out['fields'];
                    } elseif ($fields !== $out['fields'] && $skipFieldNames === false) {
                        throw (new Exceptions\InvaludFieldException("Don't match field names"))
                            ->setData($data);
                    }
                    $wrap = true;
                    $values[] = $out['values'];
                } else {
                    $fields[] = $key;
                    $values[] = $this->prepareNull($value);
                }
            }
            if (\is_array($values)) {
                $values = implode(', ', $values);
            }
            if ($wrap === false) {
                $values = '(' . $values . ')';
            }
        }

        if (! \is_scalar($values)) {
            throw (new Exceptions\InvaludFieldException('values'))
                ->setData($values);
        }

        if (($fields = $this->checkFields($fields, $maxLevel, $skipFieldNames))=== false) {
            throw (new Exceptions\InvaludFieldException('fields name'))
                ->setData($data);
        }

        if ($level === 2) {
            $data = compact('fields', 'values');
        } else {
            $data = (empty($fields) ? '' : $fields . ' VALUES ') . $values;
        }
        return $data;
    }

    /**
     * @param mixed $fields
     * @param int $level
     * @param bool $skipFieldNames
     * @return bool|string
     */
    private function checkFields($fields, $level, bool $skipFieldNames = false)
    {
        if (\is_array($fields) && $skipFieldNames === false) {
            $onlyNumbers = true;
            foreach ($fields as $name) {
                if (! \is_int($name)) {
                    $onlyNumbers = false;
                    break;
                }
            }

            if ($onlyNumbers === true) {
                $fields = ($level === 2) ? false : '';
            } else {
                $fields = '(`' . implode('`, `', $fields) . '`)';
            }
        }

        return $fields;
    }

    /**
     * @param string|array $data
     * @return string
     */
    protected function prepareValuesSet($data) : string
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->prepareNull($value);
            }

            foreach ($data as $key => $value) {
                $data[$key] = "`{$key}` = " . $value;
            }
            $data = implode(', ', $data);
        }

        return $data;
    }

    /**
     * @param string|array $data
     * @param bool $hasArray
     * @return string
     * @throws Exceptions\TableNotDefinedException
     */
    protected function prepareFrom($data, bool $hasArray = false) : string
    {
        if (\is_array($data) && $hasArray === true) {
            $tmp = [];
            foreach ($data as $table) {
                $tmp[] = $table;
            }
            $data = implode(' ', $tmp);
        }
        if (! is_scalar($data) || empty($data)) {
            throw new Exceptions\TableNotDefinedException($data);
        }

        return $data;
    }

    /**
     * @param array|string $data
     * @return string
     */
    protected function prepareWhere($data) : string
    {
        if (\is_array($data)) {
            $data = implode(' ', $data);
        }
        $data = trim($data);
        if (! empty($data) && stripos($data, 'WHERE') !== 0) {
            $data = "WHERE {$data}";
        }

        return $data;
    }

    /**
     * @param string $data
     * @return string
     */
    protected function prepareOrder($data) : string
    {
        $data = trim($data);
        if (! empty($data) && stripos($data, 'ORDER') !== 0) {
            $data = "ORDER BY {$data}";
        }

        return $data;
    }

    /**
     * @param string $data
     * @return string
     */
    protected function prepareLimit($data) : string
    {
        $data = trim($data);
        if (! empty($data) && stripos($data, 'LIMIT') !== 0) {
            $data = "LIMIT {$data}";
        }

        return $data;
    }
}
