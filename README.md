# Laravel Slide CAPTCHA

CAPTCHA visual self-hosted para Laravel, baseado no desafio de arrastar uma peça até a posição correta da imagem.

O pacote gera um desafio, recorta uma peça da imagem, salva temporariamente os arquivos gerados em um disco privado, normalmente S3, e retorna URLs internas temporariamente assinadas para o navegador. A posição correta fica somente no backend e é armazenada em cache por poucos segundos.

## Introdução

O `codedart/laravel-slide-captcha` ajuda a proteger formulários Laravel contra envios automatizados.

Use esta biblioteca quando você precisa de um CAPTCHA simples, visual e controlado pela própria aplicação, sem depender de serviços externos como Google reCAPTCHA, hCaptcha ou Cloudflare Turnstile.

Ela resolve um problema comum em formulários públicos:

- Bots enviando formulários de contato.
- Cadastros automatizados.
- Tentativas repetidas em páginas sensíveis.
- Necessidade de validar interação humana sem enviar dados para provedores externos.

O usuário vê uma imagem, arrasta a peça até o ponto correto, gira a peça quando o desafio exigir rotação e, se acertar, recebe um token temporário. Esse token deve ser enviado junto com o formulário final.

## Requisitos

- PHP `>= 7.4`
- Laravel `>= 8`
- Composer
- Extensão PHP `gd`
- Cache configurado no Laravel
- Disco de storage privado legível pela aplicação
- Recomendado: Redis para cache
- Recomendado: S3 ou storage compatível com S3 para armazenar as imagens geradas

Dependências usadas pelo pacote:

- `illuminate/support`
- `illuminate/routing`
- `illuminate/cache`
- `illuminate/filesystem`
- `illuminate/http`
- `illuminate/validation`
- `intervention/image`

Para usar S3 em um projeto Laravel, garanta que o driver esteja instalado e configurado. Em muitos projetos Laravel modernos, isso é feito com:

```bash
composer require league/flysystem-aws-s3-v3
```

Depois configure o disco `s3` no `.env` da aplicação Laravel.

## Instalação

Instale o pacote com Composer:

```bash
composer require codedart/laravel-slide-captcha
```

O Laravel deve registrar o service provider automaticamente.

Este pacote não exige migrations, não exige publicação de assets e não exige publicação de views para funcionar.

Os assets JavaScript e CSS são servidos por rotas internas do próprio pacote.

Em produção, o pacote serve automaticamente os assets minificados de `resources/dist`. Os fontes legíveis ficam em `resources/assets`.

As rotas criadas pelo pacote são:

```text
GET  /slide-captcha/assets/slide-captcha.css
GET  /slide-captcha/assets/slide-captcha.js
GET  /slide-captcha/new
GET  /slide-captcha/generated/{path}
POST /slide-captcha/verify
```

Para conferir se as rotas foram registradas:

```bash
php artisan route:list
```

Se você estiver testando este pacote localmente, antes de publicar no Packagist, adicione um repositório path no `composer.json` da aplicação Laravel:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-slide-captcha",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Depois instale:

```bash
composer require codedart/laravel-slide-captcha:@dev
```

### Build dos assets

O pacote já inclui os arquivos minificados prontos para uso.

Se você alterar `resources/assets/slide-captcha.js` ou `resources/assets/slide-captcha.css`, gere a build novamente:

```bash
composer build-assets
```

Esse comando atualiza:

```text
resources/dist/slide-captcha.min.js
resources/dist/slide-captcha.min.css
```

O objetivo é reduzir o tamanho dos arquivos enviados ao navegador e diminuir o custo de parse no dispositivo do usuário.

### Testes

Para rodar a suíte de testes do pacote:

```bash
composer install
composer test
```

Os testes cobrem a máscara puzzle, a rotação, a análise de movimento e a validação angular.

## Configuração

A configuração principal é feita pelo `.env` da aplicação Laravel.

Exemplo realista:

```env
SLIDE_CAPTCHA_ENABLED=true
SLIDE_CAPTCHA_CACHE_STORE=redis
SLIDE_CAPTCHA_TTL=120

SLIDE_CAPTCHA_IMAGE_WIDTH=320
SLIDE_CAPTCHA_IMAGE_HEIGHT=180
SLIDE_CAPTCHA_PIECE_MIN_SIZE=42
SLIDE_CAPTCHA_PIECE_MAX_SIZE=58
SLIDE_CAPTCHA_TOLERANCE=8
SLIDE_CAPTCHA_ROTATION_ENABLED=true
SLIDE_CAPTCHA_ROTATION_STEP_DEGREES=15
SLIDE_CAPTCHA_ROTATION_MAX_DEGREES=90
SLIDE_CAPTCHA_ROTATION_TOLERANCE_DEGREES=8

SLIDE_CAPTCHA_ROUTE_PREFIX=slide-captcha
SLIDE_CAPTCHA_MIDDLEWARE=web

SLIDE_CAPTCHA_STORAGE_DISK=s3
SLIDE_CAPTCHA_GENERATED_PATH=slide-captcha/generated
SLIDE_CAPTCHA_TEMPORARY_URL_TTL=300

SLIDE_CAPTCHA_VALIDATE_MOVEMENT=true
SLIDE_CAPTCHA_MOVEMENT_MIN_POINTS=8
SLIDE_CAPTCHA_MOVEMENT_MIN_DURATION_MS=250
SLIDE_CAPTCHA_MOVEMENT_MAX_DURATION_MS=15000
SLIDE_CAPTCHA_MOVEMENT_MAX_SAME_Y_RATIO=0.9

SLIDE_CAPTCHA_DDOS_ENABLED=true
SLIDE_CAPTCHA_DDOS_MODE=adaptive
SLIDE_CAPTCHA_DDOS_REPORTING_SINKS=cache
SLIDE_CAPTCHA_DDOS_BROADCAST_ENABLED=auto
```

