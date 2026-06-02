<?php

namespace CodeDart\SlideCaptcha\Http\Controllers;

use Illuminate\Routing\Controller;

class SlideCaptchaAssetController extends Controller
{
    public function __invoke($file)
    {
        $allowed = [
            'slide-captcha.css' => 'text/css; charset=UTF-8',
            'slide-captcha.js' => 'application/javascript; charset=UTF-8',
        ];

        if (! isset($allowed[$file])) {
            abort(404);
        }

        $path = $this->assetPath($file);

        if (! is_file($path)) {
            abort(404);
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => $allowed[$file],
            'Cache-Control' => 'public, max-age=' . max(0, (int) config('captcha.asset_cache_ttl', 86400)),
        ]);
    }

    protected function assetPath($file)
    {
        $dist = [
            'slide-captcha.css' => __DIR__ . '/../../../resources/dist/slide-captcha.min.css',
            'slide-captcha.js' => __DIR__ . '/../../../resources/dist/slide-captcha.min.js',
        ];

        if (isset($dist[$file]) && is_file($dist[$file])) {
            return $dist[$file];
        }

        return __DIR__ . '/../../../resources/assets/' . $file;
    }
}
