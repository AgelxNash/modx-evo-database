<?php namespace AgelxNash\Modx\Evo\Database\Tests;

use PHPUnit\Framework\TestCase;
use AgelxNash\Modx\Evo\Database;
use ReflectionClass;
use ReflectionMethod;

abstract class RealQueryTest extends TestCase
{
    /**
     * @var Database\Database
     */
    protected $instance;

    protected $driver;

    protected $connectorClass = '';

    protected $resultClass = '';

    /**
     * @var string
     */
    protected $table;

    protected function setUp()
    {
        $this->instance = new Database\Database([
            'host' => isset($_SERVER['DB_HOST']) ? $_SERVER['DB_HOST'] : 'localhost',
            'database' => isset($_SERVER['DB_BASE']) ? $_SERVER['DB_BASE'] : 'modx',
            'username' => isset($_SERVER['DB_USER']) ? $_SERVER['DB_USER'] : 'homestead',
            'password' => isset($_SERVER['DB_PASSWORD']) ? $_SERVER['DB_PASSWORD'] : 'secret',
            'prefix' => isset($_SERVER['DB_PREFIX']) ? $_SERVER['DB_PREFIX'] : '{PREFIX}',
            'charset' => isset($_SERVER['DB_CHARSET']) ? $_SERVER['DB_CHARSET'] : 'utf8mb4',
            'method' => isset($_SERVER['DB_METHOD']) ? $_SERVER['DB_METHOD'] : 'SET NAMES',
            'collation' => isset($_SERVER['DB_COLLATION']) ? $_SERVER['DB_COLLATION'] : 'utf8mb4_unicode_ci',
        ], $this->driver);

        $this->instance->setDebug(true)->connect();

        $this->table = $this->instance->getFullTableName('site_content');
    }

    public function testConnect()
    {
        $this->assertInstanceOf(
            $this->connectorClass,
            $this->instance->getDriver()->getConnect()
        );
    }

    public function testDisconnect()
    {
        $this->assertTrue(
            $this->instance->getDriver()->isConnected()
        );

        $this->instance->disconnect();

        $this->assertFalse(
            $this->instance->getDriver()->isConnected()
        );

        $this->assertCount(
            0,
            $this->instance->getAllExecutedQuery()
        );

        $this->assertInstanceOf(
            $this->connectorClass,
            $this->instance->getDriver()->getConnect()
        );
    }

    public function testVersion()
    {
        $this->assertThat(
            $this->instance->getVersion(),
            $this->isType('string')
        );
    }

    public function testOptimize()
    {
        $this->instance->flushExecutedQuery();

        $this->assertTrue(
            $this->instance->optimize($this->table)
        );

        $querys = $this->instance->getAllExecutedQuery();
        $this->assertCount(2, $querys);

        $this->assertStringStartsWith(
            'OPTIMIZE TABLE',
            $querys[1]['sql']
        );

        $this->assertStringStartsWith(
            'ALTER TABLE',
            $querys[2]['sql']
        );
    }

    public function testAlterTable()
    {
        $this->instance->flushExecutedQuery();

        $this->assertTrue(
            $this->instance->alterTable($this->table)
        );

        $querys = $this->instance->getAllExecutedQuery();
        $this->assertCount(1, $querys);

        $this->assertStringStartsWith(
            'ALTER TABLE',
            $querys[1]['sql']
        );
    }

    public function testHelperMethods()
    {
        $query = 'SELECT id,pagetitle FROM ' . $this->table . ' ORDER BY id ASC LIMIT 3';
        $result = $this->instance->query($query);

        $this->assertEquals(
            ['id', 'pagetitle'],
            $this->instance->getColumnNames($result)
        );

        $this->assertEquals(
            [],
            $this->instance->getColumnNames(null)
        );

        $out = $this->instance->getColumn('id', $result);
        $this->assertEquals(
            ['1', '2', '4'],
            $out
        );
        $this->assertEquals(
            $out,
            $this->instance->getColumn('id', $query)
        );

        $this->assertEquals(
            [],
            $this->instance->getColumn('id', null)
        );

        $this->assertEquals(
            [],
            $this->instance->getColumn('nop', $query)
        );

        $this->assertEquals(
            3,
            $this->instance->getRecordCount($result)
        );

        $this->assertEquals(
            0,
            $this->instance->getRecordCount(null)
        );

        $this->assertEquals(
            'id',
            $this->instance->fieldName($result, 0)
        );

        $this->assertEquals(
            'pagetitle',
            $this->instance->fieldName($result, 1)
        );

        $this->assertEquals(
            null,
            $this->instance->fieldName(null)
        );

        $this->assertEquals(
            2,
            $this->instance->numFields($result)
        );

        $this->assertEquals(
            0,
            $this->instance->numFields(null)
        );
    }

