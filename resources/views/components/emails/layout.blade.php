@props(['preheader' => null])
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ config('app.name') }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <style>
        table, td, div, p, a { font-family: Arial, sans-serif; }
    </style>
    <![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; }

        @media only screen and (max-width: 600px) {
            .bm-container { width: 100% !important; }
            .bm-px { padding-left: 20px !important; padding-right: 20px !important; }
            .bm-stack { display: block !important; width: 100% !important; text-align: left !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#F4F6FA; width:100%; font-family: 'Inter', Arial, Helvetica, sans-serif;">
    @if ($preheader)
        <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#F4F6FA; opacity:0;">
            {{ $preheader }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F6FA;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" class="bm-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:12px; overflow:hidden;">
                    {{-- Header: brand wordmark on a deep royal-blue band, gold underline accent (§1.5 — gold never used for primary CTAs, only as this accent). --}}
                    <tr>
                        <td style="background-color:#003594; padding:28px 40px; border-bottom:3px solid #FFB81C;" class="bm-px">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:22px; line-height:28px; font-weight:700; letter-spacing:-0.01em; color:#FFFFFF;">
                                        Buy<span style="color:#FFC94D;">And</span>Market
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Content --}}
                    <tr>
                        <td style="padding:40px;" class="bm-px">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
                                        {{ $slot }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:24px 40px 32px; border-top:1px solid #E9EDF4;" class="bm-px">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-family:'Inter', Arial, Helvetica, sans-serif; font-size:12px; line-height:19px; color:#8B96AB;">
                                        {{ config('app.name') }} &middot; Harare, Zimbabwe<br>
                                        This is an automated message about your {{ config('app.name') }} account — no need to reply.
                                    </td>
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
