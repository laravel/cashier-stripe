<?php

namespace Laravel\Cashier\Exceptions;

use Throwable;

/**
 * Class SubscriptionPlanNotFound.
 */
final class SubscriptionPlanNotFound extends \Exception
{
    public function __construct($planKey, $subscriptionName)
    {
        parent::__construct("[{$planKey}] Not found on [{$subscriptionName}]");
    }
}
