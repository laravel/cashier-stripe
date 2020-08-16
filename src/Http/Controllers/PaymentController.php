<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Middleware\VerifyRedirectUrl;
use Laravel\Cashier\Payment;
use Stripe\PaymentIntent as StripePaymentIntent;

class PaymentController extends Controller
{
    /**
     * Create a new PaymentController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(VerifyRedirectUrl::class);
    }

    /**
     * Display the form to gather additional payment verification for the given payment.
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
            'requiresPaymentMethod' => json_encode($payment->requiresPaymentMethod()),
            'requiresAction' => json_encode($payment->requiresAction()),
            'payment' => $payment,
            'redirect' => request('redirect'),
        ]);
    }
}
