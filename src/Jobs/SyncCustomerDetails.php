<?php

namespace Laravel\Cashier\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCustomerDetails implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The billable model instance.
     *
     * @var \Laravel\Cashier\Billable
     */
    public $billable;

    /**
     * Create a new job instance.
     *
     * @param  \Laravel\Cashier\Billable  $billable
     * @return void
     */
    public function __construct($billable)
    {
        $this->billable = $billable;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->billable->syncStripeCustomerDetails();
    }
}
