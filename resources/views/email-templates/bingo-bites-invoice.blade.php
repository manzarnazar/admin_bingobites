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
    $headerFile = $header_path ?? public_path('assets/email/bingo-bites/header.png');
    $logoFile = $logo_path ?? public_path('assets/email/bingo-bites/logo.png');
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
        <td align="center" style="font-size:20px;font-weight:bold;color:#222222;letter-spacing:1px;padding-bottom:14px;">TAX INVOICE</td>
    </tr>
</table>

{{-- Invoice meta --}}
<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:6px;">
    <tr>
        <td width="55%" valign="top" style="font-size:11px;line-height:1.9;">
            <span style="color:{{ $grey }};">Invoice Number:</span>
            <span style="font-weight:bold;color:#222222;"> INV-{{ $order->id }}</span><br>
            <span style="color:{{ $grey }};">Order Number:</span>
            <span style="font-weight:bold;color:{{ $red }};"> #{{ $order->id }}</span><br>
            <span style="color:{{ $grey }};">Order Type:</span>
            <span style="color:#222222;"> {{ $order_type_label }}</span>
        </td>
        <td width="45%" valign="top" align="right" style="font-size:11px;line-height:1.9;">
            <span style="color:{{ $grey }};">Date:</span>
            <span style="color:#222222;"> {{ $invoiceDate }}</span><br>
            <span style="color:{{ $grey }};">Time:</span>
            <span style="color:#222222;"> {{ $invoiceTime }}</span>
        </td>
    </tr>
</table>

<table width="100%" cellspacing="0" cellpadding="0" border="0" class="section-divider" style="margin:14px 0;"><tr><td>&nbsp;</td></tr></table>
<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:14px 0 18px;border:1px solid #E8E8E8;">
    <tr>
        <td width="50%" valign="top" style="padding:18px 20px;border-right:1px solid #E8E8E8;">
            <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td style="font-size:10px;font-weight:bold;color:{{ $red }};text-transform:uppercase;padding-bottom:14px;line-height:1.6;">
                        @include('email-templates.partials.bingo-bites-icon', ['name' => 'customer', 'mode' => 'pdf']) Customer Details
                    </td>
                </tr>
                <tr>
                    <td style="font-size:12px;font-weight:bold;color:#222222;padding-bottom:10px;line-height:1.8;">{{ $customer['name'] }}</td>
                </tr>
                @if ($customer['email'])
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};padding-bottom:10px;line-height:1.8;">{{ $customer['email'] }}</td>
                </tr>
                @endif
                @if ($customer['phone'])
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};line-height:1.8;padding-bottom:4px;">{{ $customer['phone'] }}</td>
                </tr>
                @endif
            </table>
        </td>
        <td width="50%" valign="top" style="padding:18px 20px;">
            <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td style="font-size:10px;font-weight:bold;color:{{ $red }};text-transform:uppercase;padding-bottom:14px;line-height:1.6;">
                        @include('email-templates.partials.bingo-bites-icon', ['name' => 'store', 'mode' => 'pdf']) Store Details
                    </td>
                </tr>
                <tr>
                    <td style="font-size:12px;font-weight:bold;color:#222222;padding-bottom:10px;line-height:1.8;">{{ $store['name'] }}</td>
                </tr>
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};padding-bottom:10px;line-height:1.8;">
                        @include('email-templates.partials.bingo-bites-icon', ['name' => 'location', 'mode' => 'pdf', 'size' => 12']) {{ $store['address'] }}
                    </td>
                </tr>
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};padding-bottom:10px;line-height:1.8;">{{ $store['phone'] }}</td>
                </tr>
                @if ($store['email'])
                <tr>
                    <td style="font-size:10px;color:{{ $grey }};line-height:1.8;padding-bottom:4px;">{{ $store['email'] }}</td>
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
            <div style="font-size:11px;font-weight:bold;color:#222222;margin-bottom:4px;">{{ $store['name'] }}</div>
            <div style="font-size:9px;color:{{ $grey }};line-height:1.9;">
                <div style="margin-bottom:4px;">@include('email-templates.partials.bingo-bites-icon', ['name' => 'location', 'mode' => 'pdf', 'size' => 11]) {{ $store['address'] }}</div>
                <div style="margin-bottom:4px;">{{ $store['phone'] }}</div>
                @if ($store['email'])<div style="margin-bottom:4px;">{{ $store['email'] }}</div>@endif
                <div>{{ $store['website'] }}</div>
            </div>
        </td>
        <td width="30%" valign="middle" align="right">
            <div style="font-size:13px;font-weight:bold;color:{{ $red }};line-height:1.4;">Every Bite is a Winner</div>
        </td>
    </tr>
</table>

<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:22px;">
    <tr>
        <td align="center" style="font-size:8px;color:#AAAAAA;padding-top:10px;border-top:1px solid #E5E5E5;">
            This is a computer generated tax invoice and does not require a signature.
        </td>
    </tr>
</table>

</body>
</html>
