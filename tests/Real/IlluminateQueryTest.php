<?php namespace AgelxNash\Modx\Evo\Database\Tests\Real;

use AgelxNash\Modx\Evo\Database\Tests\RealQueryTest;
use PDOStatement;
use AgelxNash\Modx\Evo\Database\Drivers\IlluminateDriver;
use Illuminate;

class IlluminateQueryTest extends RealQueryTest
{
    protected $driver = IlluminateDriver::class;
    protected $connectorClass = Illuminate\Database\ConnectionInterface::class;
    protected $resultClass = PDOStatement::class;

    protected function setUp()
    {
        if (!class_exists(Illuminate\Database\Connection::class)) {
            $this->markTestSkipped(
                'The Illuminate\Database\Connection class is not available.'
            );
        }

        parent::setUp();
    }

    public function testGetRow()
    {
        $this->markTestSkipped('No implemented');
    }
}
