@component('mail::message')
# {{ __('Confirm your :amount payment', ['amount' => $payment->amount()]) }}

{{ __('Extra confirmation is needed to process your payment. Please continue to the payment page by clicking on the button below.') }}

@component('mail::button', ['url' => route('cashier.payment', ['id' => $payment->id()])])
{{ __('Confirm Payment') }}
@endcomponent

{{ __('Thanks') }},<br>
{{ config('app.name') }}
@endcomponent
