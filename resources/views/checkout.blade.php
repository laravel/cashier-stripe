<button
    id="checkout-{{ $sessionId }}"
    role="link"
    style="{{ isset($style) && ! isset($class) ? $style : 'background-color:#6772E5;color:#FFF;padding:8px 12px;border:0;border-radius:4px;font-size:1em' }}"
    @isset($class) class="{{ $class }}" @endisset
>
    {{ $label }}
</button>

<div id="error-message"></div>

<script>
    (() => {
        const checkoutButton = document.getElementById('checkout-{{ $sessionId }}');

        checkoutButton.addEventListener('click', function () {
            Stripe('{{ $stripeKey }}').redirectToCheckout({
                sessionId: '{{ $sessionId }}'
            }).then(function (result) {
                if (result.error) {
                    document.getElementById('error-message').innerText = result.error.message;
                }
            });
        });
    })()
</script>