### Variáveis disponíveis

`SLIDE_CAPTCHA_ENABLED`

Ativa ou desativa o CAPTCHA. Use `false` apenas em ambientes controlados, como testes locais.

`SLIDE_CAPTCHA_CACHE_STORE`

Define o cache usado para armazenar desafios e tokens. Exemplo: `redis`. Se ficar vazio, usa o cache padrão do Laravel.

`SLIDE_CAPTCHA_TTL`

Tempo de validade do desafio, em segundos. Padrão: `120`.

`SLIDE_CAPTCHA_IMAGE_WIDTH`

Largura da imagem do CAPTCHA. Padrão: `320`.

`SLIDE_CAPTCHA_IMAGE_HEIGHT`

Altura da imagem do CAPTCHA. Padrão: `180`.

`SLIDE_CAPTCHA_PIECE_MIN_SIZE`

Tamanho mínimo da peça recortada. Padrão: `42`.

`SLIDE_CAPTCHA_PIECE_MAX_SIZE`

Tamanho máximo da peça recortada. Padrão: `58`.

`SLIDE_CAPTCHA_TOLERANCE`

Margem de erro permitida, em pixels. Padrão: `8`.

`SLIDE_CAPTCHA_ROTATION_ENABLED`

Ativa a rotação obrigatória da peça. Padrão: `true`.

Quando ativa, o encaixe no background aparece girado, a peça começa em `0°` e o usuário precisa girá-la antes de verificar.

`SLIDE_CAPTCHA_ROTATION_STEP_DEGREES`

Quantidade de graus aplicada a cada clique nos botões de rotação. Padrão: `15`.

`SLIDE_CAPTCHA_ROTATION_MAX_DEGREES`

Maior ângulo aleatório usado pelo desafio. Padrão: `90`.

`SLIDE_CAPTCHA_ROTATION_TOLERANCE_DEGREES`

Margem de erro permitida para a rotação. Padrão: `8`.

`SLIDE_CAPTCHA_ROUTE_PREFIX`

Prefixo das rotas internas do pacote. Padrão: `slide-captcha`.

`SLIDE_CAPTCHA_MIDDLEWARE`

Middlewares aplicados às rotas do CAPTCHA. Padrão: `web`.

`SLIDE_CAPTCHA_STORAGE_DISK`

Disco onde as imagens geradas serão salvas. Padrão: `s3`.

`SLIDE_CAPTCHA_GENERATED_PATH`

Pasta dentro do disco configurado onde as imagens temporárias serão salvas. Padrão: `slide-captcha/generated`.

`SLIDE_CAPTCHA_TEMPORARY_URL_TTL`

Tempo de validade das URLs internas assinadas das imagens, em segundos. Padrão: `300`.

`SLIDE_CAPTCHA_BACKGROUNDS_PATH`

Diretório local usado para substituir as imagens base padrão do pacote.

Se esta variável não for definida, o pacote usa as imagens incluídas em:

```text
vendor/codedart/laravel-slide-captcha/resources/backgrounds
```

Você pode usar um caminho absoluto:

```env
SLIDE_CAPTCHA_BACKGROUNDS_PATH=/var/www/my-app/storage/app/captcha-backgrounds
```

Ou um caminho relativo à raiz do projeto Laravel:

```env
SLIDE_CAPTCHA_BACKGROUNDS_PATH=storage/app/captcha-backgrounds
```

O diretório deve conter imagens `.jpg`, `.jpeg`, `.png` ou `.webp`.

`SLIDE_CAPTCHA_VALIDATE_MOVEMENT`

Ativa a análise básica do movimento do mouse ou toque. Padrão: `true`.

`SLIDE_CAPTCHA_MOVEMENT_MIN_POINTS`

Quantidade mínima de pontos de movimento enviados pelo navegador.

`SLIDE_CAPTCHA_MOVEMENT_MIN_DURATION_MS`

Duração mínima do movimento, em milissegundos.

`SLIDE_CAPTCHA_MOVEMENT_MAX_DURATION_MS`

Duração máxima do movimento, em milissegundos.

