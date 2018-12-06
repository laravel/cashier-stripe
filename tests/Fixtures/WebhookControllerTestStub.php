<?php

namespace Tests\Fixtures;

use Laravel\Cashier\Http\Controllers\WebhookController;

class WebhookControllerTestStub extends WebhookController
{
    public function handleChargeSucceeded()
    {
        $_SERVER['__received'] = true;
    }

    protected function eventExistsOnStripe($id)
    {
        return true;
    }
}
