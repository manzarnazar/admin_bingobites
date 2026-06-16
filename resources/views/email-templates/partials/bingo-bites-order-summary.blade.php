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
    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
        <thead>
            <tr style="background-color:#000000;color:#FFFFFF;">
                <th align="left" width="8%" style="font-size:10px;font-weight:bold;padding:11px 10px;text-transform:uppercase;">Qty</th>
                <th align="left" width="32%" style="font-size:10px;font-weight:bold;padding:11px 10px;text-transform:uppercase;">Item</th>
                <th align="left" width="40%" style="font-size:10px;font-weight:bold;padding:11px 10px;text-transform:uppercase;">Variation / Add-ons</th>
                <th align="right" width="20%" style="font-size:10px;font-weight:bold;padding:11px 10px;text-transform:uppercase;">Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($line_items as $index => $item)
                <tr style="background-color:{{ $index % 2 === 0 ? '#FFFFFF' : '#FAFAFA' }};">
                    <td valign="top" style="font-size:11px;padding:11px 10px;border-bottom:1px solid #EEEEEE;color:#333333;">{{ $item['quantity'] }}</td>
                    <td valign="top" style="font-size:11px;padding:11px 10px;border-bottom:1px solid #EEEEEE;font-weight:bold;color:#222222;">{{ $item['display_name'] ?? $item['name'] }}</td>
                    <td valign="top" style="font-size:10px;padding:11px 10px;border-bottom:1px solid #EEEEEE;color:#666666;">{{ $item['display_detail'] ?: '-' }}</td>
                    <td valign="top" align="right" style="font-size:11px;padding:11px 10px;border-bottom:1px solid #EEEEEE;color:#222222;white-space:nowrap;">{{ \App\CentralLogics\Helpers::set_symbol($item['line_price'] + $item['addon_cost']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin-top:14px;">
        <tr>
            <td width="58%"></td>
            <td width="22%" style="font-size:11px;color:#666666;padding:5px 10px 5px 0;">Subtotal</td>
            <td width="20%" align="right" style="font-size:11px;color:#222222;padding:5px 0;white-space:nowrap;">{{ $totals['subtotal_formatted'] }}</td>
        </tr>
        @if ($totals['addons'] > 0)
        <tr>
            <td></td>
            <td style="font-size:11px;color:#666666;padding:5px 10px 5px 0;">Add-ons</td>
            <td align="right" style="font-size:11px;color:#222222;padding:5px 0;">{{ $totals['addons_formatted'] }}</td>
        </tr>
        @endif
        @if ($totals['discount'] > 0)
        <tr>
            <td></td>
            <td style="font-size:11px;color:#666666;padding:5px 10px 5px 0;">Discount</td>
            <td align="right" style="font-size:11px;color:{{ $brandRed }};padding:5px 0;">-{{ $totals['discount_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td></td>
            <td style="font-size:11px;color:#666666;padding:5px 10px 5px 0;">GST (Included)</td>
            <td align="right" style="font-size:11px;color:#222222;padding:5px 0;">{{ $totals['gst_formatted'] }}</td>
        </tr>
        @if ($totals['delivery_fee'] > 0)
        <tr>
            <td></td>
            <td style="font-size:11px;color:#666666;padding:5px 10px 5px 0;">Delivery Fee</td>
            <td align="right" style="font-size:11px;color:#222222;padding:5px 0;">{{ $totals['delivery_fee_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td></td>
            <td colspan="2" style="padding-top:10px;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#F3F3F3;border-radius:6px;">
                    <tr>
                        <td style="font-size:12px;font-weight:bold;color:#222222;padding:12px 14px;text-transform:uppercase;">Total Paid</td>
                        <td align="right" style="font-size:16px;font-weight:bold;color:{{ $brandRed }};padding:12px 14px;white-space:nowrap;">{{ $totals['total_paid_formatted'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endif