`SLIDE_CAPTCHA_MOVEMENT_MAX_SAME_Y_RATIO`

Proporção máxima permitida de movimentos com o mesmo eixo Y. Ajuda a rejeitar movimentos muito lineares.

### Proteção DDoS e relatórios de ataque

O pacote inclui uma camada adaptativa para proteger os endpoints internos do CAPTCHA.

Ela atua antes da geração de imagem em:

```text
GET /slide-captcha/new
```

E registra sinais suspeitos em:

```text
POST /slide-captcha/verify
```

A identidade padrão combina:

- IP do request.
- Hash do user-agent.
- Hash da sessão Laravel, quando existir.

Quando uma identidade excede os limites, o pacote responde com `429`:

```json
{
  "success": false,
  "reason": "ddos_protection",
  "retry_after": 300
}
```

O header `Retry-After` também é enviado.

Configuração mínima recomendada:

```env
SLIDE_CAPTCHA_DDOS_ENABLED=true
SLIDE_CAPTCHA_DDOS_MODE=adaptive
SLIDE_CAPTCHA_DDOS_NEW_MAX_ATTEMPTS=60
SLIDE_CAPTCHA_DDOS_NEW_DECAY_SECONDS=60
SLIDE_CAPTCHA_DDOS_NEW_BLOCK_SECONDS=300
SLIDE_CAPTCHA_DDOS_VERIFY_MAX_ATTEMPTS=120
SLIDE_CAPTCHA_DDOS_VERIFY_DECAY_SECONDS=60
SLIDE_CAPTCHA_DDOS_VERIFY_BLOCK_SECONDS=300
SLIDE_CAPTCHA_DDOS_FAILURE_MAX_ATTEMPTS=20
SLIDE_CAPTCHA_DDOS_FAILURE_DECAY_SECONDS=60
SLIDE_CAPTCHA_DDOS_FAILURE_BLOCK_SECONDS=600
SLIDE_CAPTCHA_DDOS_SCORE_THRESHOLD=80
SLIDE_CAPTCHA_DDOS_SCORE_DECAY_SECONDS=120
SLIDE_CAPTCHA_DDOS_SCORE_BLOCK_SECONDS=600
```

Use `SLIDE_CAPTCHA_DDOS_MODE=monitor` se quiser apenas observar e emitir relatórios, sem bloquear tráfego.

#### Persistência dos relatórios

Os relatórios são gravados por sinks configuráveis.

O padrão é cache temporário:

```env
SLIDE_CAPTCHA_DDOS_REPORTING_SINKS=cache
SLIDE_CAPTCHA_DDOS_CACHE_TTL=3600
SLIDE_CAPTCHA_DDOS_CACHE_LIMIT=500
```

Você também pode ativar múltiplos sinks:

```env
SLIDE_CAPTCHA_DDOS_REPORTING_SINKS=cache,s3_batch
```

Sinks disponíveis:

- `none`: não persiste; apenas emite eventos em tempo real para listeners/broadcast.
- `cache`: mantém uma janela temporária em cache/Redis.
- `database`: grava linha a linha em uma tabela.
- `s3_batch`: acumula em cache e descarrega em arquivo `.jsonl` no disco configurado.

Para usar banco, publique a migration:

```bash
php artisan vendor:publish --tag=slide-captcha-migrations
php artisan migrate
```

Depois configure:

```env
SLIDE_CAPTCHA_DDOS_REPORTING_SINKS=database
SLIDE_CAPTCHA_DDOS_DATABASE_TABLE=slide_captcha_attack_reports
```

Para usar batch em S3 ou storage compatível:

```env
SLIDE_CAPTCHA_DDOS_REPORTING_SINKS=cache,s3_batch
SLIDE_CAPTCHA_DDOS_S3_BATCH_DISK=s3
SLIDE_CAPTCHA_DDOS_S3_BATCH_PATH=slide-captcha/attack-reports/{date}/{datetime}-{uuid}.jsonl
SLIDE_CAPTCHA_DDOS_S3_BATCH_CACHE_TTL=3600
```

