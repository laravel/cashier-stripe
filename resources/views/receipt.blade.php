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
            line-height: 20px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table tr.row td {
            border-bottom: 1px solid #ddd;
        }

        .table td {
            padding: 8px;
            line-height: 20px;
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

            <!-- Organization Name / Image -->
            <td align="right">
                <strong>{{ $header ?? $vendor }}</strong>
            </td>
        </tr>
        <tr valign="top">
            <td style="font-size: 28px; color: #ccc;">
                Receipt
            </td>

            <!-- Organization Name / Date -->
            <td>
                <br><br>
                <strong>To:</strong> {{ $owner->stripeEmail() ?: $owner->name }}
                <br>
                <strong>Date:</strong> {{ $invoice->date()->toFormattedDateString() }}
            </td>
        </tr>
        <tr valign="top">
            <!-- Organization Details -->
            <td style="font-size:9px;">
                {{ $vendor }}<br>

                @if (isset($street))
                    {{ $street }}<br>
                @endif

                @if (isset($location))
                    {{ $location }}<br>
                @endif

                @if (isset($phone))
                    <strong>T</strong> {{ $phone }}<br>
                @endif

                @if (isset($vendorVat))
                    {{ $vendorVat }}<br>
                @endif

                @if (isset($url))
                    <a href="{{ $url }}">{{ $url }}</a>
                @endif
            </td>
            <td>
                <!-- Invoice Info -->
                <p>
                    <strong>Product:</strong> {{ $product }}<br>
                    <strong>Invoice Number:</strong> {{ $id ?? $invoice->number }}<br>
                </p>

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
                                {{ $subscription->startDateAsCarbon()->formatLocalized('%B %e, %Y') }} -
                                {{ $subscription->endDateAsCarbon()->formatLocalized('%B %e, %Y') }}
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
                        <tr>
                            <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" style="text-align: right;">
                                @if ($invoice->discountIsPercentage())
                                    {{ $invoice->coupon() }} ({{ $invoice->percentOff() }}% Off)
                                @else
                                    {{ $invoice->coupon() }} ({{ $invoice->amountOff() }} Off)
                                @endif
                            </td>

                            <td>-{{ $invoice->discount() }}</td>
                        </tr>
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

                    <!-- Starting Balance -->
                    @if ($invoice->hasStartingBalance())
                        <tr>
                            <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" style="text-align: right;">
                                Customer Balance
                            </td>
                            <td>{{ $invoice->startingBalance() }}</td>
                        </tr>
                    @endif

                    <!-- Display The Final Total -->
                    <tr>
                        <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" style="text-align: right;">
                            <strong>Total</strong>
                        </td>
                        <td>
                            <strong>{{ $invoice->total() }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
