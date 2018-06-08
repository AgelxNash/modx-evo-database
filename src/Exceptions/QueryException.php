<?php namespace AgelxNash\Modx\Evo\Database\Exceptions;

use Throwable;

class QueryException extends Exception
{
    protected $query = '';
    /**
     * @var string
     */
    protected $code;

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, (int)$code, $previous);

        $this->code = $code;
    }

    public function setQuery($query) : self
    {
        $this->query = $query;

        return $this;
    }

    public function getQuery() : string
    {
        return $this->query;
    }
}