    public function testUpdate()
    {
        $time = time();

        $this->assertTrue(
            $this->instance->update(
                ['pagetitle' => 'testUpdate1 - ' . $time],
                $this->table,
                'id = 48'
            )
        );

        $this->assertEquals(
            'testUpdate1 - ' . $time,
            $this->instance->getValue('SELECT `pagetitle` FROM ' . $this->table . ' WHERE `id` = 48')
        );
    }

    public function testSave()
    {
        $maxId = (int)$this->instance->getColumn(
            'Auto_increment',
            "SHOW TABLE STATUS WHERE `name`='" . $this->instance->getTableName('site_content', false) . "'"
        )[0];

        $this->assertEquals(
            $maxId++,
            $this->instance->save(
                ['pagetitle' => 'testSave1'],
                $this->table
            ),
            'STEP 1/6'
        );

        $this->assertEquals(
            $maxId++,
            $this->instance->save(
                "SET `pagetitle`='testSave2'",
                $this->table
            ),
            'STEP 2/6'
        );

        $this->assertEquals(
            $maxId++,
            $this->instance->save(
                "SET `pagetitle`='testSave6'",
                $this->table,
                'id = 66666666'
            ),
            'STEP 3/6'
        );

        $time = time();

        $this->assertTrue(
            $this->instance->save(
                ['pagetitle' => 'testSave3 - ' . $time],
                $this->table,
                'id = 48'
            )
        );

        $this->assertEquals(
            'testSave3 - ' . $time,
            $this->instance->getValue('SELECT `pagetitle` FROM ' . $this->table . ' WHERE `id` = 48')
        );

        $this->assertTrue(
            $this->instance->save(
                ['pagetitle' => 'testSave4 - ' . $time],
                $this->table,
                'id = 48'
            )
        );

        $this->assertEquals(
            'testSave4 - ' . $time,
            $this->instance->getValue('SELECT `pagetitle` FROM ' . $this->table . ' WHERE `id` = 48')
        );

        $this->assertTrue(
            $this->instance->save(
                "`pagetitle`='testSave5 - " . $time . "'",
                $this->table,
                'id = 48'
            )
        );

        $this->assertEquals(
            'testSave5 - ' . $time,
            $this->instance->getValue('SELECT `pagetitle` FROM ' . $this->table . ' WHERE `id` = 48')
        );
    }

    public function testDelete()
    {
        $id = $this->instance->insert(['pagetitle' => 'for delete'], $this->table);
        $this->assertThat(
            $id,
            $this->isType('int'),
            'STEP 1/4'
        );

        $field = $this->instance->getValue('SELECT `id` FROM ' . $this->table . ' WHERE `id` = ' . $id);

        $this->assertEquals(
            $id,
            $field,
            'STEP 2/4'
        );

        $this->assertTrue(
            $this->instance->delete(
                $this->table,
                'id = ' . $id
            ),
            'STEP 3/4'
        );

        $this->assertEquals(
            null,
            $this->instance->getValue('SELECT `id` FROM ' . $this->table . ' WHERE `id` = ' . $id),
            'STEP 4/4'
        );
    }

    public function testGetTableMetaData()
    {
        $this->instance->flushExecutedQuery();

        $this->assertEquals(
            [],
            $this->instance->getTableMetaData('')
        );

        $data = $this->instance->getTableMetaData($this->table);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('Field', $data['id']);
        $this->assertArrayHasKey('Type', $data['id']);
        $this->assertArrayHasKey('Null', $data['id']);
        $this->assertArrayHasKey('Key', $data['id']);
        $this->assertArrayHasKey('Default', $data['id']);
        $this->assertArrayHasKey('Extra', $data['id']);

        $this->assertCount(
            36, //count rows
            $data
        );

        $querys = $this->instance->getAllExecutedQuery();
        $this->assertCount(1, $querys);

        $this->assertStringStartsWith(
            'SHOW FIELDS FROM',
            $querys[1]['sql']
        );
    }

