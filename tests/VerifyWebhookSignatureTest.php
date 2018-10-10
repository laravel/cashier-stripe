<?php

namespace Laravel\Cashier\Tests;

use Illuminate\Http\Request;
use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;

final class VerifyWebhookSignatureTest extends PHPUnit_Framework_TestCase
{
    public function test_signature_checks_out()
    {
        $secret = 'secret';

        config(['services.stripe.webhook.secret' => $secret, 'services.stripe.webhook.tolerance' => 300]);

        $request = new Request([], [], [], [], [], [], 'Signed Body');
        $request->headers->set('Stripe-Signature', 't='.time().',v1='.$this->sign($request->getContent(), $secret));

        $called = false;

        (new VerifyWebhookSignature)->handle($request, function ($request) use (&$called) {
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

        config(['services.stripe.webhook.secret' => $secret, 'services.stripe.webhook.tolerance' => 300]);

        $request = new Request([], [], [], [], [], [], 'Signed Body');
        $request->headers->set('Stripe-Signature', 't='.time().',v1=fail');

        static::expectException(HttpException::class);

        (new VerifyWebhookSignature)->handle($request, function ($request) {
        });
    }

    public function test_no_secret_aborts()
    {
        $secret = 'secret';

        config(['services.stripe.webhook.secret' => '', 'services.stripe.webhook.tolerance' => 300]);

        $request = new Request([], [], [], [], [], [], 'Signed Body');
        $request->headers->set('Stripe-Signature', 't='.time().',v1='.$this->sign($request->getContent(), $secret));

        static::expectException(HttpException::class);

        (new VerifyWebhookSignature)->handle($request, function ($request) {
        });
    }
}
