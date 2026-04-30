<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Concerns\HandlesTaxes;
use Laravel\Cashier\Concerns\ManagesBillingCredits;
use Laravel\Cashier\Concerns\ManagesCustomer;
use Laravel\Cashier\Concerns\ManagesInvoices;
use Laravel\Cashier\Concerns\ManagesPaymentMethods;
use Laravel\Cashier\Concerns\ManagesPricingModels;
use Laravel\Cashier\Concerns\ManagesQuotes;
use Laravel\Cashier\Concerns\ManagesSubscriptions;
use Laravel\Cashier\Concerns\ManagesSubscriptionSchedules;
use Laravel\Cashier\Concerns\ManagesUsageBilling;
use Laravel\Cashier\Concerns\PerformsCharges;

trait Billable
{
    use HandlesTaxes;
    use ManagesBillingCredits;
    use ManagesCustomer;
    use ManagesInvoices;
    use ManagesPaymentMethods;
    use ManagesPricingModels;
    use ManagesQuotes;
    use ManagesSubscriptions;
    use ManagesSubscriptionSchedules;
    use ManagesUsageBilling;
    use PerformsCharges;
}
