# Upgrade Guide

## Upgrading To 15.0 From 14.x

### Migration Changes

Cashier 15.0 no longer automatically loads migrations from its own migrations directory, so be sure to run the following command to publish Cashier's migrations to your application:

```bash
php artisan vendor:publish --tag=cashier-migrations
```
