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
            <span style="color:{{ $grey }};">&#128197; Date:</span>
            <span style="color:#222222;"> {{ $invoiceDate }}</span><br>
            <span style="color:{{ $grey }};">&#128336; Time:</span>
            <span style="color:#222222;"> {{ $invoiceTime }}</span>
        </td>
    </tr>
</table>

<table width="100%" cellspacing="0" cellpadding="0" border="0" class="section-divider" style="margin:14px 0;"><tr><td>&nbsp;</td></tr></table>
<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:14px 0 18px;border:1px solid #E8E8E8;">
    <tr>
        <td width="50%" valign="top" style="padding:14px 16px;border-right:1px solid #E8E8E8;">
            <div style="font-size:10px;font-weight:bold;color:{{ $red }};text-transform:uppercase;margin-bottom:10px;">&#128100; Customer Details</div>
            <div style="font-size:12px;font-weight:bold;color:#222222;margin-bottom:4px;">{{ $customer['name'] }}</div>
            @if ($customer['email'])
            <div style="font-size:10px;color:{{ $grey }};margin-bottom:2px;">{{ $customer['email'] }}</div>
            @endif
            @if ($customer['phone'])
            <div style="font-size:10px;color:{{ $grey }};">{{ $customer['phone'] }}</div>
            @endif
        </td>
        <td width="50%" valign="top" style="padding:14px 16px;">
            <div style="font-size:10px;font-weight:bold;color:{{ $red }};text-transform:uppercase;margin-bottom:10px;">&#127978; Store Details</div>
            <div style="font-size:12px;font-weight:bold;color:#222222;margin-bottom:4px;">{{ $store['name'] }}</div>
            <div style="font-size:10px;color:{{ $grey }};line-height:1.6;margin-bottom:2px;">{{ $store['address'] }}</div>
            <div style="font-size:10px;color:{{ $grey }};margin-bottom:2px;">&#128222; {{ $store['phone'] }}</div>
            @if ($store['email'])
            <div style="font-size:10px;color:{{ $grey }};">&#9993; {{ $store['email'] }}</div>
            @endif
        </td>
    </tr>
</table>

@include('email-templates.partials.bingo-bites-order-summary', ['mode' => 'pdf'])

<table width="100%" cellspacing="0" cellpadding="0" border="0" class="section-divider" style="margin:14px 0;"><tr><td>&nbsp;</td></tr></table>

{{-- Footer --}}
<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;">
    <tr>
        <td width="18%" valign="top" style="padding-right:10px;">
            <img src="{{ $logoFile }}" width="72" style="width:72px;display:block;">
        </td>
        <td width="52%" valign="top" style="padding:0 8px;">
            <div style="font-size:11px;font-weight:bold;color:#222222;margin-bottom:4px;">{{ $store['name'] }}</div>
            <div style="font-size:9px;color:{{ $grey }};line-height:1.7;">
                &#128205; {{ $store['address'] }}<br>
                &#128222; {{ $store['phone'] }}<br>
                @if ($store['email'])&#9993; {{ $store['email'] }}<br>@endif
                &#127760; {{ $store['website'] }}
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
