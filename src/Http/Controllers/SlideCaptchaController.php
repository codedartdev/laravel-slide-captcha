<?php

namespace CodeDart\SlideCaptcha\Http\Controllers;

use CodeDart\SlideCaptcha\Services\CaptchaGenerator;
use CodeDart\SlideCaptcha\Services\CaptchaValidator;
use CodeDart\SlideCaptcha\Services\CaptchaDdosProtector;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
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

    protected function blockedResponse(array $decision)
    {
        $retryAfter = isset($decision['retry_after']) ? (int) $decision['retry_after'] : 60;

        return response()->json($this->ddos->blockedResponse($decision), 429)
            ->header('Retry-After', $retryAfter);
    }
}
