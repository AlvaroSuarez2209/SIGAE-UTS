<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
<title>SIGAE-UTS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
  @media only screen and (max-width: 600px) {
    .inner-body { width: 100% !important; }
    .footer { width: 100% !important; }
  }
  @media only screen and (max-width: 500px) {
    .button { width: 100% !important; }
  }
</style>
</head>
<body style="box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; position: relative; -webkit-text-size-adjust: none; background-color: #ffffff; color: #3f3f46; height: 100%; line-height: 1.4; margin: 0; padding: 0; width: 100% !important;">
<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #f4f5f7; margin: 0; padding: 0; width: 100%;">
<tr>
<td align="center" style="position: relative;">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0; padding: 0; width: 100%;">

<!-- Header con marca: logo + degradado institucional -->
<tr>
<td class="header" style="padding: 32px 0 24px; text-align: center;">
  <img src="{{ asset('images/logo/logo-mark.png') }}" alt="SIGAE-UTS" width="48" height="48" style="display: block; margin: 0 auto 12px;">
  <div style="font-size: 20px; font-weight: 700; color: #1e293b; letter-spacing: -0.01em;">SIGAE-UTS</div>
  <div style="font-size: 12px; color: #0a7a45; font-weight: 500; margin-top: 2px;">Unidades Tecnológicas de Santander</div>
</td>
</tr>

<!-- Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f5f7; margin: 0; padding: 0 16px 32px; width: 100%; border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.06), 0 1px 2px -1px rgba(0,0,0,0.06); margin: 0 auto; padding: 0; width: 570px; max-width: 100%;">

<!-- Franja superior de color, para reforzar identidad sin depender de imágenes -->
<tr>
<td style="height: 4px; background: linear-gradient(90deg, #00447e, #0a7a45); border-radius: 12px 12px 0 0; font-size: 0; line-height: 0;">&nbsp;</td>
</tr>

<tr>
<td class="content-cell" style="padding: 36px 32px 32px;">

  <h1 style="color: #18181b; font-size: 19px; font-weight: 700; margin: 0 0 4px; text-align: left;">Hola, {{ $userName }}</h1>
  <p style="font-size: 15px; line-height: 1.6em; margin: 0 0 24px; color: #52525b; text-align: left;">
    Recibimos una solicitud para restablecer la contraseña de tu cuenta en SIGAE-UTS. Usa el siguiente botón para crear una nueva contraseña.
  </p>

  <!-- Botón principal con degradado de marca -->
  <table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 28px; padding: 0; text-align: center; width: 100%;">
    <tr>
      <td align="center">
        <table border="0" cellpadding="0" cellspacing="0" role="presentation">
          <tr>
            <td bgcolor="#00447e" style="border-radius: 8px; background-color: #00447e; background: linear-gradient(135deg, #00447e, #0a7a45);">
              <a href="{{ $resetUrl }}" class="button button-primary" target="_blank" rel="noopener" style="-webkit-text-size-adjust: none; border-radius: 8px; color: #ffffff; display: inline-block; overflow: hidden; text-decoration: none; font-size: 15px; font-weight: 600; padding: 13px 28px;">
                <font color="#ffffff">Restablecer contraseña</font>
              </a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <p style="font-size: 14px; line-height: 1.6em; margin: 0 0 20px; color: #71717a; text-align: left;">
    Este enlace expira en <strong style="color: #3f3f46;">{{ $expireMinutes }} minutos</strong>.
  </p>

  <!-- Aviso de seguridad destacado, con más presencia visual -->
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #f8f9fb; border: 1px solid #e4e4e7; border-radius: 8px; margin: 0 0 8px;">
    <tr>
      <td style="padding: 14px 16px;">
        <p style="font-size: 13px; line-height: 1.5em; margin: 0; color: #52525b; text-align: left;">
          <strong style="color: #3f3f46;">¿No solicitaste este cambio?</strong><br>
          Puedes ignorar este correo con tranquilidad — tu contraseña actual seguirá funcionando y no se realizará ningún cambio.
        </p>
      </td>
    </tr>
  </table>

  <p style="font-size: 14px; line-height: 1.6em; margin: 24px 0 0; color: #71717a; text-align: left;">
    Saludos,<br>
    <strong style="color: #3f3f46;">Equipo SIGAE-UTS</strong>
  </p>

  <!-- Subcopy con URL alternativa -->
  <table class="subcopy" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-top: 1px solid #e4e4e7; margin-top: 28px; padding-top: 20px;">
    <tr>
      <td>
        <p style="line-height: 1.5em; margin: 0; text-align: left; font-size: 12px; color: #a1a1aa;">
          Si tienes problemas para hacer clic en el botón "Restablecer contraseña", copia y pega la siguiente URL en tu navegador:
          <br>
          <span class="break-all" style="word-break: break-all;">
            <a href="{{ $resetUrl }}" style="color: #00447e; word-break: break-all;">{{ $resetUrl }}</a>
          </span>
        </p>
      </td>
    </tr>
  </table>

</td>
</tr>
</table>
</td>
</tr>

<!-- Footer -->
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto; padding: 0; text-align: center; width: 570px; max-width: 100%;">
<tr>
<td class="content-cell" align="center" style="padding: 8px 32px 32px;">
  <p style="line-height: 1.6em; margin: 0; color: #a1a1aa; font-size: 12px; text-align: center;">
    © {{ date('Y') }} SIGAE-UTS · Unidades Tecnológicas de Santander<br>
    Este es un mensaje automático, por favor no respondas a este correo.
  </p>
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
