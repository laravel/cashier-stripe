<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ __('Payment Confirmation') }} - {{ config('app.name', 'Laravel') }}</title>

    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">

    <script src="https://js.stripe.com/v3"></script>
</head>
<body class="font-sans text-gray-600 bg-gray-200 leading-normal p-4 h-full">
    <div class="h-full md:flex md:justify-center md:items-center">
        <div class="w-full max-w-lg">
            <p id="message" class="hidden mb-4 bg-red-100 border border-red-400 px-6 py-4 rounded-lg text-red-600"></p>

            <div class="bg-white rounded-lg shadow-xl p-4 sm:py-6 sm:px-10 mb-5">
                @if ($payment->isSucceeded())
                    <h1 class="text-xl mt-2 mb-4 text-gray-700">
                        {{ __('Payment Successful') }}
                    </h1>

                    <p class="mb-6">{{ __('This payment was already successfully confirmed.') }}</p>
                @elseif ($payment->isCancelled())
                    <h1 class="text-xl mt-2 mb-4 text-gray-700">
                        {{ __('Payment Cancelled') }}
                    </h1>

                    <p class="mb-6">{{ __('This payment was cancelled.') }}</p>
                @else
                    <div id="payment-elements">
                        <h1 class="text-xl mt-2 mb-4 text-gray-700">
                            {{ __('Confirm your :amount payment', ['amount' => $payment->amount()]) }}
                        </h1>

                        <p class="mb-6">
                            {{ __('Extra confirmation is needed to process your payment. Please confirm your payment by filling out your payment details below.') }}
                        </p>

                        <label for="cardholder-name" class="inline-block text-sm text-gray-700 font-semibold mb-2">{{ __('Full name') }}</label>
                        <input id="cardholder-name" type="text" placeholder="Jane Doe" required
                               class="inline-block bg-gray-200 border border-gray-400 rounded-lg w-full px-4 py-3 mb-3">

                        <label for="cardholder-name" class="inline-block text-sm text-gray-700 font-semibold mb-2">{{ __('Card') }}</label>
                        <div id="card-element" class="bg-gray-200 border border-gray-400 rounded-lg p-4 mb-6"></div>

                        <button id="card-button" class="inline-block w-full px-4 py-3 mb-4 bg-blue-600 text-white rounded-lg hover:bg-blue-500">
                            {{ __('Pay :amount', ['amount' => $payment->amount()]) }}
                        </button>
                    </div>
                @endif

                <a href="{{ $redirect ?? url('/') }}"
                   class="inline-block w-full px-4 py-3 bg-gray-200 hover:bg-gray-300 text-center text-gray-700 rounded-lg">
                    {{ __('Go back') }}
                </a>

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
                            if (result.error) {
                                if (result.error.code === 'parameter_invalid_empty' &&
                                    result.error.param === 'payment_method_data[billing_details][name]') {
                                    message.innerText = '⚠️ {{ __('Please provide your name.') }}';
                                } else {
                                    message.innerText = '⚠️ '+result.error.message;
                                }

                                message.classList.add('text-red-600', 'border-red-400', 'bg-red-100');
                                message.classList.remove('text-green-600', 'border-green-400', 'bg-green-100');
                            } else {
                                paymentElements.classList.add('hidden');

                                message.innerText = '✅ {{ __('The payment was successful.') }}';
                                message.classList.remove('text-red-600', 'border-red-400', 'bg-red-100');
                                message.classList.add('text-green-600', 'border-green-400', 'bg-green-100');
                            }

                            message.classList.remove('hidden');
                        });
                    });
                </script>
            </div>

            <p class="text-center text-gray-500 text-sm">
                © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </div>
</body>
</html>
