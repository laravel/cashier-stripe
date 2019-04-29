<!-- Create a button that your customers click to complete their purchase. Customize the styling to suit your branding. -->
<button
    style="background-color:#6772E5;color:#FFF;padding:8px 12px;border:0;border-radius:4px;font-size:1em"
    id="checkout-{{ $sessionId }}"
    role="link"
>
    {{ $label }}
</button>

<div id="error-message"></div>

<script>
    const checkoutButton = document.getElementById('checkout-{{ $sessionId }}');

    checkoutButton.addEventListener('click', function () {
        // When the customer clicks on the button, redirect them to Checkout.
        Stripe('{{ $stripeKey }}').redirectToCheckout({
            sessionId: '{{ $sessionId }}'
        }).then(function (result) {
            // If `redirectToCheckout` fails due to a browser or network
            // error, display the localized error message to your customer
            // using `result.error.message`.
        });
    });
</script>
