<?php

namespace Laravel\Cashier\Http\Middleware;

use Closure;
use Stripe\Error\SignatureVerification;
use Stripe\WebhookSignature;

final class VerifyWebhookSignature
{
    public function handle($request, Closure $next)
    {
        try {
            WebhookSignature::verifyHeader(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook.secret'),
                config('services.stripe.webhook.tolerance')
            );
        } catch (SignatureVerification $exception) {
            return abort(403);
        }

        return $next($request);
    }
}
