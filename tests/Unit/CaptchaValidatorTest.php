<?php

namespace CodeDart\SlideCaptcha\Tests\Unit;

use CodeDart\SlideCaptcha\Services\CaptchaValidator;
use CodeDart\SlideCaptcha\Support\MovementAnalyzer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CaptchaValidatorTest extends TestCase
{
    public function testAngularDistanceUsesCircularDistance()
    {
        $validator = new CaptchaValidator(new MovementAnalyzer());
        $method = new ReflectionMethod($validator, 'angularDistance');
        $method->setAccessible(true);

        $this->assertSame(1.0, $method->invoke($validator, 359, 0));
        $this->assertSame(4.0, $method->invoke($validator, 358, 2));
        $this->assertSame(15.0, $method->invoke($validator, 15, 0));
        $this->assertSame(90.0, $method->invoke($validator, -90, 0));
    }
}
