<?php

namespace CodeDart\SlideCaptcha\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;

class CaptchaGenerator
{
    public function generate(Request $request)
    {
        $storage = $this->generatedStorage();
        $backgroundsPath = $this->backgroundsPath();
        $generatedPath = $this->configuredStoragePath('generated_path');

        $backgroundPath = $this->randomBackgroundPath($backgroundsPath);
        $backgroundContents = File::get($backgroundPath);

        if (! is_string($backgroundContents) || $backgroundContents === '') {
            throw new RuntimeException(sprintf('Não foi possível ler a imagem de fundo [%s].', $backgroundPath));
        }

        $width = max(1, (int) config('captcha.image_width', 320));
        $height = max(1, (int) config('captcha.image_height', 180));

        $challengeId = (string) Str::uuid();
        $pieceSize = $this->randomPieceSize($width, $height);
        $canvasSize = $this->pieceCanvasSize($pieceSize);
        $rotationEnabled = (bool) config('captcha.rotation.enabled', true);
        $rotationStep = $this->rotationStep();
        $targetRotation = $rotationEnabled ? $this->randomRotation($rotationStep) : 0;
        list($targetX, $targetY) = $this->randomTargetPosition($width, $height, $canvasSize);

        $manager = $this->imageManager();
        $image = $this->readAndFit($manager, $backgroundContents, $width, $height);
        list($background, $piece) = $this->buildPuzzleImages(
            $this->toGdImage($image),
            $targetX,
            $targetY,
            $pieceSize,
            $canvasSize,
            $targetRotation,
            $rotationEnabled
        );

        $backgroundFile = $this->joinPaths($generatedPath, $challengeId . '-bg.png');
        $pieceFile = $this->joinPaths($generatedPath, $challengeId . '-piece.png');

        $this->putPngContents($storage, $this->encodeGdPng($background), $backgroundFile);
        $this->putPngContents($storage, $this->encodeGdPng($piece), $pieceFile);

        $this->cache()->put($this->challengeKey($challengeId), [
            'target_x' => $targetX,
            'target_y' => $targetY,
            'piece_size' => $pieceSize,
            'piece_canvas_size' => $canvasSize,
            'target_rotation' => $targetRotation,
            'rotation_enabled' => $rotationEnabled,
            'rotation_tolerance' => max(0, (int) config('captcha.rotation.tolerance_degrees', 8)),
            'background_path' => $backgroundFile,
            'piece_path' => $pieceFile,
            'ip' => $request->ip(),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
            'created_at' => time(),
            'used' => false,
        ], max(1, (int) config('captcha.ttl', 120)));

        return [
            'challenge_id' => $challengeId,
            'background_url' => $this->imageUrl($backgroundFile, $generatedPath),
            'piece_url' => $this->imageUrl($pieceFile, $generatedPath),
            'rotation_enabled' => $rotationEnabled,
            'rotation_step' => $rotationStep,
        ];
    }

    public static function challengeKey($challengeId)
    {
        return 'slide_captcha_challenge:' . $challengeId;
    }

    protected function configuredStoragePath($key)
    {
        return trim((string) config('captcha.' . $key), '/');
    }

