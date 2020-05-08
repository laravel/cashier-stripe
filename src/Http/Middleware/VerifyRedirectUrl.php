<?php

namespace Laravel\Cashier\Http\Middleware;

use Closure;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyRedirectUrl
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function handle($request, Closure $next)
    {
        $redirect = $request->get('redirect');

        if ($redirect && parse_url($redirect)['host'] !== $request->getHost()) {
            throw new AccessDeniedHttpException('Redirect host mismatch.');
        }

        return $next($request);
    }
}
