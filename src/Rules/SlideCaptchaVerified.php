<?php

namespace CodeDart\SlideCaptcha\Rules;

use CodeDart\SlideCaptcha\Services\CaptchaValidator;
use CodeDart\SlideCaptcha\Support\FormBinding;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Cache;

class SlideCaptchaVerified implements Rule
{
    protected $message = 'A validação do CAPTCHA é inválida ou expirou.';

    public function passes($attribute, $value)
    {
        if (! config('captcha.enabled')) {
            return true;
        }

        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $cache = $this->cache();
        $key = CaptchaValidator::verificationKey($value);
        $lock = $cache->lock('slide-captcha-verification:' . hash('sha256', $key), 5);
        if (! $lock->get()) {
            return false;
        }
        try {
            $payload = $cache->get($key);
            if (! is_array($payload)) {
                return false;
            }
            $cache->forget($key);
            return FormBinding::matches($payload, request());
        } finally {
            $lock->release();
        }
    }

    public function message()
    {
        return $this->message;
    }

    protected function cache()
    {
        $store = config('captcha.cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }
}
