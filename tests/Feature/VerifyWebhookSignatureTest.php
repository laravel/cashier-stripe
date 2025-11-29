<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Laravel\Cashier\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyWebhookSignatureTest extends TestCase
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * @var int
     */
    protected $timestamp;

    public function setUp(): void
    {
        parent::setUp();

        config(['cashier.webhook.secret' => 'secret']);
        config(['cashier.webhook.tolerance' => 300]);

        $this->request = new Request([], [], [], [], [], [], 'Signed Body');
    }

    public function test_response_is_received_when_secret_matches()
    {
        $this->withTimestamp(time());
        $this->withSignedSignature('secret');

        $response = (new VerifyWebhookSignature())
            ->handle($this->request, function ($request) {
                return new Response('OK');
            });

        $this->assertEquals('OK', $response->content());
    }

    public function test_response_is_received_when_timestamp_is_within_tolerance_zone()
    {
        $this->withTimestamp(time() - 300);
        $this->withSignedSignature('secret');

        $response = (new VerifyWebhookSignature())
            ->handle($this->request, function ($request) {
                return new Response('OK');
            });

        $this->assertEquals('OK', $response->content());
    }

    public function test_app_aborts_when_timestamp_is_too_old()
    {
        $this->withTimestamp(time() - 301);
        $this->withSignedSignature('secret');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Timestamp outside the tolerance zone');

        $response = (new VerifyWebhookSignature())
            ->handle($this->request, function ($request) {
            });
    }

    public function test_app_aborts_when_timestamp_is_invalid()
    {
        $this->withTimestamp('invalid');
        $this->withSignedSignature('secret');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Unable to extract timestamp and signatures from header');

        $response = (new VerifyWebhookSignature())
            ->handle($this->request, function ($request) {
            });
    }

    public function test_app_aborts_when_secret_does_not_match()
    {
        $this->withTimestamp(time());
        $this->withSignature('fail');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('No signatures found matching the expected signature for payload');

        (new VerifyWebhookSignature())
            ->handle($this->request, function ($request) {
            });
    }

    public function test_app_aborts_when_no_secret_was_provided()
    {
        $this->withTimestamp(time());
        $this->withSignedSignature('');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('No signatures found matching the expected signature for payload');

        (new VerifyWebhookSignature())
            ->handle($this->request, function ($request) {
            });
    }

    public function withTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
    }

    public function withSignedSignature($secret)
    {
        return $this->withSignature(
            $this->sign($this->request->getContent(), $secret)
        );
    }

    public function withSignature($signature)
    {
        $this->request->headers->set('Stripe-Signature', 't='.$this->timestamp.',v1='.$signature);

        return $this;
    }

    private function sign($payload, $secret)
    {
        return hash_hmac('sha256', $this->timestamp.'.'.$payload, $secret);
    }
}