Agende o flush no scheduler da aplicação:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('slide-captcha:flush-attack-reports')->everyMinute();
```

Cada arquivo S3 usa JSON Lines: um relatório JSON por linha.

#### Reverb e eventos em tempo real

O pacote dispara o evento:

```php
CodeDart\SlideCaptcha\Events\SlideCaptchaAttackDetected
```

Quando `SLIDE_CAPTCHA_DDOS_BROADCAST_ENABLED=auto`, o evento só é transmitido se `broadcasting.default` estiver configurado como `reverb`.

Configuração:

```env
SLIDE_CAPTCHA_DDOS_BROADCAST_ENABLED=auto
SLIDE_CAPTCHA_DDOS_BROADCAST_CHANNEL=private-slide-captcha.attacks
SLIDE_CAPTCHA_DDOS_BROADCAST_EVENT=slide-captcha.attack
```

Payload do relatório:

```json
{
  "id": "9f7a2f4f4c3d4c6d91dbff9b82b02c1e",
  "occurred_at": "2026-06-01T12:00:00-03:00",
  "occurred_at_timestamp": 1780329600,
  "action": "blocked",
  "severity": "critical",
  "endpoint": "new",
  "reason": "rate_limit_new",
  "ip": "203.0.113.10",
  "identity_hash": "sha256...",
  "user_agent_hash": "sha256...",
  "session_hash": "sha256...",
  "retry_after": 300,
  "score": 84,
  "limit_key": "slide_captcha_ddos:rate:new:...",
  "request_method": "GET",
  "request_path": "slide-captcha/new",
  "details": {
    "attempts": 61,
    "max_attempts": 60
  }
}
```

Motivos comuns de ataque ou suspeita:

- `rate_limit_new`
- `rate_limit_verify`
- `failure_limit`
- `score_threshold`
- `validation_failed`
- `not_found`
- `used`
- `expired`
- `invalid_position`
- `invalid_rotation`
- `movement_too_short`
- `movement_too_fast`
- `movement_too_slow`
- `movement_too_linear`

#### Métricas para dashboard próprio

Este pacote não renderiza dashboard. Para consultar métricas e montar sua própria tela, injete `SlideCaptchaMetrics`.

Exemplo mínimo:

```php
<?php

use CodeDart\SlideCaptcha\Services\SlideCaptchaMetrics;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->get('/admin/slide-captcha/metrics', function (SlideCaptchaMetrics $metrics) {
    return response()->json($metrics->snapshot());
});
```

O snapshot retorna:

- `total_events`
- `blocked_events`
- `by_severity`
- `by_endpoint`
- `by_reason`
- `top_ips`
- `top_identities`
- `last_event`
- `events`
- `window`

### Configuração do S3

Configure o disco `s3` no `.env` da aplicação Laravel:

```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

As imagens geradas são privadas. O navegador acessa rotas internas assinadas do pacote; essas rotas leem a imagem no disco configurado, carregam com Intervention e retornam o PNG pela própria aplicação.

O usuário ou role da AWS precisa ter permissão para:

- `s3:PutObject`
- `s3:GetObject`
- `s3:DeleteObject`
- `s3:ListBucket`, se exigido pela configuração do bucket

### Publicar configuração

Não é obrigatório publicar a configuração.

Se quiser customizar o arquivo `config/captcha.php`, rode:

```bash
php artisan vendor:publish --tag=captcha-config
```

Também é possível usar a tag antiga `slide-captcha-config`.

Depois limpe o cache de configuração:

```bash
php artisan config:clear
```

Também é possível publicar a view se quiser alterar o HTML do widget:

```bash
php artisan vendor:publish --tag=slide-captcha-views
```

## Uso básico

Inclua o CAPTCHA no formulário Blade:

```blade
{{-- resources/views/contact.blade.php --}}

<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Contato</title>
</head>
<body>
    <form method="POST" action="{{ route('contact.store') }}">
        @csrf

        <label>
            Nome
            <input type="text" name="name" value="{{ old('name') }}">
        </label>

        <label>
            Mensagem
            <textarea name="message">{{ old('message') }}</textarea>
        </label>

        @include('slide-captcha::captcha')

        @error('slide_captcha_token')
            <p>{{ $message }}</p>
        @enderror

        <button type="submit">Enviar</button>
    </form>
</body>
</html>
```

O pacote adiciona os campos ocultos automaticamente:

```text
slide_captcha_challenge_id
slide_captcha_token
slide_captcha_verified
```

No controller, valide o token:

```php
<?php

namespace App\Http\Controllers;

use CodeDart\SlideCaptcha\Rules\SlideCaptchaVerified;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'slide_captcha_token' => ['required', new SlideCaptchaVerified],
            'slide_captcha_verified' => ['accepted'],
        ]);

        // Envie e-mail, salve no banco ou execute sua lógica.

        return back()->with('status', 'Mensagem enviada com sucesso.');
    }
}
```

A regra `SlideCaptchaVerified` busca o token no cache e apaga o token após o uso. Isso impede reutilização.

## Frontend com React e React Native

Além da view Blade incluída neste pacote, você pode usar componentes prontos para aplicações frontend modernas:

