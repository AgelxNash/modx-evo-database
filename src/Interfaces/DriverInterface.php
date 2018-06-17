<?php namespace AgelxNash\Modx\Evo\Database\Interfaces;

interface DriverInterface extends ProxyInterface
{
    /**
     * @param array $config
     */
    public function __construct(array $config = []);

    /**
     * @return mixed
     */
    public function getConnect();

    /**
     * @return bool
     */
    public function isConnected();
}
