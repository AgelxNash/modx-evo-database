<?php namespace AgelxNash\Modx\Evo\Database\Traits;

use AgelxNash\Modx\Evo\Database\Exceptions;

trait DebugTrait
{
    protected $debug = false;
    protected $queryCollection = [];
    protected $queryTime = 0;
    protected $executedQueries = 0;
    protected $lastQuery = '';
    protected $connectionTime = 0;

    public $ignoreErrors = [
        '42S22', // SQLSTATE: 42S22 (ER_BAD_FIELD_ERROR) Unknown column '%s' in '%s'
        '42S21', // SQLSTATE: 42S21 (ER_DUP_FIELDNAME) Duplicate column name '%s'
        '42000', // SQLSTATE: 42000 (ER_DUP_KEYNAME) Duplicate key name '%s'
        '23000', // SQLSTATE: 23000 (ER_DUP_ENTRY) Duplicate entry '%s' for key %d
        '42000' // SQLSTATE: 42000 (ER_CANT_DROP_FIELD_OR_KEY) Can't DROP '%s'; check that column/key exists
    ];

    protected $timeFormat = '%2.5f';

    /**
      * @return mixed
     * @throws Exceptions\Exception
      */
    abstract public function getDriver();

    /**
     * @param $result
     * @return int
     */
    abstract public function getRecordCount($result) : int;

    /**
     * @return int
     * @throws Exceptions\Exception
     */
    abstract public function getAffectedRows() : int;

    /**
     * @return string|null
     * @throws Exceptions\Exception
     */
    public function getLastError() :? string
    {
        return $this->getDriver()->getLastError();
    }

    /**
     * @return string|null
     * @throws Exceptions\Exception
     */
    public function getLastErrorNo() :? string
    {
        return (string)$this->getDriver()->getLastErrorNo();
    }

    /**
     * @return array
     */
    public function getIgnoreErrors() : array
    {
        return $this->ignoreErrors;
    }

    /**
     * @param int $error
     * @return DebugTrait
     */
    public function addIgnoreErrors(int $error) : self
    {
        $this->ignoreErrors[] = $error;

        return $this;
    }

    /**
     * @return DebugTrait
     */
    public function flushIgnoreErrors() : self
    {
        $this->ignoreErrors = [];

        return $this;
    }

    /**
     * @param array $errors
     * @return DebugTrait
     */
    public function setIgnoreErrors(array $errors = []) : self
    {
        $this->flushIgnoreErrors();

        foreach ($errors as $error) {
            $this->addIgnoreErrors($error);
        }

        return $this;
    }

    /**
     * @param null|string $query
     * @return bool
     * @throws Exceptions\Exception
     */
    public function checkLastError($query = null) : bool
    {
        if ($this->getIgnoreErrors() === [] || \in_array($this->getLastErrorNo(), $this->getIgnoreErrors(), true)) {
            return false;
        }

        throw (new Exceptions\QueryException($this->getLastError()))
            ->setQuery($query);
    }

    /**
     * @param mixed $result
     * @param string $sql
     * @param int $iteration
     * @param int $time
     * @throws Exceptions\Exception
     */
    protected function collectQuery($result, string $sql, int $iteration, int $time) : void
    {
        $debug = debug_backtrace();
        array_shift($debug);
        array_shift($debug);
        $path = [];
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
            'time' => $time,
            'rows' => (stripos($sql, 'SELECT') === 0 && $this->getDriver()->isResult($result)) ?
                $this->getRecordCount($result) : $this->getAffectedRows(),
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
    public function getLastQuery() : string
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

    /**
     * @return $this
     */
    public function flushExecutedQuery() : self
    {
        $this->queryCollection = [];
        $this->executedQueries = 0;
        $this->queryTime = 0;
        $this->lastQuery = '';

        return $this;
    }

    /**
     * @return string
     */
    public function renderExecutedQuery() : string
    {
        $out = '';

        foreach ($this->getAllExecutedQuery() as $i => $query) {
            $out .= '<fieldset style="text-align:left">';
            $out .= '<legend>Query ' . $i . ' - ' . sprintf($this->timeFormat, $query['time']) . '</legend>';
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
     * @return string|float
     */
    public function getConnectionTime(bool $format = false)
    {
        return $format ? sprintf($this->timeFormat, $this->connectionTime) : $this->connectionTime;
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
    public function isDebug() : bool
    {
        return $this->debug;
    }
}
