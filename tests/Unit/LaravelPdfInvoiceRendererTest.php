<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Invoice;
use Laravel\Cashier\Invoices\LaravelPdfInvoiceRenderer;
use Laravel\Cashier\Tests\TestCase;
use Mockery as m;
use RuntimeException;

class LaravelPdfInvoiceRendererTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_it_throws_a_runtime_exception_when_laravel_pdf_is_not_installed()
    {
        $renderer = new class extends LaravelPdfInvoiceRenderer
        {
            protected static $laravelPdfFacade = 'Spatie\\LaravelPdf\\Facades\\PdfThatDoesNotExist';
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Please install spatie/laravel-pdf to use the LaravelPdfInvoiceRenderer.');

        $renderer->render(m::mock(Invoice::class));
    }

    public function test_it_renders_the_invoice_view_to_html()
    {
        $invoice = m::mock(Invoice::class);

        $invoice->shouldReceive('view')
            ->once()
            ->with(['key' => 'value'])
            ->andReturn(new class
            {
                public function render(): string
                {
                    return '<p>invoice</p>';
                }
            });

        $renderer = new LaravelPdfInvoiceRenderer;

        $method = new \ReflectionMethod(LaravelPdfInvoiceRenderer::class, 'renderInvoice');
        $method->setAccessible(true);

        $this->assertSame('<p>invoice</p>', $method->invoke($renderer, $invoice, ['key' => 'value']));
    }
}
