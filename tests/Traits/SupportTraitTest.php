<?php namespace AgelxNash\Modx\Evo\Database\Tests\Traits;

use PHPUnit\Framework\TestCase;
use AgelxNash\Modx\Evo\Database;

class SupportTraitTest extends TestCase
{
    protected $instance;

    public function setUp()
    {
        $this->instance = new Database\Database();
    }

    public function testGetFullTableName()
    {
        $this->assertSame(
            '`modx`.`site_content`',
            (new Database\Database('agel-nash.ru', 'modx'))
                ->getFullTableName('site_content'),
            'STEP 1/2'
        );

        $this->assertSame(
            '`modx`.`modx_site_content`',
            (new Database\Database('agel-nash.ru', 'modx', 'agel_nash', '', 'modx_'))
                ->getFullTableName('site_content'),
            'STEP 2/2'
        );
    }

    public function testFunctionPrepareWhere()
    {
        $method = new \ReflectionMethod($this->instance, 'prepareWhere');
        $method->setAccessible(true);

        $this->assertSame(
            'WHERE `id` = 1',
            $method->invoke($this->instance, ' `id` = 1 '),
            'STEP 1/3'
        );

        $this->assertSame(
            'WHERE id = 1',
            $method->invoke($this->instance, ' WHERE id = 1 '),
            'STEP 2/3'
        );

        $this->assertSame(
            'WHERE id = 1 AND title=test',
            $method->invoke($this->instance, ['id = 1', 'AND', 'title=test']),
            'STEP 2/3'
        );
    }

    public function testFunctionPrepareFrom()
    {
        $method = new \ReflectionMethod($this->instance, 'prepareFrom');
        $method->setAccessible(true);

        $this->assertSame(
            'test',
            $method->invoke($this->instance, 'test'),
            'STEP 1/2'
        );

        $this->assertSame(
            'table1 table2',
            $method->invoke($this->instance, [
                'table1',
                'table2'
            ], true),
            'STEP 2/2'
        );
    }

    public function testFunctionPrepareOrder()
    {
        $method = new \ReflectionMethod($this->instance, 'prepareOrder');
        $method->setAccessible(true);

        $this->assertSame(
            'ORDER BY `id`',
            $method->invoke($this->instance, ' `id` '),
            'STEP 1/2'
        );

        $this->assertSame(
            'ORDER BY `id`',
            $method->invoke($this->instance, ' ORDER BY `id` '),
            'STEP 2/2'
        );
    }

    public function testFunctionPrepareLimit()
    {
        $method = new \ReflectionMethod($this->instance, 'prepareLimit');
        $method->setAccessible(true);

        $this->assertSame(
            'LIMIT 10',
            $method->invoke($this->instance, 10),
            'STEP 1/3'
        );

        $this->assertSame(
            'LIMIT 11,5',
            $method->invoke($this->instance, ' 11,5 '),
            'STEP 2/3'
        );

        $this->assertSame(
            'LIMIT 32,1',
            $method->invoke($this->instance, ' LIMIT 32,1 '),
            'STEP 3/3'
        );
    }

    public function testFunctionPrepareNull()
    {
        $method = new \ReflectionMethod($this->instance, 'prepareNull');
        $method->setAccessible(true);

        $this->assertSame(
            "'text'",
            $method->invoke($this->instance, 'text'),
            'STEP 1/3'
        );

        $this->assertSame(
            'NULL',
            $method->invoke($this->instance, 'null'),
            'STEP 2/3'
        );

        $this->assertSame(
            'NULL',
            $method->invoke($this->instance, null),
            'STEP 3/3'
        );
    }

    public function testFunctionPrepareValues()
    {
        $method = new \ReflectionMethod($this->instance, 'prepareValues');
        $method->setAccessible(true);

        $this->assertSame(
            "('hello', NULL, 'world', '10')",
            $method->invoke($this->instance, ['hello', null, 'world', 10]),
            'STEP 1/5'
        );

        $this->assertSame(
            "(`title`, `delete`, `content`, `template`) VALUES ('hello', NULL, 'world', '10')",
            $method->invoke(
                $this->instance,
                ['title' => 'hello', 'delete' => null, 'content' => 'world', 'template' => 10]
            ),
            'STEP 2/5'
        );

        $this->assertSame(
            "(`title`, `delete`, `content`, `template`) VALUES ('hello', NULL, 'world', '10'), ('good', NULL, 'by', '20')",
            $method->invoke(
                $this->instance,
                [
                    ['title' => 'hello', 'delete' => null, 'content' => 'world', 'template' => 10],
                    ['title' => 'good', 'delete' => null, 'content' => 'by', 'template' => 20],
                ]
            ),
            'STEP 3/5'
        );

        $this->assertSame(
            "('hello', NULL, 'world', '10')",
            $method->invoke(
                $this->instance,
                ['title' => 'hello', 'delete' => null, 'content' => 'world', 'template' => 10],
                2
            )['values'],
            'STEP 4/5'
        );

        $this->assertSame(
            "('hello', NULL, 'world', '10')",
            $method->invoke(
                $this->instance,
                ['hello', null, 'world', 10],
                2,
                true
            )['values'],
            'STEP 5/5'
        );
    }

    public function testFunctionPrepareValuesSet()
    {
        $method = new \ReflectionMethod($this->instance, 'prepareValuesSet');
        $method->setAccessible(true);

        $this->assertSame(
            "`0` = 'hello', `1` = NULL, `2` = 'world', `3` = '10'",
            $method->invoke($this->instance, ['hello', null, 'world', 10]),
            'STEP 1/2'
        );

        $this->assertSame(
            "`title` = 'hello', `delete` = NULL, `content` = 'world', `template` = '10'",
            $method->invoke(
                $this->instance,
                ['title' => 'hello', 'delete' => null, 'content' => 'world', 'template' => 10]
            ),
            'STEP 2/2'
        );
    }
}
