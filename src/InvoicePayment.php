<?php

namespace Laravel\Cashier;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Stripe\InvoicePayment as StripeInvoicePayment;

class InvoicePayment implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Create a new InvoicePayment instance.
     *
     * @param  \Stripe\InvoicePayment  $invoicePayment
     * @return void
     */
    public function __construct(protected StripeInvoicePayment $invoicePayment)
    {
        //
    }

    /**
     * Get the allocated amount.
     *
     * @return string
     */
    public function amount(): string
    {
        return Cashier::formatAmount($this->rawAmount(), $this->currency());
    }

    /**
     * Get the raw allocated amount.
     *
     * @return int
     */
    public function rawAmount(): int
    {
        return $this->invoicePayment->amount_paid ?? $this->invoicePayment->amount_requested;
    }

    /**
     * Get the currency of the payment.
     *
     * @return string
     */
    public function currency(): string
    {
        return $this->invoicePayment->currency;
    }

    /**
     * Get the payment status.
     *
     * @return string
     */
    public function status(): string
    {
        return $this->invoicePayment->status;
    }

    /**
     * Determine if the payment is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->invoicePayment->status === 'paid';
    }

    /**
     * Get the Stripe InvoicePayment instance.
     *
     * @return \Stripe\InvoicePayment
     */
    public function asStripeInvoicePayment()
    {
        return $this->invoicePayment;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripeInvoicePayment()->toArray();
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
        return $this->invoicePayment->{$key};
    }
}
