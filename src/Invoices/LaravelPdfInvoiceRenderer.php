<?php

namespace Laravel\Cashier\Invoices;

use Illuminate\Http\Request;
use Laravel\Cashier\Contracts\InvoiceRenderer;
use Laravel\Cashier\Invoice;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;

class LaravelPdfInvoiceRenderer implements InvoiceRenderer
{
    /**
     * {@inheritDoc}
     */
    public function render(Invoice $invoice, array $data = [], array $options = []): string
    {
        if (! class_exists(Pdf::class)) {
            throw new RuntimeException('Please install spatie/laravel-pdf to use the LaravelPdfInvoiceRenderer.');
        }

        $paper = strtolower((string) ($options['paper'] ?? 'letter'));

        $response = Pdf::html($this->renderInvoice($invoice, $data))
            ->format($paper)
            ->toResponse(Request::create('/'));

        return (string) $response->getContent();
    }

    /**
     * Render the invoice view to HTML.
     */
    protected function renderInvoice(Invoice $invoice, array $data): string
    {
        $view = $invoice->view($data);

        if (method_exists($view, 'render')) {
            return $view->render();
        }

        return (string) $view;
    }
}
