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
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_BASE'] ?? 'modx',
            $_ENV['DB_USER'] ?? 'homestead',
            $_ENV['DB_PASSWORD'] ?? 'secret',
            $_ENV['DB_PREFIX'] ?? 'modx_',
            $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            $_ENV['DB_METHOD'] ?? 'SET NAMES'
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
