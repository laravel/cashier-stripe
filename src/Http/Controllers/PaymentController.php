<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
use Illuminate\Routing\Controller;
use Laravel\Cashier\PaymentIntent;
use Stripe\PaymentIntent as StripePaymentIntent;

class PaymentController extends Controller
{
    /**
     * Handle a Stripe PaymentIntent.
     *
     * @param  string  $id
     * @return \Illuminate\View\View
     */
    public function show(Request $request, $id)
    {
        $paymentIntent = new PaymentIntent(
            StripePaymentIntent::retrieve($id, Cashier::stripeOptions())
        );

        return view('cashier::payment', [
            'stripeKey' => Cashier::stripeKey(),
            'paymentIntent' => $paymentIntent,
            'redirect' => $request->get('redirect'),
        ]);
    }
}
