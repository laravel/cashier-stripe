<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Concerns\HandlesTaxes;
use Laravel\Cashier\Concerns\ManagesCustomer;
use Laravel\Cashier\Concerns\ManagesInvoices;
use Laravel\Cashier\Concerns\ManagesPaymentMethods;
use Laravel\Cashier\Concerns\ManagesSubscriptions;
use Laravel\Cashier\Concerns\ManagesUsageBilling;
use Laravel\Cashier\Concerns\PerformsCharges;

trait Billable
{
    use HandlesTaxes;
    use ManagesCustomer;
    use ManagesInvoices;
    use ManagesPaymentMethods;
    use ManagesSubscriptions;
    use ManagesUsageBilling;
    use PerformsCharges;
}
