<?php namespace AgelxNash\Modx\Evo\Database\Exceptions;

use Exception;

class QueryException extends Exception
{
    protected $query = '';

    public function setQuery($query)
    {
        $this->query = $query;
    }

    public function getQuery()
    {
        return $this->query;
    }
}
