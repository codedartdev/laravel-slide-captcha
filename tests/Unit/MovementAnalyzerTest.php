<?php

namespace CodeDart\SlideCaptcha\Tests\Unit;

use CodeDart\SlideCaptcha\Support\MovementAnalyzer;
use CodeDart\SlideCaptcha\Tests\Support\ConfigRepository;
use PHPUnit\Framework\TestCase;

class MovementAnalyzerTest extends TestCase
{
    protected function setUp(): void
    {
        ConfigRepository::reset();
    }

    public function testItAcceptsHumanLikeMovement()
    {
        $result = (new MovementAnalyzer())->analyze([
            ['x' => 0, 'y' => 0, 't' => 0],
            ['x' => 14, 'y' => 3, 't' => 180],
            ['x' => 31, 'y' => 8, 't' => 390],
            ['x' => 52, 'y' => 6, 't' => 640],
        ]);

        $this->assertTrue($result['success']);
    }

    public function testItRejectsTooLinearMovement()
    {
        $result = (new MovementAnalyzer())->analyze([
            ['x' => 0, 'y' => 10, 't' => 0],
            ['x' => 20, 'y' => 10, 't' => 180],
            ['x' => 40, 'y' => 10, 't' => 380],
            ['x' => 60, 'y' => 10, 't' => 620],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('movement_too_linear', $result['reason']);
    }

    public function testRotationOnlySamplesDoNotMakeMovementLookLinear()
    {
        $result = (new MovementAnalyzer())->analyze([
            ['x' => 0, 'y' => 0, 't' => 0, 'r' => 0],
            ['x' => 15, 'y' => 5, 't' => 180, 'r' => 0],
            ['x' => 32, 'y' => 11, 't' => 360, 'r' => 0],
            ['x' => 32, 'y' => 11, 't' => 520, 'r' => 15],
            ['x' => 32, 'y' => 11, 't' => 680, 'r' => 30],
            ['x' => 32, 'y' => 11, 't' => 840, 'r' => 45],
        ]);

        $this->assertTrue($result['success']);
    }
}
