<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice #{{ $order->id }}</title>
    <style>
        body {
            margin: 0;
            padding: 0 0 20px;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333333;
        }
        .section-divider td {
            border-top: 1px solid #E5E5E5;
            font-size: 0;
            line-height: 0;
            padding: 0;
            height: 1px;
        }
    </style>
</head>
<body>
@php
    $red = $brand_red ?? '#E31E24';
    $grey = '#888888';
    $lh = '1.15';
    $headerFile = $header_path ?? public_path('assets/email/bingo-bites/header.png');
    $logoFile = $logo_path ?? public_path('assets/email/bingo-bites/logo.png');
    $iconCustomer = $icons['customer'] ?? public_path('assets/email/bingo-bites/icons/customer.png');
    $iconStore = $icons['store'] ?? public_path('assets/email/bingo-bites/icons/store.png');
    $iconLocation = $icons['location'] ?? public_path('assets/email/bingo-bites/icons/location.png');
    $invoiceDate = $order_date_long ?? $order_date;
    $invoiceTime = $order_time_pdf ?? $order_time;
@endphp

{{-- Header banner --}}
<table width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="padding:0;line-height:0;">
            <img src="{{ $headerFile }}" width="100%" style="width:100%;display:block;">
        </td>
    </tr>
</table>

{{-- Title --}}
<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:18px;">
    <tr>
        <td align="center" style="font-size:20px;font-weight:bold;color:#222222;letter-spacing:1px;padding-bottom:14px;line-height:{{ $lh }};">TAX INVOICE</td>
    </tr>
</table>

{{-- Invoice meta --}}
<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:6px;">
    <tr>
        <td width="55%" valign="top" style="font-size:11px;line-height:{{ $lh }};">
            <span style="color:{{ $grey }};">Invoice Number:</span>
            <span style="font-weight:bold;color:#222222;"> INV-{{ $order->id }}</span><br>
            <span style="color:{{ $grey }};">Order Number:</span>
            <span style="font-weight:bold;color:{{ $red }};"> #{{ $order->id }}</span><br>
            <span style="color:{{ $grey }};">Order Type:</span>
            <span style="color:#222222;"> {{ $order_type_label }}</span>
        </td>
        <td width="45%" valign="top" align="right" style="font-size:11px;line-height:{{ $lh }};">
            <span style="color:{{ $grey }};">Date:</span>
            <span style="color:#222222;"> {{ $invoiceDate }}</span><br>
            <span style="color:{{ $grey }};">Time:</span>
            <span style="color:#222222;"> {{ $invoiceTime }}</span>
        </td>
    </tr>
</table>

<table width="100%" cellspacing="0" cellpadding="0" border="0" class="section-divider" style="margin:14px 0;"><tr><td>&nbsp;</td></tr></table>

{{-- Customer & Store (top border only) --}}
<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:14px 0 18px;border-top:1px solid #E8E8E8;">
    <tr>
        <td width="50%" valign="top" style="padding:18px 20px 18px 0;">
            <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td style="font-size:10px;font-weight:bold;color:{{ $red }};text-transform:uppercase;padding-bottom:12px;line-height:{{ $lh }};">
                        <img src="{{ $iconCustomer }}" width="14" height="14" style="vertical-align:middle;margin-right:6px;"> Customer Details
                    </td>
                </tr>
                <tr>
                    <td style="font-size:12px;font-weight:bold;color:#222222;padding-bottom:8px;line-height:{{ $lh }};">{{ $customer['name'] }}</td>
                </tr>
                @if ($customer['email'])
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};padding-bottom:8px;line-height:{{ $lh }};">{{ $customer['email'] }}</td>
                </tr>
                @endif
                @if ($customer['phone'])
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};line-height:{{ $lh }};">{{ $customer['phone'] }}</td>
                </tr>
                @endif
            </table>
        </td>
        <td width="50%" valign="top" style="padding:18px 0 18px 20px;">
            <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td style="font-size:10px;font-weight:bold;color:{{ $red }};text-transform:uppercase;padding-bottom:12px;line-height:{{ $lh }};">
                        <img src="{{ $iconStore }}" width="14" height="14" style="vertical-align:middle;margin-right:6px;"> Store Details
                    </td>
                </tr>
                <tr>
                    <td style="font-size:12px;font-weight:bold;color:#222222;padding-bottom:8px;line-height:{{ $lh }};">{{ $store['name'] }}</td>
                </tr>
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};padding-bottom:8px;line-height:{{ $lh }};">
                        <img src="{{ $iconLocation }}" width="12" height="12" style="vertical-align:middle;margin-right:6px;"> {{ $store['address'] }}
                    </td>
                </tr>
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};padding-bottom:8px;line-height:{{ $lh }};">{{ $store['phone'] }}</td>
                </tr>
                @if ($store['email'])
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};line-height:{{ $lh }};">{{ $store['email'] }}</td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

@include('email-templates.partials.bingo-bites-order-summary', ['mode' => 'pdf'])

<table width="100%" cellspacing="0" cellpadding="0" border="0" class="section-divider" style="margin:14px 0;"><tr><td>&nbsp;</td></tr></table>

{{-- Footer --}}
<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;">
    <tr>
        <td width="14%" valign="top" style="padding-right:4px;">
            <img src="{{ $logoFile }}" width="72" style="width:72px;display:block;">
        </td>
        <td width="56%" valign="top" style="padding-left:4px;">
            <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:#222222;padding-bottom:6px;line-height:{{ $lh }};">{{ $store['name'] }}</td>
                </tr>
                <tr>
                    <td style="font-size:9px;color:{{ $grey }};padding-bottom:4px;line-height:{{ $lh }};">
                        <img src="{{ $iconLocation }}" width="11" height="11" style="vertical-align:middle;margin-right:5px;"> {{ $store['address'] }}
                    </td>
                </tr>
                <tr>
                    <td style="font-size:9px;color:{{ $grey }};padding-bottom:4px;line-height:{{ $lh }};">{{ $store['phone'] }}</td>
                </tr>
                @if ($store['email'])
                <tr>
                    <td style="font-size:9px;color:{{ $grey }};padding-bottom:4px;line-height:{{ $lh }};">{{ $store['email'] }}</td>
                </tr>
                @endif
                <tr>
                    <td style="font-size:9px;color:{{ $grey }};line-height:{{ $lh }};">{{ $store['website'] }}</td>
                </tr>
            </table>
        </td>
        <td width="30%" valign="middle" align="right">
            <div style="font-size:13px;font-weight:bold;color:{{ $red }};line-height:{{ $lh }};">Every Bite is a Winner</div>
        </td>
    </tr>
</table>

<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:22px;">
    <tr>
        <td align="center" style="font-size:8px;color:#AAAAAA;padding-top:10px;border-top:1px solid #E5E5E5;line-height:{{ $lh }};">
            This is a computer generated tax invoice and does not require a signature.
        </td>
    </tr>
</table>

</body>
</html>
