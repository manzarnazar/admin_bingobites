<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice #{{ $order->id }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333333;
        }
        .red { color: {{ $brand_red ?? '#E31E24' }}; }
        .meta-label { color: #888888; font-size: 10px; margin-bottom: 2px; }
        .meta-value { font-size: 12px; margin-bottom: 8px; }
    </style>
</head>
<body>
@php
    $red = $brand_red ?? '#E31E24';
    $headerFile = $header_path ?? public_path('assets/email/bingo-bites/header.png');
    $logoFile = $logo_path ?? public_path('assets/email/bingo-bites/logo.png');
@endphp

<table width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="padding:0;">
            <img src="{{ $headerFile }}" width="100%" style="width:100%;max-width:100%;">
        </td>
    </tr>
</table>

<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:16px;">
    <tr>
        <td align="center" style="font-size:22px;font-weight:bold;padding:8px 0 16px;">TAX INVOICE</td>
    </tr>
</table>

<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:20px;">
    <tr>
        <td width="50%" valign="top">
            <div class="meta-label">Invoice Number</div>
            <div class="meta-value">INV-{{ $order->id }}</div>
            <div class="meta-label">Order Number</div>
            <div class="meta-value red" style="color:{{ $red }};font-weight:bold;">#{{ $order->id }}</div>
            <div class="meta-label">Order Type</div>
            <div class="meta-value">{{ $order_type_label }}</div>
        </td>
        <td width="50%" valign="top" align="right">
            <div class="meta-label">Date</div>
            <div class="meta-value">{{ $order_date }}</div>
            <div class="meta-label">Time</div>
            <div class="meta-value">{{ $order_time }}</div>
        </td>
    </tr>
</table>

<table width="100%" cellspacing="0" cellpadding="12" border="0" style="margin-bottom:20px;border:1px solid #EEEEEE;">
    <tr>
        <td width="50%" valign="top" style="border-right:1px solid #EEEEEE;">
            <div style="font-size:11px;font-weight:bold;text-transform:uppercase;margin-bottom:10px;">&#128100; Customer Details</div>
            <div style="font-size:12px;font-weight:bold;">{{ $customer['name'] }}</div>
            @if ($customer['email'])
            <div style="font-size:11px;color:#666666;margin-top:4px;">{{ $customer['email'] }}</div>
            @endif
            @if ($customer['phone'])
            <div style="font-size:11px;color:#666666;margin-top:2px;">{{ $customer['phone'] }}</div>
            @endif
        </td>
        <td width="50%" valign="top">
            <div style="font-size:11px;font-weight:bold;text-transform:uppercase;margin-bottom:10px;">&#127978; Store Details</div>
            <div style="font-size:12px;font-weight:bold;">{{ $store['name'] }}</div>
            <div style="font-size:11px;color:#666666;margin-top:4px;line-height:1.5;">{{ $store['address'] }}</div>
            <div style="font-size:11px;color:#666666;margin-top:2px;">{{ $store['phone'] }}</div>
            @if ($store['email'])
            <div style="font-size:11px;color:#666666;margin-top:2px;">{{ $store['email'] }}</div>
            @endif
        </td>
    </tr>
</table>

@include('email-templates.partials.bingo-bites-order-summary', ['mode' => 'pdf'])

<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:28px;border-top:1px solid #EEEEEE;padding-top:16px;">
    <tr>
        <td width="50%" valign="middle">
            <img src="{{ $logoFile }}" width="70" style="width:70px;">
            <div style="font-size:10px;color:#888888;margin-top:8px;line-height:1.6;">
                {{ $store['address'] }}<br>
                {{ $store['phone'] }}<br>
                @if ($store['email']){{ $store['email'] }}<br>@endif
                {{ $store['website'] }}
            </div>
        </td>
        <td width="50%" valign="middle" align="right">
            <div style="font-size:14px;font-weight:bold;color:{{ $red }};">Every Bite is a Winner</div>
        </td>
    </tr>
</table>

<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;">
    <tr>
        <td align="center" style="font-size:9px;color:#AAAAAA;padding-top:12px;border-top:1px solid #EEEEEE;">
            This is a computer generated tax invoice and does not require a signature.
        </td>
    </tr>
</table>

</body>
</html>
