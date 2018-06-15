<?php namespace AgelxNash\Modx\Evo\Database\Tests\Real;

use AgelxNash\Modx\Evo\Database\Tests\RealQueryTest;
use mysqli;
use mysqli_result;
use AgelxNash\Modx\Evo\Database\Drivers\MySqliDriver;

class MySqliQuery extends RealQueryTest
{
    protected $driver = MySqliDriver::class;
    protected $connectorClass = mysqli::class;

    protected $resultClass = mysqli_result::class;

    protected function setUp()
    {
        if (!class_exists(mysqli::class)) {
            $this->markTestSkipped(
                'The mysqli class is not available.'
            );
        }

        parent::setUp();
    }
}
