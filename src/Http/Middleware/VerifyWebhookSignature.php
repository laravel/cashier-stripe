<?php

namespace Laravel\Cashier\Http\Middleware;

use Closure;
use Stripe\WebhookSignature;
use Stripe\Error\SignatureVerification;

final class VerifyWebhookSignature
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
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
            abort(403);
        }

        return $next($request);
    }
}
