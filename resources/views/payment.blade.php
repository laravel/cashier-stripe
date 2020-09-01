<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ __('Payment Confirmation') }} - {{ config('app.name', 'Laravel') }}</title>

    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.10/dist/vue.min.js"></script>
    <script src="https://js.stripe.com/v3"></script>
</head>
<body class="font-sans text-gray-600 bg-gray-200 leading-normal p-4 h-full">
    <div id="app" class="h-full md:flex md:justify-center md:items-center">
        <div class="w-full max-w-lg">
            <!-- Status Messages -->
            <p class="flex items-center mb-4 bg-red-100 border border-red-200 px-5 py-2 rounded-lg text-red-500" v-if="errorMessage">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="flex-shrink-0 w-6 h-6">
                    <path class="fill-current text-red-300" d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20z"/>
                    <path class="fill-current text-red-500" d="M12 18a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm1-5.9c-.13 1.2-1.88 1.2-2 0l-.5-5a1 1 0 0 1 1-1.1h1a1 1 0 0 1 1 1.1l-.5 5z"/>
                </svg>

                <span class="ml-3">@{{ errorMessage }}</span>
            </p>

            <p class="flex items-center mb-4 bg-green-100 border border-green-200 px-5 py-4 rounded-lg text-green-700" v-if="paymentProcessed && successMessage">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="flex-shrink-0 w-6 h-6">
                    <circle cx="12" cy="12" r="10" class="fill-current text-green-300"/>
                    <path class="fill-current text-green-500" d="M10 14.59l6.3-6.3a1 1 0 0 1 1.4 1.42l-7 7a1 1 0 0 1-1.4 0l-3-3a1 1 0 0 1 1.4-1.42l2.3 2.3z"/>
                </svg>

                <span class="ml-3">@{{ successMessage }}</span>
            </p>

            <div class="bg-white rounded-lg shadow-xl p-4 sm:py-6 sm:px-10 mb-5">
                @if ($payment->isSucceeded())
                    <h1 class="text-xl mt-2 mb-4 text-gray-700">
                        {{ __('Payment Successful') }}
                    </h1>

                    <p class="mb-6">
                        {{ __('This payment was already successfully confirmed.') }}
                    </p>
                @elseif ($payment->isCancelled())
                    <h1 class="text-xl mt-2 mb-4 text-gray-700">
                        {{ __('Payment Cancelled') }}
                    </h1>

                    <p class="mb-6">{{ __('This payment was cancelled.') }}</p>
                @else
                    <div id="payment-elements" v-if="! paymentProcessed">
                        <!-- Payment Method Form -->
                        <div v-show="requiresPaymentMethod">
                            <!-- Instructions -->
                            <h1 class="text-xl mt-2 mb-4 text-gray-700">
                                {{ __('Confirm your :amount payment', ['amount' => $payment->amount()]) }}
                            </h1>

                            <p class="mb-6">
                                {{ __('Extra confirmation is needed to process your payment. Please confirm your payment by filling out your payment details below.') }}
                            </p>

                            <!-- Name -->
                            <label for="cardholder-name" class="inline-block text-sm text-gray-700 font-semibold mb-2">
                                {{ __('Full name') }}
                            </label>

                            <input
                                id="cardholder-name"
                                type="text" placeholder="{{ __('Jane Doe') }}"
                                required
                                class="inline-block bg-gray-200 border border-gray-400 rounded-lg w-full px-4 py-3 mb-3 focus:outline-none"
                                v-model="name"
                            />

                            <!-- Card -->
                            <label for="card-element" class="inline-block text-sm text-gray-700 font-semibold mb-2">
                                {{ __('Card') }}
                            </label>

                            <div id="card-element" class="bg-gray-200 border border-gray-400 rounded-lg p-4 mb-6"></div>

                            <!-- Pay Button -->
                            <button
                                id="card-button"
                                class="inline-block w-full px-4 py-3 mb-4 text-white rounded-lg hover:bg-blue-500"
                                :class="{ 'bg-blue-400': paymentProcessing, 'bg-blue-600': ! paymentProcessing }"
                                @click="addPaymentMethod"
                                :disabled="paymentProcessing"
                            >
                                {{ __('Pay :amount', ['amount' => $payment->amount()]) }}
                            </button>
                        </div>

                        <!-- Confirm Payment Method Button -->
                        <div v-show="requiresAction">
                            <button
                                id="card-button"
                                class="inline-block w-full px-4 py-3 mb-4 text-white rounded-lg hover:bg-blue-500"
                                :class="{ 'bg-blue-400': paymentProcessing, 'bg-blue-600': ! paymentProcessing }"
                                @click="confirmPaymentMethod"
                                :disabled="paymentProcessing"
                            >
                                {{ __('Confirm your :amount payment', ['amount' => $payment->amount()]) }}
                            </button>
                        </div>
                    </div>
                @endif

                <button @click="goBack" ref="goBackButton" data-redirect="{{ $redirect ?? url('/') }}"
                   class="inline-block w-full px-4 py-3 bg-gray-200 hover:bg-gray-300 text-center text-gray-700 rounded-lg">
                    {{ __('Go back') }}
                </button>
            </div>

            <p class="text-center text-gray-500 text-sm">
                © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </div>

    <script>
        window.stripe = Stripe('{{ $stripeKey }}');

        var app = new Vue({
            el: '#app',

            data: {
                name: '',
                cardElement: null,
                paymentProcessing: false,
                paymentProcessed: false,
                requiresPaymentMethod: @json($payment->requiresPaymentMethod()),
                requiresAction: @json($payment->requiresAction()),
                successMessage: '',
                errorMessage: ''
            },

            @if (! $payment->isSucceeded() && ! $payment->isCancelled() && ! $payment->requiresAction())
                mounted: function () {
                    this.configureStripe();
                },
            @endif

            methods: {
                addPaymentMethod: function () {
                    var self = this;

                    this.paymentProcessing = true;
                    this.paymentProcessed = false;
                    this.successMessage = '';
                    this.errorMessage = '';

                    stripe.confirmCardPayment(
                        '{{ $payment->clientSecret() }}', {
                            payment_method: {
                                card: this.cardElement,
                                billing_details: { name: this.name }
                            }
                        }
                    ).then(function (result) {
                        self.paymentProcessing = false;

                        if (result.error) {
                            if (result.error.code === '{{ Stripe\ErrorObject::CODE_PARAMETER_INVALID_EMPTY }}' &&
                                result.error.param === 'payment_method_data[billing_details][name]') {
                                self.errorMessage = '{{ __('Please provide your name.') }}';
                            } else {
                                self.errorMessage = result.error.message;
                            }
                        } else {
                            self.paymentProcessed = true;

                            self.successMessage = '{{ __('The payment was successful.') }}';
                        }
                    });
                },

                confirmPaymentMethod: function () {
                    var self = this;

                    this.paymentProcessing = true;
                    this.paymentProcessed = false;
                    this.successMessage = '';
                    this.errorMessage = '';

                    stripe.confirmCardPayment(
                        '{{ $payment->clientSecret() }}', {
                            payment_method: '{{ $payment->payment_method }}'
                        }
                    ).then(function (result) {
                        self.paymentProcessing = false;

                        if (result.error) {
                            self.errorMessage = result.error.message;

                            if (result.error.code === '{{ Stripe\ErrorObject::CODE_PAYMENT_INTENT_AUTHENTICATION_FAILURE }}') {
                                self.requestPaymentMethod();
                            }
                        } else {
                            self.paymentProcessed = true;

                            self.successMessage = '{{ __('The payment was successful.') }}';
                        }
                    });
                },

                requestPaymentMethod: function () {
                    this.configureStripe();

                    this.requiresPaymentMethod = true;
                    this.requiresAction = false;
                },

                configureStripe: function () {
                    const elements = stripe.elements();

                    this.cardElement = elements.create('card');
                    this.cardElement.mount('#card-element');
                },

                goBack: function () {
                    var self = this;
                    var button = this.$refs.goBackButton;
                    var redirect = new URL(button.dataset.redirect);

                    if (self.successMessage || self.errorMessage) {
                        redirect.searchParams.append('message', self.successMessage ? self.successMessage : self.errorMessage);
                        redirect.searchParams.append('success', !! self.successMessage);
                    }

                    window.location.href = redirect;
                },
            },
        })
    </script>
</body>
</html>
