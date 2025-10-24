<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use JsonSerializable;
use Laravel\Cashier\Contracts\InvoiceRenderer;
use Laravel\Cashier\Exceptions\InvalidInvoice;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;
use Stripe\Price as StripePrice;
use Stripe\TaxRate as StripeTaxRate;
use Symfony\Component\HttpFoundation\Response;

class Invoice implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Stripe invoice line items.
     *
     * @var \Laravel\Cashier\InvoiceLineItem[]
     */
    protected array $items = [];

    /**
     * The taxes applied to the invoice.
     *
     * @var \Laravel\Cashier\Tax[]|null
     */
    protected ?array $taxes = null;

    /**
     * The payments associated with the invoice.
     *
     * @var \Laravel\Cashier\InvoicePayment[]
     */
    protected array $payments = [];

    /**
     * The discounts applied to the invoice.
     *
     * @var \Laravel\Cashier\Discount[]|null
     */
    protected ?array $discounts = null;

    /**
     * Indicate if the Stripe Object was refreshed with extra data.
     *
     * @var bool
     */
    protected bool $refreshed = false;

    /**
     * Create a new invoice instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Stripe\Invoice  $invoice
     * @param  array  $refreshData
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidInvoice
     */
    public function __construct(
        protected $owner,
        protected StripeInvoice $invoice,
        protected array $refreshData = []
    ) {
        if ($owner->stripe_id !== $invoice->customer) {
            throw InvalidInvoice::invalidOwner($invoice, $owner);
        }
    }

    /**
     * Get a Carbon instance for the invoicing date.
     *
     * @param  \DateTimeZone|string|int|null  $timezone
     * @return \Carbon\CarbonInterface
     */
    public function date(DateTimeZone|string|int|null $timezone = null): CarbonInterface
    {
        $carbon = Carbon::createFromTimestampUTC($this->invoice->created);

        return $timezone ? $carbon->setTimezone($timezone) : $carbon;
    }

    /**
     * Get a Carbon instance for the invoice's due date.
     *
     * @param  \DateTimeZone|string|int|null  $timezone
     * @return \Carbon\CarbonInterface|null
     */
    public function dueDate(DateTimeZone|string|int|null $timezone = null): ?CarbonInterface
    {
        if ($this->invoice->due_date) {
            $carbon = Carbon::createFromTimestampUTC($this->invoice->due_date);

            return $timezone ? $carbon->setTimezone($timezone) : $carbon;
        }

        return null;
    }

    /**
     * Get the total amount minus the starting balance that was paid (or will be paid).
     *
     * @return string
     */
    public function total(): string
    {
        return $this->formatAmount($this->rawTotal());
    }

    /**
     * Get the raw total amount minus the starting balance that was paid (or will be paid).
     *
     * @return int
     */
    public function rawTotal(): int
    {
        return $this->invoice->total + $this->rawStartingBalance();
    }

    /**
     * Get the total amount that was paid (or will be paid).
     *
     * @return string
     */
    public function realTotal(): string
    {
        return $this->formatAmount($this->rawRealTotal());
    }

    /**
     * Get the raw total amount that was paid (or will be paid).
     *
     * @return int
     */
    public function rawRealTotal(): int
    {
        return $this->invoice->total;
    }

    /**
     * Get the total of the invoice (before discounts).
     *
     * @return string
     */
    public function subtotal(): string
    {
        return $this->formatAmount($this->invoice->subtotal);
    }

    /**
     * Get the amount due for the invoice.
     *
     * @return string
     */
    public function amountDue(): string
    {
        return $this->formatAmount($this->rawAmountDue());
    }

    /**
     * Get the raw amount due for the invoice.
     *
     * @return int
     */
    public function rawAmountDue(): int
    {
        return $this->invoice->amount_due ?? 0;
    }

    /**
     * Determine if the account had a starting balance.
     *
     * @return bool
     */
    public function hasStartingBalance(): bool
    {
        return $this->rawStartingBalance() < 0;
    }

    /**
     * Get the starting balance for the invoice.
     *
     * @return string
     */
    public function startingBalance(): string
    {
        return $this->formatAmount($this->rawStartingBalance());
    }

    /**
     * Get the raw starting balance for the invoice.
     *
     * @return int
     */
    public function rawStartingBalance(): int
    {
        return $this->invoice->starting_balance ?? 0;
    }

    /**
     * Determine if the account had an ending balance.
     *
     * @return bool
     */
    public function hasEndingBalance(): bool
    {
        return ! is_null($this->invoice->ending_balance);
    }

    /**
     * Get the ending balance for the invoice.
     *
     * @return string
     */
    public function endingBalance(): string
    {
        return $this->formatAmount($this->rawEndingBalance());
    }

    /**
     * Get the raw ending balance for the invoice.
     *
     * @return int
     */
    public function rawEndingBalance(): int
    {
        return $this->invoice->ending_balance ?? 0;
    }

    /**
     * Determine if the invoice has balance applied.
     *
     * @return bool
     */
    public function hasAppliedBalance(): bool
    {
        return $this->rawAppliedBalance() < 0;
    }

    /**
     * Get the applied balance for the invoice.
     *
     * @return string
     */
    public function appliedBalance(): string
    {
        return $this->formatAmount($this->rawAppliedBalance());
    }

    /**
     * Get the raw applied balance for the invoice.
     *
     * @return int
     */
    public function rawAppliedBalance(): int
    {
        return $this->rawStartingBalance() - $this->rawEndingBalance();
    }

    /**
     * Determine if the invoice has one or more discounts applied.
     *
     * @return bool
     */
    public function hasDiscount(): bool
    {
        if (is_null($this->invoice->discounts)) {
            return false;
        }

        return count($this->invoice->discounts) > 0;
    }

    /**
     * Get all of the discount objects from the Stripe invoice.
     *
     * @return \Laravel\Cashier\Discount[]
     */
    public function discounts(): array
    {
        if (! is_null($this->discounts)) {
            return $this->discounts;
        }

        /** @var array<int, \Stripe\Discount|string> $discounts */
        $discounts = $this->invoice->discounts ?? [];

        // If the discounts are returned as an array of strings we need to refresh
        // the invoice to get the full discount objects. This can happen if the
        // invoice was created before the discounts were fully expanded here.
        if (isset($discounts[0]) && is_string($discounts[0])) {
            $this->refresh(['discounts']);
            $discounts = $this->invoice->discounts ?? [];
        }

        $this->discounts = collect($discounts)->map(function ($discount) {
            return new Discount($discount);
        })->all();

        return $this->discounts;
    }

    /**
     * Calculate the amount for a given discount.
     *
     * @param  \Laravel\Cashier\Discount  $discount
     * @return string|null
     */
    public function discountFor(Discount $discount): ?string
    {
        if (! is_null($discountAmount = $this->rawDiscountFor($discount))) {
            return $this->formatAmount($discountAmount);
        }

        return null;
    }

    /**
     * Calculate the raw amount for a given discount.
     *
     * @param  \Laravel\Cashier\Discount  $discount
     * @return int|null
     */
    public function rawDiscountFor(Discount $discount): ?int
    {
        return optional(Collection::make($this->invoice->total_discount_amounts)
            ->first(function ($discountAmount) use ($discount) {
                if (is_string($discountAmount->discount)) {
                    return $discountAmount->discount === $discount->id;
                } else {
                    return $discountAmount->discount->id === $discount->id;
                }
            }))
            ->amount;
    }

    /**
     * Get the total discount amount.
     *
     * @return string
     */
    public function discount(): string
    {
        return $this->formatAmount($this->rawDiscount());
    }

    /**
     * Get the raw total discount amount.
     *
     * @return int
     */
    public function rawDiscount(): int
    {
        $total = 0;

        foreach ((array) $this->invoice->total_discount_amounts as $discount) {
            $total += $discount->amount;
        }

        return (int) $total;
    }

    /**
     * Get the total tax amount.
     *
     * @return string
     */
    public function tax(): string
    {
        return $this->formatAmount($this->invoice->tax ?? 0);
    }

    /**
     * Determine if the invoice has tax applied.
     *
     * @return bool
     */
    public function hasTax(): bool
    {
        $lineItems = $this->invoiceItems() + $this->subscriptions();

        return Collection::make($lineItems)->contains(function (InvoiceLineItem $item) {
            return $item->hasTaxRates();
        });
    }

    /**
     * Get the taxes applied to the invoice.
     *
     * @return \Laravel\Cashier\Tax[]
     */
    public function taxes(): array
    {
        if (! is_null($this->taxes)) {
            return $this->taxes;
        }

        $this->taxes = collect($this->invoice->total_taxes ?? [])->map(function ($tax) {
            if (isset($tax->type) && $tax->type === 'tax_rate_details') {
                $taxRate = $this->getTaxRate($tax->tax_rate_details);

                return new Tax($tax->amount, $this->invoice->currency, $taxRate);
            }

            return new Tax($tax->amount, $this->invoice->currency, null);
        })->all();

        return $this->taxes;
    }

    /**
     * Get the tax rate from tax rate details, fetching from Stripe if needed.
     *
     * @param  object  $taxRateDetails
     * @return \Stripe\TaxRate|null
     */
    protected function getTaxRate($taxRateDetails): ?StripeTaxRate
    {
        // If tax_rate is already expanded as an object, return it...
        if ($taxRateDetails->tax_rate instanceof StripeTaxRate) {
            return $taxRateDetails->tax_rate;
        }

        // If tax_rate is just an ID string, fetch it from Stripe...
        if (isset($taxRateDetails->tax_rate) && is_string($taxRateDetails->tax_rate)) {
            try {
                return $this->owner->stripe()->taxRates->retrieve($taxRateDetails->tax_rate);
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Determine if the customer is not exempted from taxes.
     *
     * @return bool
     */
    public function isNotTaxExempt(): bool
    {
        return $this->invoice->customer_tax_exempt === StripeCustomer::TAX_EXEMPT_NONE;
    }

    /**
     * Determine if the customer is exempted from taxes.
     *
     * @return bool
     */
    public function isTaxExempt(): bool
    {
        return $this->invoice->customer_tax_exempt === StripeCustomer::TAX_EXEMPT_EXEMPT;
    }

    /**
     * Determine if reverse charge applies to the customer.
     *
     * @return bool
     */
    public function reverseChargeApplies(): bool
    {
        return $this->invoice->customer_tax_exempt === StripeCustomer::TAX_EXEMPT_REVERSE;
    }

    /**
     * Determine if the invoice will charge the customer automatically.
     *
     * @return bool
     */
    public function chargesAutomatically(): bool
    {
        return $this->invoice->collection_method === StripeInvoice::COLLECTION_METHOD_CHARGE_AUTOMATICALLY;
    }

    /**
     * Determine if the invoice will send an invoice to the customer.
     *
     * @return bool
     */
    public function sendsInvoice(): bool
    {
        return $this->invoice->collection_method === StripeInvoice::COLLECTION_METHOD_SEND_INVOICE;
    }

    /**
     * Get all of the "invoice item" line items.
     *
     * @return \Laravel\Cashier\InvoiceLineItem[]
     */
    public function invoiceItems(): array
    {
        return Collection::make($this->invoiceLineItems())->filter(function (InvoiceLineItem $item) {
            return $item->isInvoiceItem();
        })->all();
    }

    /**
     * Get all of the "subscription" line items.
     *
     * @return \Laravel\Cashier\InvoiceLineItem[]
     */
    public function subscriptions(): array
    {
        return Collection::make($this->invoiceLineItems())->filter(function (InvoiceLineItem $item) {
            return $item->isSubscription();
        })->all();
    }

    /**
     * Get all of the invoice line items.
     *
     * @param  array{ending_before?: string, expand?: string[], limit?: int, starting_after?: string}  $params
     * @return \Laravel\Cashier\InvoiceLineItem[]
     */
    public function invoiceLineItems(array $params = []): array
    {
        $params['expand'] = array_values(array_unique(array_merge(
            $params['expand'] ?? [],
            ['data.price']
        )));

        /** @var \Stripe\Service\InvoiceService $invoiceService */
        $invoiceService = $this->owner->stripe()->invoices;

        $stripeLineItems = $invoiceService->allLines(
            $this->invoice->id, $params
        );

        return collect($stripeLineItems->data)->map(function ($line) {
            return new InvoiceLineItem($this, $line);
        })->all();
    }

    /**
     * Add an invoice item to this invoice.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @return \Stripe\InvoiceItem
     */
    public function tab(string $description, int $amount, array $options = [])
    {
        $item = $this->owner()->tab($description, $amount, array_merge($options, ['invoice' => $this->invoice->id]));

        $this->refresh();

        return $item;
    }

    /**
     * Add an invoice item for a specific Price ID to this invoice.
     *
     * @param  \Stripe\Price|string  $price
     * @param  int  $quantity
     * @param  array  $options
     * @return \Stripe\InvoiceItem
     */
    public function tabPrice(StripePrice|string $price, int $quantity = 1, array $options = [])
    {
        $item = $this->owner()->tabPrice($price, $quantity, array_merge($options, ['invoice' => $this->invoice->id]));

        $this->refresh();

        return $item;
    }

    /**
     * Refresh the invoice instance from Stripe.
     *
     * @param  array<int, string>  $expand
     * @return $this
     */
    public function refresh(array $expand = [])
    {
        if (! empty($expand)) {
            /** @var \Stripe\Service\InvoiceService $invoiceService */
            $invoiceService = $this->owner->stripe()->invoices;

            // If the invoice has an ID, we can retrieve it with the expanded objects...
            $this->invoice = $invoiceService->retrieve($this->invoice->id, [
                'expand' => $expand,
            ]);
        } else {
            $this->invoice = $this->invoice->refresh();
        }

        return $this;
    }

    /**
     * Refresh the invoice with expanded objects.
     *
     * @return void
     */
    protected function refreshWithExpandedData(): void
    {
        if ($this->refreshed) {
            return;
        }

        $expand = [
            'account_tax_ids',
            'discounts',
            'lines.data.taxes.tax_rate_details',
            'lines.data.taxes.tax_rate_details.tax_rate',
            'lines.data.price',
            'payments',
            'total_discount_amounts.discount',
            'total_taxes.tax_rate_details',
            'total_taxes.tax_rate_details.tax_rate',
        ];

        /** @var \Stripe\Service\InvoiceService $invoiceService */
        $invoiceService = $this->owner->stripe()->invoices;

        if (isset($this->invoice->id) && $this->invoice->id) {
            $this->invoice = $invoiceService->retrieve($this->invoice->id, [
                'expand' => $expand,
            ]);
        } else {
            $this->invoice = $invoiceService->createPreview(array_merge($this->refreshData, [
                'customer' => $this->owner->stripe_id,
                'expand' => $expand,
            ]));
        }

        $this->refreshed = true;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount(int $amount): string
    {
        return Cashier::formatAmount($amount, $this->invoice->currency);
    }

    /**
     * Return the Tax Ids of the account.
     *
     * @return \Stripe\TaxId[]
     */
    public function accountTaxIds(): array
    {
        return $this->invoice->account_tax_ids ?? [];
    }

    /**
     * Return the Tax Ids of the customer.
     *
     * @return array
     */
    public function customerTaxIds(): array
    {
        return $this->invoice->customer_tax_ids ?? [];
    }

    /**
     * Finalize the Stripe invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function finalize(array $options = [])
    {
        $this->invoice = $this->invoice->finalizeInvoice($options);

        return $this;
    }

    /**
     * Pay the Stripe invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function pay(array $options = [])
    {
        $this->invoice = $this->invoice->pay($options);

        return $this;
    }

    /**
     * Send the Stripe invoice to the customer.
     *
     * @param  array  $options
     * @return $this
     */
    public function send(array $options = [])
    {
        $this->invoice = $this->invoice->sendInvoice($options);

        return $this;
    }

    /**
     * Void the Stripe invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function void(array $options = [])
    {
        $this->invoice = $this->invoice->voidInvoice($options);

        return $this;
    }

    /**
     * Mark an invoice as uncollectible.
     *
     * @param  array  $options
     * @return $this
     */
    public function markUncollectible(array $options = [])
    {
        $this->invoice = $this->invoice->markUncollectible($options);

        return $this;
    }

    /**
     * Delete the Stripe invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function delete(array $options = [])
    {
        $this->invoice = $this->invoice->delete($options);

        return $this;
    }

    /**
     * Determine if the invoice is open.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->invoice->status === StripeInvoice::STATUS_OPEN;
    }

    /**
     * Determine if the invoice is a draft.
     *
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this->invoice->status === StripeInvoice::STATUS_DRAFT;
    }

    /**
     * Determine if the invoice is paid.
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->invoice->status === StripeInvoice::STATUS_PAID;
    }

    /**
     * Get the invoice payments.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Cashier\InvoicePayment>
     */
    public function payments(): Collection
    {
        if ($this->payments) {
            return collect($this->payments);
        }

        // Retrieve invoice payments via the API, allowing users to expand or filter via list parameters...
        return $this->owner->invoicePaymentsForInvoice($this->invoice->id);
    }

    /**
     * Get the amount paid on the invoice.
     *
     * @return string
     */
    public function amountPaid(): string
    {
        return $this->formatAmount($this->rawAmountPaid());
    }

    /**
     * Get the raw amount paid on the invoice.
     *
     * @return int
     *
     * @see https://docs.stripe.com/api/invoices/object#invoice_object-amount_paid
     */
    public function rawAmountPaid(): int
    {
        return $this->invoice->amount_paid ?? 0;
    }

    /**
     * Get the confirmation secret for Payment Element integrations.
     *
     * @return string|null
     *
     * @see https://docs.stripe.com/api/invoices/object#invoice_object-confirmation_secret
     */
    public function confirmationSecret(): ?string
    {
        return $this->invoice->confirmation_secret->client_secret ?? null;
    }

    /**
     * Get the subscription ID associated with this invoice.
     *
     * @return string|null
     *
     * @see https://docs.stripe.com/api/invoices/object#invoice_object-parent-subscription_details-subscription
     */
    public function subscriptionId(): ?string
    {
        return transform($this->subscriptionDetails(), function ($details) {
            return $details->subscription ?? null;
        });
    }

    /**
     * Get the quote ID associated with this invoice.
     *
     * @return string|null
     *
     * @see https://stripe.com/docs/api/invoices/object#invoice_object-parent
     */
    public function quoteId(): ?string
    {
        return transform($this->parent(), function ($parent) {
            return $parent->type === 'quote_details'
                ? $parent->quote_details->quote
                : null;
        });
    }

    /**
     * Get the subscription details for this invoice.
     *
     * @return (object{metadata: object|null, subscription: string, subscription_proration_date: int|null})|null
     *
     * @see https://docs.stripe.com/api/invoices/object#invoice_object-parent-subscription_details
     */
    public function subscriptionDetails(): ?object
    {
        return transform($this->parent(), function ($parent) {
            return $parent->type === 'subscription_details'
                ? $parent->subscription_details
                : null;
        });
    }

    /**
     * Get the subscription proration date for this invoice.
     *
     * @return int|null
     *
     * @see https://docs.stripe.com/api/invoices/object#invoice_object-parent-subscription_details-subscription_proration_date
     */
    public function subscriptionProrationDate(): ?int
    {
        return transform($this->subscriptionDetails(), function ($details) {
            return $details->subscription_proration_date ?? null;
        });
    }

    /**
     * Apply a coupon to this invoice.
     *
     * @param  string  $couponId
     * @return $this
     *
     * @see https://docs.stripe.com/api/invoices/update#update_invoice-discounts
     */
    public function applyCoupon(string $couponId, array $options = [])
    {
        $options = array_merge([
            'discounts' => [['coupon' => $couponId]],
        ], $options);

        /** @var \Stripe\Service\InvoiceService $invoiceService */
        $invoiceService = $this->owner->stripe()->invoices;

        $this->invoice = $invoiceService->update(
            $this->invoice->id, $options
        );

        return $this;
    }

    /**
     * Apply a discount to this invoice.
     *
     * @param  array<string, mixed>  $discounts
     * @return $this
     *
     * @see https://docs.stripe.com/api/invoices/update#update_invoice-discounts
     */
    public function applyDiscount(array $discounts)
    {
        /** @var \Stripe\Service\InvoiceService $invoiceService */
        $invoiceService = $this->owner->stripe()->invoices;

        $this->invoice = $invoiceService->update(
            $this->invoice->id,
            ['discounts' => [$discounts]]
        );

        return $this;
    }

    /**
     * Get the parent information for this invoice.
     *
     * @return (object{quote_details?: object, subscription_details?: object,  type: 'quote_details'|'subscription_details'})|null
     *
     * @see https://docs.stripe.com/api/invoices/object#invoice_object-parent
     */
    public function parent(): ?object
    {
        return $this->invoice->parent ?? null;
    }

    /**
     * Determine if the invoice is uncollectible.
     *
     * @return bool
     */
    public function isUncollectible(): bool
    {
        return $this->invoice->status === StripeInvoice::STATUS_UNCOLLECTIBLE;
    }

    /**
     * Determine if the invoice is void.
     *
     * @return bool
     */
    public function isVoid(): bool
    {
        return $this->invoice->status === StripeInvoice::STATUS_VOID;
    }

    /**
     * Get the View instance for the invoice.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\View\View
     */
    public function view(array $data = [])
    {
        return View::make('cashier::invoice', array_merge($data, [
            'invoice' => $this,
            'owner' => $this->owner,
            'user' => $this->owner,
        ]));
    }

    /**
     * Capture the invoice as a PDF and return the raw bytes.
     *
     * @param  array  $data
     * @return string
     */
    public function pdf(array $data = []): string
    {
        $options = config('cashier.invoices.options', []);

        if ($paper = config('cashier.paper')) {
            $options['paper'] = $paper;
        }

        return app(InvoiceRenderer::class)->render($this, $data, $options);
    }

    /**
     * Create an invoice download response.
     *
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(array $data = [])
    {
        $filename = $data['product'] ?? Str::slug(config('app.name'));
        $filename .= '_'.$this->date()->month.'_'.$this->date()->year;

        return $this->downloadAs($filename, $data);
    }

    /**
     * Create an invoice download response with a specific filename.
     *
     * @param  string  $filename
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadAs($filename, array $data = [])
    {
        return new Response($this->pdf($data), 200, [
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
            'X-Vapor-Base64-Encode' => 'True',
        ]);
    }

    /**
     * Get the Stripe model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the Stripe invoice instance.
     *
     * @return \Stripe\Invoice
     */
    public function asStripeInvoice()
    {
        return $this->invoice;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripeInvoice()->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the Stripe object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->invoice->{$key};
    }
}
