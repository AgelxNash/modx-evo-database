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
