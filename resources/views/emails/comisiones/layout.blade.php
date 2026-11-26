<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('titulo')</title>
    <style>
        @media only screen and (max-width: 620px) {
            .contenido { padding-left: 14px !important; padding-right: 14px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#F2F4F7;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F2F4F7;">
        <tr>
            <td align="center" style="padding:24px 8px;">
                <!--[if mso]><table role="presentation" width="600" align="center"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#FFFFFF; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td align="center" style="background-color:#1F3A5F; padding:22px;">
                            <img src="{{ asset('images/logos/criocord_logo.png') }}" alt="Criocord" width="160" style="display:block; border:0; height:auto;">
                        </td>
                    </tr>
                    <tr>
                        <td class="contenido" style="padding:24px 28px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:23px; color:#263547;">
                            @yield('contenido')
                        </td>
                    </tr>
                    <tr>
                        <td class="contenido" style="background-color:#F7F8FA; border-top:1px solid #E4E7EC; padding:18px 28px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#526071;">
                            @yield('pie')
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
