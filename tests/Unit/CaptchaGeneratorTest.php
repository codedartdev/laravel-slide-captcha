<?php

namespace CodeDart\SlideCaptcha\Tests\Unit;

use CodeDart\SlideCaptcha\Services\CaptchaGenerator;
use CodeDart\SlideCaptcha\Tests\Support\ConfigRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CaptchaGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        ConfigRepository::reset();
    }

    public function testRandomRotationNeverReturnsZeroWhenRotationIsEnabled()
    {
        $generator = new CaptchaGenerator();
        $method = new ReflectionMethod($generator, 'randomRotation');
        $method->setAccessible(true);

        for ($index = 0; $index < 50; $index++) {
            $rotation = $method->invoke($generator, 15);

            $this->assertNotSame(0.0, $rotation);
            $this->assertGreaterThanOrEqual(0, $rotation);
            $this->assertLessThan(360, $rotation);
        }
    }

    public function testPuzzleImagesKeepPieceAtZeroDegreesAndRotateTheTargetCutout()
    {
        $generator = new CaptchaGenerator();
        $source = $this->createSourceImage(320, 180);
        $method = new ReflectionMethod($generator, 'buildPuzzleImages');
        $method->setAccessible(true);

        list($background, $piece) = $method->invoke($generator, $source, 90, 40, 58, 91, 45, true);

        $this->assertSame(320, imagesx($background));
        $this->assertSame(180, imagesy($background));
        $this->assertSame(91, imagesx($piece));
        $this->assertSame(91, imagesy($piece));
        $this->assertGreaterThan(0, $this->countVisiblePixels($piece));
        $this->assertGreaterThan(0, $this->countTransparentPixels($piece));
        $this->assertTrue($this->hasCutoutPixels($background, 90, 40, 91));
    }

    private function createSourceImage($width, $height)
    {
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorallocate($image, ($x * 3) % 255, ($y * 5) % 255, ($x + $y) % 255);
                imagesetpixel($image, $x, $y, $color);
            }
        }

        return $image;
    }

    private function countVisiblePixels($image)
    {
        $count = 0;

        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                if ($this->alphaAt($image, $x, $y) < 100) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function countTransparentPixels($image)
    {
        $count = 0;

        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                if ($this->alphaAt($image, $x, $y) > 100) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function hasCutoutPixels($image, $offsetX, $offsetY, $size)
    {
        for ($y = $offsetY; $y < $offsetY + $size; $y++) {
            for ($x = $offsetX; $x < $offsetX + $size; $x++) {
                $color = imagecolorat($image, $x, $y);
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;

                if ($red > 180 && $green > 180 && $blue > 180) {
                    return true;
                }
            }
        }

        return false;
    }

    private function alphaAt($image, $x, $y)
    {
        return (imagecolorat($image, $x, $y) >> 24) & 0x7F;
    }
}
