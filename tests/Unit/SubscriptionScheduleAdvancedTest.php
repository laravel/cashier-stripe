<?php

namespace Laravel\Cashier\Tests\Unit;

use Hamcrest\Matchers;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\SubscriptionSchedule;
use Mockery as m;
use Orchestra\Testbench\Concerns\InteractsWithMockery;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException;
use Stripe\Invoice as StripeInvoice;

class SubscriptionScheduleAdvancedTest extends TestCase
{
    use InteractsWithMockery;

    /** {@inheritdoc} */
    #[\Override]
    protected function tearDown(): void
    {
        $this->tearDownTheTestEnvironmentUsingMockery();

        parent::tearDown();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_subscription_schedule_upcoming_invoice_preview()
    {
        // Mock the subscription schedule
        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['active', 'notStarted'])
            ->getMock();

        $schedule->method('active')->willReturn(true);
        $schedule->method('notStarted')->willReturn(false);

        $mockStripeInvoice = new TestStripeInvoice();
        $mockStripeInvoice->id = 'in_test123';
        $mockStripeInvoice->customer = 'cus_test123';

        // Mock the owner with Stripe client
        $mockInvoicesService = m::mock(TestStripeInvoiceService::class, [$mockStripeInvoice]);

        $mockStripeClient = new class($mockInvoicesService)
        {
            public function __construct(public $invoices)
            {
            }
        };

        $mockOwner = new class($mockStripeClient)
        {
            public $stripe_id;

            public function __construct(protected $stripeClient)
            {
            }

            public function stripe()
            {
                return $this->stripeClient;
            }
        };

        // Set the owner
        $schedule->owner = $mockOwner;
        $schedule->stripe_id = 'sub_sched_test123';
        $mockOwner->stripe_id = 'cus_test123';

        // We can't easily test the actual Invoice creation due to constructor complexity
        // Instead, verify the method calls the Stripe API with correct parameters
        $mockInvoicesService->shouldReceive('upcoming')
            ->once()
            ->with(Matchers::identicalTo([
                'customer' => 'cus_test123',
                'subscription_schedule' => 'sub_sched_test123',
            ]))->andReturn($mockStripeInvoice);

        // Use reflection to call the method
        $reflection = new \ReflectionClass($schedule);
        $method = $reflection->getMethod('upcomingInvoicePreview');

        // Mock the Invoice constructor
        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->getMock();

        $invoice = $method->invoke($schedule);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_subscription_schedule_upcoming_invoice_preview_throws_for_inactive()
    {
        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['active', 'notStarted'])
            ->getMock();

        $schedule->method('active')->willReturn(false);
        $schedule->method('notStarted')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot preview invoices for inactive subscription schedules.');

        $schedule->upcomingInvoicePreview();
    }

    public function test_subscription_schedule_preview_next_invoice_returns_null_on_no_upcoming()
    {
        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['upcomingInvoicePreview'])
            ->getMock();

        $schedule->method('upcomingInvoicePreview')
            ->willThrowException(new InvalidRequestException('No upcoming invoice'));

