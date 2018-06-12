<?php namespace AgelxNash\Modx\Evo\Database\Tests\Real;

use PHPUnit\Framework\TestCase;
use AgelxNash\Modx\Evo\Database;
use mysqli;
use mysqli_result;
use ReflectionClass;
use ReflectionMethod;
use AgelxNash\Modx\Evo\Database\Drivers\MySqliDriver;

class MySqliQuery extends TestCase
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
            isset($_SERVER['DB_HOST']) ? $_SERVER['DB_HOST'] : 'localhost',
            isset($_SERVER['DB_BASE']) ? $_SERVER['DB_BASE'] : 'modx',
            isset($_SERVER['DB_USER']) ? $_SERVER['DB_USER'] : 'homestead',
            isset($_SERVER['DB_PASSWORD']) ? $_SERVER['DB_PASSWORD'] : 'secret',
            isset($_SERVER['DB_PREFIX']) ? $_SERVER['DB_PREFIX'] : '{PREFIX}',
            isset($_SERVER['DB_CHARSET']) ? $_SERVER['DB_CHARSET'] : 'utf8mb4',
            isset($_SERVER['DB_METHOD']) ? $_SERVER['DB_METHOD'] : 'SET NAMES',
            isset($_SERVER['DB_COLLATION']) ? $_SERVER['DB_COLLATION'] : 'utf8mb4_unicode_ci',
            MySqliDriver::class
        );

        $this->instance->setDebug(true)->connect();

        $this->table = $this->instance->getFullTableName('site_content');
    }

    public function testConnect()
    {
        $this->assertInstanceOf(
            mysqli::class,
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
            mysqli::class,
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
            3,
            $this->instance->getRecordCount($result)
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
            2,
            $this->instance->numFields($result)
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
            $this->assertEquals(
                '23000',
                $exception->getCode(),
                'STEP 4/4'
            );

            $this->assertThat(
                $exception->getQuery(),
                $this->isType('string')
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
                1050,
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
            mysqli_result::class,
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
