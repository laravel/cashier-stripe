<?php

namespace Laravel\Cashier\Contracts;

use Laravel\Cashier\Invoice;

interface InvoiceRenderer
{
    /**
     * Render the invoice as a PDF and return the raw bytes.
     */
    public function render(Invoice $invoice, array $data = [], array $options = []): string;
}
