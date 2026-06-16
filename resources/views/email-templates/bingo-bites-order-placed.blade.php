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
    $headerSrc = $header_cid ?? asset('assets/email/bingo-bites/header.png');
    $logoSrc = $logo_cid ?? asset('assets/email/bingo-bites/logo.png');
    $cardStyle = 'background-color:#FFFFFF;border:1px solid #E8E8E8;border-radius:8px;padding:16px;';
    $sectionTitle = 'font-size:13px;font-weight:bold;color:#333333;margin:0 0 10px 0;text-transform:uppercase;letter-spacing:0.3px;';
    $labelStyle = 'font-size:12px;color:#888888;padding:3px 0;';
    $valueStyle = 'font-size:13px;color:#333333;padding:3px 0;';
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
                        <h1 style="margin:14px 0 8px;font-size:24px;font-weight:bold;color:#222222;">Order Confirmed!</h1>
                        <p style="margin:0;font-size:14px;color:#666666;line-height:1.6;">
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
                                                <p style="{{ $sectionTitle }}">&#128203; Order Details</p>
                                                <p style="{{ $labelStyle }}">Order Number</p>
                                                <p style="margin:0 0 8px;font-size:15px;font-weight:bold;color:{{ $red }};">#{{ $order->id }}</p>
                                                <p style="{{ $labelStyle }}">Order Type</p>
                                                <p style="{{ $valueStyle }}">{{ $order_type_label }}</p>
                                                <p style="{{ $labelStyle }}">Order Date</p>
                                                <p style="{{ $valueStyle }}">{{ $order_date }}</p>
                                                <p style="{{ $labelStyle }}">Order Time</p>
                                                <p style="{{ $valueStyle }}">{{ $order_time }}</p>
                                                <p style="{{ $labelStyle }}">Estimated Ready Time</p>
                                                <p style="margin:0;font-size:13px;font-weight:bold;color:{{ $red }};">{{ $estimated_ready_time }}</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td width="4%"></td>
                                <td width="48%" valign="top" style="padding-left:8px;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="{{ $cardStyle }}">
                                        <tr>
                                            <td>
                                                <p style="{{ $sectionTitle }}">&#128100; Customer Information</p>
                                                <p style="{{ $labelStyle }}">Name</p>
                                                <p style="{{ $valueStyle }}">{{ $customer['name'] }}</p>
                                                @if ($customer['email'])
                                                <p style="{{ $labelStyle }}">Email</p>
                                                <p style="{{ $valueStyle }}">{{ $customer['email'] }}</p>
                                                @endif
                                                @if ($customer['phone'])
                                                <p style="{{ $labelStyle }}">Phone</p>
                                                <p style="margin:0;font-size:13px;color:#333333;">{{ $customer['phone'] }}</p>
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
                                    <p style="{{ $sectionTitle }}">&#128205; {{ $location['title'] }}</p>
                                    <p style="margin:0 0 4px;font-size:14px;font-weight:bold;color:#333333;">{{ $location['name'] }}</p>
                                    @if ($location['address'])
                                    <p style="margin:0 0 4px;font-size:13px;color:#666666;line-height:1.5;">{{ $location['address'] }}</p>
                                    @endif
                                    @if ($location['phone'])
                                    <p style="margin:0;font-size:13px;color:#666666;">{{ $location['phone'] }}</p>
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
                                    <p style="{{ $sectionTitle }}">&#128722; Order Summary</p>
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
                                <td style="padding:12px 16px;font-size:13px;color:#666666;">
                                    &#128206; Your tax invoice is attached to this email.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:24px 32px 28px;border-top:1px solid #EEEEEE;text-align:center;">
                        <p style="margin:0 0 8px;font-size:13px;color:#888888;line-height:1.6;">
                            If you have any questions, please contact the store directly.<br>
                            Thank you for choosing Bingo Bites.
                        </p>
                        <p style="margin:0 0 20px;font-size:15px;font-weight:bold;color:{{ $red }};">Every Bite is a Winner</p>
                        <img src="{{ $logoSrc }}" alt="Bingo Bites" width="80" style="display:block;margin:0 auto 16px;border:0;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                            <tr>
                                <td style="font-size:11px;color:#888888;padding:2px 8px;">&#128205; {{ $store['address'] }}</td>
                            </tr>
                            <tr>
                                <td style="font-size:11px;color:#888888;padding:2px 8px;">&#128222; {{ $store['phone'] }}</td>
                            </tr>
                            @if ($store['email'])
                            <tr>
                                <td style="font-size:11px;color:#888888;padding:2px 8px;">&#9993; {{ $store['email'] }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td style="font-size:11px;color:#888888;padding:2px 8px;">&#127760; {{ $store['website'] }}</td>
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
