<?php namespace AgelxNash\Modx\Evo\Database\Tests;

use PHPUnit\Framework\TestCase;
use AgelxNash\Modx\Evo\Database;
use mysqli;
use mysqli_result;

class QueryTest extends TestCase
{
    /**
     * @var Database\Database
     */
    protected $instance;

    /**
     * @var string
     */
    protected $table;

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
            $_SERVER['DB_PREFIX'] ?? '{PREFIX}',
            $_SERVER['DB_CHARSET'] ?? 'utf8mb4',
            $_SERVER['DB_METHOD'] ?? 'SET NAMES'
        );

        $this->instance->connect();
        $this->table = $this->instance->getFullTableName('site_content');

    }

    public function testConnect()
    {
        $this->assertInstanceOf(
            mysqli::class,
            $this->instance->getConnect()
        );
    }

    public function testSelect()
    {
        $this->assertInstanceOf(
            mysqli_result::class,
            $this->instance->query('SELECT * FROM ' . $this->table . ' WHERE parent = 0 ORDER BY pagetitle DESC LIMIT 10')
        );

        $this->assertInstanceOf(
            mysqli_result::class,
            $this->instance->select('*', $this->table, 'parent = 0', 'pagetitle DESC', '10')
        );

        $this->assertInstanceOf(
            mysqli_result::class,
            $this->instance->select(
                ['id', 'pagetitle', 'title' => 'longtitle'],
                ['c' => $this->table],
                ['parent = 0'],
                'ORDER BY pagetitle DESC',
                'LIMIT 10'
            )
        );

        $this->assertInstanceOf(
            mysqli_result::class,
            $this->instance->select(
                ['id', 'pagetitle', 'title' => 'longtitle'],
                ['c' => $this->table],
                ['parent = 0'],
                'ORDER BY pagetitle DESC',
                'LIMIT 10'
            )
        );
    }

    public function testQuery()
    {
        $this->assertInstanceOf(
            mysqli_result::class,
            $this->instance->query([
                'SELECT *',
                'FROM ' . $this->table,
                'WHERE parent = 0',
                'ORDER BY id DESC',
                'LIMIT 10',
            ])
        );

        $table = $this->instance->getFullTableName('clone');

        $this->assertTrue(
            $this->instance->query(
                'CREATE TABLE '. $table . ' LIKE ' . $this->table
            )
        );

        try {
            $this->instance->query(
                'CREATE TABLE ' . $table . ' LIKE ' . $this->table
            );
            $this->assertTrue(false, 'Need QueryException');
        } catch(Database\Exceptions\QueryException $exception) {
            $this->assertEquals(
                1050,
                $exception->getCode()
            );
        }

        $this->assertTrue(
            true,
            $this->instance->query(
                'DROP TABLE ' . $table
            )
        );

    }

    public function testMakeArray()
    {
        $results = $this->instance->query('SELECT * FROM ' . $this->table . ' WHERE parent = 0 ORDER BY pagetitle DESC LIMIT 10');

        $data = $this->instance->makeArray($results);

        $this->assertThat(
            $data,
            $this->isType('array')
        );

        $this->assertCount(10, $data);

        $this->assertArrayHasKey('pagetitle', $data[0]);

        $this->assertEquals(
            $data,
            $this->instance->makeArray(
                $this->instance->select('*', $this->table, 'parent = 0', 'pagetitle DESC', '10')
            )
        );
    }

    public function testInsert()
    {
        $this->assertThat(
            $this->instance->insert(
                ['pagetitle' => 'test', 'parent' => 100],
                $this->table
            ),
            $this->isType('int')
        );

        try {
            $this->instance->insert(
                ['id' => 1],
                $this->table
            );

            $this->assertTrue(false, 'Need QueryException');
        } catch(Database\Exceptions\QueryException $exception) {
            $this->assertEquals(
                1062,
                $exception->getCode()
            );
        }
    }
}
