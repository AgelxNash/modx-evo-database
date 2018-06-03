<?php namespace AgelxNash\Modx\Evo\Database\Tests\Traits;

use PHPUnit\Framework\TestCase;
use AgelxNash\Modx\Evo\Database;

class DebugTraitTest extends TestCase
{
    protected $instance;

    public function setUp()
    {
        $this->instance = new Database\Database();
    }


    public function testDebug()
    {
        $this->assertFalse(
            $this->instance->isDebug()
        );

        $this->instance->setDebug(true);

        $this->assertTrue(
            $this->instance->isDebug()
        );
    }

    public function testGetAllExecutedQuery()
    {
        $this->assertSame(
            [],
            $this->instance->getAllExecutedQuery()
        );
    }

    public function testGetLastQuery()
    {
        $this->assertSame(
            '',
            $this->instance->getLastQuery()
        );
    }

    public function testRenderConnectionTime()
    {
        $this->assertThat(
            $this->instance->renderConnectionTime(),
            $this->isType('string')
        );
    }

    public function testRenderExecutedQuery()
    {
        $this->assertThat(
            $this->instance->renderExecutedQuery(),
            $this->isType('string')
        );
    }

    public function testErrorList()
    {
        $this->assertInstanceOf(
            Database\Database::class,
            $this->instance->flushIgnoreErrors()
        );

        $this->assertEquals(
            [],
            $this->instance->getIgnoreErrors()
        );

        $this->assertInstanceOf(
            Database\Database::class,
            $this->instance->addIgnoreErrors(1000)
        );

        $this->assertEquals(
            [1000],
            $this->instance->getIgnoreErrors()
        );

        $this->assertInstanceOf(
            Database\Database::class,
            $this->instance->setIgnoreErrors([10, '20'])
        );

        $this->assertEquals(
            [10, 20],
            $this->instance->getIgnoreErrors()
        );
    }
}
