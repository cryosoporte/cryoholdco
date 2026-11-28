<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8">
  @unless ($marca['fondo'])
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @endunless
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="x-apple-disable-message-reformatting">
  <title>Informes del contrato {{ $cuerpo['numero_base'] }}</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    @media only screen and (max-width: 660px) {
      .container { width: 100% !important; }
      .px { padding-left: 24px !important; padding-right: 24px !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background-color:#EEEEEE;">

  <!-- Texto de vista previa (se ve en la bandeja de entrada, no en el cuerpo) -->
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#EEEEEE;">
    Informes del contrato N° {{ $cuerpo['numero_base'] }}.
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EEEEEE;">
    <tr>
      <td align="center" style="padding:24px 12px;">

        @if ($marca['fondo'])
          {{-- Hoja de proporción A4 de tamaño fijo (640 x 905 = 702 de contenido + 143 y 60 de relleno): el fondo se ve siempre sin deformarse; los clientes móviles la reducen en bloque --}}
          <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:640px; min-width:640px; background-color:#FFFFFF;">
            <tr>
              <td height="702" valign="top" background="{{ $marca['fondo'] }}" style="height:702px; background-color:#FFFFFF; background-image:url('{{ $marca['fondo'] }}'); background-size:100% 100%; background-repeat:no-repeat; background-position:center; padding:143px 40px 60px 61px; font-family:Poppins, Arial, Helvetica, sans-serif; font-size:12.5px; line-height:20px; color:#171717;">
                @include('emails.partials.cuerpo-resultados')
              </td>
            </tr>
          </table>
        @else
          <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:8px; overflow:hidden;">
            <tr>
              <td align="center" style="background-color:{{ $marca['color'] }}; padding:24px;">
                <img src="{{ asset($marca['logo']) }}" alt="Logo {{ $marca['nombre'] }}" width="160">
              </td>
            </tr>
            <tr>
              <td class="px" style="padding:32px 40px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#333333;">
                @include('emails.partials.cuerpo-resultados')
              </td>
            </tr>
            <tr>
              <td align="center" style="background-color:#F7F8FA; border-top:1px solid #E4E7EC; padding:20px 24px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#7A8494;">
                {{ $marca['nombre'] }}
                @if ($marca['web'])
                  <br><a href="https://{{ $marca['web'] }}" style="color:{{ $marca['color'] }}; text-decoration:underline;">{{ $marca['web'] }}</a>
                @endif
              </td>
            </tr>
          </table>
        @endif

      </td>
    </tr>
  </table>
</body>
</html>
