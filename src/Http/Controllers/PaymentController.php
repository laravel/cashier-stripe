<?php

namespace Laravel\Cashier\Http\Controllers;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\Payment;
use Illuminate\Routing\Controller;
use Stripe\PaymentIntent as StripePaymentIntent;

class PaymentController extends Controller
{
    /**
     * Handle a Stripe PaymentIntent.
     *
     * @param  string  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $payment = new Payment(
            StripePaymentIntent::retrieve($id, Cashier::stripeOptions())
        );

        return view('cashier::payment', [
            'stripeKey' => config('cashier.key'),
            'payment' => $payment,
            'redirect' => request('redirect'),
        ]);
    }
}
