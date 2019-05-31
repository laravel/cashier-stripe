<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ __('Payment Confirmation') }} - {{ config('app.name', 'Laravel') }}</title>

    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">

    <script src="https://js.stripe.com/v3"></script>

    <style>
        #card-element {
            margin-bottom: 1.5rem;
            padding: 1rem 1.5rem;
        }
    </style>
</head>
<body class="font-sans bg-gray-100 leading-normal p-4 h-full">
    <div class="h-full flex justify-center items-center">
        <div class="w-auto">
            <div class="max-w-lg bg-white rounded-lg shadow-xl p-6 mb-4">
                <h1 class="text-3xl mb-4 font-bold text-center">
                    {{ __('Payment Confirmation') }}
                </h1>

                @if ($payment->isSucceeded())
                    <p class="mb-4">{{ __('The payment was successful.') }}</p>
                @elseif ($payment->isCancelled())
                    <p class="mb-4">{{ __('The payment was cancelled.') }}</p>
                @else
                    <div id="payment-elements">
                        <p class="mb-6">{{ __('Extra confirmation is needed to process your payment. Please confirm your payment by filling out your payment details below.') }}</p>

                        <input id="cardholder-name" type="text" placeholder="{{ __('Name') }}"
                            class="inline-block w-full px-6 py-4 mb-6 bg-gray-100 rounded">
                        <div id="card-element"></div>

                        <button id="card-button" class="inline-block w-full px-6 py-4 bg-blue-600 text-white font-bold text-lg rounded">
                            {{ __('Submit Payment') }}
                        </button>
                    </div>

                    <p id="message" class="mb-4"></p>

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
                                    message.innerText = 'Error: '+result.error.message;
                                } else {
                                    paymentElements.style.display = 'none';

                                    message.innerText = '{{ __('The payment was successful.') }}';
                                }
                            });
                        });
                    </script>
                @endif
            </div>

            <p class="text-center">
                <a href="{{ $redirect ?? url('/') }}" class="text-blue-600">
                    < {{ __('Back') }}
                </a>
            </p>
        </div>
    </div>
</body>
</html>
