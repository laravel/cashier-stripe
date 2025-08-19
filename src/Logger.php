<?php

namespace Laravel\Cashier;

use Psr\Log\LoggerInterface;
use Stripe\Util\LoggerInterface as StripeLogger;

class Logger implements StripeLogger
{
    /**
     * Create a new Logger instance.
     *
     * @param  \Psr\Log\LoggerInterface  $logger
     * @return void
     */
    public function __construct(protected LoggerInterface $logger)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = [])
    {
        $this->logger->error($message, $context);
    }
}
