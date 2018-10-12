<?php

namespace Laravel\Cashier\Tests;

use Mockery as m;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as Config;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;

final class VerifyWebhookSignatureTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function test_signature_checks_out()
    {
        $secret = 'secret';

        $app = m::mock(Application::class);

        $config = m::mock(Config::class);
        $config->shouldReceive('get')->with('services.stripe.webhook.secret')->andReturn($secret);
        $config->shouldReceive('get')->with('services.stripe.webhook.tolerance')->andReturn(300);

        $request = new Request([], [], [], [], [], [], 'Signed Body');
        $request->headers->set('Stripe-Signature', 't='.time().',v1='.$this->sign($request->getContent(), $secret));

        $called = false;

        (new VerifyWebhookSignature($app, $config))->handle($request, function ($request) use (&$called) {
            $called = true;
        });

        static::assertTrue($called);
    }

    private function sign($payload, $secret)
    {
        return hash_hmac('sha256', time().'.'.$payload, $secret);
    }

    public function test_bad_signature_aborts()
    {
        $secret = 'secret';

        $app = m::mock(Application::class);
        $app->shouldReceive('abort')->andThrow(HttpException::class, 403);

        $config = m::mock(Config::class);
        $config->shouldReceive('get')->with('services.stripe.webhook.secret')->andReturn($secret);
        $config->shouldReceive('get')->with('services.stripe.webhook.tolerance')->andReturn(300);

        $request = new Request([], [], [], [], [], [], 'Signed Body');
        $request->headers->set('Stripe-Signature', 't='.time().',v1=fail');

        static::expectException(HttpException::class);

        (new VerifyWebhookSignature($app, $config))->handle($request, function ($request) {
        });
    }

    public function test_no_or_mismatching_secret_aborts()
    {
        $secret = 'secret';

        $app = m::mock(Application::class);
        $app->shouldReceive('abort')->andThrow(HttpException::class, 403);

        $config = m::mock(Config::class);
        $config->shouldReceive('get')->with('services.stripe.webhook.secret')->andReturn($secret);
        $config->shouldReceive('get')->with('services.stripe.webhook.tolerance')->andReturn(300);

        $request = new Request([], [], [], [], [], [], 'Signed Body');
        $request->headers->set('Stripe-Signature', 't='.time().',v1='.$this->sign($request->getContent(), ''));

        static::expectException(HttpException::class);

        (new VerifyWebhookSignature($app, $config))->handle($request, function ($request) {
        });
    }
}
