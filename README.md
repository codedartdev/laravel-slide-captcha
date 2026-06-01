# Laravel Slide CAPTCHA

CAPTCHA visual self-hosted para Laravel, baseado no desafio de arrastar uma peça até a posição correta da imagem.

O pacote gera um desafio, recorta uma peça da imagem, salva temporariamente os arquivos gerados em um disco privado, normalmente S3, e retorna URLs temporárias para o navegador. A posição correta fica somente no backend e é armazenada em cache por poucos segundos.

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
- Disco de storage com suporte a URLs temporárias
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

Tempo de validade das URLs temporárias das imagens, em segundos. Padrão: `300`.

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

As imagens geradas são privadas. O navegador acessa essas imagens por URL temporária.

O usuário ou role da AWS precisa ter permissão para:

- `s3:PutObject`
- `s3:GetObject`
- `s3:DeleteObject`
- `s3:ListBucket`, se exigido pela configuração do bucket

### Publicar configuração

Não é obrigatório publicar a configuração.

Se quiser customizar o arquivo `config/slide-captcha.php`, rode:

```bash
php artisan vendor:publish --tag=slide-captcha-config
```

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
5. O navegador recebe URLs temporárias para ver as imagens.
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

### Erro: disco não suporta URL temporária

O pacote precisa gerar URLs temporárias para as imagens.

Use um disco S3:

```env
SLIDE_CAPTCHA_STORAGE_DISK=s3
```

E confirme se o S3 está configurado corretamente no Laravel.

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

## Boas práticas

- Use `SLIDE_CAPTCHA_STORAGE_DISK=s3` em produção.
- Mantenha as imagens geradas privadas.
- Use URLs temporárias com TTL curto.
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

Causa provável: a URL temporária expirou, o S3 está sem permissão ou o disco está mal configurado.

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
