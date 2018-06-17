<?php namespace AgelxNash\Modx\Evo\Database\Exceptions;

class InvalidFieldException extends Exception
{
    /**
     * @var string
     */
    protected $data;

    /**
     * @param string $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }
}
