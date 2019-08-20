<?php

namespace Laravel\Cashier\Tests\Unit;

use Mockery as m;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as Config;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;

class VerifyWebhookSignatureTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_response_is_received_when_secret_matches()
    {
        $mock = VerifierMock::withWebhookSecret('secret')
            ->setSignedSignature('secret');

        $response = (new VerifyWebhookSignature($mock->app, $mock->config))
            ->handle($mock->request, function ($request) {
                return new Response('OK');
            });

        $this->assertEquals('OK', $response->content());
    }

    public function test_app_aborts_when_secret_does_not_match()
    {
        $mock = VerifierMock::withWebhookSecret('secret')
            ->setSignature('fail')
            ->expectAbort();

        $this->expectException(HttpException::class);

        (new VerifyWebhookSignature($mock->app, $mock->config))
            ->handle($mock->request, function ($request) {
            });
    }

    public function test_app_aborts_when_no_secret_was_provided()
    {
        $mock = VerifierMock::withWebhookSecret('secret')
            ->setSignedSignature('')
            ->expectAbort();

        $this->expectException(HttpException::class);

        (new VerifyWebhookSignature($mock->app, $mock->config))
            ->handle($mock->request, function ($request) {
            });
    }
}

class VerifierMock
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    public $app;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    public $config;

    /**
     * @var \Illuminate\Http\Request
     */
    public $request;

    public function __construct($webhookSecret)
    {
        $this->app = m::mock(Application::class);
        $this->config = m::mock(Config::class);
        $this->config->shouldReceive('get')->with('cashier.webhook.secret')->andReturn($webhookSecret);
        $this->config->shouldReceive('get')->with('cashier.webhook.tolerance')->andReturn(300);
        $this->request = new Request([], [], [], [], [], [], 'Signed Body');
    }

    public static function withWebhookSecret($webhookSecret)
    {
        return new self($webhookSecret);
    }

    public function expectAbort()
    {
        $this->app->shouldReceive('abort')->andThrow(HttpException::class, 403);

        return $this;
    }

    public function setSignedSignature($secret)
    {
        $signature = $this->sign($this->request->getContent(), $secret);

        return $this->setSignature($signature);
    }

    public function setSignature($signature)
    {
        $this->request->headers->set('Stripe-Signature', 't='.time().',v1='.$signature);

        return $this;
    }

    private function sign($payload, $secret)
    {
        return hash_hmac('sha256', time().'.'.$payload, $secret);
    }
}
