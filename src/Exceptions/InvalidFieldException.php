<?php namespace AgelxNash\Modx\Evo\Database\Exceptions;

class InvalidFieldException extends Exception
{
    protected $data;

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }
}
