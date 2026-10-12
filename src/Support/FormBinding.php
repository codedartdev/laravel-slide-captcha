<?php

namespace CodeDart\SlideCaptcha\Support;

use Illuminate\Http\Request;
use InvalidArgumentException;

final class FormBinding
{
    private const CONTROL_FIELDS = ['_token', 'slide_captcha_token', 'slide_captcha_verified', 'slide_captcha_challenge_id'];

    // Read the wire values before Laravel trims strings or converts empty strings.
    public static function requestData(Request $request): array
    {
        if (count($request->allFiles()) > 0) {
            throw new InvalidArgumentException('File uploads require a separate binding strategy.');
        }

        if ($request->isJson() && $request->getContent() !== '') {
            $data = json_decode($request->getContent(), false, 64, JSON_THROW_ON_ERROR);
            if (! $data instanceof \stdClass) {
                throw new InvalidArgumentException('Expected a JSON object.');
            }
            return array_replace($request->query->all(), (array) $data);
        }

        if (strpos((string) $request->header('Content-Type'), 'application/x-www-form-urlencoded') === 0 && $request->getContent() !== '') {
            parse_str($request->getContent(), $data);
            return array_replace($request->query->all(), $data);
        }

        return $request->all();
    }

    public static function fingerprint(array $data): string
    {
        foreach (self::CONTROL_FIELDS as $field) {
            unset($data[$field]);
        }
        $key = (string) config('app.key', '');
        if ($key === '') {
            throw new InvalidArgumentException('An application key is required for form binding.');
        }
        return hash_hmac('sha256', 'v2:' . json_encode(self::canonical($data), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), $key);
    }

    public static function matches(array $payload, Request $request): bool
    {
        if (! isset($payload['form_fingerprint'])) {
            return ! config('captcha.require_form_binding', true);
        }
        try {
            return is_string($payload['form_fingerprint'])
                && hash_equals($payload['form_fingerprint'], self::fingerprint(self::requestData($request)));
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private static function canonical($value)
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            foreach ($properties as $key => $item) {
                $properties[$key] = self::canonical($item);
            }
            return (object) $properties;
        }
        if (is_array($value)) {
            // Preserve list ordering, but ignore object key ordering.
            if ($value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $item) {
                $value[$key] = self::canonical($item);
            }
        } elseif (! is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('Unsupported form value.');
        }
        return $value;
    }
}
