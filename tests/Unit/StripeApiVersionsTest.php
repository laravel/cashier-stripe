<?php

namespace Laravel\Cashier\Tests\Unit;

use Laravel\Cashier\Enums\StripeApiVersions;
use PHPUnit\Framework\TestCase;

class StripeApiVersionsTest extends TestCase
{
    public function test_dahlia_transforms_ui_modes()
    {
        $this->assertSame('hosted_page', StripeApiVersions::DAHLIA->transformUiMode('hosted'));
        $this->assertSame('embedded_page', StripeApiVersions::DAHLIA->transformUiMode('embedded'));
        $this->assertSame('elements', StripeApiVersions::DAHLIA->transformUiMode('custom'));
        $this->assertSame('embedded_page', StripeApiVersions::DAHLIA->transformUiMode('embedded_page'));
    }
}
