<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
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
    public function show(Request $request, $id)
    {
        $payment = new Payment(
            StripePaymentIntent::retrieve($id, Cashier::stripeOptions())
        );

        return view('cashier::payment', [
            'stripeKey' => Cashier::stripeKey(),
            'payment' => $payment,
            'redirect' => $request->get('redirect'),
        ]);
    }
}
