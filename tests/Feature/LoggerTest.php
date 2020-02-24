<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Logger;
use Laravel\Cashier\Tests\TestCase;
use Mockery as m;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Stripe\Stripe;
use Stripe\Util\DefaultLogger;
use Stripe\Util\LoggerInterface;

class LoggerTest extends TestCase
{
    /** @var string|null */
    protected $channel;

    public function tearDown(): void
    {
        config(['cashier.logger' => null]);

        parent::tearDown();
    }

    public function test_the_logger_is_correctly_bound()
    {
        $logger = $this->app->make(LoggerInterface::class);

        $this->assertInstanceOf(
            Logger::class,
            $logger,
            'Failed asserting that the Stripe logger interface is bound to the Cashier logger.'
        );

        $this->assertInstanceOf(
            LoggerInterface::class,
            $logger,
            'Failed asserting that the Cashier logger implements the Stripe logger interface.'
        );
    }

    public function test_the_logger_uses_a_log_channel()
    {
        $channel = m::mock(PsrLoggerInterface::class);
        $channel->shouldReceive('error')->once()->with('foo', ['bar']);

        $this->mock('log', function ($logger) use ($channel) {
            $logger->shouldReceive('channel')->with('default')->once()->andReturn($channel);
        });

        config(['cashier.logger' => 'default']);

        $logger = $this->app->make(LoggerInterface::class);

        $logger->error('foo', ['bar']);
    }

    public function test_it_uses_the_default_stripe_logger()
    {
        $logger = Stripe::getLogger();

        $this->assertInstanceOf(
            DefaultLogger::class,
            $logger,
            'Failed asserting that Stripe uses its own logger.'
        );
    }

    public function test_it_uses_a_configured_logger()
    {
        $this->channel = 'default';

        $this->refreshApplication();

        $logger = Stripe::getLogger();

        $this->assertInstanceOf(
            Logger::class,
            $logger,
            'Failed asserting that Stripe uses the Cashier logger.'
        );
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cashier.logger', $this->channel);
    }
}
