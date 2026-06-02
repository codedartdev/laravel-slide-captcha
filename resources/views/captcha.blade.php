@once
    <link rel="stylesheet" href="{{ route('slide-captcha.asset', ['file' => 'slide-captcha.css']) }}">
    <script defer src="{{ route('slide-captcha.asset', ['file' => 'slide-captcha.js']) }}"></script>
@endonce

<div
    class="slide-captcha"
    data-slide-captcha
    data-slide-captcha-new-url="{{ route('slide-captcha.new') }}"
    data-slide-captcha-verify-url="{{ route('slide-captcha.verify') }}"
    data-slide-captcha-width="{{ (int) config('captcha.image_width') }}"
    data-slide-captcha-height="{{ (int) config('captcha.image_height') }}"
    style="--slide-captcha-width: {{ (int) config('captcha.image_width') }}; --slide-captcha-height: {{ (int) config('captcha.image_height') }};"
>
    <div class="slide-captcha__stage" data-slide-captcha-stage>
        <img class="slide-captcha__background" data-slide-captcha-background alt="">
        <img class="slide-captcha__piece" data-slide-captcha-piece alt="">
    </div>

    <input type="hidden" name="slide_captcha_challenge_id" data-slide-captcha-challenge-id>
    <input type="hidden" name="slide_captcha_token" data-slide-captcha-token>
    <input type="hidden" name="slide_captcha_verified" value="0" data-slide-captcha-verified>

    <div class="slide-captcha__rotation" data-slide-captcha-rotation-controls hidden>
        <button class="slide-captcha__control" type="button" data-slide-captcha-rotate-left aria-label="Girar peça para a esquerda">
            -15°
        </button>
        <button class="slide-captcha__control" type="button" data-slide-captcha-check>
            Verificar
        </button>
        <button class="slide-captcha__control" type="button" data-slide-captcha-rotate-right aria-label="Girar peça para a direita">
            +15°
        </button>
    </div>

    <div class="slide-captcha__actions">
        <button class="slide-captcha__reload" type="button" data-slide-captcha-reload>
            Recarregar
        </button>
        <span class="slide-captcha__status" aria-live="polite" data-slide-captcha-status></span>
    </div>
</div>
