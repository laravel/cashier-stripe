# Contribution Guide

The Laravel contributing guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Running Cashier's Tests

You will need to set the Stripe **testing** secret environment variable in a custom `phpunit.xml` file in order to run the Cashier tests.

Copy the default file using `cp phpunit.xml.dist phpunit.xml` and add the following line below the `CASHIER_MODEL` environment variable in your new `phpunit.xml` file:

    <env name="STRIPE_SECRET" value="Your Stripe Secret Key"/>

Please note that due to the fact that actual API requests against Stripe are being made, these tests take a few minutes to run.
