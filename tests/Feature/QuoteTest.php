<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\Cashier\Quote;

class QuoteTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $productId;

    /**
     * @var string
     */
    protected static $priceId;

    /**
     * @var string
     */
    protected static $otherPriceId;

    public static function setUpBeforeClass(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            return;
        }

        static::$productId = self::stripe()->products->create([
            'name' => 'Laravel Cashier Test Product',
            'type' => 'service',
        ])->id;

        static::$priceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $10',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'unit_amount' => 1000,
        ])->id;

        static::$otherPriceId = self::stripe()->prices->create([
            'product' => static::$productId,
            'nickname' => 'Monthly $20',
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
            ],
            'unit_amount' => 2000,
        ])->id;
    }

    public static function tearDownAfterClass(): void
    {
        static::deleteStripeResource(new \Stripe\Product(static::$productId));
    }

    public function test_quotes_can_be_created()
    {
        $user = $this->createCustomer('quotes_can_be_created');

        // Create quote with line items
        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->addLineItem(static::$otherPriceId, 2)
            ->description('Test quote for customer')
            ->create();

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertNotNull($quote->stripe_id);
        $this->assertEquals('draft', $quote->status);
        $this->assertEquals(1, count($user->quotes));
        $this->assertTrue($quote->draft());
    }

    public function test_quotes_can_be_created_with_flexible_billing_mode()
    {
        $user = $this->createCustomer('quotes_flexible_billing');

        try {
            // Create quote with flexible billing mode
            $quote = $user->newQuote()
                ->addLineItem(static::$priceId, 1)
                ->withBillingMode('flexible')
                ->create();

            $this->assertInstanceOf(Quote::class, $quote);
            $this->assertNotNull($quote->stripe_id);
            $this->assertEquals('draft', $quote->status);
            $this->assertEquals(1, count($user->quotes));
        } catch (\Exception $e) {
            // Skip test if API version doesn't support flexible billing
            if (strpos($e->getMessage(), 'billing_mode') !== false ||
                strpos($e->getMessage(), 'API version') !== false) {
                $this->markTestSkipped('Stripe API version does not support flexible billing mode');
            }
            throw $e;
        }
    }

    public function test_quotes_can_be_created_with_metadata()
    {
        $user = $this->createCustomer('quotes_with_metadata');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->withMetadata(['test' => 'value', 'quote_type' => 'subscription'])
            ->create();

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertNotNull($quote->stripe_id);

        $stripeQuote = $quote->asStripeQuote();
        $this->assertEquals('value', $stripeQuote->metadata->test);
        $this->assertEquals('subscription', $stripeQuote->metadata->quote_type);
    }

    public function test_quotes_can_be_created_with_expiration()
    {
        $user = $this->createCustomer('quotes_with_expiration');
        $expiresAt = now()->addWeek();

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->expiresAt($expiresAt)
            ->create();

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertNotNull($quote->expires_at);
        $this->assertEquals($expiresAt->format('Y-m-d H:i'), $quote->expires_at->format('Y-m-d H:i'));
    }

    public function test_quotes_can_be_finalized()
    {
        $user = $this->createCustomer('quotes_can_be_finalized');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        $this->assertTrue($quote->draft());

        // Finalize the quote
        $quote->finalize();

        $this->assertTrue($quote->open());
        $this->assertNotNull($quote->status_transitions_finalized_at);
        $this->assertNotNull($quote->number);
    }

    public function test_quotes_can_be_accepted()
    {
        $user = $this->createCustomer('quotes_can_be_accepted');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        // Finalize first, then accept
        $quote->finalize();
        $this->assertTrue($quote->open());

        $quote->accept();

        $this->assertTrue($quote->accepted());
        $this->assertNotNull($quote->status_transitions_accepted_at);
    }

    public function test_quotes_can_be_canceled()
    {
        $user = $this->createCustomer('quotes_can_be_canceled');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        $this->assertTrue($quote->draft());

        $quote->cancel();

        $this->assertTrue($quote->canceled());
        $this->assertNotNull($quote->status_transitions_canceled_at);
    }

    public function test_quotes_can_be_updated()
    {
        $user = $this->createCustomer('quotes_can_be_updated');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->withMetadata(['test' => 'original'])
            ->create();

        // Update the quote
        $quote->updateStripeQuote([
            'metadata' => ['test' => 'updated'],
        ]);

        $stripeQuote = $quote->asStripeQuote();
        $this->assertEquals('updated', $stripeQuote->metadata->test);
    }

    public function test_quotes_can_be_updated_with_billing_mode()
    {
        $user = $this->createCustomer('quotes_updated_with_billing_mode');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        try {
            // Update with billing mode
            $quote->withBillingMode('flexible')
                  ->updateStripeQuote(['metadata' => ['updated' => 'true']]);

            $stripeQuote = $quote->asStripeQuote();
            $this->assertEquals('true', $stripeQuote->metadata->updated);
        } catch (\Exception $e) {
            // Skip test if API version doesn't support flexible billing
            if (strpos($e->getMessage(), 'billing_mode') !== false ||
                strpos($e->getMessage(), 'API version') !== false) {
                $this->markTestSkipped('Stripe API version does not support flexible billing mode');
            }
            throw $e;
        }
    }

    public function test_quotes_can_sync_with_stripe()
    {
        $user = $this->createCustomer('quotes_sync_with_stripe');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        // Modify via Stripe API directly
        $user->stripe()->quotes->update($quote->stripe_id, [
            'metadata' => ['synced' => 'true'],
        ]);

        // Sync with local model
        $quote->syncWithStripe();

        $stripeQuote = $quote->asStripeQuote();
        $this->assertEquals('true', $stripeQuote->metadata->synced);
    }

    public function test_quotes_can_download_pdf()
    {
        $user = $this->createCustomer('quotes_download_pdf');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        // Finalize to make PDF available
        $quote->finalize();

        $response = $quote->downloadPdf();

        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('quote-'.$quote->id.'.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_quote_respects_config_default_billing_mode()
    {
        // Set config to flexible
        config(['cashier.default_billing_mode' => 'flexible']);

        $user = $this->createCustomer('quote_config_default');

        try {
            // Create quote without explicit billing mode (should use config default)
            $quote = $user->newQuote()
                ->addLineItem(static::$priceId, 1)
                ->create();

            $this->assertInstanceOf(Quote::class, $quote);
            $this->assertNotNull($quote->stripe_id);
        } catch (\Exception $e) {
            // Reset config first
            config(['cashier.default_billing_mode' => 'classic']);

            // Skip test if API version doesn't support flexible billing
            if (strpos($e->getMessage(), 'billing_mode') !== false ||
                strpos($e->getMessage(), 'API version') !== false) {
                $this->markTestSkipped('Stripe API version does not support flexible billing mode');
            }
            throw $e;
        }

        // Reset config
        config(['cashier.default_billing_mode' => 'classic']);
    }

    public function test_user_can_find_quote()
    {
        $user = $this->createCustomer('user_can_find_quote');

        $quote = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        $found = $user->findQuote($quote->stripe_id);

        $this->assertInstanceOf(Quote::class, $found);
        $this->assertEquals($quote->id, $found->id);
    }

    public function test_user_quotes_relationship()
    {
        $user = $this->createCustomer('user_quotes_relationship');

        // Create multiple quotes
        $quote1 = $user->newQuote()
            ->addLineItem(static::$priceId, 1)
            ->create();

        $quote2 = $user->newQuote()
            ->addLineItem(static::$otherPriceId, 2)
            ->create();

        $this->assertEquals(2, count($user->quotes));
        $this->assertTrue($user->quotes->contains($quote1));
        $this->assertTrue($user->quotes->contains($quote2));
    }

    public function test_quotes_require_line_items()
    {
        $user = $this->createCustomer('quotes_require_line_items');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one line item is required when creating quotes.');

        // Try to create quote without line items
        $user->newQuote()
            ->description('Quote without line items')
            ->create();
    }
}
