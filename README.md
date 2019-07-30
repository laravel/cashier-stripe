<p align="center"><img src="https://laravel.com/assets/img/components/logo-cashier.svg"></p>

<p align="center">
<a href="https://travis-ci.org/laravel/cashier"><img src="https://travis-ci.org/laravel/cashier.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/cashier"><img src="https://poser.pugx.org/laravel/cashier/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/cashier"><img src="https://poser.pugx.org/laravel/cashier/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/cashier"><img src="https://poser.pugx.org/laravel/cashier/license.svg" alt="License"></a>
</p>

## Introduction

Laravel Cashier provides an expressive, fluent interface to [Stripe's](https://stripe.com) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing. In addition to basic subscription management, Cashier can handle coupons, swapping subscription, subscription "quantities", cancellation grace periods, and even generate invoice PDFs.

## Official Documentation

Documentation for Cashier can be found on the [Laravel website](https://laravel.com/docs/billing).

## Running Cashier's Tests

You will need to set the Stripe **testing** secret environment variable in a custom `phpunit.xml` file in order to run the Cashier tests.

Copy the default file using `cp phpunit.xml.dist phpunit.xml` and add the following line below the `STRIPE_MODEL` environment variable in your new `phpunit.xml` file:

    <env name="STRIPE_SECRET" value="Your Stripe Secret Key"/>

Please note that due to the fact that actual API requests against Stripe are being made, these tests take a few minutes to run.

## Contributing

Thank you for considering contributing to the Cashier. You can read the contribution guide lines [here](contributing.md).

## License

Laravel Cashier is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