        $result = $schedule->previewNextInvoice();
        $this->assertNull($result);
    }

    public function test_subscription_schedule_preview_next_invoice_rethrows_other_exceptions()
    {
        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['upcomingInvoicePreview'])
            ->getMock();

        $schedule->method('upcomingInvoicePreview')
            ->willThrowException(new InvalidRequestException('Different error'));

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Different error');

        $schedule->previewNextInvoice();
    }

    public function test_subscription_schedule_preview_phase_transition_invoice_invalid_phase()
    {
        $mockStripeSchedule = new \stdClass();
        $mockStripeSchedule->phases = [];

        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['asStripeSubscriptionSchedule'])
            ->getMock();

        $schedule->method('asStripeSubscriptionSchedule')
            ->willReturn($mockStripeSchedule);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Phase index 0 does not exist on this schedule.');

        $schedule->previewPhaseTransitionInvoice(0);
    }

    public function test_subscription_schedule_format_phase_items_for_preview()
    {
        $schedule = new SubscriptionSchedule();

        $items = [
            ['price' => 'price_1', 'quantity' => 2],
            ['price' => 'price_2'], // No quantity
            ['price' => 'price_3', 'quantity' => 5, 'extra' => 'ignored'],
        ];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($schedule);
        $method = $reflection->getMethod('formatPhaseItemsForPreview');

        $result = $method->invoke($schedule, $items);

        $expected = [
            ['price' => 'price_1', 'quantity' => 2],
            ['price' => 'price_2', 'quantity' => 1],
            ['price' => 'price_3', 'quantity' => 5],
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_subscription_schedule_phases_method()
    {
        $mockStripeSchedule = new \stdClass();
        $mockStripeSchedule->phases = new \stdClass();
        $mockStripeSchedule->phases->data = [
            (object) ['items' => ['price_1'], 'start_date' => 1640995200],
            (object) ['items' => ['price_2'], 'start_date' => 1641081600],
        ];

        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['asStripeSubscriptionSchedule'])
            ->getMock();

        $schedule->method('asStripeSubscriptionSchedule')
            ->willReturn($mockStripeSchedule);

        $phases = $schedule->phases();

        $this->assertCount(2, $phases);
        $this->assertEquals(['price_1'], $phases[0]->items);
        $this->assertEquals(['price_2'], $phases[1]->items);
    }

    public function test_subscription_schedule_current_phase()
    {
        $mockStripeSchedule = new \stdClass();
        $mockStripeSchedule->current_phase = (object) [
            'items' => ['price_current'],
            'start_date' => 1640995200,
            'end_date' => 1641081600,
        ];

        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['asStripeSubscriptionSchedule'])
            ->getMock();

        $schedule->method('asStripeSubscriptionSchedule')
            ->willReturn($mockStripeSchedule);

        $currentPhase = $schedule->currentPhase();

        $this->assertEquals(['price_current'], $currentPhase->items);
        $this->assertEquals(1640995200, $currentPhase->start_date);
        $this->assertEquals(1641081600, $currentPhase->end_date);
    }

    public function test_subscription_schedule_remaining_phases()
    {
        $currentTime = time();
        $futureTime = $currentTime + 3600;

        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['phases'])
            ->getMock();

        $phases = [
            (object) ['start_date' => $currentTime - 3600], // Past phase
            (object) ['start_date' => $futureTime], // Future phase
            (object) ['start_date' => $futureTime + 3600], // Another future phase
        ];

        $schedule->method('phases')->willReturn($phases);

        $remainingPhases = $schedule->remainingPhases();

        $this->assertCount(2, $remainingPhases);
        $this->assertEquals($futureTime, $remainingPhases[1]->start_date);
        $this->assertEquals($futureTime + 3600, $remainingPhases[2]->start_date);
    }

    public function test_subscription_schedule_update_phase_invalid_index()
    {
        $mockStripeSchedule = new \stdClass();
        $mockStripeSchedule->phases = new \stdClass();
        $mockStripeSchedule->phases->data = [];

        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['asStripeSubscriptionSchedule'])
            ->getMock();

        $schedule->method('asStripeSubscriptionSchedule')
            ->willReturn($mockStripeSchedule);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Phase index 0 does not exist on this schedule.');

        $schedule->updatePhase(0, ['iterations' => 2]);
    }

    public function test_subscription_schedule_update_phase_valid()
    {
        $mockStripeSchedule = new \stdClass();
        $mockStripeSchedule->phases = new \stdClass();
        $mockStripeSchedule->phases->data = [
            (object) ['items' => ['price_1'], 'iterations' => 1],
            (object) ['items' => ['price_2'], 'iterations' => 1],
        ];

        $schedule = $this->getMockBuilder(SubscriptionSchedule::class)
            ->onlyMethods(['asStripeSubscriptionSchedule', 'updateSchedule'])
            ->getMock();

        $schedule->method('asStripeSubscriptionSchedule')
            ->willReturn($mockStripeSchedule);

        $schedule->expects($this->once())
            ->method('updateSchedule')
            ->with($this->equalTo([
                'phases' => [
                    ['items' => ['price_1'], 'iterations' => 2], // Updated phase
                    ['items' => ['price_2'], 'iterations' => 1], // Unchanged phase
                ],
            ]))
            ->willReturn($schedule);

        $result = $schedule->updatePhase(0, ['iterations' => 2]);

        $this->assertSame($schedule, $result);
    }
}

class TestStripeInvoiceService
{
    public function __construct(
        public $upcomingStripeInvoice
    ) {
    }

    public function upcoming($options)
    {
        var_dump($options);

        return $this->upcomingStripeInvoice;
    }
}

class TestStripeInvoice extends StripeInvoice
{
    public $id;
    public $customer;

    public function __construct()
    {
        // Skip parent constructor to avoid API calls
    }
}
