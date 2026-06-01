<?php

namespace CodeDart\SlideCaptcha\Services;

use CodeDart\SlideCaptcha\Support\MovementAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CaptchaValidator
{
    protected $movementAnalyzer;

    public function __construct(MovementAnalyzer $movementAnalyzer)
    {
        $this->movementAnalyzer = $movementAnalyzer;
    }

    public function validate($challengeId, $x, $y, $rotation = null, array $movement = [], Request $request = null)
    {
        $cache = $this->cache();
        $challengeKey = CaptchaGenerator::challengeKey($challengeId);
        $challenge = $cache->get($challengeKey);

        if (! is_array($challenge)) {
            return $this->failure('not_found', 'O desafio do CAPTCHA expirou ou não existe.');
        }

        if (! empty($challenge['used'])) {
            $this->forgetChallenge($cache, $challengeKey, $challenge);

            return $this->failure('used', 'Este desafio do CAPTCHA já foi usado.');
        }

        $createdAt = (int) ($challenge['created_at'] ?? 0);
        $ttl = max(1, (int) config('slide-captcha.ttl', 120));

        if ($createdAt > 0 && (time() - $createdAt) > $ttl) {
            $this->forgetChallenge($cache, $challengeKey, $challenge);

            return $this->failure('expired', 'O desafio do CAPTCHA expirou.');
        }

        if (config('slide-captcha.validate_movement', true)) {
            $movementResult = $this->movementAnalyzer->analyze($movement);

            if (! $movementResult['success']) {
                $this->forgetChallenge($cache, $challengeKey, $challenge);

                return $movementResult;
            }
        }

        $distance = sqrt(
            pow($x - (float) $challenge['target_x'], 2)
            + pow($y - (float) $challenge['target_y'], 2)
        );

        if ($distance > (float) config('slide-captcha.tolerance', 8)) {
            $this->forgetChallenge($cache, $challengeKey, $challenge);

            return $this->failure('invalid_position', 'A posição enviada não confere com o desafio.', [
                'distance' => round($distance, 2),
            ]);
        }

        if (! empty($challenge['rotation_enabled'])) {
            if (! is_numeric($rotation)) {
                $this->forgetChallenge($cache, $challengeKey, $challenge);

                return $this->failure('invalid_rotation', 'A rotação enviada para o CAPTCHA é inválida.');
            }

            $rotationDistance = $this->angularDistance(
                (float) $rotation,
                (float) ($challenge['target_rotation'] ?? 0)
            );
            $rotationTolerance = (float) ($challenge['rotation_tolerance'] ?? config('slide-captcha.rotation.tolerance_degrees', 8));

            if ($rotationDistance > $rotationTolerance) {
                $this->forgetChallenge($cache, $challengeKey, $challenge);

                return $this->failure('invalid_rotation', 'A rotação enviada não confere com o desafio.', [
                    'rotation_distance' => round($rotationDistance, 2),
                ]);
            }
        }

        $this->forgetChallenge($cache, $challengeKey, $challenge);

        $verificationToken = bin2hex(random_bytes(32));
        $cache->put(self::verificationKey($verificationToken), [
            'challenge_id' => $challengeId,
            'created_at' => time(),
        ], 300);

        return [
            'success' => true,
            'verification_token' => $verificationToken,
        ];
    }

    public static function verificationKey($token)
    {
        return 'slide_captcha_verified:' . hash('sha256', (string) $token);
    }

    protected function failure($reason, $message, array $extra = [])
    {
        return array_merge([
            'success' => false,
            'reason' => $reason,
            'message' => $message,
        ], $extra);
    }

    protected function angularDistance($given, $target)
    {
        $given = fmod(fmod($given, 360.0) + 360.0, 360.0);
        $target = fmod(fmod($target, 360.0) + 360.0, 360.0);
        $distance = abs($given - $target);

        return min($distance, 360 - $distance);
    }

    protected function forgetChallenge($cache, $challengeKey, array $challenge)
    {
        $cache->forget($challengeKey);

        $paths = array_values(array_filter([
            $challenge['background_path'] ?? null,
            $challenge['piece_path'] ?? null,
        ]));

        if (count($paths) > 0) {
            try {
                $this->storage()->delete($paths);
            } catch (Throwable $exception) {
                // The signed URLs expire independently; cleanup should not block validation.
            }
        }
    }

    protected function cache()
    {
        $store = config('slide-captcha.cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }

    protected function storage()
    {
        return Storage::disk((string) config('slide-captcha.storage_disk', 's3'));
    }
}
