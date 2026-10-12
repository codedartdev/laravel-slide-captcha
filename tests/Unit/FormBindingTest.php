<?php

namespace CodeDart\SlideCaptcha\Tests\Unit;

use CodeDart\SlideCaptcha\Support\FormBinding;
use CodeDart\SlideCaptcha\Services\CaptchaValidator;
use CodeDart\SlideCaptcha\Services\CaptchaGenerator;
use CodeDart\SlideCaptcha\Support\MovementAnalyzer;
use CodeDart\SlideCaptcha\Tests\Support\ConfigRepository;
use CodeDart\SlideCaptcha\Tests\Support\InMemoryCache;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class FormBindingTest extends TestCase
{
    protected function setUp(): void
    {
        ConfigRepository::reset();
        ConfigRepository::set(['app.key' => 'test-only-key', 'captcha.validate_movement' => false]);
    }

    private function request(array $data): Request
    {
        return Request::create('/submit', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));
    }

    public function testBindingRejectsEveryFormMutationAndAcceptsKeyReordering(): void
    {
        $form = ['email' => 'a@example.test', 'password' => ' secret ', 'remember' => false, 'nested' => ['a' => 1, 'b' => [1, 2]]];
        $payload = ['form_fingerprint' => FormBinding::fingerprint($form)];
        $this->assertTrue(FormBinding::matches($payload, $this->request(array_reverse($form, true) + ['slide_captcha_token' => 'token', '_token' => 'csrf'])));
        foreach (['email' => 'b@example.test', 'password' => 'secret', 'remember' => 'false', 'nested' => ['a' => 1, 'b' => [2, 1]]] as $key => $value) {
            $this->assertFalse(FormBinding::matches($payload, $this->request(array_replace($form, [$key => $value]))));
        }
        $this->assertFalse(FormBinding::matches($payload, $this->request($form + ['extra' => 'x'])));
        unset($form['email']);
        $this->assertFalse(FormBinding::matches($payload, $this->request($form)));
        $this->assertFalse(FormBinding::matches([], $this->request($form)));
    }

    public function testWireValuesSurviveLaravelNormalization(): void
    {
        $form = ['password' => ' secret ', 'name' => ' Name ', 'empty' => ''];
        $request = $this->request($form);
        $request->merge(['name' => 'Name', 'empty' => null]);
        $this->assertSame($form, FormBinding::requestData($request));
        $urlencoded = Request::create('/submit', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($form));
        $this->assertSame($form, FormBinding::requestData($urlencoded));
    }

    public function testSolvedChallengeStoresOnlyFingerprintAndCannotBeSolvedAgain(): void
    {
        $cache = new InMemoryCache();
        $validator = new class(new MovementAnalyzer(), $cache) extends CaptchaValidator {
            private $testCache;
            public function __construct($analyzer, $cache) { parent::__construct($analyzer); $this->testCache = $cache; }
            protected function cache() { return $this->testCache; }
        };
        $cache->put(CaptchaGenerator::challengeKey('challenge'), ['target_x' => 12, 'target_y' => 9, 'created_at' => time()]);
        $this->assertSame('form_data_required', $validator->validate('challenge', 12, 9)['reason']);
        $request = $this->request(['form_data' => ['password' => ' secret ']]);
        $result = $validator->validate('challenge', 12, 9, null, [], $request);
        $this->assertTrue($result['success']);
        $payload = $cache->get(CaptchaValidator::verificationKey($result['verification_token']));
        $this->assertSame(FormBinding::fingerprint(['password' => ' secret ']), $payload['form_fingerprint']);
        $this->assertStringNotContainsString('secret', json_encode($cache->items));
        $this->assertSame('not_found', $validator->validate('challenge', 12, 9, null, [], $request)['reason']);
        $this->assertSame('invalid_form_data', $validator->validate('challenge', 12, 9, null, [], $this->request(['form_data' => 'bad']))['reason']);
    }

    public function testRuleConsumesTokensOnSuccessAndMismatchAndHonorsTheLock(): void
    {
        $cache = new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore());
        $rule = new class($cache) extends \CodeDart\SlideCaptcha\Rules\SlideCaptchaVerified {
            private $testCache;
            public function __construct($cache) { $this->testCache = $cache; }
            protected function cache() { return $this->testCache; }
        };
        $request = $this->request(['email' => 'a@example.test']);
        ConfigRepository::set(['captcha.enabled' => true, 'test.request' => $request]);
        $key = CaptchaValidator::verificationKey('token');
        $payload = ['form_fingerprint' => FormBinding::fingerprint(['email' => 'a@example.test'])];
        $cache->put($key, $payload, 300);
        $lock = $cache->lock('slide-captcha-verification:' . hash('sha256', $key), 5);
        $this->assertTrue($lock->get());
        $this->assertFalse($rule->passes('slide_captcha_token', 'token'));
        $lock->release();
        $this->assertTrue($rule->passes('slide_captcha_token', 'token'));
        $this->assertFalse($rule->passes('slide_captcha_token', 'token'));
        $cache->put($key, $payload, 300);
        ConfigRepository::set(['test.request' => $this->request(['email' => 'changed@example.test'])]);
        $this->assertFalse($rule->passes('slide_captcha_token', 'token'));
        ConfigRepository::set(['test.request' => $request]);
        $this->assertFalse($rule->passes('slide_captcha_token', 'token'));
        $cache->put($key, ['created_at' => time()], 300);
        $this->assertFalse($rule->passes('slide_captcha_token', 'token'));
    }
}
