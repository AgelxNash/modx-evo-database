<?php namespace AgelxNash\Modx\Evo\Database\Traits;

trait ConfigTrait
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * @param null|string $key
     * @return mixed
     */
    public function getConfig($key = null)
    {
        return ($key === null ? $this->config : (isset($this->config[$key]) ? $this->config[$key] : null));
    }

    /**
     * @param $data
     * @return $this
     */
    public function setConfig($data)
    {
        $this->config = $data;

        return $this;
    }
}
