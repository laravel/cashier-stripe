<?php

namespace Laravel\Cashier;

use Psr\Log\LoggerInterface;
use Stripe\Util\LoggerInterface as StripeLogger;

class Logger implements StripeLogger
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = [])
    {
        $this->logger->error($message, $context);
    }
};