- React Web: [`@codedart/slide-captcha-react`](https://github.com/codedartdev/slide-captcha-react)
- React Native e Expo: [`@codedart/slide-captcha-react-native`](https://github.com/codedartdev/slide-captcha-react-native)

Esses pacotes não substituem este backend Laravel. Eles apenas consomem os endpoints já criados por esta biblioteca:

```text
GET  /slide-captcha/new
POST /slide-captcha/verify
```

O fluxo continua o mesmo:

1. O frontend carrega um desafio.
2. O usuário arrasta e gira a peça, quando houver rotação.
3. O frontend envia a tentativa para `/slide-captcha/verify`.
4. O backend retorna um token temporário.
5. O formulário final envia `slide_captcha_token`.
6. O Laravel valida esse token com `SlideCaptchaVerified`.

### React com Laravel Vite e react-hook-form

Use este caminho quando o formulário é renderizado por React dentro de uma aplicação Laravel.

Instale as dependências:

```bash
npm install react react-dom react-hook-form @codedart/slide-captcha-react
npm install -D @vitejs/plugin-react
```

Configure o Vite para compilar React.

Arquivo: `vite.config.js`

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/login.tsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
```

#### Rotas do login

Arquivo: `routes/web.php`

```php
<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'login'])
        ->name('login.store');
});
```

#### Controller do login

Arquivo: `app/Http/Controllers/Auth/LoginController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use CodeDart\SlideCaptcha\Rules\SlideCaptchaVerified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'slide_captcha_token' => ['required', new SlideCaptchaVerified],
        ]);

        $throttleKey = Str::lower($request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Muitas tentativas. Aguarde alguns segundos e tente novamente.',
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas não conferem.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        return response()->json([
            'redirect_to' => url('/dashboard'),
        ]);
    }
}
```

Quando você usa o pacote React, o campo essencial é `slide_captcha_token`. O campo `slide_captcha_verified` é usado pela view Blade padrão, mas não é obrigatório neste fluxo.

#### View Blade

Arquivo: `resources/views/auth/login.blade.php`

```blade
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login</title>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/login.tsx'])
</head>
<body>
    <div id="login-root"></div>
</body>
</html>
```

#### Componente React

Arquivo: `resources/js/login.tsx`

```tsx
import { SlideCaptcha } from '@codedart/slide-captcha-react';
import '@codedart/slide-captcha-react/styles.css';
import { createRoot } from 'react-dom/client';
import { useForm } from 'react-hook-form';

type LoginFormData = {
    email: string;
    password: string;
    slide_captcha_token: string;
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
}

function LoginForm() {
    const {
        register,
        handleSubmit,
        setError,
        setValue,
        clearErrors,
        formState: { errors, isSubmitting },
    } = useForm<LoginFormData>({
        defaultValues: {
            email: '',
            password: '',
            slide_captcha_token: '',
        },
    });

    async function submit(values: LoginFormData) {
        const response = await fetch('/login', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(values),
        });

        if (response.status === 422) {
            const payload = await response.json();

            Object.entries(payload.errors || {}).forEach(([field, messages]) => {
                setError(field as keyof LoginFormData, {
                    message: Array.isArray(messages) ? messages[0] : 'Campo inválido.',
                });
            });

            setValue('slide_captcha_token', '', { shouldValidate: true });
            return;
        }

        if (! response.ok) {
            setError('email', {
                message: 'Não foi possível entrar. Tente novamente.',
            });
            return;
        }

        const payload = await response.json();
        window.location.href = payload.redirect_to || '/dashboard';
    }

    return (
        <main className="login-page">
            <form className="login-form" onSubmit={handleSubmit(submit)}>
                <h1>Entrar</h1>

                <div>
                    <label htmlFor="email">E-mail</label>
                    <input
                        id="email"
                        type="email"
                        autoComplete="email"
                        {...register('email', {
                            required: 'Informe seu e-mail.',
                        })}
                    />
                    {errors.email ? <p>{errors.email.message}</p> : null}
                </div>

                <div>
                    <label htmlFor="password">Senha</label>
                    <input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        {...register('password', {
                            required: 'Informe sua senha.',
                        })}
                    />
                    {errors.password ? <p>{errors.password.message}</p> : null}
                </div>

                <input
                    type="hidden"
                    {...register('slide_captcha_token', {
                        required: 'Resolva o CAPTCHA antes de entrar.',
                    })}
                />

                <SlideCaptcha
                    csrfToken={csrfToken()}
                    onSuccess={(token) => {
                        setValue('slide_captcha_token', token, { shouldValidate: true });
                        clearErrors('slide_captcha_token');
                    }}
                    onError={() => {
                        setValue('slide_captcha_token', '', { shouldValidate: true });
                    }}
                />

                {errors.slide_captcha_token ? <p>{errors.slide_captcha_token.message}</p> : null}

                <button type="submit" disabled={isSubmitting}>
                    {isSubmitting ? 'Entrando...' : 'Entrar'}
                </button>
            </form>
        </main>
    );
}

createRoot(document.getElementById('login-root') as HTMLElement).render(<LoginForm />);
```

Se o React estiver no mesmo domínio do Laravel, `baseUrl` pode ser omitido. Se estiver em outro host, configure a URL no `.env` do Vite:

```env
VITE_API_BASE_URL=https://api.example.com
```

E informe no componente:

```tsx
<SlideCaptcha
    baseUrl={import.meta.env.VITE_API_BASE_URL}
    csrfToken={csrfToken()}
    onSuccess={(token) => setValue('slide_captcha_token', token, { shouldValidate: true })}