    public function testSelect()
    {
        $this->assertInstanceOf(
            $this->resultClass,
            $this->instance->query('SELECT * FROM ' . $this->table . ' WHERE parent = 0 ORDER BY pagetitle DESC LIMIT 10')
        );

        $this->assertInstanceOf(
            $this->resultClass,
            $this->instance->select('*', $this->table, 'parent = 0', 'pagetitle DESC', '10')
        );

        $this->assertInstanceOf(
            $this->resultClass,
            $this->instance->select(
                ['id', 'pagetitle', 'title' => 'longtitle'],
                ['c' => $this->table],
                ['parent = 0'],
                'ORDER BY pagetitle DESC',
                'LIMIT 10'
            )
        );

        $this->assertInstanceOf(
            $this->resultClass,
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
            $this->resultClass,
            $this->instance->query([
                'SELECT *',
                'FROM ' . $this->table,
                'WHERE parent = 0',
                'ORDER BY id DESC',
                'LIMIT 10',
            ])
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
        $maxId = (int)$this->instance->getColumn(
            'Auto_increment',
            "SHOW TABLE STATUS WHERE `name`='" . $this->instance->getTableName('site_content', false) . "'"
        )[0];
        $this->assertGreaterThan(
            0,
            $maxId,
            'STEP 1/4'
        );

        $this->assertEquals(
            $this->instance->insert(
                "SET `pagetitle`='hello'",
                $this->table
            ),
            $maxId++,
            'STEP 2/4'
        );


        $this->assertEquals(
            $this->instance->insert(
                ['pagetitle' => 'test', 'parent' => 100],
                $this->table
            ),
            $maxId++,
            'STEP 3/4'
        );

        try {
            $this->instance->insert(
                ['id' => 1],
                $this->table
            );

            $this->assertTrue(false, 'STEP 3/3 (Need QueryException)');
        } catch (Database\Exceptions\QueryException $exception) {
            $this->assertThat(
                $exception->getQuery(),
                $this->isType('string')
            );

            $this->assertEquals(
                '23000',
                $exception->getCode(),
                'STEP 4/4'
            );
        }
    }

    public function testMassInsert()
    {
        $table = $this->instance->getFullTableName('clone');

        $this->assertTrue(
            $this->instance->query(
                'CREATE TABLE ' . $table . ' LIKE ' . $this->table
            )
        );

        try {
            $this->instance->query(
                'CREATE TABLE ' . $table . ' LIKE ' . $this->table
            );
            $this->assertTrue(false, 'Need QueryException');
        } catch (Database\Exceptions\QueryException $exception) {
            $this->assertEquals(
                '42S01',
                $exception->getCode()
            );

            $this->assertEquals(
                '42S01',
                $this->instance->getLastErrorNo()
            );

            $this->assertStringEndsWith(
                'already exists',
                $this->instance->getLastError()
            );
        }

        try {
            $this->instance->checkLastError();
            $this->assertTrue(false, 'Need QueryException');
        } catch (Database\Exceptions\QueryException $exception) {
            $this->assertTrue(true);
        }

        $this->assertTrue(
            $this->instance->insert(
                ['pagetitle'],
                $table,
                '*',
                $this->table
            ),
            'STEP 1/3'
        );

        $this->instance->truncate($table);

        $this->assertTrue(
            $this->instance->insert(
                ['pagetitle'],
                $table,
                'longtitle',
                $this->table
            ),
            'STEP 2/3'
        );

        $this->instance->truncate($table);

        $this->assertTrue(
            $this->instance->insert(
                ['pagetitle'],
                $table,
                ['longtitle'],
                $this->table
            ),
            'STEP 3/3'
        );

        $this->assertTrue(
            true,
            $this->instance->query(
                'DROP TABLE ' . $table
            )
        );
    }

    public function testGetConnectionTime()
    {
        $this->assertThat(
            $this->instance->getConnectionTime(),
            $this->isType('float')
        );

        $this->assertThat(
            $this->instance->getConnectionTime(true),
            $this->isType('string')
        );
    }

    public function testDebugMethods()
    {
        $query = 'SELECT 1';

        $querys = \count($this->instance->getAllExecutedQuery());

        $this->instance->query($query);

        $this->assertEquals(
            $query,
            $this->instance->getLastQuery()
        );

        $this->assertCount(
            ++$querys,
            $this->instance->getAllExecutedQuery()
        );

        $this->instance->flushExecutedQuery();

        $this->assertCount(
            0,
            $this->instance->getAllExecutedQuery()
        );

        $this->assertEquals(
            '',
            $this->instance->getLastQuery()
        );
    }

    public function testEscape()
    {
        $this->assertEquals(
            "st\'ring",
            $this->instance->escape("st'ring")
        );

        $this->assertEquals(
            10,
            $this->instance->escape(10)
        );

        $this->assertEquals(
            null,
            $this->instance->escape(null)
        );

        $this->assertEquals(
            [
                [
                    "st\'ring"
                ]
            ],
            $this->instance->escape([
                [
                    "st'ring"
                ]
            ])
        );

        $class = new ReflectionClass($this->instance);
        $property = $class->getProperty('safeLoopCount');
        $property->setAccessible(true);
        $property->setValue($this->instance, 3);

        try {
            $this->instance->escape(
                [
                    [
                        [
                            [
                                'string'
                            ]
                        ]
                    ]
                ]
            );
            $this->assertTrue(false, 'Need TooManyLoopsException');
        } catch (Database\Exceptions\TooManyLoopsException $exception) {
            $this->assertTrue(true);
        }
    }

    public function checkLastErrorWithoutException()
    {
        $this->assertFalse(
            $this->instance->query(
                'ALTER TABLE ' . $this->table . 'ADD COLUMN `id` int(20) NOT NULL'
            )
        );

        $this->assertFalse(
            $this->instance->checkLastError()
        );
    }

    public function testCollectQuery()
    {
        $method = new ReflectionMethod($this->instance, 'collectQuery');
        $method->setAccessible(true);

        $query = 'SELECT 1';
        $result = $this->instance->query($query);

        $this->instance->flushExecutedQuery();

        $num = 1;
        $time = 100;
        $method->invoke($this->instance, $result, $query, $num, $time);

        $data = $this->instance->getAllExecutedQuery();
        $this->assertCount(
            $num, $data
        );

        $this->assertEquals(
            $query, $data[$num]['sql']
        );

        $this->assertEquals(
            $time, $data[$num]['time']
        );

        $this->assertEquals(
            $num, $data[$num]['rows']
        );

        $this->assertNotEmpty($data[$num]['path']);
    }

    public function testGetRow()
    {
        $query = 'SELECT `id`, `alias` FROM ' . $this->table . ' WHERE id = 1';

        $result = $this->instance->query($query);
        $this->assertInstanceOf(
            $this->resultClass,
            $result
        );

        $data = $this->instance->getRow($query);

        $this->assertEquals(
            ['id' => 1, 'alias' => 'index'],
            $data
        );

        $this->assertEquals(
            $data,
            $this->instance->getRow($result)
        );

        $this->assertTrue(
            $this->instance->getDriver()->dataSeek($result, 0)
        );

        $this->assertEquals(
            $data,
            $this->instance->getRow($result, 'assoc')
        );
        $this->instance->getDriver()->dataSeek($result, 0);

        $this->assertEquals(
            [0 => 1, 1 => 'index'],
            $this->instance->getRow($result, 'num')
        );
        $this->instance->getDriver()->dataSeek($result, 0);

        $this->assertEquals(
            (object)['id' => 1, 'alias' => 'index'],
            $this->instance->getRow($result, 'object')
        );
        $this->instance->getDriver()->dataSeek($result, 0);

        $this->assertEquals(
            [0 => '1', 'id' => '1', 1 => 'index', 'alias' => 'index'],
            $this->instance->getRow($result, 'both')
        );
    }
}
