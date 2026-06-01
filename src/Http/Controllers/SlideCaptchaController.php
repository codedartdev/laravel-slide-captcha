<?php

namespace CodeDart\SlideCaptcha\Http\Controllers;

use CodeDart\SlideCaptcha\Services\CaptchaGenerator;
use CodeDart\SlideCaptcha\Services\CaptchaValidator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SlideCaptchaController extends Controller
{
    protected $generator;

    protected $validator;

    public function __construct(CaptchaGenerator $generator, CaptchaValidator $validator)
    {
        $this->generator = $generator;
        $this->validator = $validator;
    }

    public function new(Request $request)
    {
        if (! config('slide-captcha.enabled')) {
            return response()->json([
                'enabled' => false,
            ]);
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
        if (! config('slide-captcha.enabled')) {
            return response()->json([
                'enabled' => false,
                'success' => true,
            ]);
        }

        $validator = Validator::make($request->all(), [
            'challenge_id' => ['required', 'string'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'rotation' => [config('slide-captcha.rotation.enabled', true) ? 'required' : 'nullable', 'numeric'],
            'movement' => ['nullable', 'array'],
            'movement.*.x' => ['required_with:movement', 'numeric'],
            'movement.*.y' => ['required_with:movement', 'numeric'],
            'movement.*.t' => ['required_with:movement', 'numeric'],
            'movement.*.r' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'reason' => 'validation_failed',
                'message' => 'Os dados enviados para o CAPTCHA são inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return response()->json($this->validator->validate(
            (string) $request->input('challenge_id'),
            (float) $request->input('x'),
            (float) $request->input('y'),
            $request->has('rotation') ? (float) $request->input('rotation') : null,
            (array) $request->input('movement', []),
            $request
        ));
    }
}
