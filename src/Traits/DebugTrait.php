<?php namespace AgelxNash\Modx\Evo\Database\Traits;

use mysqli_result;
use AgelxNash\Modx\Evo\Database\Exceptions;

trait DebugTrait
{
    protected $debug = false;
    protected $queryCollection = [];
    protected $queryTime = 0;
    protected $executedQueries = 0;
    protected $lastQuery = '';
    protected $connectionTime = 0;

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
     * @param null|string $query
     * @throws Exceptions\ConnectException
     * @throws Exceptions\QueryException
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
            'time' => sprintf('%2.2f ms', $time * 1000),
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
    public function isDebug() : bool
    {
        return $this->debug;
    }
}