/>
```

### React Native e Expo

Use este caminho quando o CAPTCHA será usado em um aplicativo mobile.

Instale o pacote:

```bash
npm install @codedart/slide-captcha-react-native
```

O app mobile precisa chamar uma URL absoluta. Em desenvolvimento, use o IP da sua máquina na rede:

```text
http://192.168.0.10:8000
```

Não use `localhost` no celular físico, porque `localhost` aponta para o próprio aparelho.

Para um fluxo mobile simples, você pode expor os endpoints do CAPTCHA por `api`:

```env
SLIDE_CAPTCHA_ROUTE_PREFIX=api/slide-captcha
SLIDE_CAPTCHA_MIDDLEWARE=api
```

Com essa configuração, use `baseUrl` apontando para `/api`:

```tsx
baseUrl="http://192.168.0.10:8000/api"
```

Se sua aplicação mobile usa Laravel Sanctum com cookies e CSRF, você também pode manter o middleware `web`, desde que envie cookies e CSRF corretamente. Para a maioria dos apps nativos, usar endpoints de API é mais simples.

#### Exemplo de tela de login

Arquivo: `App.tsx`

```tsx
import { useState } from 'react';
import { Pressable, Text, TextInput, View } from 'react-native';
import { SlideCaptcha, SlideCaptchaError } from '@codedart/slide-captcha-react-native';

const API_BASE_URL = 'http://192.168.0.10:8000/api';

export default function LoginScreen() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [captchaVisible, setCaptchaVisible] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    async function submitLogin(slideCaptchaToken: string) {
        setSubmitting(true);
        setError('');

        try {
            const response = await fetch(`${API_BASE_URL}/login`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email,
                    password,
                    slide_captcha_token: slideCaptchaToken,
                }),
            });

            if (! response.ok) {
                setError('Não foi possível entrar. Confira seus dados e tente novamente.');
            }
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <View style={{ flex: 1, justifyContent: 'center', gap: 12, padding: 24 }}>
            <Text>E-mail</Text>
            <TextInput
                value={email}
                onChangeText={setEmail}
                autoCapitalize="none"
                keyboardType="email-address"
                style={{ borderWidth: 1, padding: 10 }}
            />

            <Text>Senha</Text>
            <TextInput
                value={password}
                onChangeText={setPassword}
                secureTextEntry
                style={{ borderWidth: 1, padding: 10 }}
            />

            {error ? <Text>{error}</Text> : null}

            <Pressable disabled={submitting} onPress={() => setCaptchaVisible(true)}>
                <Text>{submitting ? 'Entrando...' : 'Entrar'}</Text>
            </Pressable>

            <SlideCaptcha
                baseUrl={API_BASE_URL}
                visible={captchaVisible}
                onRequestClose={() => setCaptchaVisible(false)}
                onSuccess={(token) => {
                    setCaptchaVisible(false);
                    void submitLogin(token);
                }}
                onError={(captchaError) => {
                    setSubmitting(false);

                    if (captchaError instanceof SlideCaptchaError) {
                        setError(captchaError.message);
                        return;
                    }

                    setError('Falha ao validar o CAPTCHA.');
                }}
            />
        </View>
    );
}
```

#### Rota de login para API

Arquivo: `routes/api.php`

```php
<?php

use App\Http\Controllers\Api\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1');
```

Arquivo: `app/Http/Controllers/Api/LoginController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use CodeDart\SlideCaptcha\Rules\SlideCaptchaVerified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'slide_captcha_token' => ['required', new SlideCaptchaVerified],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas não conferem.',
            ]);
        }

        return response()->json([
            'user' => $user,
            // Retorne aqui o token usado pela sua aplicação, como Sanctum, Passport ou JWT.
        ]);
    }
}
```

Em produção, use HTTPS para proteger credenciais, cookies e tokens.

## Exemplo prático em um projeto real

Este exemplo cria uma página de contato protegida pelo CAPTCHA.

### 1. Rotas

Arquivo: `routes/web.php`

```php
<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/contato', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contato', [ContactController::class, 'store'])->name('contact.store');
```

### 2. Controller

Arquivo: `app/Http/Controllers/ContactController.php`

```php
<?php

namespace App\Http\Controllers;

