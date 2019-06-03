<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ __('Payment Confirmation') }} - {{ config('app.name', 'Laravel') }}</title>

    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">

    <script src="https://js.stripe.com/v3"></script>
</head>
<body class="font-sans text-gray-700 bg-gray-100 leading-normal p-4 h-full">
    <div class="h-full md:flex md:justify-center md:items-center">
        <div class="w-full max-w-lg">
            <p id="message" class="hidden mb-4 bg-red-400 px-6 py-4 rounded text-white"></p>

            <div class="bg-white rounded-lg shadow-xl p-4 pt-6 mb-5">
                <h1 class="text-2xl mb-4 font-bold text-center">
                    {{ __('Payment Confirmation') }}
                </h1>

                @if ($payment->isSucceeded())
                    <p class="mb-4">{{ __('This payment was already successfully confirmed.') }}</p>

                    @include('cashier::components.button', ['label' => __('Continue')])
                @elseif ($payment->isCancelled())
                    <p class="mb-4">{{ __('This payment was cancelled.') }}</p>

                    @include('cashier::components.button', ['label' => __('Continue')])
                @else
                    <div id="payment-elements">
                        <p class="mb-4">
                            {{ __('Extra confirmation is needed to process your payment. Please confirm your payment by filling out your payment details below.') }}
                        </p>

                        <input id="cardholder-name" type="text" placeholder="{{ __('Name') }}" required
                               class="inline-block w-full px-6 py-4 mb-4 bg-gray-100 rounded">

                        <div id="card-element" class="bg-gray-100 rounded px-6 py-4 mb-4"></div>

                        <button id="card-button" class="inline-block w-full px-4 py-3 mb-4 bg-blue-600 text-white font-bold rounded hover:bg-blue-500">
                            {{ __('Confirm Payment') }}
                        </button>
                    </div>

                    @include('cashier::components.button', ['label' => __('Back')])
                @endif

                <script>
                    const paymentElements = document.getElementById('payment-elements');
                    const cardholderName = document.getElementById('cardholder-name');
                    const cardButton = document.getElementById('card-button');
                    const message = document.getElementById('message');

                    const stripe = Stripe('{{ $stripeKey }}');
                    const elements = stripe.elements();
                    const cardElement = elements.create('card');
                    cardElement.mount('#card-element');

                    cardButton.addEventListener('click', function() {
                        stripe.handleCardPayment(
                            '{{ $payment->clientSecret() }}', cardElement, {
                                payment_method_data: {
                                    billing_details: { name: cardholderName.value }
                                }
                            }
                        ).then(function (result) {
                            console.log(result);

                            if (result.error) {
                                if (result.error.code === 'parameter_invalid_empty' &&
                                    result.error.param === 'payment_method_data[billing_details][name]') {
                                    message.innerText = '⚠️ {{ __('Please provide your name.') }}';
                                } else {
                                    message.innerText = '⚠️ '+result.error.message;
                                }

                                message.classList.add('bg-red-400');
                            } else {
                                paymentElements.style.display = 'none';

                                message.innerText = '✅ {{ __('The payment was successful.') }}';
                                message.classList.add('bg-green-400');
                            }

                            message.style.display = 'block';
                        });
                    });
                </script>
            </div>

            <p class="text-center text-gray-400 text-sm">
                © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </div>
</body>
</html>
