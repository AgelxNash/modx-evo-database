<?php namespace AgelxNash\Modx\Evo\Database\Exceptions;

class QueryException extends Exception
{
    protected $query = '';

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
