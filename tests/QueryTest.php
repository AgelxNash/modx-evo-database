<?php namespace AgelxNash\Modx\Evo\Database\Tests;

use PHPUnit\Framework\TestCase;
use AgelxNash\Modx\Evo\Database;
use mysqli;

class QueryTest extends TestCase
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

        $this->instance = new Database\Database(
            $_SERVER['DB_HOST'] ?? 'localhost',
            $_SERVER['DB_BASE'] ?? 'modx',
            $_SERVER['DB_USER'] ?? 'homestead',
            $_SERVER['DB_PASSWORD'] ?? 'secret',
            $_SERVER['DB_PREFIX'] ?? 'modx_',
            $_SERVER['DB_CHARSET'] ?? 'utf8mb4',
            $_SERVER['DB_METHOD'] ?? 'SET NAMES'
        );
    }

    public function testConnect()
    {
        $this->assertInstanceOf(
            mysqli::class,
            $this->instance->connect()
        );
    }
}
