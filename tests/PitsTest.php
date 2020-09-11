<?php

namespace PitsTest;

use App\Api\V1\Controllers\PitsController;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;
use Laravel\Lumen\Testing\TestCase as TestingTestCase;
use Mockery as Mock;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\TestCase;

class PitsTest extends TestCase
{
    // public function testAddPitsDataIsArray()
    // {
    //     $mockUserId = Mock::mock("Users");
    //     $mockUserId->shouldReceive("getUserById")->andReturn(50);

    //     $pit = new PitsController($mockUserId);

    //     $newPitData = ['name' => "Roulete", 'size' => 200, 'float' => "20000"];
    //     $this->assertIsArray($newPitData);
    //     // $this->assertEquals(50, $this->kolp());
    // }
}
