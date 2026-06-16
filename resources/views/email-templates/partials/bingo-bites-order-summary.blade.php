@php
    $mode = $mode ?? 'email';
    $brandRed = $brand_red ?? '#E31E24';
@endphp

@if ($mode === 'email')
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
        @foreach ($line_items as $item)
            <tr>
                <td style="padding:10px 0;border-bottom:1px solid #EEEEEE;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333333;">
                    {{ $item['email_label'] }}
                </td>
                <td align="right" style="padding:10px 0;border-bottom:1px solid #EEEEEE;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333333;white-space:nowrap;">
                    {{ \App\CentralLogics\Helpers::set_symbol($item['line_price'] + $item['addon_cost']) }}
                </td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin-top:16px;">
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#666666;">Subtotal</td>
            <td align="right" width="100" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333333;">{{ $totals['subtotal_formatted'] }}</td>
        </tr>
        @if ($totals['addons'] > 0)
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#666666;">Add-ons</td>
            <td align="right" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333333;">{{ $totals['addons_formatted'] }}</td>
        </tr>
        @endif
        @if ($totals['discount'] > 0)
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#666666;">Discount</td>
            <td align="right" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $brandRed }};">-{{ $totals['discount_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#666666;">GST (Included)</td>
            <td align="right" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333333;">{{ $totals['gst_formatted'] }}</td>
        </tr>
        @if ($totals['delivery_fee'] > 0)
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#666666;">Delivery Fee</td>
            <td align="right" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333333;">{{ $totals['delivery_fee_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td align="right" style="padding:12px 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#333333;">Total Paid</td>
            <td align="right" style="padding:12px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:bold;color:{{ $brandRed }};">{{ $totals['total_paid_formatted'] }}</td>
        </tr>
    </table>
@else
    <table width="100%" cellspacing="0" cellpadding="8" border="0" style="border-collapse:collapse;margin-top:12px;">
        <thead>
            <tr style="background-color:#000000;color:#FFFFFF;">
                <th align="left" style="font-size:11px;font-weight:bold;padding:10px 8px;">QTY</th>
                <th align="left" style="font-size:11px;font-weight:bold;padding:10px 8px;">ITEM</th>
                <th align="left" style="font-size:11px;font-weight:bold;padding:10px 8px;">VARIATION / ADD-ONS</th>
                <th align="right" style="font-size:11px;font-weight:bold;padding:10px 8px;">PRICE</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($line_items as $index => $item)
                <tr style="background-color:{{ $index % 2 === 0 ? '#FFFFFF' : '#F8F8F8' }};">
                    <td valign="top" style="font-size:12px;padding:10px 8px;border-bottom:1px solid #EEEEEE;">{{ $item['quantity'] }}</td>
                    <td valign="top" style="font-size:12px;padding:10px 8px;border-bottom:1px solid #EEEEEE;font-weight:bold;">{{ $item['name'] }}</td>
                    <td valign="top" style="font-size:11px;padding:10px 8px;border-bottom:1px solid #EEEEEE;color:#666666;">{{ $item['display_detail'] ?: '-' }}</td>
                    <td valign="top" align="right" style="font-size:12px;padding:10px 8px;border-bottom:1px solid #EEEEEE;white-space:nowrap;">{{ \App\CentralLogics\Helpers::set_symbol($item['line_price'] + $item['addon_cost']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="4" border="0" style="border-collapse:collapse;margin-top:16px;">
        <tr>
            <td width="55%"></td>
            <td style="font-size:12px;color:#666666;padding:4px 8px;">Subtotal</td>
            <td align="right" style="font-size:12px;padding:4px 8px;white-space:nowrap;">{{ $totals['subtotal_formatted'] }}</td>
        </tr>
        @if ($totals['addons'] > 0)
        <tr>
            <td></td>
            <td style="font-size:12px;color:#666666;padding:4px 8px;">Add-ons</td>
            <td align="right" style="font-size:12px;padding:4px 8px;">{{ $totals['addons_formatted'] }}</td>
        </tr>
        @endif
        @if ($totals['discount'] > 0)
        <tr>
            <td></td>
            <td style="font-size:12px;color:#666666;padding:4px 8px;">Discount</td>
            <td align="right" style="font-size:12px;padding:4px 8px;color:{{ $brandRed }};">-{{ $totals['discount_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td></td>
            <td style="font-size:12px;color:#666666;padding:4px 8px;">GST (Included)</td>
            <td align="right" style="font-size:12px;padding:4px 8px;">{{ $totals['gst_formatted'] }}</td>
        </tr>
        @if ($totals['delivery_fee'] > 0)
        <tr>
            <td></td>
            <td style="font-size:12px;color:#666666;padding:4px 8px;">Delivery Fee</td>
            <td align="right" style="font-size:12px;padding:4px 8px;">{{ $totals['delivery_fee_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td></td>
            <td style="font-size:14px;font-weight:bold;padding:10px 8px 4px;">Total Paid</td>
            <td align="right" style="font-size:16px;font-weight:bold;padding:10px 8px 4px;color:{{ $brandRed }};">{{ $totals['total_paid_formatted'] }}</td>
        </tr>
    </table>
@endif
