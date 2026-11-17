<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="x-apple-disable-message-reformatting">
  <title>Recordatorio: prueba serológica pendiente</title>
  <!--[if mso]>
  <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
  <![endif]-->
  <style>
    @media only screen and (max-width: 620px) {
      .container { width: 100% !important; }
      .px { padding-left: 24px !important; padding-right: 24px !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background-color:#F2F4F7;">

  <!-- Texto de vista previa (se ve en la bandeja de entrada, no en el cuerpo) -->
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#F2F4F7;">
    Nos falta un pasito para completar tu proceso: la prueba serológica. Aquí te contamos cómo hacerla.
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F2F4F7;">
    <tr>
      <td align="center" style="padding:24px 12px;">

        <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:8px; overflow:hidden;">

          <!-- Encabezado -->
          <tr>
            <td align="center" style="background-color:#1F3A5F; padding:24px;">
                <img src="{{ asset('images/logos/criocord_logo.png') }}" alt="Logo Criocord" width="160">
            </td>
          </tr>

          <!-- Cuerpo -->
          <tr>
            <td class="px" style="padding:32px 40px 8px 40px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#333333;">
              <p style="margin:0 0 16px 0;">Buen día, querida <strong>#FamiliaCriocord</strong>:</p>
              <p style="margin:0 0 16px 0;">Esperamos que se encuentren muy bien.</p>
              <p style="margin:0 0 16px 0;">
                Te escribimos porque, al revisar el expediente de tu contrato
                <strong>N.º {{ $numeroBase }}</strong>, notamos que todavía nos falta la
                <strong>prueba serológica de enfermedades infecciosas</strong> de la mamá.
              </p>
              <p style="margin:0 0 24px 0;">
                Este examen es muy importante: con él podemos emitir el informe final y completar el proceso de
                criopreservación de las células madre de tu bebé.
              </p>
            </td>
          </tr>

          <!-- Recuadro: gratis y sin ayuno -->
          <tr>
            <td class="px" style="padding:0 40px 16px 40px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EAF6EE; border-left:4px solid #2E9E5B; border-radius:4px;">
                <tr>
                  <td style="padding:16px 20px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#1E5A36;">
                    &#10004;&nbsp; <strong>Es gratis:</strong> la prueba está incluida dentro de nuestro servicio.<br>
                    &#10004;&nbsp; <strong>No necesitas ir en ayunas.</strong>
                  </td>
                </tr>
              </table> 
            </td>
          </tr>

          <!-- Recuadro de plazo -->
          <tr>
            <td class="px" style="padding:0 40px 24px 40px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFF6E5; border-left:4px solid #E8A317; border-radius:4px;">
                <tr>
                  <td style="padding:16px 20px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:22px; color:#5C4300;">
                    <strong>Tienes 30 días</strong> desde la fecha de este correo para realizarla.
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Cómo regularizarlo -->
          <tr>
            <td class="px" style="padding:0 40px 8px 40px; font-family:Arial, Helvetica, sans-serif; color:#333333;">
              <p style="margin:0 0 12px 0; font-size:17px; line-height:24px; font-weight:bold; color:#1F3A5F;">¿Cómo hacerlo?</p>
              <p style="margin:0 0 20px 0; font-size:15px; line-height:24px; color:#333333;">
                ¡Es muy sencillo! Ingresa a nuestra plataforma de serología. Ahí encontrarás los <strong>locales disponibles</strong>
                para la toma de muestra, sus <strong>horarios</strong> y todo lo que necesitas para continuar con el proceso.
              </p>
            </td>
          </tr>

          <!-- Botón (compatible con Outlook) -->
          <tr>
            <td align="center" class="px" style="padding:0 40px 16px 40px;">
              <!--[if mso]>
              <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $enlacePortal }}" style="height:48px; v-text-anchor:middle; width:300px;" arcsize="12%" stroke="f" fillcolor="#1F3A5F">
                <w:anchorlock/>
                <center style="color:#FFFFFF; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:bold;">Ver locales y horarios</center>
              </v:roundrect>
              <![endif]-->
              <!--[if !mso]><!-->
              <a href="{{ $enlacePortal }}" target="_blank" style="display:inline-block; background-color:#1F3A5F; color:#FFFFFF; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:bold; line-height:48px; text-align:center; text-decoration:none; width:300px; border-radius:6px;">Ver locales y horarios</a>
              <!--<![endif]-->
            </td>
          </tr>

          <!-- Enlace de respaldo -->
          <tr>
            <td align="center" class="px" style="padding:0 40px 8px 40px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#7A8494;">
              Si el botón no funciona, copia este enlace en tu navegador:<br>
              <a href="{{ $enlacePortal }}" target="_blank" style="color:#1F3A5F; text-decoration:underline; word-break:break-all;">{{ $enlacePortal }}</a>
            </td>
          </tr>

          <!-- Cierre -->
          <tr>
            <td class="px" style="padding:24px 40px 32px 40px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#333333;">
              <p style="margin:0 0 16px 0;">Gracias por ayudarnos a completar este paso. Si tienes alguna duda, responde este correo y con gusto te ayudamos.</p>
              <p style="margin:0;">Con cariño,<br><strong>Equipo CrioCord</strong></p>
            </td>
          </tr>

          <!-- Pie -->
          <tr>
            <td align="center" style="background-color:#F7F8FA; border-top:1px solid #E4E7EC; padding:20px 24px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#7A8494;">
              CrioCord · Más que criopreservación<br>
              <a href="https://www.criocord.com.pe" style="color:#1F3A5F; text-decoration:underline;">www.criocord.com.pe</a>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>
</body>
</html>
