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
}
