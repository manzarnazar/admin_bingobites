@php
    $mode = $mode ?? 'email';
    $brandRed = $brand_red ?? '#E31E24';
    $black = '#000000';
    $lh = '1.15';
@endphp

@if ($mode === 'email')
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
        @foreach ($line_items as $item)
            <tr>
                <td style="padding:10px 0;border-bottom:1px solid #EEEEEE;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">
                    {{ $item['email_label'] }}
                </td>
                <td align="right" style="padding:10px 0;border-bottom:1px solid #EEEEEE;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};white-space:nowrap;line-height:{{ $lh }};">
                    {{ \App\CentralLogics\Helpers::set_symbol($item['line_price'] + $item['addon_cost']) }}
                </td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin-top:16px;">
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">Subtotal</td>
            <td align="right" width="100" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">{{ $totals['subtotal_formatted'] }}</td>
        </tr>
        @if ($totals['addons'] > 0)
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">Add-ons</td>
            <td align="right" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">{{ $totals['addons_formatted'] }}</td>
        </tr>
        @endif
        @if ($totals['discount'] > 0)
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">Discount</td>
            <td align="right" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $brandRed }};line-height:{{ $lh }};">-{{ $totals['discount_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">GST (Included)</td>
            <td align="right" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">{{ $totals['gst_formatted'] }}</td>
        </tr>
        @if ($totals['delivery_fee'] > 0)
        <tr>
            <td align="right" style="padding:4px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">Delivery Fee</td>
            <td align="right" style="padding:4px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">{{ $totals['delivery_fee_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td align="right" style="padding:12px 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:{{ $black }};line-height:{{ $lh }};">Total Paid</td>
            <td align="right" style="padding:12px 0 4px 16px;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:bold;color:{{ $brandRed }};line-height:{{ $lh }};">{{ $totals['total_paid_formatted'] }}</td>
        </tr>
    </table>
@else
    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
        <thead>
            <tr style="background-color:#000000;">
                <th align="left" width="8%" style="font-size:10px;font-weight:bold;padding:11px 10px;text-transform:uppercase;color:#FFFFFF;background-color:#000000;">Qty</th>
                <th align="left" width="32%" style="font-size:10px;font-weight:bold;padding:11px 10px;text-transform:uppercase;color:#FFFFFF;background-color:#000000;">Item</th>
                <th align="left" width="40%" style="font-size:10px;font-weight:bold;padding:11px 10px;text-transform:uppercase;color:#FFFFFF;background-color:#000000;">Variation / Add-ons</th>
                <th align="right" width="20%" style="font-size:10px;font-weight:bold;padding:11px 10px;text-transform:uppercase;color:#FFFFFF;background-color:#000000;">Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($line_items as $index => $item)
                <tr style="background-color:{{ $index % 2 === 0 ? '#FFFFFF' : '#FAFAFA' }};">
                    <td valign="top" style="font-size:11px;padding:11px 10px;border-bottom:1px solid #EEEEEE;color:{{ $black }};line-height:{{ $lh }};">{{ $item['quantity'] }}</td>
                    <td valign="top" style="font-size:11px;padding:11px 10px;border-bottom:1px solid #EEEEEE;font-weight:bold;color:{{ $black }};line-height:{{ $lh }};">{{ $item['display_name'] ?? $item['name'] }}</td>
                    <td valign="top" style="font-size:10px;padding:11px 10px;border-bottom:1px solid #EEEEEE;color:{{ $black }};line-height:{{ $lh }};">{{ $item['display_detail'] ?: '-' }}</td>
                    <td valign="top" align="right" style="font-size:11px;padding:11px 10px;border-bottom:1px solid #EEEEEE;color:{{ $black }};white-space:nowrap;line-height:{{ $lh }};">{{ \App\CentralLogics\Helpers::set_symbol($item['line_price'] + $item['addon_cost']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin-top:14px;">
        <tr>
            <td width="58%"></td>
            <td width="22%" style="font-size:11px;color:{{ $black }};padding:5px 10px 5px 0;line-height:{{ $lh }};">Subtotal</td>
            <td width="20%" align="right" style="font-size:11px;color:{{ $black }};padding:5px 0;white-space:nowrap;line-height:{{ $lh }};">{{ $totals['subtotal_formatted'] }}</td>
        </tr>
        @if ($totals['addons'] > 0)
        <tr>
            <td></td>
            <td style="font-size:11px;color:{{ $black }};padding:5px 10px 5px 0;line-height:{{ $lh }};">Add-ons</td>
            <td align="right" style="font-size:11px;color:{{ $black }};padding:5px 0;line-height:{{ $lh }};">{{ $totals['addons_formatted'] }}</td>
        </tr>
        @endif
        @if ($totals['discount'] > 0)
        <tr>
            <td></td>
            <td style="font-size:11px;color:{{ $black }};padding:5px 10px 5px 0;line-height:{{ $lh }};">Discount</td>
            <td align="right" style="font-size:11px;color:{{ $brandRed }};padding:5px 0;">-{{ $totals['discount_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td></td>
            <td style="font-size:11px;color:{{ $black }};padding:5px 10px 5px 0;line-height:{{ $lh }};">GST (Included)</td>
            <td align="right" style="font-size:11px;color:{{ $black }};padding:5px 0;line-height:{{ $lh }};">{{ $totals['gst_formatted'] }}</td>
        </tr>
        @if ($totals['delivery_fee'] > 0)
        <tr>
            <td></td>
            <td style="font-size:11px;color:{{ $black }};padding:5px 10px 5px 0;line-height:{{ $lh }};">Delivery Fee</td>
            <td align="right" style="font-size:11px;color:{{ $black }};padding:5px 0;line-height:{{ $lh }};">{{ $totals['delivery_fee_formatted'] }}</td>
        </tr>
        @endif
        <tr>
            <td></td>
            <td colspan="2" style="padding-top:10px;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#F3F3F3;border-radius:6px;">
                    <tr>
                        <td style="font-size:12px;font-weight:bold;color:{{ $black }};padding:12px 14px;text-transform:uppercase;line-height:{{ $lh }};">Total Paid</td>
                        <td align="right" style="font-size:16px;font-weight:bold;color:{{ $brandRed }};padding:12px 14px;white-space:nowrap;">{{ $totals['total_paid_formatted'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endif
