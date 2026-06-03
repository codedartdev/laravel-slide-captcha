<?php

namespace CodeDart\SlideCaptcha\Http\Controllers;

use CodeDart\SlideCaptcha\Services\CaptchaGenerator;
use CodeDart\SlideCaptcha\Services\CaptchaValidator;
use CodeDart\SlideCaptcha\Services\CaptchaDdosProtector;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use Throwable;

class SlideCaptchaController extends Controller
{
    protected $generator;

    protected $validator;

    protected $ddos;

    public function __construct(CaptchaGenerator $generator, CaptchaValidator $validator, CaptchaDdosProtector $ddos)
    {
        $this->generator = $generator;
        $this->validator = $validator;
        $this->ddos = $ddos;
    }

    public function new(Request $request)
    {
        if (! config('captcha.enabled')) {
            return response()->json([
                'enabled' => false,
            ]);
        }

        $decision = $this->ddos->inspect($request, 'new');

        if (! $decision['allowed']) {
            return $this->blockedResponse($decision);
        }

        try {
            return response()->json(array_merge([
                'enabled' => true,
            ], $this->generator->generate($request)));
        } catch (Throwable $exception) {
            return response()->json([
                'enabled' => true,
                'success' => false,
                'reason' => 'generation_failed',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function verify(Request $request)
    {
        if (! config('captcha.enabled')) {
            return response()->json([
                'enabled' => false,
                'success' => true,
            ]);
        }

        $decision = $this->ddos->inspect($request, 'verify');

        if (! $decision['allowed']) {
            return $this->blockedResponse($decision);
        }

        $validator = Validator::make($request->all(), [
            'challenge_id' => ['required', 'string'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'rotation' => [config('captcha.rotation.enabled', true) ? 'required' : 'nullable', 'numeric'],
            'movement' => ['nullable', 'array'],
            'movement.*.x' => ['required_with:movement', 'numeric'],
            'movement.*.y' => ['required_with:movement', 'numeric'],
            'movement.*.t' => ['required_with:movement', 'numeric'],
            'movement.*.r' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            $failureDecision = $this->ddos->recordFailure($request, 'verify', 'validation_failed', [
                'fields' => array_keys($validator->failed()),
            ]);

            if (! $failureDecision['allowed']) {
                return $this->blockedResponse($failureDecision);
            }

            return response()->json([
                'success' => false,
                'reason' => 'validation_failed',
                'message' => 'Os dados enviados para o CAPTCHA são inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->validator->validate(
            (string) $request->input('challenge_id'),
            (float) $request->input('x'),
            (float) $request->input('y'),
            $request->has('rotation') ? (float) $request->input('rotation') : null,
            (array) $request->input('movement', []),
            $request
        );

        if (empty($result['success'])) {
            $failureDecision = $this->ddos->recordFailure($request, 'verify', (string) ($result['reason'] ?? 'unknown'), [
                'distance' => isset($result['distance']) ? $result['distance'] : null,
                'rotation_distance' => isset($result['rotation_distance']) ? $result['rotation_distance'] : null,
            ]);

            if (! $failureDecision['allowed']) {
                return $this->blockedResponse($failureDecision);
            }
        }

        return response()->json($result);
    }

    public function image(Request $request, $path)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $path = $this->normalizeImagePath($path);

        if (! $this->isAllowedGeneratedImagePath($path)) {
            abort(404);
        }

        $storagePath = $this->generatedStoragePath($path);

        try {
            $contents = Storage::disk((string) config('captcha.storage_disk', 's3'))->get($storagePath);
        } catch (Throwable $exception) {
            abort(404);
        }

        if (! is_string($contents) || $contents === '') {
            abort(404);
        }

        try {
            $image = $this->readImage($contents);
            $encoded = $this->encodePng($image);
        } catch (Throwable $exception) {
            abort(404);
        }

        return response($encoded, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=' . max(0, (int) config('captcha.temporary_url_ttl', 300)),
        ]);
    }

    protected function blockedResponse(array $decision)
    {
        $retryAfter = isset($decision['retry_after']) ? (int) $decision['retry_after'] : 60;

        return response()->json($this->ddos->blockedResponse($decision), 429)
            ->header('Retry-After', $retryAfter);
    }

    protected function normalizeImagePath($path)
    {
        return trim(str_replace('\\', '/', (string) $path), '/');
    }

    protected function isAllowedGeneratedImagePath($path)
    {
        return $path !== ''
            && strpos($path, '..') === false
            && substr($path, -4) === '.png';
    }

    protected function generatedStoragePath($path)
    {
        $generatedPath = trim((string) config('captcha.generated_path', 'slide-captcha/generated'), '/');

        if ($generatedPath === '') {
            return $path;
        }

        return $generatedPath . '/' . ltrim($path, '/');
    }

    protected function readImage($contents)
    {
        $manager = $this->imageManager();

        if (method_exists($manager, 'read')) {
            return $manager->read($contents);
        }

        return $manager->make($contents);
    }

    protected function imageManager()
    {
        if (class_exists('Intervention\\Image\\Drivers\\Gd\\Driver')) {
            $driver = 'Intervention\\Image\\Drivers\\Gd\\Driver';

            return new ImageManager(new $driver());
        }

        return new ImageManager(['driver' => 'gd']);
    }

    protected function encodePng($image)
    {
        if (method_exists($image, 'toPng')) {
            return (string) $image->toPng();
        }

        if (method_exists($image, 'encode')) {
            return (string) $image->encode('png');
        }

        abort(404);
    }
}
