<?php

namespace Laravel\Cashier\Invoices;

use Laravel\Cashier\Contracts\InvoiceRenderer;
use Laravel\Cashier\Invoice;
use Spatie\Browsershot\Browsershot;

class BrowsershotInvoiceRenderer implements InvoiceRenderer
{
    /**
     * {@inheritDoc}
     */
    public function render(Invoice $invoice, array $data = [], array $options = []): string
    {
        $browsershot = Browsershot::html($invoice->view($data)->render());

        if ($options['paper'] ?? false) {
            $browsershot->format($options['paper']);
        }

        return $browsershot->pdf();
    }
}
