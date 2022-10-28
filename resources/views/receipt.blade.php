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

        .container {
            padding-top: 30px;
        }

        .table th {
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            padding: 8px 8px 8px 0;
            vertical-align: bottom;
        }

        .table tr.row td {
            border-bottom: 1px solid #ddd;
        }

        .table td {
            padding: 8px 8px 8px 0;
            vertical-align: top;
        }

        .table th:last-child,
        .table td:last-child {
            padding-right: 0;
        }

        .dates {
            color: #555;
            font-size: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <table style="margin-left: auto; margin-right: auto;" width="100%">
        <tr valign="top">
            <td width="160">
                <span style="font-size: 28px;">
                    Receipt
                </span>

                <!-- Invoice Info -->
                <p>
                    @isset ($product)
                        <strong>Product:</strong> {{ $product }}<br>
                    @endisset

                    <strong>Date:</strong> {{ $invoice->date()->toFormattedDateString() }}<br>

                    @if ($dueDate = $invoice->dueDate())
                        <strong>Due date:</strong> {{ $dueDate->toFormattedDateString() }}<br>
                    @endif

                    @if ($invoiceId = $id ?? $invoice->number)
                        <strong>Invoice Number:</strong> {{ $invoiceId }}<br>
                    @endif
                </p>
            </td>

            <!-- Account Name / Header Image -->
            <td align="right">
                <span style="font-size: 28px; color: #ccc;">
                    <strong>{{ $header ?? $vendor ?? $invoice->account_name }}</strong>
                </span>
            </td>
        </tr>
        <tr valign="top">
            <td width="50%">
                <!-- Account Details -->
                <strong>{{ $vendor ?? $invoice->account_name }}</strong><br>

                @isset($street)
                    {{ $street }}<br>
                @endisset

                @isset($location)
                    {{ $location }}<br>
                @endisset

                @isset($country)
                    {{ $country }}<br>
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
            </td>
            <td width="50%">
                <!-- Customer Details -->
                <strong>Recipient</strong><br>

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
        </tr>
        <tr valign="top">
            <td colspan="2">
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
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <!-- Invoice Table -->
                <table width="100%" class="table" border="0">
                    <tr>
                        <th align="left">Description</th>
                        <th align="left">Qty</th>
                        <th align="left">Unit price</th>

                        @if ($invoice->hasTax())
                            <th align="right">Tax</th>
                        @endif

                        <th align="right">Amount</th>
                    </tr>

                    <!-- Display The Invoice Line Items -->
                    @foreach ($invoice->invoiceLineItems() as $item)
                        <tr class="row">
                            <td>
                                {{ $item->description }}

                                @if ($item->hasPeriod() && ! $item->periodStartAndEndAreEqual())
                                    <br><span class="dates">
                                        {{ $item->startDate() }} - {{ $item->endDate() }}
                                    </span>
                                @endif
                            </td>

                            <td>{{ $item->quantity }}</td>
                            <td>{{ $item->unitAmountExcludingTax() }}</td>

                            @if ($invoice->hasTax())
                                <td align="right">
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

                            <td align="right">{{ $item->total() }}</td>
                        </tr>
                    @endforeach

                    <!-- Display The Subtotal -->
                    @if ($invoice->hasDiscount() || $invoice->hasTax() || $invoice->hasStartingBalance())
                        <tr>
                            <td></td>
                            <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}">Subtotal</td>
                            <td align="right">{{ $invoice->subtotal() }}</td>
                        </tr>
                    @endif

                    <!-- Display The Discount -->
                    @if ($invoice->hasDiscount())
                        @foreach ($invoice->discounts() as $discount)
                            @php($coupon = $discount->coupon())

                            <tr>
                                <td></td>
                                <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" align="right">
                                    @if ($coupon->isPercentage())
                                        {{ $coupon->name() }} ({{ $coupon->percentOff() }}% Off)
                                    @else
                                        {{ $coupon->name() }} ({{ $coupon->amountOff() }} Off)
                                    @endif
                                </td>

                                <td align="right">-{{ $invoice->discountFor($discount) }}</td>
                            </tr>
                        @endforeach
                    @endif

                    <!-- Display The Taxes -->
                    @unless ($invoice->isNotTaxExempt())
                        <tr>
                            <td></td>
                            <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}" align="right">
                                @if ($invoice->isTaxExempt())
                                    Tax is exempted
                                @else
                                    Tax to be paid on reverse charge basis
                                @endif
                            </td>
                            <td align="right"></td>
                        </tr>
                    @else
                        @foreach ($invoice->taxes() as $tax)
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    {{ $tax->display_name }} {{ $tax->jurisdiction ? ' - '.$tax->jurisdiction : '' }}
                                    ({{ $tax->percentage }}%{{ $tax->isInclusive() ? ' incl.' : '' }})
                                </td>
                                <td align="right">{{ $tax->amount() }}</td>
                            </tr>
                        @endforeach
                    @endunless

                    <!-- Display The Final Total -->
                    <tr>
                        <td></td>
                        <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}">
                            Total
                        </td>
                        <td align="right">
                            {{ $invoice->realTotal() }}
                        </td>
                    </tr>

                    <!-- Applied Balance -->
                    @if ($invoice->hasAppliedBalance())
                        <tr>
                            <td></td>
                            <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}">
                                Applied balance
                            </td>
                            <td align="right">{{ $invoice->appliedBalance() }}</td>
                        </tr>
                    @endif

                    <!-- Display The Amount Due -->
                    <tr>
                        <td></td>
                        <td colspan="{{ $invoice->hasTax() ? 3 : 2 }}">
                            <strong>Amount due</strong>
                        </td>
                        <td align="right">
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
