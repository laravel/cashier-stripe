<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Invoice</title>

    <style>
        body {
            background: #fff none;
            font-family: DejaVu Sans, 'sans-serif';
            font-size: 12px;
        }

        h2 {
            font-size: 28px;
            color: #ccc;
        }

        .container {
            padding-top: 30px;
        }

        .invoice-head td {
            padding: 0 8px;
        }

        .table th {
            vertical-align: bottom;
            font-weight: bold;
            padding: 8px;
            line-height: 14px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table tr.row td {
            border-bottom: 1px solid #ddd;
        }

        .table td {
            padding: 8px;
            line-height: 14px;
            text-align: left;
            vertical-align: top;
        }
    </style>
</head>
<body>

<div class="container">
    <table style="margin-left: auto; margin-right: auto;" width="550">
        <tr>
            <td width="160">
                &nbsp;
            </td>

            <!-- Account Name / Header Image -->
            <td align="right">
                <strong>{{ $header ?? $vendor ?? $invoice->account_name }}</strong>
            </td>
        </tr>
        <tr valign="top">
            <td style="font-size:9px;">
                <span style="font-size: 28px; color: #ccc;">
                    Receipt
                </span><br><br>

                <!-- Account Details -->
                {{ $vendor ?? $invoice->account_name }}<br>

                @isset($street)
                    {{ $street }}<br>
                @endisset

                @isset($location)
                    {{ $location }}<br>
                @endisset

                @isset($phone)
                    {{ $phone }}<br>
                @endisset

                @isset($email)
                    {{ $email }}<br>
                @endisset

                @isset($url)
                    <a href="{{ $url }}">{{ $url }}</a><br>
                @endisset

                @isset($vendorVat)
                    {{ $vendorVat }}<br>
                @else
                    @foreach ($invoice->accountTaxIds() as $taxId)
                        {{ $taxId->value }}<br>
                    @endforeach
                @endisset

                <br><br>

                <!-- Customer Details -->
                <strong>Bill to:</strong><br>

                {{ $invoice->customer_name ?? $invoice->customer_email }}<br>

                @if ($address = $invoice->customer_address)
                    @if ($address->line1)
                        {{ $address->line1 }}<br>
                    @endif

                    @if ($address->line2)
                        {{ $address->line2 }}<br>
                    @endif

                    @if ($address->city)
                        {{ $address->city }}<br>
                    @endif

                    @if ($address->state || $address->postal_code)
                        {{ implode(' ', [$address->state, $address->postal_code]) }}<br>
                    @endif

                    @if ($address->country)
                        {{ $address->country }}<br>
                    @endif
                @endif

                @if ($invoice->customer_phone)
                    {{ $invoice->customer_phone }}<br>
                @endif

                @if ($invoice->customer_name)
                    {{ $invoice->customer_email }}<br>
                @endif

                @foreach ($invoice->customerTaxIds() as $taxId)
                    {{ $taxId->value }}<br>
                @endforeach
            </td>
            <td>
                <!-- Invoice Info -->
                <p>
                    @isset ($product)
                        <strong>Product:</strong> {{ $product }}<br>
                    @endisset

                    <strong>Date:</strong> {{ $invoice->date()->toFormattedDateString() }}<br>

                    @if ($dueDate = $invoice->dueDate())
                        <strong>Due date:</strong> {{ $dueDate->toFormattedDateString() }}<br>
                    @endif

                    <strong>Invoice Number:</strong> {{ $id ?? $invoice->number }}<br>
                </p>

                <!-- Memo / Description -->
                @if ($invoice->description)
                    <p>
                        {{ $invoice->description }}
                    </p>
                @endif

                <!-- Extra / VAT Information -->
                @if (isset($vat))
                    <p>
                        {{ $vat }}
                    </p>
                @endif

                <br><br>

                <!-- Invoice Table -->
                <table width="100%" class="table" border="0">
                    <tr>
                        <th align="left">Description</th>
                        <th align="right">Date</th>

                        @if ($invoice->hasTax())
                            <th align="right">Tax</th>
                        @endif

                        <th align="right">Amount</th>
                    </tr>

                    <!-- Display The Invoice Items -->
                    @foreach ($invoice->invoiceItems() as $item)
                        <tr class="row">
                            <td colspan="2">{{ $item->description }}</td>

                            @if ($invoice->hasTax())
                                <td>
                                    @if ($inclusiveTaxPercentage = $item->inclusiveTaxPercentage())
                                        {{ $inclusiveTaxPercentage }}% incl.
                                    @endif

                                    @if ($item->hasBothInclusiveAndExclusiveTax())
                                        +
                                    @endif

                                    @if ($exclusiveTaxPercentage = $item->exclusiveTaxPercentage())
                                        {{ $exclusiveTaxPercentage }}%
                                    @endif
                                </td>
                            @endif

                            <td>{{ $item->total() }}</td>
                        </tr>
                    @endforeach

                    <!-- Display The Subscriptions -->
                    @foreach ($invoice->subscriptions() as $subscription)
                        <tr class="row">
                            <td>{{ $subscription->description }}</td>
                            <td>
                                {{ $subscription->startDateAsCarbon()->toFormattedDateString() }} -
                                {{ $subscription->endDateAsCarbon()->toFormattedDateString() }}
                            </td>

                            @if ($invoice->hasTax())
                                <td>
                                    @if ($inclusiveTaxPercentage = $subscription->inclusiveTaxPercentage())
                                        {{ $inclusiveTaxPercentage }}% incl.
                                    @endif

                                    @if ($subscription->hasBothInclusiveAndExclusiveTax())
                                        +
                                    @endif

                                    @if ($exclusiveTaxPercentage = $subscription->exclusiveTaxPercentage())
                                        {{ $exclusiveTaxPercentage }}%
                                    @endif
                                </td>
                            @endif

                            <td>{{ $subscription->total() }}</td>
                        </tr>
                    @endforeach

                    <!-- Display The Subtotal -->
                    @if ($invoice->hasDiscount() || $invoice->hasTax() || $invoice->hasStartingBalance())
                        <tr>
                            <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" style="text-align: right;">Subtotal</td>
                            <td>{{ $invoice->subtotal() }}</td>
                        </tr>
                    @endif

                    <!-- Display The Discount -->
                    @if ($invoice->hasDiscount())
                        @foreach ($invoice->discounts() as $discount)
                            @php($coupon = $discount->coupon())

                            <tr>
                                <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" style="text-align: right;">
                                    @if ($coupon->isPercentage())
                                        {{ $coupon->name() }} ({{ $coupon->percentOff() }}% Off)
                                    @else
                                        {{ $coupon->name() }} ({{ $coupon->amountOff() }} Off)
                                    @endif
                                </td>

                                <td>-{{ $invoice->discountFor($discount) }}</td>
                            </tr>
                        @endforeach
                    @endif

                    <!-- Display The Taxes -->
                    @unless ($invoice->isNotTaxExempt())
                        <tr>
                            <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" style="text-align: right;">
                                @if ($invoice->isTaxExempt())
                                    Tax is exempted
                                @else
                                    Tax to be paid on reverse charge basis
                                @endif
                            </td>
                            <td></td>
                        </tr>
                    @else
                        @foreach ($invoice->taxes() as $tax)
                            <tr>
                                <td colspan="3" style="text-align: right;">
                                    {{ $tax->display_name }} {{ $tax->jurisdiction ? ' - '.$tax->jurisdiction : '' }}
                                    ({{ $tax->percentage }}%{{ $tax->isInclusive() ? ' incl.' : '' }})
                                </td>
                                <td>{{ $tax->amount() }}</td>
                            </tr>
                        @endforeach
                    @endunless

                    <!-- Display The Final Total -->
                    <tr>
                        <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" style="text-align: right;">
                            Total
                        </td>
                        <td>
                            {{ $invoice->realTotal() }}
                        </td>
                    </tr>

                    <!-- Applied Balance -->
                    @if ($invoice->hasEndingBalance())
                        <tr>
                            <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" style="text-align: right;">
                                Applied balance
                            </td>
                            <td>{{ $invoice->appliedBalance() }}</td>
                        </tr>
                    @endif

                    <!-- Display The Amount Due -->
                    <tr>
                        <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" style="text-align: right;">
                            <strong>Amount due</strong>
                        </td>
                        <td>
                            <strong>{{ $invoice->amountDue() }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
