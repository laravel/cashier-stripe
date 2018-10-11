<?php

namespace Laravel\Cashier\Http\Middleware;

use Closure;
use Stripe\WebhookSignature;
use Stripe\Error\SignatureVerification;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as Config;

final class VerifyWebhookSignature
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $app;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    private $config;

    public function __construct(Application $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

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
                $this->config->get('services.stripe.webhook.secret'),
                $this->config->get('services.stripe.webhook.tolerance')
            );
        } catch (SignatureVerification $exception) {
            $this->app->abort(403);
        }

        return $next($request);
    }
}