    protected function backgroundsPath()
    {
        $configuredPath = config('captcha.backgrounds_path');

        if (is_string($configuredPath) && trim($configuredPath) !== '') {
            return $this->normalizeLocalPath($configuredPath);
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'backgrounds';
    }

    protected function normalizeLocalPath($path)
    {
        $path = trim($path);

        if ($this->isAbsolutePath($path)) {
            return rtrim($path, '/\\');
        }

        if (function_exists('base_path')) {
            return rtrim(base_path($path), '/\\');
        }

        return rtrim(getcwd() . DIRECTORY_SEPARATOR . $path, '/\\');
    }

    protected function isAbsolutePath($path)
    {
        return isset($path[0])
            && ($path[0] === '/' || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1);
    }

    protected function joinPaths($path, $file)
    {
        return trim(trim($path, '/') . '/' . ltrim($file, '/'), '/');
    }

    protected function randomBackgroundPath($path)
    {
        if (! File::isDirectory($path)) {
            throw new RuntimeException(sprintf(
                'O diretório de imagens base do CAPTCHA não foi encontrado em [%s].',
                $path
            ));
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $files = array_values(array_filter(File::allFiles($path), function ($file) use ($allowedExtensions) {
            return in_array(strtolower($file->getExtension()), $allowedExtensions, true);
        }));

        if (count($files) === 0) {
            throw new RuntimeException(sprintf(
                'Nenhuma imagem base foi encontrada em [%s]. Adicione arquivos JPG, PNG ou WEBP nesse diretório ou configure SLIDE_CAPTCHA_BACKGROUNDS_PATH.',
                $path
            ));
        }

        return $files[array_rand($files)]->getPathname();
    }

    protected function randomPieceSize($width, $height)
    {
        $maxAllowed = (int) floor((min($width, $height) - 12) / 1.5);

        if ($maxAllowed < 12) {
            throw new RuntimeException('As dimensões configuradas para a imagem são pequenas demais para gerar a peça do CAPTCHA.');
        }

        $configuredMin = (int) config('captcha.piece_min_size', 42);
        $configuredMax = (int) config('captcha.piece_max_size', 58);
        $min = max(12, min($configuredMin, $maxAllowed));
        $max = max($min, min($configuredMax, $maxAllowed));

        return random_int($min, $max);
    }

    protected function pieceCanvasSize($pieceSize)
    {
        return (int) (ceil($pieceSize * 1.5) + 4);
    }

    protected function rotationStep()
    {
        return max(1, min(180, (int) config('captcha.rotation.step_degrees', 15)));
    }

    protected function randomRotation($step)
    {
        $max = max($step, min(180, abs((int) config('captcha.rotation.max_degrees', 90))));
        $candidates = [];

        for ($angle = $step; $angle <= $max; $angle += $step) {
            $candidates[] = $angle;
            $candidates[] = $this->normalizeRotation(-$angle);
        }

        return $candidates[array_rand($candidates)];
    }

    protected function normalizeRotation($degrees)
    {
        return fmod(fmod((float) $degrees, 360.0) + 360.0, 360.0);
    }

    protected function randomTargetPosition($width, $height, $pieceSize)
    {
        $maxX = max(4, $width - $pieceSize - 4);
        $minX = min($maxX, max(4, (int) floor($width * 0.25)));
        $maxY = max(4, $height - $pieceSize - 4);

        return [
            random_int($minX, $maxX),
            random_int(4, $maxY),
        ];
    }

    protected function buildPuzzleImages($source, $targetX, $targetY, $pieceSize, $canvasSize, $targetRotation, $rotationEnabled)
    {
        $background = $this->copyGdImage($source);
        $baseMask = $this->createPuzzleMask($pieceSize);
        $maskCanvas = $this->centerOnCanvas($baseMask, $canvasSize);
        $targetMask = $rotationEnabled
            ? $this->rotateAndCrop($maskCanvas, -$targetRotation, $canvasSize)
            : $maskCanvas;

        $finalPiece = $this->transparentCanvas($canvasSize, $canvasSize);
        imagecopy($finalPiece, $source, 0, 0, $targetX, $targetY, $canvasSize, $canvasSize);
        $this->applyAlphaMask($finalPiece, $targetMask);
        $this->drawMaskBorder($finalPiece, $targetMask, 255, 255, 255, 20);

        $piece = $rotationEnabled
            ? $this->rotateAndCrop($finalPiece, $targetRotation, $canvasSize)
            : $finalPiece;

        $this->drawMaskCutout($background, $targetMask, $targetX, $targetY);
        $this->drawMaskBorder($background, $targetMask, 20, 20, 20, 35, $targetX, $targetY);

        return [$background, $piece];
    }

    protected function createPuzzleMask($size)
    {
        $mask = $this->transparentCanvas($size, $size);
        $opaque = imagecolorallocatealpha($mask, 255, 255, 255, 0);
        $transparent = imagecolorallocatealpha($mask, 255, 255, 255, 127);
        $inset = max(4, (int) round($size * 0.12));
        $tab = max(8, (int) round($size * 0.32));

        imagefilledrectangle($mask, $inset, $inset, $size - $inset - 1, $size - $inset - 1, $opaque);

        imagefilledellipse(
            $mask,
            $size - $inset,
            (int) round($size * 0.38),
            $tab,
            $tab,
            $opaque
        );
        imagefilledellipse(
            $mask,
            (int) round($size * 0.62),
            $size - $inset,
            $tab,
            $tab,
            $opaque
        );

        imagefilledellipse(
            $mask,
            $inset,
            (int) round($size * 0.62),
            $tab,
            $tab,
            $transparent
        );
        imagefilledellipse(
            $mask,
            (int) round($size * 0.35),
            $inset,
            max(8, (int) round($size * 0.28)),
            max(8, (int) round($size * 0.28)),
            $transparent
        );

        return $mask;
    }

    protected function centerOnCanvas($image, $canvasSize)
    {
        $canvas = $this->transparentCanvas($canvasSize, $canvasSize);
        $x = (int) floor(($canvasSize - imagesx($image)) / 2);
        $y = (int) floor(($canvasSize - imagesy($image)) / 2);

        imagecopy($canvas, $image, $x, $y, 0, 0, imagesx($image), imagesy($image));

        return $canvas;
    }

    protected function rotateAndCrop($image, $degrees, $size)
    {
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $rotated = imagerotate($image, (float) $degrees, $transparent);

        if ($rotated === false) {
            throw new RuntimeException('Não foi possível rotacionar a peça do CAPTCHA.');
        }

        imagesavealpha($rotated, true);
        imagealphablending($rotated, false);

        $output = $this->transparentCanvas($size, $size);
        $sourceX = (int) max(0, floor((imagesx($rotated) - $size) / 2));
        $sourceY = (int) max(0, floor((imagesy($rotated) - $size) / 2));

        imagecopy($output, $rotated, 0, 0, $sourceX, $sourceY, $size, $size);

        return $output;
    }

    protected function applyAlphaMask($image, $mask)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        imagealphablending($image, false);
        imagesavealpha($image, true);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $maskAlpha = $this->alphaAt($mask, $x, $y);
                $rgba = $this->rgbaAt($image, $x, $y);
                $alpha = max($rgba['a'], $maskAlpha);
                $color = imagecolorallocatealpha($image, $rgba['r'], $rgba['g'], $rgba['b'], $alpha);

                imagesetpixel($image, $x, $y, $color);
            }
        }
    }

    protected function drawMaskCutout($image, $mask, $offsetX, $offsetY)
    {
        for ($y = 0; $y < imagesy($mask); $y++) {
            for ($x = 0; $x < imagesx($mask); $x++) {
                $alpha = $this->alphaAt($mask, $x, $y);

                if ($alpha >= 120) {
                    continue;
                }

                $targetX = $offsetX + $x;
                $targetY = $offsetY + $y;

                if ($targetX < 0 || $targetY < 0 || $targetX >= imagesx($image) || $targetY >= imagesy($image)) {
                    continue;
                }

                $rgba = $this->rgbaAt($image, $targetX, $targetY);
                $strength = 0.48 * ((127 - $alpha) / 127);
                $r = (int) round($rgba['r'] * (1 - $strength) + 255 * $strength);
                $g = (int) round($rgba['g'] * (1 - $strength) + 255 * $strength);
                $b = (int) round($rgba['b'] * (1 - $strength) + 255 * $strength);
                $color = imagecolorallocatealpha($image, $r, $g, $b, $rgba['a']);

                imagesetpixel($image, $targetX, $targetY, $color);
            }
        }
    }

    protected function drawMaskBorder($image, $mask, $red, $green, $blue, $alpha, $offsetX = 0, $offsetY = 0)
    {
        $color = imagecolorallocatealpha($image, $red, $green, $blue, $alpha);

        for ($y = 0; $y < imagesy($mask); $y++) {
            for ($x = 0; $x < imagesx($mask); $x++) {
                if (! $this->isMaskOpaque($mask, $x, $y) || ! $this->isMaskEdge($mask, $x, $y)) {
                    continue;
                }

                $targetX = $offsetX + $x;
                $targetY = $offsetY + $y;

                if ($targetX < 0 || $targetY < 0 || $targetX >= imagesx($image) || $targetY >= imagesy($image)) {
                    continue;
                }

                imagesetpixel($image, $targetX, $targetY, $color);
            }
        }
    }

    protected function isMaskEdge($mask, $x, $y)
    {
        $neighbors = [
            [$x - 1, $y],
            [$x + 1, $y],
            [$x, $y - 1],
            [$x, $y + 1],
        ];

        foreach ($neighbors as $neighbor) {
            if (! $this->isMaskOpaque($mask, $neighbor[0], $neighbor[1])) {
                return true;
            }
        }

        return false;
    }

    protected function isMaskOpaque($mask, $x, $y)
    {
        return $x >= 0
            && $y >= 0
            && $x < imagesx($mask)
            && $y < imagesy($mask)
            && $this->alphaAt($mask, $x, $y) < 100;
    }

    protected function alphaAt($image, $x, $y)
    {
        return ($this->rgbaAt($image, $x, $y)['a']);
    }

    protected function rgbaAt($image, $x, $y)
    {
        $color = imagecolorat($image, $x, $y);

        return [
            'a' => ($color >> 24) & 0x7F,
            'r' => ($color >> 16) & 0xFF,
            'g' => ($color >> 8) & 0xFF,
            'b' => $color & 0xFF,
        ];
    }

    protected function transparentCanvas($width, $height)
    {
        $image = imagecreatetruecolor($width, $height);

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        return $image;
    }

    protected function copyGdImage($source)
    {
        $copy = $this->transparentCanvas(imagesx($source), imagesy($source));

        imagecopy($copy, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));

        return $copy;
    }

    protected function imageManager()
    {
        if (class_exists('Intervention\\Image\\Drivers\\Gd\\Driver')) {
            $driver = 'Intervention\\Image\\Drivers\\Gd\\Driver';

            return new ImageManager(new $driver());
        }

        return new ImageManager(['driver' => 'gd']);
    }

    protected function readAndFit($manager, $source, $width, $height)
    {
        if (method_exists($manager, 'read')) {
            $image = $manager->read($source);

            if (method_exists($image, 'cover')) {
                return $image->cover($width, $height);
            }

            return $image->resize($width, $height);
        }

        return $manager->make($source)->fit($width, $height);
    }

    protected function toGdImage($image)
    {
        $gdImage = imagecreatefromstring($this->encodePng($image));

        if ($gdImage === false) {
            throw new RuntimeException('Não foi possível preparar a imagem base do CAPTCHA.');
        }

        imagepalettetotruecolor($gdImage);
        imagealphablending($gdImage, false);
        imagesavealpha($gdImage, true);

        return $gdImage;
    }

    protected function putPngContents($storage, $contents, $path)
    {
        $storage->put($path, $contents, [
            'visibility' => 'private',
            'ContentType' => 'image/png',
        ]);
    }

    protected function encodeGdPng($image)
    {
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();

        if (! is_string($contents)) {
            throw new RuntimeException('Não foi possível codificar a imagem do CAPTCHA em PNG.');
        }

        return $contents;
    }

    protected function encodePng($image)
    {
        if (method_exists($image, 'toPng')) {
            return (string) $image->toPng();
        }

        if (method_exists($image, 'encode')) {
            return (string) $image->encode('png');
        }

        throw new RuntimeException('Não foi possível codificar a imagem do CAPTCHA em PNG.');
    }

    protected function imageUrl($path, $generatedPath)
    {
        return URL::temporarySignedRoute(
            'slide-captcha.generated',
            Carbon::now()->addSeconds(max(1, (int) config('captcha.temporary_url_ttl', 300))),
            ['path' => $this->relativeGeneratedPath($path, $generatedPath)]
        );
    }

    protected function relativeGeneratedPath($path, $generatedPath)
    {
        $path = trim((string) $path, '/');
        $generatedPath = trim((string) $generatedPath, '/');

        if ($generatedPath !== '' && strpos($path, $generatedPath . '/') === 0) {
            return substr($path, strlen($generatedPath) + 1);
        }

        return $path;
    }

    protected function cache()
    {
        $store = config('captcha.cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }

    protected function generatedStorage()
    {
        return Storage::disk((string) config('captcha.storage_disk', 's3'));
    }
}
