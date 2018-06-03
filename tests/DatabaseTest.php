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
        $this->instance = new Database\Database();
    }

    public function testConnection()
    {
        try {
            $this->instance->connect();
            $this->assertTrue(false, 'Need ConnectException');
        } catch (Database\Exceptions\ConnectException $exception) {
            $this->assertInstanceOf(
                Database\Exceptions\ConnectException::class,
                $exception
            );
        }
    }

    public function testNoConnection()
    {
        $this->assertFalse($this->instance->isConnected());
    }

    public function testIsResult()
    {
        $this->assertFalse($this->instance->isResult(null));
    }

    public function testConfig()
    {
        $this->assertSame(
            [
                'host' => '',
                'base' => '',
                'user' => '',
                'pass' => '',
                'prefix' => '',
                'charset' => 'utf8mb4',
                'method' => 'SET CHARACTER SET'
            ],
            (new Database\Database())->getConfig(),
            'STEP 1/5'
        );

        $this->assertSame(
            'agel-nash.ru',
            (new Database\Database('agel-nash.ru'))->getConfig('host'),
            'STEP 2/5'
        );

        $this->assertEquals(
            null,
            (new Database\Database())->getConfig('password'),
            'STEP 3/5'
        );

        $this->assertSame(
            '',
            (new Database\Database())->getConfig('pass'),
            'STEP 4/5'
        );

        $this->assertSame(
            'utf8mb4',
            (new Database\Database())->getConfig('charset'),
            'STEP 5/5'
        );
    }
}
