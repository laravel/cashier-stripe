<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ __('Payment Confirmation') }} - {{ config('app.name', 'Laravel') }}</title>

    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.10/dist/vue.min.js"></script>
    <script src="https://js.stripe.com/v3"></script>
</head>
<body class="font-sans text-gray-600 bg-gray-100 leading-normal p-4 h-full">
    <div id="app" class="h-full md:flex md:justify-center md:items-center">
        <div class="w-full max-w-lg">
            <h1 class="text-4xl font-bold text-center p-4 sm:p-6 mt-4">
                Your {{ $payment->amount() }} payment
            </h1>

            <!-- Status Messages -->
            <p class="flex items-center bg-red-100 border border-red-200 px-5 py-2 rounded-lg text-red-500" v-if="errorMessage">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="flex-shrink-0 w-6 h-6">
                    <path class="fill-current text-red-300" d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20z"/>
                    <path class="fill-current text-red-500" d="M12 18a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm1-5.9c-.13 1.2-1.88 1.2-2 0l-.5-5a1 1 0 0 1 1-1.1h1a1 1 0 0 1 1 1.1l-.5 5z"/>
                </svg>

                <span class="ml-3">@{{ errorMessage }}</span>
            </p>

            <div class="bg-white rounded-lg shadow-xl p-4 sm:p-6 mt-4">
                <div v-if="paymentIntent.status === 'succeeded'">
                    <h2 class="text-xl mb-4 text-gray-600">
                        Payment Successful
                    </h2>

                    <p class="mb-6">
                        This payment was successfully confirmed.
                    </p>
                </div>

                <div v-else-if="paymentIntent.status === 'processing'">
                    <h2 class="text-xl mb-4 text-gray-600">
                        Payment Processing
                    </h2>

                    <p class="mb-6">
                        This payment is currently processing. Refresh this page from time to time to see its status.
                    </p>
                </div>

                <div v-else-if="paymentIntent.status === 'canceled'">
                    <h2 class="text-xl mb-4 text-gray-600">
                        Payment Cancelled
                    </h2>

                    <p class="mb-6">
                        This payment was cancelled.
                    </p>
                </div>

                <div v-else>
                    <!-- Payment Method Form -->
                    <div v-if="paymentIntent.status === 'requires_payment_method'">
                        <!-- Instructions -->
                        <h2 class="text-xl mb-4 text-gray-600">
                            Confirm Your Payment
                        </h2>

                        <p class="mb-6">
                            A valid payment method is needed to process your payment. Please confirm your payment by filling out your payment details below.
                        </p>

                        <!-- Name -->
                        <label for="name" class="inline-block text-sm text-gray-700 font-semibold mb-2">
                            Full name
                        </label>

                        <input
                            id="name"
                            type="text" placeholder="Jane Doe"
                            required
                            class="inline-block bg-gray-100 border border-gray-300 rounded-lg w-full px-4 py-3 mb-3 focus:outline-none"
                            v-model="name"
                        />

                        <!-- E-mail Address -->
                        <label for="email" class="inline-block text-sm text-gray-700 font-semibold mb-2">
                            E-mail address
                        </label>

                        <input
                            id="email"
                            type="text" placeholder="jane@example.com"
                            required
                            class="inline-block bg-gray-100 border border-gray-300 rounded-lg w-full px-4 py-3 mb-3 focus:outline-none"
                            v-model="email"
                        />

                        <!-- Stripe Payment Element -->
                        <label for="payment-element" class="inline-block text-sm text-gray-700 font-semibold mb-2">
                            Payment
                        </label>

                        <div id="payment-element" class="bg-gray-100 border border-gray-300 rounded-lg p-4 mb-6"></div>

                        <p v-if="paymentIntent.payment_method_types.includes('sepa_debit')" class="text-xs text-gray-400 mb-6">
                            By providing your payment information and confirming this payment, you authorise (A) and Stripe, our payment service provider, to send instructions to your bank to debit your account and (B) your bank to debit your account in accordance with those instructions. As part of your rights, you are entitled to a refund from your bank under the terms and conditions of your agreement with your bank. A refund must be claimed within 8 weeks starting from the date on which your account was debited. Your rights are explained in a statement that you can obtain from your bank. You agree to receive notifications for future debits up to 2 days before they occur.
                        </p>
                    </div>

                    <!-- Confirm Payment Method Button -->
                    <button
                        class="inline-block w-full px-4 py-3 mb-4 text-white rounded-lg hover:bg-blue-500"
                        :class="{ 'bg-blue-400': isPaymentProcessing, 'bg-blue-600': ! isPaymentProcessing }"
                        @click="confirmPaymentMethod"
                        :disabled="isPaymentProcessing"
                    >
                        <span v-if="isPaymentProcessing">
                            Processing...
                        </span>
                        <span v-else>
                            Confirm your {{ $payment->amount() }} payment
                        </span>
                    </button>
                </div>

                <button @click="goBack" ref="goBackButton" data-redirect="{{ $redirect }}"
                   class="inline-block w-full px-4 py-3 bg-gray-100 hover:bg-gray-200 text-center text-gray-600 rounded-lg">
                    Go back
                </button>
            </div>

            <p class="text-center text-gray-500 text-sm mt-4 pb-4">
                © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        window.stripe = Stripe('{{ $stripeKey }}');

        new Vue({
            el: '#app',

            data() {
                return {
                    paymentIntent: {!! $payment->asStripePaymentIntent()->toJSON() !!},
                    name: '{{ optional($customer)->stripeName() }}',
                    email: '{{ optional($customer)->stripeEmail() }}',
                    paymentElement: null,
                    isPaymentProcessing: false,
                    errorMessage: ''
                }
            },

            mounted: function () {
                this.configureStripeElements();
            },

            methods: {
                configureStripeElements: function () {
                    // Stripe Elements are only needed when a payment method is required.
                    if (this.paymentIntent.status !== 'requires_payment_method') {
                        return;
                    }

                    const elements = stripe.elements();

                    if (this.paymentIntent.payment_method_types.includes('sepa_debit')) {
                        this.paymentElement = elements.create('iban', {
                            supportedCountries: ['SEPA']
                        });
                    } else {
                        this.paymentElement = elements.create('card');
                    }

                    this.paymentElement.mount('#payment-element');
                },

                confirmPaymentMethod: function () {
                    this.isPaymentProcessing = true;
                    this.errorMessage = '';

                    let data = {};

                    if (this.paymentIntent.payment_method_types.includes('sepa_debit')) {
                        if (this.paymentIntent.status === 'requires_payment_method') {
                            data = {
                                payment_method: {
                                    sepa_debit: this.paymentElement,
                                    billing_details: { name: this.name, email: this.email }
                                }
                            };
                        }

                        stripe.confirmSepaDebitPayment('{{ $payment->clientSecret() }}', data)
                            .then(result => this.confirmCallback(result));
                    } else {
                        if (this.paymentIntent.status === 'requires_payment_method') {
                            data = {
                                payment_method: {
                                    card: this.paymentElement,
                                    billing_details: { name: this.name, email: this.email }
                                }
                            };
                        }

                        stripe.confirmCardPayment('{{ $payment->clientSecret() }}', data)
                            .then(result => this.confirmCallback(result));
                    }
                },

                confirmCallback: function (result) {
                    let self = this;

                    self.isPaymentProcessing = false;

                    if (result.error) {
                        if (result.error.code === '{{ Stripe\ErrorObject::CODE_PARAMETER_INVALID_EMPTY }}') {
                            self.errorMessage = 'Please provide your name and e-mail address.';
                        } else {
                            self.errorMessage = result.error.message;
                        }

                        if (result.error.payment_intent) {
                            this.paymentIntent = result.error.payment_intent;

                            this.configureStripeElements();
                        }
                    } else {
                        this.paymentIntent = result.paymentIntent;
                    }
                },

                goBack: function () {
                    let self = this;
                    let button = this.$refs.goBackButton;
                    let redirect = new URL(button.dataset.redirect);

                    redirect.searchParams.append(
                        'success', self.paymentIntent.status === 'succeeded' ? 'true' : 'false'
                    );

                    if (self.errorMessage) {
                        redirect.searchParams.append('message', self.errorMessage);
                    }

                    window.location.href = redirect;
                },
            },
        })
    </script>
</body>
</html>
