<?php

namespace CodeDart\SlideCaptcha\Rules;

use CodeDart\SlideCaptcha\Services\CaptchaValidator;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Cache;

class SlideCaptchaVerified implements Rule
{
    protected $message = 'A validação do CAPTCHA é inválida ou expirou.';

    public function passes($attribute, $value)
    {
        if (! config('slide-captcha.enabled')) {
            return true;
        }

        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $cache = $this->cache();
        $key = CaptchaValidator::verificationKey($value);
        $payload = $cache->get($key);

        if (! $payload) {
            return false;
        }

        $cache->forget($key);

        return true;
    }

    public function message()
    {
        return $this->message;
    }

    protected function cache()
    {
        $store = config('slide-captcha.cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }
}
