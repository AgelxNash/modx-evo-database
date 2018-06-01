<?php namespace AgelxNash\Modx\Evo\Database\Tests;

use PHPUnit\Framework\TestCase;
use AgelxNash\Modx\Evo\Database;

class DatabaseTest extends TestCase
{
    /**
     * @var Database\Database
     */
    protected $instance;

    protected function setUp()
    {
        if (! extension_loaded('mysqli')) {
            $this->markTestSkipped(
                'The MySQLi extension is not available.'
            );
        }

        $this->instance = new Database\Database();
    }

    public function testNoConnection()
    {
        $this->assertFalse($this->instance->isConnected());
    }
}