use CodeDart\SlideCaptcha\Rules\SlideCaptchaVerified;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function create()
    {
        return view('contact.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'slide_captcha_token' => ['required', new SlideCaptchaVerified],
            'slide_captcha_verified' => ['accepted'],
        ]);

        // Aqui você pode salvar no banco, enviar e-mail ou criar um ticket.

        return redirect()
            ->route('contact.create')
            ->with('status', 'Mensagem enviada com sucesso.');
    }
}
```

### 3. View

Arquivo: `resources/views/contact/create.blade.php`

```blade
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Contato</title>
</head>
<body>
    @if (session('status'))
        <p>{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('contact.store') }}">
        @csrf

        <div>
            <label for="name">Nome</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}">
            @error('name') <p>{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}">
            @error('email') <p>{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="message">Mensagem</label>
            <textarea id="message" name="message">{{ old('message') }}</textarea>
            @error('message') <p>{{ $message }}</p> @enderror
        </div>

        @include('slide-captcha::captcha')

        @error('slide_captcha_token')
            <p>{{ $message }}</p>
        @enderror

        @error('slide_captcha_verified')
            <p>Confirme o CAPTCHA antes de enviar.</p>
        @enderror

        <button type="submit">Enviar</button>
    </form>
</body>
</html>
```

### O que acontece neste fluxo

1. O usuário abre `/contato`.
2. A view renderiza o CAPTCHA.
3. O JavaScript chama `GET /slide-captcha/new`.
4. O pacote gera o desafio e salva as imagens temporárias no S3.
5. O navegador recebe URLs internas assinadas para ver as imagens.
6. O usuário arrasta a peça e, quando a rotação estiver ativa, gira a peça até encaixar.
7. O JavaScript chama `POST /slide-captcha/verify`.
8. Se estiver correto, o pacote retorna um token.
9. O formulário envia `slide_captcha_token`.
10. O controller valida o token com `SlideCaptchaVerified`.

## Tratamento de erros

### Erro: CAPTCHA não carrega

Verifique se as rotas existem:

```bash
php artisan route:list
```

Procure por:

```text
slide-captcha.new
slide-captcha.verify
slide-captcha.asset
slide-captcha.generated
```

Se não aparecerem, limpe caches:

```bash
php artisan optimize:clear
```

### Erro: CSRF token mismatch

O JavaScript envia o CSRF usando a meta tag:

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

Garanta que essa tag existe no `<head>` da página.

### Erro: imagem do CAPTCHA não abre

O pacote precisa ler as imagens geradas no disco configurado.

Use um disco S3:

```env
SLIDE_CAPTCHA_STORAGE_DISK=s3
```

E confirme se o S3 está configurado corretamente no Laravel e se a aplicação tem permissão de leitura no caminho configurado em `SLIDE_CAPTCHA_GENERATED_PATH`.

### Erro: nenhuma imagem base encontrada

Mensagem comum:

```text
Nenhuma imagem base foi encontrada
```

Causas prováveis:

- `SLIDE_CAPTCHA_BACKGROUNDS_PATH` aponta para uma pasta inexistente.
- A pasta existe, mas não tem imagens.
- As imagens não são `.jpg`, `.jpeg`, `.png` ou `.webp`.

Solução:

```env
SLIDE_CAPTCHA_BACKGROUNDS_PATH=storage/app/captcha-backgrounds
```

Depois coloque imagens nessa pasta.

### Erro: validação do CAPTCHA inválida ou expirada

Mensagem:

```text
A validação do CAPTCHA é inválida ou expirou.
```

Causas prováveis:

- O usuário demorou demais para enviar.
- O token já foi usado.
- O cache foi limpo.
- O cache configurado na geração é diferente do cache usado na validação.

Solução:

- Use Redis em produção.
- Aumente `SLIDE_CAPTCHA_TTL` se necessário.
- Não reutilize o mesmo token em mais de um submit.

### Respostas do endpoint de verificação

O endpoint `POST /slide-captcha/verify` pode retornar:

```json
{
  "success": false,
  "reason": "invalid_position",
  "message": "A posição enviada não confere com o desafio."
}
```

Motivos comuns:

- `validation_failed`: dados enviados inválidos.
- `not_found`: desafio expirado ou inexistente.
- `used`: desafio já usado.
- `expired`: desafio expirado.
- `invalid_position`: usuário errou a posição.
- `invalid_rotation`: usuário errou a rotação da peça.
- `movement_too_short`: poucos pontos de movimento.
- `movement_too_fast`: movimento rápido demais.
- `movement_too_slow`: movimento lento demais.
- `movement_too_linear`: movimento muito linear.
- `ddos_protection`: identidade bloqueada temporariamente pela proteção adaptativa.

## Boas práticas

- Use `SLIDE_CAPTCHA_STORAGE_DISK=s3` em produção.
- Mantenha as imagens geradas privadas.
- Use URLs internas assinadas com TTL curto.
- Use Redis para cache.
- Não valide apenas `slide_captcha_verified`; valide sempre `slide_captcha_token` com `SlideCaptchaVerified`.
- Coloque a validação no controller ou em um Form Request.
- Mantenha as imagens base sem texto, logos ou rostos identificáveis.
- Use imagens com detalhes distribuídos para facilitar o encaixe da peça.
- Não use imagens muito lisas ou muito escuras.
- Em produção, monitore erros de S3 e cache.
- Evite desativar `SLIDE_CAPTCHA_VALIDATE_MOVEMENT` em produção.

Responsabilidades recomendadas:

- `routes/web.php`: define as rotas do formulário da sua aplicação.
- `Controller`: valida o formulário e aplica `SlideCaptchaVerified`.
- `Blade`: renderiza o formulário e inclui `@include('slide-captcha::captcha')`.
- `.env`: configura cache, S3 e comportamento do CAPTCHA.
- Diretório de backgrounds: guarda as imagens base customizadas, se você não quiser usar as imagens padrão do pacote.

## Problemas comuns

### O botão enviar não valida mesmo após acertar o CAPTCHA

Causa provável: o campo `slide_captcha_token` não chegou no request.

Solução: confira se o CAPTCHA está dentro da tag `<form>`.

### O JavaScript não carrega

Causa provável: rota de asset não registrada ou cache antigo de rotas.

Solução:

```bash
php artisan optimize:clear
php artisan route:list
```

### A imagem aparece quebrada

Causa provável: a URL interna assinada expirou, o S3 está sem permissão de leitura ou o disco está mal configurado.

Solução: confira as credenciais AWS e aumente temporariamente:

```env
SLIDE_CAPTCHA_TEMPORARY_URL_TTL=600
```

### O CAPTCHA sempre retorna erro de movimento

Causa provável: ambiente de teste automatizado ou navegador bloqueando eventos.

Solução para desenvolvimento:

```env
SLIDE_CAPTCHA_VALIDATE_MOVEMENT=false
```

Não é recomendado deixar isso desativado em produção.

### O pacote usa as imagens padrão em vez das minhas

Causa provável: `SLIDE_CAPTCHA_BACKGROUNDS_PATH` não foi definido ou está errado.

Solução:

```env
SLIDE_CAPTCHA_BACKGROUNDS_PATH=storage/app/captcha-backgrounds
```

Depois rode:

```bash
php artisan config:clear
```

### A configuração do `.env` não muda o comportamento

Causa provável: configuração cacheada.

Solução:

```bash
php artisan config:clear
php artisan cache:clear
```

## Exemplo final completo

Este exemplo funciona em um projeto Laravel limpo, desde que o pacote esteja instalado e o disco S3 esteja configurado.

### `.env`

```env
APP_URL=http://localhost

CACHE_STORE=redis

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

SLIDE_CAPTCHA_ENABLED=true
SLIDE_CAPTCHA_CACHE_STORE=redis
SLIDE_CAPTCHA_STORAGE_DISK=s3
SLIDE_CAPTCHA_GENERATED_PATH=slide-captcha/generated
SLIDE_CAPTCHA_TEMPORARY_URL_TTL=300
SLIDE_CAPTCHA_TTL=120
SLIDE_CAPTCHA_TOLERANCE=8
SLIDE_CAPTCHA_ROTATION_ENABLED=true
SLIDE_CAPTCHA_ROTATION_STEP_DEGREES=15
SLIDE_CAPTCHA_ROTATION_MAX_DEGREES=90
SLIDE_CAPTCHA_ROTATION_TOLERANCE_DEGREES=8
SLIDE_CAPTCHA_VALIDATE_MOVEMENT=true
```

Em projetos Laravel mais antigos, a variável do cache padrão pode se chamar `CACHE_DRIVER` em vez de `CACHE_STORE`. Use o nome adotado pela sua aplicação.

### `routes/web.php`

```php
<?php

use App\Http\Controllers\RegisterInterestController;
use Illuminate\Support\Facades\Route;

Route::get('/interesse', [RegisterInterestController::class, 'create'])
    ->name('interest.create');

Route::post('/interesse', [RegisterInterestController::class, 'store'])
    ->name('interest.store');
```

### `app/Http/Controllers/RegisterInterestController.php`

```php
<?php

namespace App\Http\Controllers;

use CodeDart\SlideCaptcha\Rules\SlideCaptchaVerified;
use Illuminate\Http\Request;

class RegisterInterestController extends Controller
{
    public function create()
    {
        return view('interest.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'slide_captcha_token' => ['required', new SlideCaptchaVerified],
            'slide_captcha_verified' => ['accepted'],
        ]);

        // Exemplo simples: aqui você salvaria $data no banco.

        return redirect()
            ->route('interest.create')
            ->with('status', 'Cadastro recebido com sucesso.');
    }
}
```

### `resources/views/interest/create.blade.php`

```blade
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tenho interesse</title>
</head>
<body>
    <h1>Tenho interesse</h1>

    @if (session('status'))
        <p>{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('interest.store') }}">
        @csrf

        <div>
            <label for="name">Nome</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}">
            @error('name') <p>{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}">
            @error('email') <p>{{ $message }}</p> @enderror
        </div>

        @include('slide-captcha::captcha')

        @error('slide_captcha_token')
            <p>{{ $message }}</p>
        @enderror

        @error('slide_captcha_verified')
            <p>Resolva o CAPTCHA antes de enviar.</p>
        @enderror

        <button type="submit">Enviar</button>
    </form>
</body>
</html>
```

### Teste rápido

Rode a aplicação:

```bash
php artisan serve
```

Acesse:

```text
http://localhost:8000/interesse
```

Resolva o CAPTCHA e envie o formulário.

Se algo falhar, rode:

```bash
php artisan optimize:clear
php artisan route:list
```

E confira as configurações de cache e S3 no `.env`.
