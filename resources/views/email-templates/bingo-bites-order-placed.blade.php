<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - Bingo Bites</title>
</head>
<body style="margin:0;padding:0;background-color:#F2F2F2;font-family:Arial,Helvetica,sans-serif;">
@php
    $red = $brand_red ?? '#E31E24';
    $black = '#000000';
    $lh = '1.15';
    $headerSrc = 'cid:bingo_header';
    $logoSrc = 'cid:bingo_logo';
    $cardStyle = 'background-color:#FFFFFF;border:1px solid #E8E8E8;border-radius:8px;padding:16px;';
    $sectionTitle = 'font-size:13px;font-weight:bold;color:' . $black . ';margin:0 0 10px 0;text-transform:uppercase;letter-spacing:0.3px;line-height:' . $lh . ';';
    $textStyle = 'font-size:13px;color:' . $black . ';padding:4px 0;margin:0;line-height:' . $lh . ';';
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#F2F2F2;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background-color:#FFFFFF;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">

                {{-- Header banner --}}
                <tr>
                    <td style="padding:0;line-height:0;">
                        <img src="{{ $headerSrc }}" alt="Bingo Bites" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                    </td>
                </tr>

                {{-- Order confirmed --}}
                <tr>
                    <td style="padding:28px 32px 8px;text-align:center;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                            <tr>
                                <td style="width:36px;height:36px;background-color:{{ $red }};border-radius:50%;text-align:center;vertical-align:middle;color:#FFFFFF;font-size:20px;font-weight:bold;line-height:36px;">&#10003;</td>
                            </tr>
                        </table>
                        <h1 style="margin:14px 0 8px;font-size:24px;font-weight:bold;color:{{ $black }};">Order Confirmed!</h1>
                        <p style="margin:0;font-size:14px;color:{{ $black }};line-height:{{ $lh }};">
                            Hi {{ $customer['name'] }}, your order has been confirmed and is being prepared.
                        </p>
                    </td>
                </tr>

                {{-- Order Details + Customer Information --}}
                <tr>
                    <td style="padding:16px 24px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td width="48%" valign="top" style="padding-right:8px;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="{{ $cardStyle }}">
                                        <tr>
                                            <td>
                                                <p style="{{ $sectionTitle }}">@include('email-templates.partials.bingo-bites-icon', ['name' => 'order_details', 'mode' => 'email']) Order Details</p>
                                                <p style="{{ $textStyle }}">Order Number: <strong style="color:{{ $red }};">#{{ $order->id }}</strong></p>
                                                <p style="{{ $textStyle }}">Order Type: {{ $order_type_label }}</p>
                                                <p style="{{ $textStyle }}">Order Date: {{ $order_date }}</p>
                                                <p style="{{ $textStyle }}">Order Time: {{ $order_time }}</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td width="4%"></td>
                                <td width="48%" valign="top" style="padding-left:8px;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="{{ $cardStyle }}">
                                        <tr>
                                            <td>
                                                <p style="{{ $sectionTitle }}">@include('email-templates.partials.bingo-bites-icon', ['name' => 'customer', 'mode' => 'email']) Customer Information</p>
                                                <p style="{{ $textStyle }}">Name: {{ $customer['name'] }}</p>
                                                @if ($customer['email'])
                                                <p style="{{ $textStyle }}">Email: {{ $customer['email'] }}</p>
                                                @endif
                                                @if ($customer['phone'])
                                                <p style="{{ $textStyle }}">Phone: {{ $customer['phone'] }}</p>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Location card --}}
                <tr>
                    <td style="padding:0 24px 16px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="{{ $cardStyle }}">
                            <tr>
                                <td>
                                    <p style="{{ $sectionTitle }}">@include('email-templates.partials.bingo-bites-icon', ['name' => 'location', 'mode' => 'email']) {{ $location['title'] }}</p>
                                    <p style="margin:0 0 4px;font-size:14px;font-weight:bold;color:{{ $black }};line-height:{{ $lh }};">{{ $location['name'] }}</p>
                                    @if ($location['address'])
                                    <p style="margin:0 0 4px;font-size:13px;color:{{ $black }};line-height:{{ $lh }};">{{ $location['address'] }}</p>
                                    @endif
                                    @if ($location['phone'])
                                    <p style="margin:0;font-size:13px;color:{{ $black }};line-height:{{ $lh }};">{{ $location['phone'] }}</p>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Order Summary --}}
                <tr>
                    <td style="padding:0 24px 16px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="{{ $cardStyle }}">
                            <tr>
                                <td>
                                    <p style="{{ $sectionTitle }}">@include('email-templates.partials.bingo-bites-icon', ['name' => 'order_details', 'mode' => 'email']) Order Summary</p>
                                    @include('email-templates.partials.bingo-bites-order-summary', ['mode' => 'email'])
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Attachment notice --}}
                <tr>
                    <td style="padding:0 24px 24px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#FFF0F0;border-radius:6px;">
                            <tr>
                                <td style="padding:12px 16px;font-size:13px;color:{{ $black }};line-height:{{ $lh }};">
                                    Your tax invoice is attached to this email.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:24px 32px 28px;border-top:1px solid #EEEEEE;text-align:center;">
                        <p style="margin:0 0 8px;font-size:13px;color:{{ $black }};line-height:{{ $lh }};">
                            If you have any questions, please contact the store directly.<br>
                            Thank you for choosing Bingo Bites.
                        </p>
                        <p style="margin:0 0 20px;font-size:15px;font-weight:bold;color:{{ $red }};">Every Bite is a Winner</p>
                        <img src="{{ $logoSrc }}" alt="Bingo Bites" width="80" style="display:block;margin:0 auto 16px;border:0;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                            <tr>
                                <td style="font-size:11px;color:{{ $black }};padding:4px 8px;line-height:{{ $lh }};">
                                    @include('email-templates.partials.bingo-bites-icon', ['name' => 'location', 'mode' => 'email', 'size' => 14]) {{ $store['address'] }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:11px;color:{{ $black }};padding:4px 8px;line-height:{{ $lh }};">{{ $store['phone'] }}</td>
                            </tr>
                            @if ($store['email'])
                            <tr>
                                <td style="font-size:11px;color:{{ $black }};padding:4px 8px;line-height:{{ $lh }};">{{ $store['email'] }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td style="font-size:11px;color:{{ $black }};padding:4px 8px;line-height:{{ $lh }};">{{ $store['website'] }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
